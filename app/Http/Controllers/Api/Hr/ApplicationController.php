<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AssessmentSubmission;
use App\Models\Interview;
use App\Models\JobApplication;
use App\Models\Notification;
use App\Models\Task;
use App\Services\ActivityLogger;
use Carbon\Carbon;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ApplicationController extends Controller
{
    public function index(Request $request)
    {
        $hr = $request->user();

        $applications = JobApplication::with([
            'candidate:id,name,email,status',
            'job:id,hr_id,company_id,title,assessment_id,status',
        ])
            ->whereHas('job', function ($query) use ($hr) {
                $query->where('hr_id', $hr->id)
                    ->where('company_id', $hr->company_id);
            })
            ->when($request->status, function ($query) use ($request) {
                $query->where('status', $request->status);
            })
            ->latest()
            ->paginate(20);

        return response()->json($applications);
    }

    public function show(Request $request, JobApplication $application)
    {
        $this->authorizeApplicationOwnership($request, $application);

        $application->load([
            'candidate:id,name,email,status,company_id',
            'job:id,hr_id,company_id,title,assessment_id,status',
            'task',
            'interview',
        ]);

        $assessmentSubmission = null;
        $scoreBreakdown = null;

        if ($application->job && $application->job->assessment_id) {
            $assessmentSubmission = AssessmentSubmission::where('assessment_id', $application->job->assessment_id)
                ->where('candidate_id', $application->candidate_id)
                ->first();

            if ($assessmentSubmission) {
                $scoreBreakdown = [
                    'assessment_id' => $assessmentSubmission->assessment_id,
                    'score' => $assessmentSubmission->score,
                    'status' => $assessmentSubmission->status,
                ];
            }
        }

        $candidateActivityLogs = ActivityLog::where('user_id', $application->candidate_id)
            ->latest()
            ->take(20)
            ->get();

        return response()->json([
            'application' => $application,
            'assessment_score_breakdown' => $scoreBreakdown,
            'skill_match_percentage' => $application->skill_match_percentage,
            'experience_verification_status' => $application->experience_verification_status,
            'plagiarism_report' => $application->plagiarism_report,
            'anti_cheat_logs' => $application->anti_cheat_logs,
            'portfolio_links' => $application->portfolio_links,
            'activity_logs' => $candidateActivityLogs,
        ]);
    }

  // ApplicationController.php mein shortlist function ko update karein
public function shortlist(Request $request, JobApplication $application)
{
    $this->authorizeApplicationOwnership($request, $application);

    // Document Rule: Candidate must be in 'passed' state to move to 'shortlisted'
    // Ya agar wo process mein aage hai tab bhi allow karein
    $validPriorStatuses = ['passed', 'second_task_assigned', 'interview_scheduled'];

    if (!in_array($application->status, $validPriorStatuses)) {
        return response()->json([
            'message' => 'Candidate must pass the assessment before they can be shortlisted.',
        ], 422);
    }

    $application->update([
        'status' => 'shortlisted', // Document Section 9: Applied -> Passed -> Shortlisted
        'rejection_reason' => null,
    ]);

    // Notification & Logging (Same as before)
    // ...
}

    public function reject(Request $request, JobApplication $application)
    {
        $this->authorizeApplicationOwnership($request, $application);

        $request->validate([
            'reason' => ['required', 'string', 'min:3'],
        ]);

        $application->update([
            'status' => 'rejected',
            'rejection_reason' => $request->reason,
        ]);

        Notification::create([
            'user_id' => $application->candidate_id,
            'type' => 'system_alert',
            'title' => 'Application rejected',
            'message' => "Your application was rejected. Reason: {$request->reason}",
        ]);

        ActivityLogger::log(
            'reject',
            'hr_applications',
            "Application {$application->id} rejected by HR {$request->user()->email}. Reason: {$request->reason}",
            $request
        );

        return response()->json([
            'message' => 'Candidate rejected successfully.',
            'application' => $application->fresh(['candidate:id,name,email', 'job:id,title']),
        ]);
    }

    public function assignTask(Request $request, JobApplication $application)
    {
        $this->authorizeApplicationOwnership($request, $application);

        if ($application->status !== 'shortlisted') {
            return response()->json([
                'message' => 'Candidate must be shortlisted before assigning second round task.',
            ], 422);
        }

        $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'deadline' => ['nullable', 'date'],
            'instructions_file' => ['nullable', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $taskData = [
            'title' => $request->title,
            'description' => $request->description,
            'deadline' => $request->deadline,
            'status' => 'assigned',
        ];

        if ($request->hasFile('instructions_file')) {
            $taskData['instructions_file'] = $request->file('instructions_file')->store('second-round-tasks');
        }

        $task = Task::updateOrCreate(
            ['application_id' => $application->id],
            $taskData
        );

        $application->update([
            'status' => 'second_task_assigned',
        ]);

        Notification::create([
            'user_id' => $application->candidate_id,
            'type' => 'system_alert',
            'title' => 'Second round task assigned',
            'message' => "A second round task has been assigned: {$task->title}",
        ]);

        ActivityLogger::log(
            'assign_task',
            'hr_applications',
            "Second round task assigned to application {$application->id} by HR {$request->user()->email}.",
            $request
        );

        return response()->json([
            'message' => 'Second round task assigned successfully.',
            'application' => $application->fresh(['candidate:id,name,email', 'job:id,title', 'task']),
            'task' => $task,
        ]);
    }

    public function reviewTask(Request $request, JobApplication $application)
    {
        $this->authorizeApplicationOwnership($request, $application);

        $request->validate([
            'status' => ['required', Rule::in(['passed', 'failed'])],
        ]);

        $task = $application->task;

        if (! $task) {
            return response()->json([
                'message' => 'Second round task not found for this application.',
            ], 404);
        }

        if (! in_array($task->status, ['assigned', 'submitted', 'passed', 'failed'], true)) {
            return response()->json([
                'message' => 'Second round task cannot be reviewed in its current status.',
            ], 422);
        }

        $task->update([
            'status' => $request->status,
        ]);

        $task->submissions()->latest()->first()?->update([
            'status' => 'reviewed',
        ]);

        Notification::create([
            'user_id' => $application->candidate_id,
            'type' => 'system_alert',
            'title' => 'Second round task reviewed',
            'message' => "Your second round task was marked {$request->status}.",
        ]);

        ActivityLogger::log(
            'review_task',
            'hr_applications',
            "Second round task for application {$application->id} marked {$request->status} by HR {$request->user()->email}.",
            $request
        );

        return response()->json([
            'message' => 'Second round task reviewed successfully.',
            'application' => $application->fresh(['candidate:id,name,email', 'job:id,title', 'task']),
            'task' => $task->fresh(),
        ]);
    }

    private function authorizeApplicationOwnership(Request $request, JobApplication $application): void
    {
        $job = $application->job;

        if (! $job || $job->hr_id !== $request->user()->id || $job->company_id !== $request->user()->company_id) {
            throw new HttpResponseException(response()->json([
                'message' => 'Application not found for this HR user.',
            ], 404));
        }
    }

    public function scheduleInterview(Request $request, JobApplication $application)
    {
        $this->authorizeApplicationOwnership($request, $application);

        if (! in_array($application->status, ['shortlisted', 'second_task_assigned', 'interview_scheduled'], true)) {
            return response()->json([
                'message' => 'Interview can only be scheduled after candidate is shortlisted or second task is assigned.',
            ], 422);
        }

        if ($application->task && $application->task->status !== 'passed') {
            return response()->json([
                'message' => 'Second round task must be passed before scheduling interview.',
            ], 422);
        }

        $request->validate([
            'date' => ['required', 'date', 'after_or_equal:today'],
            'time' => ['required', 'date_format:H:i'],
            'mode' => ['required', Rule::in(['onsite', 'online', 'hybrid'])],
        ]);

        $scheduledAt = Carbon::parse($request->date.' '.$request->time);

        if ($scheduledAt->isPast()) {
            return response()->json([
                'message' => 'Interview date and time must be in the future.',
            ], 422);
        }

        $interview = Interview::updateOrCreate(
            ['application_id' => $application->id],
            [
                'date' => $request->date,
                'time' => $request->time,
                'mode' => $request->mode,
            ]
        );

        $application->update([
            'status' => 'interview_scheduled',
        ]);

        Notification::create([
            'user_id' => $application->candidate_id,
            'type' => 'system_alert',
            'title' => 'Interview scheduled',
            'message' => "Your interview is scheduled on {$scheduledAt->format('Y-m-d H:i')}.",
        ]);

        ActivityLogger::log(
            'schedule_interview',
            'hr_applications',
            "Interview scheduled for application {$application->id} on {$scheduledAt->format('Y-m-d H:i')} ({$request->mode}) by HR {$request->user()->email}.",
            $request
        );

        return response()->json([
            'message' => 'Interview scheduled successfully.',
            'application' => $application->fresh(['candidate:id,name,email', 'job:id,title', 'interview']),
            'interview' => $interview,
        ]);
    }

    public function hire(Request $request, JobApplication $application)
    {
        $this->authorizeApplicationOwnership($request, $application);

        if (! in_array($application->status, ['interview_scheduled', 'shortlisted'], true)) {
            return response()->json([
                'message' => 'Candidate must be interviewed or shortlisted before hiring.',
            ], 422);
        }

        $request->validate([
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'salary' => ['required', 'string', 'min:3'],
            'position' => ['required', 'string', 'min:3'],
            'employment_type' => ['required', Rule::in(['full_time', 'part_time', 'contract', 'internship'])],
            'offer_letter_file' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:5120'],
        ]);

        $startDate = Carbon::parse($request->start_date);

        // Update application status to hired
        $application->update([
            'status' => 'hired',
            'rejection_reason' => null,
        ]);

        // Store offer letter if provided
        $offerLetterPath = null;
        if ($request->hasFile('offer_letter_file')) {
            $offerLetterPath = $request->file('offer_letter_file')->store('offer-letters');
        }

        // Create hiring notification for candidate
        Notification::create([
            'user_id' => $application->candidate_id,
            'type' => 'system_alert',
            'title' => 'Congratulations! You are hired!',
            'message' => "You have been hired for the position of {$request->position} starting from {$startDate->format('Y-m-d')}. Salary: {$request->salary}.",
        ]);

        // Log the hiring action
        ActivityLogger::log(
            'hire_candidate',
            'hr_applications',
            "Candidate {$application->candidate->name} hired for position {$request->position} by HR {$request->user()->email}. Start date: {$startDate->format('Y-m-d')}.",
            $request
        );

        // Update job status if all positions are filled
        $job = $application->job;
        $hiredCount = JobApplication::where('job_id', $job->id)
            ->where('status', 'hired')
            ->count();

        if ($hiredCount >= $job->candidates_required) {
            $job->update(['status' => 'filled']);
        }

        return response()->json([
            'message' => 'Candidate hired successfully!',
            'application' => $application->fresh(['candidate:id,name,email', 'job:id,title']),
            'hiring_details' => [
                'start_date' => $startDate->format('Y-m-d'),
                'salary' => $request->salary,
                'position' => $request->position,
                'employment_type' => $request->employment_type,
                'offer_letter_file' => $offerLetterPath,
            ],
        ]);
    }

    public function completeInterview(Request $request, JobApplication $application)
    {
        $this->authorizeApplicationOwnership($request, $application);

        if ($application->status !== 'interview_scheduled') {
            return response()->json([
                'message' => 'Application must have interview scheduled to complete.',
            ], 422);
        }

        $request->validate([
            'result' => ['required', Rule::in(['passed', 'failed'])],
            'feedback' => ['nullable', 'string', 'max:1000'],
        ]);

        $interview = $application->interview;
        if ($interview) {
            $interview->update([
                'status' => 'completed',
                'result' => $request->result,
                'feedback' => $request->feedback,
            ]);
        }

        // Update application status based on interview result
        $newStatus = $request->result === 'passed' ? 'interview_passed' : 'rejected';
        $application->update([
            'status' => $newStatus,
        ]);

        Notification::create([
            'user_id' => $application->candidate_id,
            'type' => 'system_alert',
            'title' => 'Interview completed',
            'message' => "Your interview was marked as {$request->result}.",
        ]);

        ActivityLogger::log(
            'complete_interview',
            'hr_applications',
            "Interview for application {$application->id} marked {$request->result} by HR {$request->user()->email}.",
            $request
        );

        return response()->json([
            'message' => 'Interview completed successfully.',
            'application' => $application->fresh(['candidate:id,name,email', 'job:id,title', 'interview']),
        ]);
    }
}
