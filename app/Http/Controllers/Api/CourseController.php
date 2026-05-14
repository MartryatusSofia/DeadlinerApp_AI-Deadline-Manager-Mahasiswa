<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function index(Request $request)
    {
        $courses = $request->user()->courses()->latest()->get();

        return response()->json([
            'message' => 'Data mata kuliah berhasil diambil',
            'courses' => $courses
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:20',
        ]);

        $course = $request->user()->courses()->create($validated);

        return response()->json([
            'message' => 'Mata kuliah berhasil ditambahkan',
            'course'  => $course,
        ], 201);
    }

    public function show(Request $request, Course $course)
    {
        if ($course->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Tidak diizinkan'], 403);
        }

        return response()->json([
            'message' => 'Data mata kuliah berhasil diambil',
            'course'  => $course
        ]);
    }

    public function update(Request $request, Course $course)
    {
        if ($course->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Tidak diizinkan'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'code' => 'nullable|string|max:20',
        ]);

        $course->update($validated);

        return response()->json([
            'message' => 'Mata kuliah berhasil diperbarui',
            'course'  => $course,
        ]);
    }

    public function destroy(Request $request, Course $course)
    {
        if ($course->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Tidak diizinkan'], 403);
        }

        $course->delete();

        return response()->json([
            'message' => 'Mata kuliah berhasil dihapus'
        ]);
    }
}