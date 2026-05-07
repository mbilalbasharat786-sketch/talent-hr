<?php

namespace App\Services;

use App\Models\AssessmentSubmission;
use App\Models\JobApplication;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class AdvancedNLPPlagiarismService
{
    private $plagiarismThreshold = 0.75;
    private $semanticThreshold = 0.8;
    private $ngramSize = 3;

    /**
     * Perform advanced plagiarism detection on assessment submission
     */
    public function detectPlagiarism(AssessmentSubmission $submission): array
    {
        try {
            $result = [
                'is_plagiarized' => false,
                'plagiarism_score' => 0,
                'matches' => [],
                'analysis' => [
                    'exact_matches' => 0,
                    'semantic_matches' => 0,
                    'paraphrased_matches' => 0,
                    'source_types' => []
                ]
            ];

            if (empty($submission->answers)) {
                return $result;
            }

            // Get all previous submissions for comparison
            $previousSubmissions = AssessmentSubmission::where('id', '!=', $submission->id)
                ->where('assessment_id', $submission->assessment_id)
                ->whereNotNull('answers')
                ->get();

            if ($previousSubmissions->isEmpty()) {
                return $result;
            }

            // Analyze text patterns
            $submissionText = $this->extractTextFromAnswers($submission->answers);
            $submissionTokens = $this->tokenizeText($submissionText);
            $submissionNgrams = $this->generateNgrams($submissionTokens, $this->ngramSize);

            foreach ($previousSubmissions as $previous) {
                $comparisonResult = $this->compareSubmissions($submission, $previous, $submissionText, $submissionNgrams);
                
                if ($comparisonResult['similarity'] > $this->plagiarismThreshold) {
                    $result['is_plagiarized'] = true;
                    $result['matches'][] = $comparisonResult;
                    
                    // Update analysis stats
                    $this->updateAnalysisStats($result['analysis'], $comparisonResult);
                }
                
                // Update overall plagiarism score
                $result['plagiarism_score'] = max($result['plagiarism_score'], $comparisonResult['similarity']);
            }

            // Check for common plagiarism patterns
            $patternResults = $this->checkPlagiarismPatterns($submissionText);
            if ($patternResults['suspicious']) {
                $result['is_plagiarized'] = true;
                $result['plagiarism_score'] = max($result['plagiarism_score'], $patternResults['score']);
                $result['matches'] = array_merge($result['matches'], $patternResults['matches']);
            }

            // Store plagiarism report
            $this->storePlagiarismReport($submission, $result);

            return $result;

        } catch (\Exception $e) {
            Log::error('Advanced plagiarism detection error: ' . $e->getMessage());
            return [
                'is_plagiarized' => false,
                'plagiarism_score' => 0,
                'matches' => [],
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Extract text from assessment answers
     */
    private function extractTextFromAnswers($answers): string
    {
        $text = '';
        
        if (is_array($answers)) {
            foreach ($answers as $answer) {
                if (is_string($answer)) {
                    $text .= ' ' . $answer;
                } elseif (is_array($answer) && isset($answer['text'])) {
                    $text .= ' ' . $answer['text'];
                }
            }
        } elseif (is_string($answers)) {
            $text = $answers;
        }

        return strtolower(trim($text));
    }

    /**
     * Tokenize text into words
     */
    private function tokenizeText(string $text): array
    {
        // Remove punctuation and split into words
        $text = preg_replace('/[^\w\s]/', ' ', $text);
        $words = preg_split('/\s+/', $text);
        
        // Remove empty strings and common stop words
        $stopWords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'is', 'are', 'was', 'were', 'be', 'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should', 'may', 'might', 'can', 'must', 'this', 'that', 'these', 'those', 'i', 'you', 'he', 'she', 'it', 'we', 'they'];
        
        return array_filter($words, function($word) use ($stopWords) {
            return !empty($word) && !in_array(strtolower($word), $stopWords);
        });
    }

    /**
     * Generate n-grams from tokens
     */
    private function generateNgrams(array $tokens, int $n): array
    {
        $ngrams = [];
        $count = count($tokens);
        
        for ($i = 0; $i <= $count - $n; $i++) {
            $ngram = [];
            for ($j = 0; $j < $n; $j++) {
                $ngram[] = $tokens[$i + $j];
            }
            $ngrams[] = implode(' ', $ngram);
        }
        
        return array_unique($ngrams);
    }

    /**
     * Compare two submissions for plagiarism
     */
    private function compareSubmissions(AssessmentSubmission $submission, AssessmentSubmission $previous, string $submissionText, array $submissionNgrams): array
    {
        $previousText = $this->extractTextFromAnswers($previous->answers);
        $previousTokens = $this->tokenizeText($previousText);
        $previousNgrams = $this->generateNgrams($previousTokens, $this->ngramSize);

        // Calculate different similarity metrics
        $exactSimilarity = $this->calculateExactSimilarity($submissionText, $previousText);
        $ngramSimilarity = $this->calculateNgramSimilarity($submissionNgrams, $previousNgrams);
        $semanticSimilarity = $this->calculateSemanticSimilarity($submissionTokens ?? [], $previousTokens);
        $structureSimilarity = $this->calculateStructureSimilarity($submission->answers, $previous->answers);

        // Weighted combination of similarities
        $overallSimilarity = (
            $exactSimilarity * 0.3 +
            $ngramSimilarity * 0.3 +
            $semanticSimilarity * 0.25 +
            $structureSimilarity * 0.15
        );

        return [
            'submission_id' => $previous->id,
            'candidate_id' => $previous->candidate_id,
            'similarity' => $overallSimilarity,
            'exact_similarity' => $exactSimilarity,
            'ngram_similarity' => $ngramSimilarity,
            'semantic_similarity' => $semanticSimilarity,
            'structure_similarity' => $structureSimilarity,
            'match_type' => $this->determineMatchType($overallSimilarity, $exactSimilarity, $semanticSimilarity),
            'suspicious_phrases' => $this->findSuspiciousPhrases($submissionText, $previousText)
        ];
    }

    /**
     * Calculate exact text similarity
     */
    private function calculateExactSimilarity(string $text1, string $text2): float
    {
        if (empty($text1) || empty($text2)) {
            return 0;
        }

        $words1 = str_word_count($text1, 1);
        $words2 = str_word_count($text2, 1);
        
        $intersection = array_intersect($words1, $words2);
        $union = array_unique(array_merge($words1, $words2));
        
        return count($union) > 0 ? count($intersection) / count($union) : 0;
    }

    /**
     * Calculate n-gram similarity
     */
    private function calculateNgramSimilarity(array $ngrams1, array $ngrams2): float
    {
        if (empty($ngrams1) || empty($ngrams2)) {
            return 0;
        }

        $intersection = array_intersect($ngrams1, $ngrams2);
        $union = array_unique(array_merge($ngrams1, $ngrams2));
        
        return count($union) > 0 ? count($intersection) / count($union) : 0;
    }

    /**
     * Calculate semantic similarity (simplified version)
     */
    private function calculateSemanticSimilarity(array $tokens1, array $tokens2): float
    {
        if (empty($tokens1) || empty($tokens2)) {
            return 0;
        }

        // Create word frequency vectors
        $freq1 = array_count_values($tokens1);
        $freq2 = array_count_values($tokens2);
        
        // Get all unique words
        $allWords = array_unique(array_merge(array_keys($freq1), array_keys($freq2)));
        
        // Create vectors
        $vector1 = [];
        $vector2 = [];
        
        foreach ($allWords as $word) {
            $vector1[] = $freq1[$word] ?? 0;
            $vector2[] = $freq2[$word] ?? 0;
        }
        
        // Calculate cosine similarity
        return $this->cosineSimilarity($vector1, $vector2);
    }

    /**
     * Calculate cosine similarity between two vectors
     */
    private function cosineSimilarity(array $vector1, array $vector2): float
    {
        $dotProduct = 0;
        $magnitude1 = 0;
        $magnitude2 = 0;
        
        for ($i = 0; $i < count($vector1); $i++) {
            $dotProduct += $vector1[$i] * $vector2[$i];
            $magnitude1 += $vector1[$i] * $vector1[$i];
            $magnitude2 += $vector2[$i] * $vector2[$i];
        }
        
        $magnitude1 = sqrt($magnitude1);
        $magnitude2 = sqrt($magnitude2);
        
        if ($magnitude1 == 0 || $magnitude2 == 0) {
            return 0;
        }
        
        return $dotProduct / ($magnitude1 * $magnitude2);
    }

    /**
     * Calculate structure similarity
     */
    private function calculateStructureSimilarity($answers1, $answers2): float
    {
        if (!is_array($answers1) || !is_array($answers2)) {
            return 0;
        }

        $structure1 = $this->extractStructure($answers1);
        $structure2 = $this->extractStructure($answers2);
        
        return $this->cosineSimilarity($structure1, $structure2);
    }

    /**
     * Extract structure from answers (sentence lengths, patterns)
     */
    private function extractStructure($answers): array
    {
        $structure = [];
        
        foreach ($answers as $answer) {
            if (is_string($answer)) {
                $sentences = preg_split('/[.!?]+/', $answer);
                foreach ($sentences as $sentence) {
                    if (!empty(trim($sentence))) {
                        $structure[] = strlen(trim($sentence));
                    }
                }
            }
        }
        
        return $structure;
    }

    /**
     * Determine match type based on similarity scores
     */
    private function determineMatchType(float $overall, float $exact, float $semantic): string
    {
        if ($exact > 0.8) {
            return 'exact_copy';
        } elseif ($semantic > 0.8 && $exact < 0.3) {
            return 'paraphrased';
        } elseif ($overall > 0.7) {
            return 'similar_structure';
        } else {
            return 'partial_match';
        }
    }

    /**
     * Find suspicious phrases that match exactly
     */
    private function findSuspiciousPhrases(string $text1, string $text2): array
    {
        $suspicious = [];
        $phrases1 = $this->extractPhrases($text1);
        $phrases2 = $this->extractPhrases($text2);
        
        foreach ($phrases1 as $phrase1) {
            foreach ($phrases2 as $phrase2) {
                if (strlen($phrase1) > 20 && strtolower($phrase1) === strtolower($phrase2)) {
                    $suspicious[] = $phrase1;
                }
            }
        }
        
        return array_unique($suspicious);
    }

    /**
     * Extract phrases from text
     */
    private function extractPhrases(string $text): array
    {
        $sentences = preg_split('/[.!?]+/', $text);
        $phrases = [];
        
        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if (!empty($sentence)) {
                $phrases[] = $sentence;
                
                // Also extract sub-phrases (comma-separated)
                $subPhrases = explode(',', $sentence);
                foreach ($subPhrases as $subPhrase) {
                    $subPhrase = trim($subPhrase);
                    if (!empty($subPhrase) && strlen($subPhrase) > 10) {
                        $phrases[] = $subPhrase;
                    }
                }
            }
        }
        
        return $phrases;
    }

    /**
     * Check for common plagiarism patterns
     */
    private function checkPlagiarismPatterns(string $text): array
    {
        $result = [
            'suspicious' => false,
            'score' => 0,
            'matches' => []
        ];

        // Check for template-like answers
        $templatePatterns = [
            '/^(i think|in my opinion|i believe)\s+/i',
            '/^(firstly|secondly|finally|in conclusion)\s+/i',
            '/^(therefore|however|moreover|furthermore)\s+/i'
        ];

        $templateCount = 0;
        foreach ($templatePatterns as $pattern) {
            if (preg_match_all($pattern, $text)) {
                $templateCount++;
            }
        }

        if ($templateCount > 3) {
            $result['suspicious'] = true;
            $result['score'] = 0.6;
            $result['matches'][] = [
                'type' => 'template_pattern',
                'description' => 'Excessive use of template phrases detected'
            ];
        }

        // Check for unusually consistent sentence lengths
        $sentences = preg_split('/[.!?]+/', $text);
        $lengths = array_map('strlen', array_filter($sentences, 'trim'));
        
        if (count($lengths) > 5) {
            $avgLength = array_sum($lengths) / count($lengths);
            $variance = array_sum(array_map(function($length) use ($avgLength) {
                return pow($length - $avgLength, 2);
            }, $lengths)) / count($lengths);
            
            if ($variance < 100) { // Very low variance suggests template
                $result['suspicious'] = true;
                $result['score'] = max($result['score'], 0.5);
                $result['matches'][] = [
                    'type' => 'uniform_structure',
                    'description' => 'Unusually uniform sentence structure detected'
                ];
            }
        }

        return $result;
    }

    /**
     * Update analysis statistics
     */
    private function updateAnalysisStats(array &$analysis, array $comparisonResult): void
    {
        $matchType = $comparisonResult['match_type'];
        
        switch ($matchType) {
            case 'exact_copy':
                $analysis['exact_matches']++;
                break;
            case 'paraphrased':
                $analysis['paraphrased_matches']++;
                break;
            case 'similar_structure':
                $analysis['semantic_matches']++;
                break;
        }
        
        $sourceType = 'previous_submission';
        if (!isset($analysis['source_types'][$sourceType])) {
            $analysis['source_types'][$sourceType] = 0;
        }
        $analysis['source_types'][$sourceType]++;
    }

    /**
     * Store plagiarism report in job application
     */
    private function storePlagiarismReport(AssessmentSubmission $submission, array $result): void
    {
        try {
            // Find associated job application
            $jobApplication = JobApplication::where('candidate_id', $submission->candidate_id)
                ->whereHas('job', function($query) use ($submission) {
                    $query->where('assessment_id', $submission->assessment_id);
                })
                ->first();

            if ($jobApplication) {
                $report = [
                    'plagiarism_detected' => $result['is_plagiarized'],
                    'plagiarism_score' => $result['plagiarism_score'],
                    'analysis_date' => now()->toISOString(),
                    'matches_count' => count($result['matches']),
                    'analysis_details' => $result['analysis']
                ];

                if (!empty($result['matches'])) {
                    $report['top_matches'] = array_slice($result['matches'], 0, 3);
                }

                $jobApplication->update([
                    'plagiarism_report' => json_encode($report)
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error storing plagiarism report: ' . $e->getMessage());
        }
    }

    /**
     * Update plagiarism thresholds
     */
    public function setThresholds(float $plagiarismThreshold, float $semanticThreshold): void
    {
        $this->plagiarismThreshold = max(0.1, min(1.0, $plagiarismThreshold));
        $this->semanticThreshold = max(0.1, min(1.0, $semanticThreshold));
    }

    /**
     * Get detailed plagiarism report for submission
     */
    public function getDetailedReport(AssessmentSubmission $submission): array
    {
        return $this->detectPlagiarism($submission);
    }
}
