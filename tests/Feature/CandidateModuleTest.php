<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentSession;
use App\Models\Company;
use App\Models\HrJob;
use App\Models\JobApplication;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CandidateModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_candidate_can_login_and_apply_for_live_job(): void
    {
        [$candidate, $job] = $this->candidateAndJobFixture();

        $login = $this->postJson('/api/candidate/login', [
            'email' => $candidate->email,
            'password' => 'password',
        ]);

        $login->assertOk()->assertJsonStructure(['token', 'user']);

        Sanctum::actingAs($candidate);

        $apply = $this->postJson('/api/candidate/apply', [
            'job_id' => $job->id,
        ]);

        $apply->assertCreated()
            ->assertJsonPath('application.status', 'assessment_pending');

        $this->assertDatabaseHas('job_applications', [
            'job_id' => $job->id,
            'candidate_id' => $candidate->id,
            'status' => 'assessment_pending',
        ]);
    }

    public function test_candidate_can_start_assessment_and_session_is_created(): void
    {
        [$candidate, $job, $application, $assessment] = $this->candidateAndJobFixture(withApplication: true);

        Sanctum::actingAs($candidate);

        $response = $this->postJson('/api/candidate/assessment/start', [
            'application_id' => $application->id,
            'device_fingerprint' => 'device-1',
            'browser' => 'Chrome',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['session' => ['session_token'], 'submission']);

        $this->assertDatabaseHas('assessment_sessions', [
            'candidate_id' => $candidate->id,
            'assessment_id' => $assessment->id,
            'job_application_id' => $application->id,
            'status' => 'in_progress',
        ]);

        $this->assertDatabaseHas('assessments', [
            'id' => $assessment->id,
            'status' => 'locked',
        ]);
    }

    public function test_candidate_assessment_submission_processes_score_and_flags(): void
    {
        [$candidate, $job, $application, $assessment, $question] = $this->candidateAndJobFixture(withApplication: true, withQuestion: true);

        Sanctum::actingAs($candidate);

        $start = $this->postJson('/api/candidate/assessment/start', [
            'application_id' => $application->id,
            'device_fingerprint' => 'device-1',
            'browser' => 'Chrome',
        ])->assertCreated();

        $sessionToken = $start->json('session.session_token');

        $this->postJson('/api/candidate/assessment/log', [
            'session_token' => $sessionToken,
            'event_type' => 'window_blur',
        ])->assertOk();

        $submit = $this->postJson('/api/candidate/assessment/submit', [
            'session_token' => $sessionToken,
            'answers' => [
                $question->id => 'A',
            ],
        ]);

        $submit->assertOk();

        $this->assertDatabaseHas('assessment_submissions', [
            'assessment_id' => $assessment->id,
            'candidate_id' => $candidate->id,
            'status' => 'passed',
            'cheating_flag' => 'normal',
        ]);

        $this->assertDatabaseHas('job_applications', [
            'id' => $application->id,
            'status' => 'passed',
        ]);
    }

    public function test_five_violations_auto_submit_assessment(): void
    {
        [$candidate, $job, $application, $assessment, $question] = $this->candidateAndJobFixture(withApplication: true, withQuestion: true);

        Sanctum::actingAs($candidate);

        $start = $this->postJson('/api/candidate/assessment/start', [
            'application_id' => $application->id,
            'device_fingerprint' => 'device-1',
            'browser' => 'Chrome',
        ])->assertCreated();

        $sessionToken = $start->json('session.session_token');

        foreach (range(1, 5) as $count) {
            $response = $this->postJson('/api/candidate/assessment/log', [
                'session_token' => $sessionToken,
                'event_type' => 'tab_switch',
            ]);
        }

        $response->assertOk()
            ->assertJsonPath('message', 'Assessment auto-submitted due to security or timeout rules.');

        $this->assertDatabaseHas('assessment_submissions', [
            'assessment_id' => $assessment->id,
            'candidate_id' => $candidate->id,
            'status' => 'auto_submitted',
            'cheating_flag' => 'cheating_detected',
        ]);
    }

    public function test_expired_session_auto_submits_when_candidate_reopens_assessment(): void
    {
        [$candidate, $job, $application, $assessment, $question] = $this->candidateAndJobFixture(withApplication: true, withQuestion: true);

        Sanctum::actingAs($candidate);

        $this->postJson('/api/candidate/assessment/start', [
            'application_id' => $application->id,
            'device_fingerprint' => 'device-1',
            'browser' => 'Chrome',
            'tab_id' => 'tab-1',
        ])->assertCreated();

        AssessmentSession::where('candidate_id', $candidate->id)
            ->where('assessment_id', $assessment->id)
            ->update(['expires_at' => now()->subMinute()]);

        $this->postJson('/api/candidate/assessment/start', [
            'application_id' => $application->id,
            'device_fingerprint' => 'device-1',
            'browser' => 'Chrome',
            'tab_id' => 'tab-1',
        ])->assertOk()
            ->assertJsonPath('message', 'Assessment auto-submitted due to security or timeout rules.');

        $this->assertDatabaseHas('assessment_submissions', [
            'assessment_id' => $assessment->id,
            'candidate_id' => $candidate->id,
            'status' => 'auto_submitted',
        ]);
    }

    public function test_candidate_can_submit_task_and_internship_and_update_profile(): void
    {
        Storage::fake();
        Mail::fake();

        [$candidate, $job, $application] = $this->candidateAndJobFixture(withApplication: true, applicationStatus: 'second_task_assigned');

        $task = Task::create([
            'application_id' => $application->id,
            'title' => 'Case Study',
            'description' => 'Solve this practical assignment.',
            'status' => 'assigned',
        ]);

        Sanctum::actingAs($candidate);

        $this->postJson('/api/candidate/task/submit', [
            'task_id' => $task->id,
            'submission_file' => UploadedFile::fake()->create('task-answer.pdf', 100),
        ])->assertCreated();

        $this->postJson('/api/candidate/internships', [
            'company_name' => 'Intern Co',
            'duration' => '3 months',
            'supervisor_email' => 'supervisor@example.com',
            'certificate' => UploadedFile::fake()->create('certificate.pdf', 120),
        ])->assertCreated();

        $profile = $this->putJson('/api/candidate/profile', [
            'skills' => ['Laravel', 'React'],
            'education' => 'BS Computer Science',
            'experience' => 'Built multiple hiring products.',
        ]);

        $profile->assertOk()
            ->assertJsonStructure(['profile', 'score_breakdown' => ['candidate_rating']]);

        $this->assertDatabaseHas('task_submissions', [
            'task_id' => $task->id,
            'candidate_id' => $candidate->id,
            'status' => 'submitted',
        ]);
        $this->assertDatabaseHas('internships', [
            'candidate_id' => $candidate->id,
            'company_name' => 'Intern Co',
            'status' => 'pending',
        ]);
    }

    private function candidateAndJobFixture(
        bool $withApplication = false,
        bool $withQuestion = false,
        string $applicationStatus = 'assessment_pending'
    ): array {
        $company = Company::create([
            'name' => 'Talent Co',
            'email' => uniqid('company').'@example.com',
            'status' => 'approved',
            'trust_level' => 'standard',
        ]);

        $hr = User::create([
            'name' => 'HR User',
            'email' => uniqid('hr').'@example.com',
            'password' => 'password',
            'role' => 'hr',
            'status' => 'active',
            'company_id' => $company->id,
        ]);

        $candidate = User::create([
            'name' => 'Candidate User',
            'email' => uniqid('candidate').'@example.com',
            'password' => 'password',
            'role' => 'candidate',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $assessment = Assessment::create([
            'hr_id' => $hr->id,
            'title' => 'Frontend Assessment',
            'time_limit' => 45,
            'one_attempt_only' => true,
            'auto_submit' => true,
            'randomize_questions' => false,
            'cooldown_days' => 5,
            'status' => 'active',
        ]);

        $question = null;

        if ($withQuestion) {
            $question = $assessment->questions()->create([
                'type' => 'mcq',
                'question_text' => 'Choose A',
                'options' => ['A', 'B', 'C'],
                'expected_answer' => 'A',
                'marks' => 5,
            ]);
        }

        $job = HrJob::create([
            'company_id' => $company->id,
            'hr_id' => $hr->id,
            'title' => 'Frontend Developer',
            'type' => 'full_time',
            'work_mode' => 'remote',
            'location' => 'Lahore',
            'skills' => ['React', 'JS'],
            'description' => 'Build frontend apps.',
            'candidates_required' => 1,
            'hiring_urgency' => 'medium',
            'assessment_id' => $assessment->id,
            'status' => 'live',
        ]);

        if (! $withApplication) {
            return [$candidate, $job];
        }

        $application = JobApplication::create([
            'job_id' => $job->id,
            'candidate_id' => $candidate->id,
            'status' => $applicationStatus,
        ]);

        return [$candidate, $job, $application, $assessment, $question];
    }
}
