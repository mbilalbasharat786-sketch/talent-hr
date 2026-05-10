<?php

namespace App\Http\Controllers\Api\Candidate;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Task;
use App\Models\TaskSubmission;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function submit(Request $request)
    {
        $candidate = $request->user();

        $request->validate([
            'task_id' => ['required', 'integer', 'exists:tasks,id'],
            'submission_file' => ['required', 'file', 'mimes:pdf,doc,docx,zip,txt,jpg,jpeg,png,webp', 'max:10240'],
        ]);

        $task = Task::with('application')->findOrFail($request->task_id);

        if (! $task->application || $task->application->candidate_id !== $candidate->id) {
            return response()->json([
                'message' => 'Task not found for this candidate.',
            ], 404);
        }

        if ($task->status !== 'assigned') {
            return response()->json([
                'message' => 'Task has already been submitted and cannot be edited.',
            ], 422);
        }

        if ($task->deadline && $task->deadline->isPast()) {
            return response()->json([
                'message' => 'Task deadline has passed.',
            ], 422);
        }

        $existingSubmission = TaskSubmission::where('task_id', $task->id)
            ->where('candidate_id', $candidate->id)
            ->exists();

        if ($existingSubmission) {
            return response()->json([
                'message' => 'Task has already been submitted and cannot be edited.',
            ], 422);
        }

        $path = $request->file('submission_file')->store('candidate-task-submissions');

        $submission = TaskSubmission::create([
            'task_id' => $task->id,
            'candidate_id' => $candidate->id,
            'file_path' => $path,
            'status' => 'submitted',
        ]);

        $task->update([
            'submission_file' => $path,
            'status' => 'submitted',
        ]);

        Notification::create([
            'user_id' => $candidate->id,
            'type' => 'system_alert',
            'title' => 'Task submitted',
            'message' => "Your second round task for {$task->title} was submitted.",
        ]);

        ActivityLogger::log(
            'submit_task',
            'candidate_tasks',
            "Candidate {$candidate->email} submitted task {$task->id}.",
            $request,
            $candidate->id
        );

        return response()->json([
            'message' => 'Task submitted successfully.',
            'task_submission' => $submission,
            'task' => $task->fresh(),
        ], 201);
    }
}
