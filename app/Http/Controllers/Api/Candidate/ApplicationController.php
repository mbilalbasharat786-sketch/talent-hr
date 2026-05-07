<?php

namespace App\Http\Controllers\Api\Candidate;

use App\Http\Controllers\Controller;
use App\Models\HrJob;
use App\Models\JobApplication;
use App\Models\Notification;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;

class ApplicationController extends Controller
{
    public function jobs(Request $request)
    {
        $candidate = $request->user();

        $jobs = HrJob::with([
            'company:id,name,industry,trust_level,status',
            'assessment:id,title,time_limit,status',
        ])
            ->where('status', 'live')
            ->whereNotNull('assessment_id')
            ->latest()
            ->paginate(12);

        $appliedJobIds = JobApplication::where('candidate_id', $candidate->id)
            ->whereIn('job_id', $jobs->getCollection()->pluck('id'))
            ->pluck('job_id')
            ->all();

        $jobs->getCollection()->transform(function ($job) use ($appliedJobIds) {
            $job->already_applied = in_array($job->id, $appliedJobIds, true);

            return $job;
        });

        return response()->json($jobs);
    }

    public function apply(Request $request)
    {
        $candidate = $request->user();

        $request->validate([
            'job_id' => ['required', 'integer', 'exists:hr_jobs,id'],
        ]);

        $job = HrJob::with('assessment')
            ->where('id', $request->job_id)
            ->where('status', 'live')
            ->first();

        if (! $job) {
            return response()->json([
                'message' => 'Job not available for application.',
            ], 422);
        }

        if (! $job->assessment_id || ! $job->assessment) {
            return response()->json([
                'message' => 'Job assessment is not available yet.',
            ], 422);
        }

        $existing = JobApplication::where('job_id', $job->id)
            ->where('candidate_id', $candidate->id)
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'You have already applied for this job.',
                'application' => $existing->load('job:id,title'),
            ], 422);
        }

        $application = JobApplication::create([
            'job_id' => $job->id,
            'candidate_id' => $candidate->id,
            'status' => 'assessment_pending',
        ]);

        Notification::create([
            'user_id' => $candidate->id,
            'type' => 'system_alert',
            'title' => 'Application submitted',
            'message' => "You applied for {$job->title}. Assessment is now pending.",
        ]);

        ActivityLogger::log(
            'apply',
            'candidate_applications',
            "Candidate {$candidate->email} applied for job {$job->title}.",
            $request,
            $candidate->id
        );

        return response()->json([
            'message' => 'Job applied successfully.',
            'application' => $application->load(['job:id,title,assessment_id,status']),
        ], 201);
    }

    public function index(Request $request)
    {
        $applications = $request->user()->jobApplications()
            ->with(['job:id,title,location,work_mode,status,assessment_id', 'task', 'interview'])
            ->latest()
            ->paginate(20);

        return response()->json($applications);
    }

    public function show(Request $request, JobApplication $application)
    {
        if ($application->candidate_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Application not found.',
            ], 404);
        }

        $application->load([
            'job:id,title,location,work_mode,status,assessment_id',
            'task.submissions',
            'interview',
            'candidate:id,name,email',
        ]);

        $submission = $request->user()->assessmentSubmissions()
            ->where('assessment_id', $application->job->assessment_id)
            ->latest()
            ->first();

        return response()->json([
            'application' => $application,
            'assessment_submission' => $submission,
            'latest_assessment_session' => $request->user()->assessmentSessions()
                ->where('job_application_id', $application->id)
                ->latest()
                ->first(),
        ]);
    }
}
