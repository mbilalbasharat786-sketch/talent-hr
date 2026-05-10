<?php

use Illuminate\Foundation\Inspiring;
use App\Jobs\ProcessSubmissionJob;
use App\Models\AssessmentSession;
use App\Models\AssessmentSubmission;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('assessments:auto-submit-expired', function () {
    $sessions = AssessmentSession::where('status', 'in_progress')
        ->whereNotNull('expires_at')
        ->where('expires_at', '<=', now())
        ->get();

    $count = 0;

    foreach ($sessions as $session) {
        $submission = AssessmentSubmission::where('assessment_id', $session->assessment_id)
            ->where('candidate_id', $session->candidate_id)
            ->first();

        if (! $submission || $submission->submitted_at) {
            $session->update(['status' => 'expired']);
            continue;
        }

        ProcessSubmissionJob::dispatchSync(
            $session->id,
            $submission->id,
            $session->job_application_id,
            $submission->answers_payload ?? [],
            true
        );

        $count++;
    }

    $this->info("Auto-submitted {$count} expired assessment session(s).");
})->purpose('Auto-submit expired candidate assessment sessions');

Schedule::command('assessments:auto-submit-expired')->everyMinute();
