<?php

namespace App\Services;

use App\Models\JobApplication;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class AdvancedSkillMatchingService
{
    private $skillWeights = [
        'exact_match' => 1.0,
        'partial_match' => 0.7,
        'related_match' => 0.4,
        'experience_bonus' => 0.2,
        'education_bonus' => 0.15,
        'portfolio_bonus' => 0.1
    ];

    private $skillSynonyms = [
        'javascript' => ['js', 'ecmascript', 'es6', 'node.js'],
        'python' => ['django', 'flask', 'fastapi'],
        'java' => ['spring', 'springboot', 'j2ee'],
        'php' => ['laravel', 'symfony', 'codeigniter'],
        'react' => ['reactjs', 'react.js', 'jsx'],
        'vue' => ['vuejs', 'vue.js', 'nuxt'],
        'angular' => ['angularjs', 'typescript'],
        'css' => ['scss', 'sass', 'less', 'tailwind'],
        'html' => ['html5', 'markup'],
        'sql' => ['mysql', 'postgresql', 'database', 'oracle'],
        'aws' => ['amazon web services', 'cloud', 'ec2', 's3'],
        'docker' => ['containers', 'kubernetes', 'k8s'],
        'git' => ['version control', 'github', 'gitlab'],
        'testing' => ['unit testing', 'integration testing', 'jest', 'cypress'],
        'ui' => ['user interface', 'frontend', 'ux'],
        'ux' => ['user experience', 'user centered design'],
        'api' => ['rest', 'graphql', 'web services'],
        'mobile' => ['android', 'ios', 'react native', 'flutter']
    ];

    /**
     * Calculate advanced skill match percentage for a job application
     */
    public function calculateSkillMatch(JobApplication $application): float
    {
        try {
            $candidate = $application->candidate;
            $job = $application->job;

            if (!$candidate || !$job) {
                return 0;
            }

            $jobSkills = $this->normalizeSkills($job->skills ?? []);
            $candidateSkills = $this->normalizeSkills($candidate->skills ?? []);

            if (empty($jobSkills) || empty($candidateSkills)) {
                return 0;
            }

            // Calculate base skill matching
            $skillScore = $this->calculateSkillScore($jobSkills, $candidateSkills);
            
            // Add experience bonus
            $experienceBonus = $this->calculateExperienceBonus($candidate->experience ?? '', $jobSkills);
            
            // Add education bonus
            $educationBonus = $this->calculateEducationBonus($candidate->education ?? '', $job);
            
            // Add portfolio bonus
            $portfolioBonus = $this->calculatePortfolioBonus($application->portfolio_links ?? []);

            // Calculate final score
            $finalScore = min(100, ($skillScore + $experienceBonus + $educationBonus + $portfolioBonus) * 100);

            // Update the application with new score
            $application->update(['skill_match_percentage' => round($finalScore, 2)]);

            return round($finalScore, 2);

        } catch (\Exception $e) {
            Log::error('Skill matching calculation error: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Normalize and clean skills array
     */
    private function normalizeSkills($skills): array
    {
        if (is_string($skills)) {
            $skills = explode(',', $skills);
        }

        $normalized = [];
        foreach ($skills as $skill) {
            if (is_string($skill)) {
                $skill = strtolower(trim($skill));
                if (!empty($skill)) {
                    $normalized[] = $skill;
                }
            }
        }

        return array_unique($normalized);
    }

    /**
     * Calculate base skill matching score
     */
    private function calculateSkillScore(array $jobSkills, array $candidateSkills): float
    {
        $totalScore = 0;
        $maxPossibleScore = count($jobSkills);

        foreach ($jobSkills as $jobSkill) {
            $bestMatch = $this->findBestSkillMatch($jobSkill, $candidateSkills);
            $totalScore += $bestMatch['score'];
        }

        return $maxPossibleScore > 0 ? $totalScore / $maxPossibleScore : 0;
    }

    /**
     * Find best match for a skill against candidate skills
     */
    private function findBestSkillMatch(string $jobSkill, array $candidateSkills): array
    {
        $bestScore = ['score' => 0, 'type' => 'none'];

        foreach ($candidateSkills as $candidateSkill) {
            $matchScore = $this->compareSkills($jobSkill, $candidateSkill);
            if ($matchScore['score'] > $bestScore['score']) {
                $bestScore = $matchScore;
            }
        }

        return $bestScore;
    }

    /**
     * Compare two skills and determine match type and score
     */
    private function compareSkills(string $jobSkill, string $candidateSkill): array
    {
        // Exact match
        if ($jobSkill === $candidateSkill) {
            return ['score' => $this->skillWeights['exact_match'], 'type' => 'exact'];
        }

        // Check if one contains the other
        if (str_contains($candidateSkill, $jobSkill) || str_contains($jobSkill, $candidateSkill)) {
            return ['score' => $this->skillWeights['partial_match'], 'type' => 'partial'];
        }

        // Check synonyms and related skills
        foreach ($this->skillSynonyms as $mainSkill => $synonyms) {
            $jobInSynonyms = in_array($jobSkill, $synonyms) || $jobSkill === $mainSkill;
            $candidateInSynonyms = in_array($candidateSkill, $synonyms) || $candidateSkill === $mainSkill;
            
            if ($jobInSynonyms && $candidateInSynonyms) {
                return ['score' => $this->skillWeights['related_match'], 'type' => 'related'];
            }
        }

        // Fuzzy matching for similar words
        $similarity = $this->calculateStringSimilarity($jobSkill, $candidateSkill);
        if ($similarity > 0.7) {
            return ['score' => $this->skillWeights['partial_match'] * $similarity, 'type' => 'fuzzy'];
        }

        return ['score' => 0, 'type' => 'none'];
    }

    /**
     * Calculate string similarity using Levenshtein distance
     */
    private function calculateStringSimilarity(string $str1, string $str2): float
    {
        $len1 = strlen($str1);
        $len2 = strlen($str2);
        
        if ($len1 === 0 || $len2 === 0) {
            return 0;
        }

        $distance = levenshtein($str1, $str2);
        $maxLen = max($len1, $len2);
        
        return 1 - ($distance / $maxLen);
    }

    /**
     * Calculate experience bonus based on relevant experience
     */
    private function calculateExperienceBonus(string $experience, array $jobSkills): float
    {
        if (empty($experience)) {
            return 0;
        }

        $experienceLower = strtolower($experience);
        $bonusScore = 0;
        $foundSkills = 0;

        foreach ($jobSkills as $skill) {
            if (str_contains($experienceLower, $skill)) {
                $foundSkills++;
                
                // Extract years of experience if mentioned
                if (preg_match('/(\d+)\s*(?:years?|yrs?)/i', $experience, $matches)) {
                    $years = (int) $matches[1];
                    $bonusScore += min($years * 0.05, 0.2); // Max 0.2 per skill
                } else {
                    $bonusScore += 0.1; // Basic mention bonus
                }
            }
        }

        return $foundSkills > 0 ? min($bonusScore / count($jobSkills), $this->skillWeights['experience_bonus']) : 0;
    }

    /**
     * Calculate education bonus based on relevance
     */
    private function calculateEducationBonus(string $education, $job): float
    {
        if (empty($education)) {
            return 0;
        }

        $educationLower = strtolower($education);
        $bonus = 0;

        // Check for relevant degrees
        $relevantDegrees = ['computer science', 'software engineering', 'information technology', 'data science'];
        foreach ($relevantDegrees as $degree) {
            if (str_contains($educationLower, $degree)) {
                $bonus += 0.1;
                break;
            }
        }

        // Check for advanced degrees
        if (preg_match('/(master|phd|doctorate|bachelor|bsc|msc)/i', $education)) {
            $bonus += 0.05;
        }

        return min($bonus, $this->skillWeights['education_bonus']);
    }

    /**
     * Calculate portfolio bonus
     */
    private function calculatePortfolioBonus($portfolioLinks): float
    {
        if (!is_array($portfolioLinks) || empty($portfolioLinks)) {
            return 0;
        }

        $validLinks = 0;
        foreach ($portfolioLinks as $link) {
            if (filter_var($link, FILTER_VALIDATE_URL)) {
                $validLinks++;
            }
        }

        return min($validLinks * 0.03, $this->skillWeights['portfolio_bonus']);
    }

    /**
     * Get detailed skill matching breakdown
     */
    public function getSkillMatchingBreakdown(JobApplication $application): array
    {
        $candidate = $application->candidate;
        $job = $application->job;

        if (!$candidate || !$job) {
            return [];
        }

        $jobSkills = $this->normalizeSkills($job->skills ?? []);
        $candidateSkills = $this->normalizeSkills($candidate->skills ?? []);

        $breakdown = [
            'job_skills' => [],
            'candidate_skills' => $candidateSkills,
            'overall_score' => 0,
            'breakdown' => [
                'skill_match' => 0,
                'experience_bonus' => 0,
                'education_bonus' => 0,
                'portfolio_bonus' => 0
            ]
        ];

        foreach ($jobSkills as $jobSkill) {
            $bestMatch = $this->findBestSkillMatch($jobSkill, $candidateSkills);
            $breakdown['job_skills'][] = [
                'skill' => $jobSkill,
                'match_type' => $bestMatch['type'],
                'score' => $bestMatch['score'],
                'matched_with' => $bestMatch['score'] > 0 ? $this->getMatchingSkill($jobSkill, $candidateSkills) : null
            ];
        }

        $skillScore = array_sum(array_column($breakdown['job_skills'], 'score')) / count($jobSkills);
        $experienceBonus = $this->calculateExperienceBonus($candidate->experience ?? '', $jobSkills);
        $educationBonus = $this->calculateEducationBonus($candidate->education ?? '', $job);
        $portfolioBonus = $this->calculatePortfolioBonus($application->portfolio_links ?? []);

        $breakdown['breakdown']['skill_match'] = $skillScore;
        $breakdown['breakdown']['experience_bonus'] = $experienceBonus;
        $breakdown['breakdown']['education_bonus'] = $educationBonus;
        $breakdown['breakdown']['portfolio_bonus'] = $portfolioBonus;
        $breakdown['overall_score'] = min(100, ($skillScore + $experienceBonus + $educationBonus + $portfolioBonus) * 100);

        return $breakdown;
    }

    /**
     * Get the skill that matched with the job skill
     */
    private function getMatchingSkill(string $jobSkill, array $candidateSkills): ?string
    {
        foreach ($candidateSkills as $candidateSkill) {
            $match = $this->compareSkills($jobSkill, $candidateSkill);
            if ($match['score'] > 0) {
                return $candidateSkill;
            }
        }
        return null;
    }

    /**
     * Recalculate skill matches for all applications of a job
     */
    public function recalculateJobSkillMatches($jobId): void
    {
        try {
            $applications = JobApplication::where('job_id', $jobId)->get();
            
            foreach ($applications as $application) {
                $this->calculateSkillMatch($application);
            }

            Log::info("Skill matches recalculated for job {$jobId}");

        } catch (\Exception $e) {
            Log::error("Error recalculating skill matches for job {$jobId}: " . $e->getMessage());
        }
    }

    /**
     * Add custom skill synonyms
     */
    public function addSkillSynonyms(string $mainSkill, array $synonyms): void
    {
        $this->skillSynonyms[strtolower($mainSkill)] = array_map('strtolower', $synonyms);
    }

    /**
     * Update skill weights
     */
    public function updateSkillWeights(array $weights): void
    {
        $this->skillWeights = array_merge($this->skillWeights, $weights);
    }
}
