<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\HrJob;
use App\Services\ActivityLogger;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class JobController extends Controller
{
    public function index(Request $request)
    {
        $hr = $request->user();

        $jobs = HrJob::with(['assessment:id,title,status', 'hr:id,name,email'])
            ->where('hr_id', $hr->id)
            ->where('company_id', $hr->company_id)
            ->when($request->status, function ($query) use ($request) {
                $query->where('status', $request->status);
            })
            ->latest()
            ->paginate(20);

        return response()->json($jobs);
    }

    public function show(Request $request, HrJob $job)
    {
        $this->authorizeJobOwnership($request, $job);

        return response()->json(
            $job->load([
                'assessment:id,title,status,time_limit,one_attempt_only,auto_submit,randomize_questions,cooldown_days',
                'hr:id,name,email',
                'company:id,name,email,status,trust_level',
            ])
        );
    }

    public function store(Request $request)
    {
        $hr = $request->user();

        if (! $hr->company || $hr->company->status !== 'approved') {
            return response()->json([
                'message' => 'Only verified companies can post jobs.',
            ], 403);
        }

        $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['full_time', 'part_time', 'contract', 'internship'])],
            'work_mode' => ['required', Rule::in(['onsite', 'remote', 'hybrid'])],
            'location' => ['required', 'string', 'max:255'],
            'skills' => ['required', 'array', 'min:1'],
            'skills.*' => ['required', 'string', 'max:100'],
            'experience_level' => ['nullable', 'string', 'max:255'],
            'education' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'candidates_required' => ['required', 'integer', 'min:1'],
            'hiring_urgency' => ['required', Rule::in(['low', 'medium', 'high'])],
            'assessment_id' => [
                'required',
                'integer',
                Rule::exists('assessments', 'id')->where(function ($query) use ($hr) {
                    $query->where('hr_id', $hr->id);
                }),
            ],
        ]);

        $assessment = Assessment::where('id', $request->assessment_id)
            ->where('hr_id', $hr->id)
            ->firstOrFail();

        $job = HrJob::create([
            'company_id' => $hr->company_id,
            'hr_id' => $hr->id,
            'title' => $request->title,
            'type' => $request->type,
            'work_mode' => $request->work_mode,
            'location' => $request->location,
            'skills' => $request->skills,
            'experience_level' => $request->experience_level,
            'education' => $request->education,
            'description' => $request->description,
            'candidates_required' => $request->candidates_required,
            'hiring_urgency' => $request->hiring_urgency,
            'assessment_id' => $assessment->id,
            'status' => 'pending_approval',
        ]);

        ActivityLogger::log(
            'create',
            'hr_jobs',
            "HR {$hr->email} created job {$job->title}.",
            $request
        );

        return response()->json([
            'message' => 'Job created successfully and sent for admin approval.',
            'job' => $job->load('assessment:id,title,status'),
        ], 201);
    }

    public function update(Request $request, HrJob $job)
    {
        $hr = $request->user();
        $this->authorizeJobOwnership($request, $job);

        if (! $hr->company || $hr->company->status !== 'approved') {
            return response()->json([
                'message' => 'Only verified companies can update and submit jobs.',
            ], 403);
        }

        $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'required', Rule::in(['full_time', 'part_time', 'contract', 'internship'])],
            'work_mode' => ['sometimes', 'required', Rule::in(['onsite', 'remote', 'hybrid'])],
            'location' => ['sometimes', 'required', 'string', 'max:255'],
            'skills' => ['sometimes', 'required', 'array', 'min:1'],
            'skills.*' => ['required', 'string', 'max:100'],
            'experience_level' => ['nullable', 'string', 'max:255'],
            'education' => ['nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'required', 'string'],
            'candidates_required' => ['sometimes', 'required', 'integer', 'min:1'],
            'hiring_urgency' => ['sometimes', 'required', Rule::in(['low', 'medium', 'high'])],
            'assessment_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('assessments', 'id')->where(function ($query) use ($hr) {
                    $query->where('hr_id', $hr->id);
                }),
            ],
        ]);

        if ($request->filled('assessment_id') && $job->applications()->exists()) {
            return response()->json([
                'message' => 'Assessment cannot be changed after candidates have started applying.',
            ], 422);
        }

        $data = $request->only([
            'title',
            'type',
            'work_mode',
            'location',
            'skills',
            'experience_level',
            'education',
            'description',
            'candidates_required',
            'hiring_urgency',
            'assessment_id',
        ]);

        if (! empty($data)) {
            $data['status'] = 'pending_approval';
        }

        $job->update($data);

        ActivityLogger::log(
            'update',
            'hr_jobs',
            "HR {$hr->email} updated job {$job->title}.",
            $request
        );

        return response()->json([
            'message' => 'Job updated successfully and resubmitted for admin approval.',
            'job' => $job->fresh()->load('assessment:id,title,status'),
        ]);
    }

    public function deactivate(Request $request, HrJob $job)
    {
        $hr = $request->user();
        $this->authorizeJobOwnership($request, $job);

        $job->update([
            'status' => 'closed',
        ]);

        ActivityLogger::log(
            'deactivate',
            'hr_jobs',
            "HR {$hr->email} deactivated job {$job->title}.",
            $request
        );

        return response()->json([
            'message' => 'Job deactivated successfully.',
            'job' => $job->fresh()->load('assessment:id,title,status'),
        ]);
    }

    private function authorizeJobOwnership(Request $request, HrJob $job): void
    {
        if ($job->hr_id !== $request->user()->id || $job->company_id !== $request->user()->company_id) {
            throw new HttpResponseException(response()->json([
                'message' => 'Job not found for this HR user.',
            ], 404));
        }
    }
}
