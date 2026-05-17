<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user  = $request->user();
        $tasks = $user->tasks()->with('course')->get();

        // ── Statistik Utama ────────────────────────────────
        $totalTasks      = $tasks->count();
        $completedTasks  = $tasks->where('status', 'completed')->count();
        $pendingTasks    = $tasks->whereIn('status', ['pending', 'in_progress'])->count();

        // Deadline dekat (<=3 hari)
        $upcomingDeadlines = $tasks
            ->where('status', '!=', 'completed')
            ->filter(fn($t) => $t->deadline->isFuture()
                            && now()->diffInDays($t->deadline, false) <= 3)
            ->count();

        // Prioritas tinggi
        $highPriorityTasks = $tasks
            ->whereIn('ai_priority_level', ['high', 'urgent'])
            ->where('status', '!=', 'completed')
            ->count();

        // ── Tugas Terbaru (5 terdekat deadlinenya) ─────────
        $recentTasks = $tasks
            ->where('status', '!=', 'completed')
            ->sortBy('deadline')
            ->take(5)
            ->map(fn($task) => [
                'id'                => $task->id,
                'name'              => $task->name,
                'course'            => $task->course?->name,
                'deadline'          => $task->deadline->format('Y-m-d H:i'),
                'days_remaining'    => $task->days_remaining,
                'difficulty'        => $task->difficulty,
                'estimated_hours'   => $task->estimated_hours,
                'status'            => $task->status,
                'ai_priority_level' => $task->ai_priority_level,
                'ai_recommendation' => $task->ai_recommendation,
            ])
            ->values();

        // ── AI Insight: rekomendasi tugas prioritas tertinggi
        $topTask   = $tasks
            ->where('status', '!=', 'completed')
            ->sortByDesc('ai_priority_score')
            ->first();

        $aiInsight = null;
        if ($topTask) {
            $aiInsight = [
                'task_name'       => $topTask->name,
                'course'          => $topTask->course?->name,
                'recommendation'  => $topTask->ai_recommendation,
                'priority_level'  => $topTask->ai_priority_level,
                'priority_score'  => $topTask->ai_priority_score,
                'suggested_start' => $topTask->ai_suggested_start?->format('Y-m-d'),
                'deadline'        => $topTask->deadline->format('Y-m-d H:i'),
                'days_remaining'  => $topTask->days_remaining,
            ];
        }

        return response()->json([
            'user' => [
                'name'       => $user->name,
                'university' => $user->university,
            ],
            'stats' => [
                'total_tasks'        => $totalTasks,
                'completed_tasks'    => $completedTasks,
                'pending_tasks'      => $pendingTasks,
                'upcoming_deadlines' => $upcomingDeadlines,
                'high_priority'      => $highPriorityTasks,
                'completion_rate'    => $totalTasks > 0
                    ? round(($completedTasks / $totalTasks) * 100)
                    : 0,
            ],
            'recent_tasks' => $recentTasks,
            'ai_insight'   => $aiInsight,
        ]);
    }
}