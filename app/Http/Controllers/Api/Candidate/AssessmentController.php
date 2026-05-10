<?php

namespace App\Http\Controllers\Api\Candidate;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessSubmissionJob;
use App\Models\AssessmentLog;
use App\Models\AssessmentSession;
use App\Models\AssessmentSubmission;
use App\Models\JobApplication;
use App\Services\AssessmentAttemptService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AssessmentController extends Controller
{
    public function start(Request $request, AssessmentAttemptService $attemptService)
    {
        $candidate = $request->user();

        $request->validate([
            'application_id' => ['required', 'integer', 'exists:job_applications,id'],
            'device_fingerprint' => ['required', 'string', 'max:255'],
            'browser' => ['required', 'string', 'max:255'],
            'tab_id' => ['nullable', 'string', 'max:100'],
        ]);

        $application = JobApplication::with('job.assessment.questions')->findOrFail($request->application_id);

        if ($application->candidate_id !== $candidate->id) {
            return response()->json([
                'message' => 'Application not found.',
            ], 404);
        }

        $assessment = $application->job?->assessment;

        if (! $assessment) {
            return response()->json([
                'message' => 'Assessment not found for this application.',
            ], 422);
        }

        $existingSession = AssessmentSession::where('candidate_id', $candidate->id)
            ->where('assessment_id', $assessment->id)
            ->where('job_application_id', $application->id)
            ->where('status', 'in_progress')
            ->latest()
            ->first();

        if ($existingSession && $existingSession->expires_at->isPast()) {
            return $this->autoSubmitExpiredSession($existingSession, $candidate);
        }

        if ($existingSession && $existingSession->expires_at->isFuture()) {
            $sameDevice = ($existingSession->device_info['fingerprint'] ?? null) === $request->device_fingerprint
                && ($existingSession->device_info['browser'] ?? null) === $request->browser
                && $existingSession->ip_address === $request->ip();

            if (! $sameDevice) {
                $this->logViolation($existingSession, 'multiple_device_login', ['browser' => $request->browser]);

                return response()->json([
                    'message' => 'Assessment already locked to another device/session.',
                ], 422);
            }

            $activeTabId = $existingSession->device_info['tab_id'] ?? null;
            if ($request->filled('tab_id') && $activeTabId && $activeTabId !== $request->tab_id) {
                $this->logViolation($existingSession, 'multiple_tab_open', ['tab_id' => $request->tab_id]);

                return response()->json([
                    'message' => 'Assessment is already open in another tab.',
                ], 422);
            }

            return response()->json([
                'message' => 'Assessment session resumed successfully.',
                'session' => $existingSession->load('assessment.questions'),
                'submission' => AssessmentSubmission::where('assessment_id', $assessment->id)
                    ->where('candidate_id', $candidate->id)
                    ->first(),
            ]);
        }

        $submission = $attemptService->start($application, $candidate, $request);

        $session = AssessmentSession::create([
            'candidate_id' => $candidate->id,
            'assessment_id' => $assessment->id,
            'job_application_id' => $application->id,
            'session_token' => (string) Str::uuid(),
            'started_at' => now(),
            'expires_at' => now()->addMinutes($assessment->time_limit ?: 30),
            'status' => 'in_progress',
            'device_info' => [
                'fingerprint' => $request->device_fingerprint,
                'browser' => $request->browser,
                'tab_id' => $request->tab_id,
            ],
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'message' => 'Assessment started successfully.',
            'session' => $session->load('assessment.questions'),
            'submission' => $submission,
        ], 201);
    }

    public function logEvent(Request $request)
    {
        $candidate = $request->user();

        $request->validate([
            'session_token' => ['required', 'string'],
            'event_type' => ['required', Rule::in([
                'tab_switch',
                'window_blur',
                'fullscreen_exit',
                'copy_paste_attempt',
                'right_click',
                'multiple_device_login',
                'multiple_tab_open',
                'suspicious_pattern',
            ])],
            'metadata' => ['nullable', 'array'],
        ]);

        $session = AssessmentSession::where('session_token', $request->session_token)
            ->where('candidate_id', $candidate->id)
            ->first();

        if (! $session) {
            return response()->json([
                'message' => 'Assessment session not found.',
            ], 404);
        }

        if ($session->status !== 'in_progress') {
            return response()->json([
                'message' => 'Assessment session is no longer active.',
            ], 422);
        }

        if ($session->expires_at->isPast()) {
            return $this->autoSubmitExpiredSession($session, $candidate);
        }

        $log = $this->logViolation($session, $request->event_type, $request->metadata ?? []);

        if ($session->fresh()->violation_count >= 5) {
            return $this->autoSubmitExpiredSession($session->fresh(), $candidate);
        }

        return response()->json([
            'message' => 'Assessment event logged successfully.',
            'log' => $log,
            'session' => $session->fresh(),
        ]);
    }

    public function submit(Request $request)
    {
        $candidate = $request->user();

        $request->validate([
            'session_token' => ['required', 'string'],
            'answers' => ['nullable', 'array'],
        ]);

        $session = AssessmentSession::with(['assessment', 'application'])
            ->where('session_token', $request->session_token)
            ->where('candidate_id', $candidate->id)
            ->first();

        if (! $session) {
            return response()->json([
                'message' => 'Assessment session not found.',
            ], 404);
        }

        if ($session->status !== 'in_progress') {
            return response()->json([
                'message' => 'Assessment already submitted.',
            ], 422);
        }

        $submission = AssessmentSubmission::where('assessment_id', $session->assessment_id)
            ->where('candidate_id', $candidate->id)
            ->first();

        if (! $submission) {
            return response()->json([
                'message' => 'Assessment submission record not found.',
            ], 422);
        }

        $autoSubmitted = $session->expires_at->isPast() || $session->violation_count >= 5;

        ProcessSubmissionJob::dispatchSync(
            $session->id,
            $submission->id,
            $session->job_application_id,
            $request->input('answers', []),
            $autoSubmitted
        );

        return response()->json([
            'message' => $autoSubmitted ? 'Assessment auto-submitted successfully.' : 'Assessment submitted successfully.',
            'session' => $session->fresh(),
            'submission' => $submission->fresh(),
        ]);
    }

    private function logViolation(AssessmentSession $session, string $eventType, array $metadata = []): AssessmentLog
    {
        $log = AssessmentLog::create([
            'session_id' => $session->id,
            'event_type' => $eventType,
            'event_time' => now(),
            'metadata' => $metadata,
        ]);

        $session->increment('warning_count');
        $session->increment('violation_count');

        $submission = AssessmentSubmission::where('assessment_id', $session->assessment_id)
            ->where('candidate_id', $session->candidate_id)
            ->first();

        $application = $session->application;

        if (($session->fresh()->warning_count >= 3 || $eventType === 'suspicious_pattern') && $submission && $submission->cheating_flag === 'normal') {
            $submission->update([
                'cheating_flag' => 'suspicious',
            ]);
        }

        if ($application) {
            $application->update([
                'anti_cheat_logs' => json_encode([
                    'warnings' => $session->fresh()->warning_count,
                    'violations' => $session->fresh()->violation_count,
                    'latest_event' => $eventType,
                ]),
            ]);
        }

        return $log;
    }

    private function autoSubmitExpiredSession(AssessmentSession $session, $candidate)
    {
        $submission = AssessmentSubmission::where('assessment_id', $session->assessment_id)
            ->where('candidate_id', $candidate->id)
            ->first();

        if ($submission) {
            ProcessSubmissionJob::dispatchSync(
                $session->id,
                $submission->id,
                $session->job_application_id,
                $submission->answers_payload ?? [],
                true
            );
        }

        return response()->json([
            'message' => 'Assessment auto-submitted due to security or timeout rules.',
            'session' => $session->fresh(),
            'submission' => $submission?->fresh(),
        ]);
    }
}
