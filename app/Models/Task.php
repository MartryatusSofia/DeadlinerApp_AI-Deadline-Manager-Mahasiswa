<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $fillable = [
        'user_id', 'course_id', 'name', 'deadline',
        'difficulty', 'estimated_hours', 'status',
        'ai_priority_score', 'ai_recommendation',
        'ai_suggested_start', 'ai_priority_level',
    ];

    protected $casts = [
        'deadline'         => 'datetime',
        'ai_suggested_start' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    // Helper otomatis hitung sisa hari
    public function getDaysRemainingAttribute(): int
    {
        return now()->diffInDays($this->deadline, false);
    }
}