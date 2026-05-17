<?php

namespace App\Services;

use App\Models\Task;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIService
{
    private string $apiKey;
    private string $model;
    private string $apiUrl;

    public function __construct()
    {
        $this->apiKey = config('services.groq.key');
        $this->model  = config('services.groq.model');
        $this->apiUrl = 'https://api.groq.com/openai/v1/chat/completions';
    }

    public function analyzeTask(Task $task): void
    {
        try {
            $prompt   = $this->buildPrompt($task);
            $response = $this->callGroq($prompt);
            $result   = $this->parseResponse($response);

            $task->update([
                'ai_priority_score'  => $result['priority_score'],
                'ai_priority_level'  => $result['priority_level'],
                'ai_recommendation'  => $result['recommendation'],
                'ai_suggested_start' => $result['suggested_start'],
            ]);

        } catch (\Exception $e) {
            Log::error('AI analysis failed for task ' . $task->id . ': ' . $e->getMessage());
        }
    }

    private function buildPrompt(Task $task): string
    {
        $daysLeft      = max(0, now()->diffInDays($task->deadline, false));
        $courseName    = $task->course?->name ?? 'Tidak ada mata kuliah';
        $difficultyMap = [
            1 => 'Sangat Mudah',
            2 => 'Mudah',
            3 => 'Sedang',
            4 => 'Sulit',
            5 => 'Sangat Sulit'
        ];
        $difficultyLabel = $difficultyMap[$task->difficulty] ?? 'Sedang';
        $today           = now()->format('Y-m-d');
        $deadlineStr     = $task->deadline->format('Y-m-d H:i');

        return <<<PROMPT
Kamu adalah asisten AI untuk manajemen tugas mahasiswa.

Analisis tugas berikut dan berikan rekomendasi prioritas:

DATA TUGAS:
- Nama Tugas: {$task->name}
- Mata Kuliah: {$courseName}
- Deadline: {$deadlineStr} ({$daysLeft} hari lagi)
- Tingkat Kesulitan: {$task->difficulty}/5 ({$difficultyLabel})
- Estimasi Pengerjaan: {$task->estimated_hours} jam
- Tanggal Hari Ini: {$today}

Berikan respons HANYA dalam format JSON berikut, tanpa teks tambahan apapun, tanpa markdown:
{
  "priority_score": <angka 1-100>,
  "priority_level": "<low|medium|high|urgent>",
  "recommendation": "<saran 2-4 kalimat dalam Bahasa Indonesia yang spesifik>",
  "suggested_start": "<YYYY-MM-DD>"
}

Aturan penilaian:
- urgent (80-100): deadline < 2 hari ATAU (deadline < 4 hari DAN difficulty >= 4)
- high (60-79): deadline < 5 hari ATAU difficulty >= 4
- medium (40-59): deadline < 10 hari DAN difficulty 2-3
- low (1-39): deadline > 10 hari DAN difficulty 1-2
PROMPT;
    }

    private function callGroq(string $prompt): string
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type'  => 'application/json',
        ])->post($this->apiUrl, [
            'model'    => $this->model,
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'max_tokens'  => 500,
            'temperature' => 0.3,
        ]);

        if ($response->failed()) {
            throw new \Exception('Groq API error: ' . $response->body());
        }

        return $response->json('choices.0.message.content', '');
    }

    private function parseResponse(string $rawResponse): array
    {
        // Bersihkan markdown kalau ada
        $cleaned = preg_replace('/```json|```/', '', $rawResponse);
        $cleaned = trim($cleaned);

        $data = json_decode($cleaned, true);

        if (!$data || !isset($data['priority_score'])) {
            return [
                'priority_score'  => 50,
                'priority_level'  => 'medium',
                'recommendation'  => 'Segera kerjakan tugas ini sesuai deadline.',
                'suggested_start' => now()->format('Y-m-d'),
            ];
        }

        return [
            'priority_score'  => (int) $data['priority_score'],
            'priority_level'  => $data['priority_level'] ?? 'medium',
            'recommendation'  => $data['recommendation'] ?? '',
            'suggested_start' => $data['suggested_start'] ?? now()->format('Y-m-d'),
        ];
    }
}