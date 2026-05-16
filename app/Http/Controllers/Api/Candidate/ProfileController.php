<?php

namespace App\Http\Controllers\Api\Candidate;

use App\Http\Controllers\Controller;
use App\Services\CandidateProfileScoreService;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request, CandidateProfileScoreService $scoreService)
    {
        $candidate = $request->user()->load(['internships', 'assessmentSubmissions']);
        $scoreBreakdown = $scoreService->calculate($candidate);

        return response()->json([
            'profile' => $candidate->fresh(),
            'score_breakdown' => $scoreBreakdown,
        ]);
    }

    public function update(Request $request, CandidateProfileScoreService $scoreService)
    {
        $candidate = $request->user();

        $request->validate([
            'skills' => ['nullable', 'array'],
            'skills.*' => ['required', 'string', 'max:100'],
            'education' => ['nullable', 'string'],
            'experience' => ['nullable', 'string'],
            'portfolio_links' => ['nullable', 'array'],
            'portfolio_links.*' => ['required', 'url', 'max:500'],
        ]);

        $data = $request->only([
            'skills',
            'education',
            'experience',
        ]);

        if ($request->has('portfolio_links')) {
            $data['portfolio_links'] = $request->portfolio_links;
        }

        $candidate->update($data);

        $scoreBreakdown = $scoreService->calculate($candidate->fresh(['internships', 'assessmentSubmissions']));

        return response()->json([
            'message' => 'Candidate profile updated successfully.',
            'profile' => $candidate->fresh(),
            'score_breakdown' => $scoreBreakdown,
        ]);
    }
}
