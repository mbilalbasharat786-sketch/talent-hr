<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\HrJob;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class JobController extends Controller
{
    /**
     * Get all publicly available jobs with filtering and pagination
     */
    public function index(Request $request): JsonResponse
    {
        $query = HrJob::with(['company:id,name,logo,industry'])
            ->where('status', 'live')
            ->whereNotNull('title')
            ->whereNotNull('description');

        // Apply filters
        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('title', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('description', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('skills', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('location', 'LIKE', "%{$searchTerm}%");
            });
        }

        if ($request->filled('location')) {
            $location = $request->location;
            if ($location === 'remote') {
                $query->where('work_mode', 'remote');
            } elseif ($location === 'onsite') {
                $query->where('work_mode', 'onsite');
            } elseif ($location === 'hybrid') {
                $query->where('work_mode', 'hybrid');
            } else {
                $query->where('location', 'LIKE', "%{$location}%");
            }
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('work_mode')) {
            $query->where('work_mode', $request->work_mode);
        }

        if ($request->filled('hiring_urgency')) {
            $query->where('hiring_urgency', $request->hiring_urgency);
        }

        // Sort by latest first, then by urgency
        $query->orderByRaw("FIELD(hiring_urgency, 'high', 'medium', 'low') ASC")
              ->orderBy('created_at', 'desc');

        // Paginate
        $perPage = $request->get('per_page', 12);
        $jobs = $query->paginate($perPage);

        // Transform the data for public consumption
        $transformedJobs = $jobs->getCollection()->map(function ($job) {
            return [
                'id' => $job->id,
                'title' => $job->title,
                'description' => $this->truncateDescription($job->description),
                'company' => [
                    'id' => $job->company->id,
                    'name' => $job->company->name,
                    'logo' => $job->company->logo,
                    'industry' => $job->company->industry,
                ],
                'location' => $job->location,
                'type' => $job->type,
                'work_mode' => $job->work_mode,
                'hiring_urgency' => $job->hiring_urgency,
                'candidates_required' => $job->candidates_required,
                'skills' => $job->skills,
                'experience_level' => $job->experience_level,
                'education' => $job->education,
                'created_at' => $job->created_at,
                'posted_date' => $job->created_at->format('Y-m-d'),
                'days_ago' => $job->created_at->diffForHumans(),
            ];
        });

        return response()->json([
            'data' => $transformedJobs,
            'current_page' => $jobs->currentPage(),
            'last_page' => $jobs->lastPage(),
            'per_page' => $jobs->perPage(),
            'total' => $jobs->total(),
            'from' => $jobs->firstItem(),
            'to' => $jobs->lastItem(),
        ]);
    }

    /**
     * Get detailed information about a specific job
     */
    public function show(Request $request, $id): JsonResponse
    {
        $job = HrJob::with(['company:id,name,logo,industry,about,website'])
            ->where('id', $id)
            ->where('status', 'live')
            ->first();

        if (!$job) {
            return response()->json([
                'message' => 'Job not found or no longer available'
            ], 404);
        }

        // Get application statistics (public info only)
        $applicationStats = [
            'total_applications' => $job->jobApplications()->count(),
            'active_applications' => $job->jobApplications()
                ->whereIn('status', ['applied', 'assessment_pending', 'submitted', 'passed', 'shortlisted'])
                ->count(),
            'hired_count' => $job->jobApplications()->where('status', 'hired')->count(),
        ];

        return response()->json([
            'id' => $job->id,
            'title' => $job->title,
            'description' => $job->description,
            'company' => [
                'id' => $job->company->id,
                'name' => $job->company->name,
                'logo' => $job->company->logo,
                'industry' => $job->company->industry,
                'about' => $job->company->about,
                'website' => $job->company->website,
            ],
            'location' => $job->location,
            'type' => $job->type,
            'work_mode' => $job->work_mode,
            'hiring_urgency' => $job->hiring_urgency,
            'candidates_required' => $job->candidates_required,
            'skills' => $job->skills,
            'experience_level' => $job->experience_level,
            'education' => $job->education,
            'created_at' => $job->created_at,
            'posted_date' => $job->created_at->format('Y-m-d'),
            'days_ago' => $job->created_at->diffForHumans(),
            'application_stats' => $applicationStats,
            'application_deadline' => $job->application_deadline,
            'salary_range' => $job->salary_range,
            'benefits' => $job->benefits,
        ]);
    }

    /**
     * Get popular job categories/skills
     */
    public function categories(): JsonResponse
    {
        // Get most common skills across all live jobs
        $skills = HrJob::where('status', 'live')
            ->whereNotNull('skills')
            ->get()
            ->flatMap(function ($job) {
                return is_array($job->skills) ? $job->skills : [];
            })
            ->countBy()
            ->sortDesc()
            ->take(20)
            ->map(function ($count, $skill) {
                return [
                    'name' => $skill,
                    'count' => $count,
                ];
            })
            ->values();

        // Get job types distribution
        $jobTypes = HrJob::where('status', 'live')
            ->selectRaw('type, COUNT(*) as count')
            ->groupBy('type')
            ->get()
            ->map(function ($item) {
                return [
                    'type' => $item->type,
                    'count' => $item->count,
                    'label' => $this->formatJobType($item->type),
                ];
            });

        // Get work modes distribution
        $workModes = HrJob::where('status', 'live')
            ->selectRaw('work_mode, COUNT(*) as count')
            ->groupBy('work_mode')
            ->get()
            ->map(function ($item) {
                return [
                    'mode' => $item->work_mode,
                    'count' => $item->count,
                    'label' => $this->formatWorkMode($item->work_mode),
                ];
            });

        // Get top locations
        $locations = HrJob::where('status', 'live')
            ->whereNotNull('location')
            ->selectRaw('location, COUNT(*) as count')
            ->groupBy('location')
            ->orderBy('count', 'desc')
            ->limit(15)
            ->get();

        return response()->json([
            'popular_skills' => $skills,
            'job_types' => $jobTypes,
            'work_modes' => $workModes,
            'top_locations' => $locations,
        ]);
    }

    /**
     * Get featured/companies with jobs
     */
    public function featuredCompanies(): JsonResponse
    {
        $companies = Company::withCount(['hrJobs' => function ($query) {
                $query->where('status', 'live');
            }])
            ->whereHas('hrJobs', function ($query) {
                $query->where('status', 'live');
            })
            ->where('status', 'approved')
            ->orderBy('hr_jobs_count', 'desc')
            ->limit(12)
            ->get(['id', 'name', 'logo', 'industry', 'about']);

        return response()->json($companies);
    }

    /**
     * Get job statistics for public display
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'total_jobs' => HrJob::where('status', 'live')->count(),
            'total_companies' => Company::where('status', 'approved')->count(),
            'new_jobs_this_week' => HrJob::where('status', 'live')
                ->where('created_at', '>=', now()->subDays(7))
                ->count(),
            'urgent_jobs' => HrJob::where('status', 'live')
                ->where('hiring_urgency', 'high')
                ->count(),
        ];

        return response()->json($stats);
    }

    /**
     * Search jobs with advanced filters
     */
    public function search(Request $request): JsonResponse
    {
        $query = HrJob::with(['company:id,name,logo'])
            ->where('status', 'live');

        // Advanced search
        if ($request->filled('q')) {
            $searchTerm = $request->q;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('title', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('description', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('skills', 'LIKE', "%{$searchTerm}%");
            });
        }

        // Filter by skills
        if ($request->filled('skills')) {
            $skills = explode(',', $request->skills);
            foreach ($skills as $skill) {
                $query->where('skills', 'LIKE', "%".trim($skill)."%");
            }
        }

        // Filter by salary range (if implemented)
        if ($request->filled('min_salary')) {
            // This would require salary_range to be structured data
            // For now, we'll skip this implementation
        }

        // Filter by experience level
        if ($request->filled('experience_level')) {
            $query->where('experience_level', $request->experience_level);
        }

        // Sort options
        $sortBy = $request->get('sort_by', 'latest');
        switch ($sortBy) {
            case 'latest':
                $query->orderBy('created_at', 'desc');
                break;
            case 'oldest':
                $query->orderBy('created_at', 'asc');
                break;
            case 'urgent':
                $query->orderByRaw("FIELD(hiring_urgency, 'high', 'medium', 'low') ASC")
                      ->orderBy('created_at', 'desc');
                break;
            case 'most_applications':
                // This would require joining with applications table
                $query->orderBy('created_at', 'desc');
                break;
            default:
                $query->orderBy('created_at', 'desc');
        }

        $perPage = $request->get('per_page', 12);
        $jobs = $query->paginate($perPage);

        return response()->json($jobs);
    }

    /**
     * Truncate description for preview
     */
    private function truncateDescription($description, $maxLength = 150): string
    {
        if (empty($description)) {
            return 'No description available';
        }

        $description = strip_tags($description);
        if (strlen($description) <= $maxLength) {
            return $description;
        }

        return substr($description, 0, $maxLength) . '...';
    }

    /**
     * Format job type for display
     */
    private function formatJobType($type): string
    {
        return ucfirst(str_replace('_', ' ', $type));
    }

    /**
     * Format work mode for display
     */
    private function formatWorkMode($mode): string
    {
        return ucfirst($mode);
    }
}
