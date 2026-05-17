<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Services\AIService;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function __construct(private AIService $aiService) {}

    public function index(Request $request)
    {
        $tasks = $request->user()
            ->tasks()
            ->with('course')
            ->orderBy('deadline')
            ->get()
            ->map(function ($task) {
                $task->days_remaining = $task->days_remaining;
                return $task;
            });

        return response()->json([
            'message' => 'Data tugas berhasil diambil',
            'tasks'   => $tasks
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'            => 'required|string|max:255',
            'course_id'       => 'nullable|exists:courses,id',
            'deadline'        => 'required|date|after:now',
            'difficulty'      => 'required|integer|min:1|max:5',
            'estimated_hours' => 'required|numeric|min:0.5|max:100',
        ]);

        $task = $request->user()->tasks()->create($validated);

        // Auto AI analyze
        $this->aiService->analyzeTask($task);
        $task->refresh();

        return response()->json([
            'message' => 'Tugas berhasil ditambahkan dan dianalisis AI',
            'task'    => $task->load('course'),
        ], 201);
    }

    public function show(Request $request, Task $task)
    {
        if ($task->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Tidak diizinkan'], 403);
        }

        return response()->json([
            'message' => 'Data tugas berhasil diambil',
            'task'    => $task->load('course')
        ]);
    }

    public function update(Request $request, Task $task)
    {
        if ($task->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Tidak diizinkan'], 403);
        }

        $validated = $request->validate([
            'name'            => 'sometimes|string|max:255',
            'course_id'       => 'nullable|exists:courses,id',
            'deadline'        => 'sometimes|date',
            'difficulty'      => 'sometimes|integer|min:1|max:5',
            'estimated_hours' => 'sometimes|numeric|min:0.5',
        ]);

        $task->update($validated);

        // Re-analyze setelah update
        $this->aiService->analyzeTask($task);
        $task->refresh();

        return response()->json([
            'message' => 'Tugas berhasil diperbarui',
            'task'    => $task->load('course'),
        ]);
    }

    public function destroy(Request $request, Task $task)
    {
        if ($task->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Tidak diizinkan'], 403);
        }

        $task->delete();

        return response()->json(['message' => 'Tugas berhasil dihapus']);
    }

    public function updateStatus(Request $request, Task $task)
    {
        if ($task->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Tidak diizinkan'], 403);
        }

        $request->validate([
            'status' => 'required|in:pending,in_progress,completed',
        ]);

        $task->update(['status' => $request->status]);

        return response()->json([
            'message' => 'Status berhasil diperbarui',
            'task'    => $task,
        ]);
    }

    public function analyze(Request $request, Task $task)
    {
        if ($task->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Tidak diizinkan'], 403);
        }

        $this->aiService->analyzeTask($task);
        $task->refresh();

        return response()->json([
            'message' => 'Analisis AI selesai',
            'task'    => $task->load('course'),
        ]);
    }
}