<?php

namespace App\Services;

use App\Models\FraudLog;
use App\Models\Internship;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class DuplicateCertificateDetectionService
{
    private $similarityThreshold = 0.85;

    /**
     * Detect duplicate internship certificates
     */
    public function detectDuplicateCertificate($file, $candidateId, $internshipData = []): array
    {
        $result = [
            'is_duplicate' => false,
            'similarity_score' => 0,
            'matches' => [],
            'fraud_log_id' => null
        ];

        try {
            if (!$file) {
                return $result;
            }

            // Get file hash for comparison
            $fileHash = $this->generateFileHash($file);
            
            // Check for exact duplicates
            $exactMatches = $this->findExactDuplicates($fileHash, $candidateId);
            
            if (!empty($exactMatches)) {
                $result['is_duplicate'] = true;
                $result['similarity_score'] = 1.0;
                $result['matches'] = $exactMatches;
                
                $fraudLog = $this->logDuplicateDetection($candidateId, 'exact', $result);
                $result['fraud_log_id'] = $fraudLog->id;
                
                return $result;
            }

            // Check for similar certificates using content analysis
            $similarMatches = $this->findSimilarCertificates($file, $candidateId, $internshipData);
            
            if (!empty($similarMatches)) {
                $result['is_duplicate'] = true;
                $result['similarity_score'] = $similarMatches[0]['similarity'];
                $result['matches'] = $similarMatches;
                
                $fraudLog = $this->logDuplicateDetection($candidateId, 'similar', $result);
                $result['fraud_log_id'] = $fraudLog->id;
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('Duplicate certificate detection error: ' . $e->getMessage());
            return $result;
        }
    }

    /**
     * Generate unique file hash
     */
    private function generateFileHash($file): string
    {
        $content = file_get_contents($file->getPathname());
        return hash('sha256', $content);
    }

    /**
     * Find exact duplicates by file hash
     */
    private function findExactDuplicates($fileHash, $candidateId): array
    {
        $matches = [];
        
        try {
            // Search in internships table for same certificate hash
            $internships = Internship::where('certificate_hash', $fileHash)
                ->where('candidate_id', '!=', $candidateId)
                ->with('candidate:id,name,email')
                ->get();

            foreach ($internships as $internship) {
                $matches[] = [
                    'type' => 'exact_duplicate',
                    'candidate_id' => $internship->candidate_id,
                    'candidate_name' => $internship->candidate->name ?? 'Unknown',
                    'candidate_email' => $internship->candidate->email ?? 'Unknown',
                    'company_name' => $internship->company_name,
                    'duration' => $internship->duration,
                    'similarity' => 1.0,
                    'internship_id' => $internship->id
                ];
            }

        } catch (\Exception $e) {
            Log::error('Exact duplicate search error: ' . $e->getMessage());
        }

        return $matches;
    }

    /**
     * Find similar certificates using content analysis
     */
    private function findSimilarCertificates($file, $candidateId, $internshipData): array
    {
        $matches = [];
        
        try {
            // Extract text from certificate
            $certificateText = $this->extractTextFromCertificate($file);
            
            if (empty($certificateText)) {
                return $matches;
            }

            // Get all internships except current candidate
            $allInternships = Internship::where('candidate_id', '!=', $candidateId)
                ->whereNotNull('certificate_text')
                ->with('candidate:id,name,email')
                ->get();

            foreach ($allInternships as $internship) {
                $similarity = $this->calculateTextSimilarity($certificateText, $internship->certificate_text);
                
                if ($similarity >= $this->similarityThreshold) {
                    $matches[] = [
                        'type' => 'similar_content',
                        'candidate_id' => $internship->candidate_id,
                        'candidate_name' => $internship->candidate->name ?? 'Unknown',
                        'candidate_email' => $internship->candidate->email ?? 'Unknown',
                        'company_name' => $internship->company_name,
                        'duration' => $internship->duration,
                        'similarity' => $similarity,
                        'internship_id' => $internship->id
                    ];
                }
            }

            // Sort by similarity score (highest first)
            usort($matches, function ($a, $b) {
                return $b['similarity'] <=> $a['similarity'];
            });

        } catch (\Exception $e) {
            Log::error('Similar certificate search error: ' . $e->getMessage());
        }

        return $matches;
    }

    /**
     * Extract text from certificate file
     */
    private function extractTextFromCertificate($file): string
    {
        $text = '';
        
        try {
            $mimeType = $file->getMimeType();
            
            if (str_contains($mimeType, 'pdf')) {
                $text = $this->extractTextFromPDF($file->getPathname());
            } elseif (str_contains($mimeType, 'image')) {
                $text = $this->extractTextFromImage($file->getPathname());
            }

        } catch (\Exception $e) {
            Log::error('Text extraction error: ' . $e->getMessage());
        }

        return $text;
    }

    /**
     * Extract text from PDF file
     */
    private function extractTextFromPDF($filePath): string
    {
        $text = '';
        
        try {
            // Basic PDF text extraction (simplified version)
            $content = file_get_contents($filePath);
            
            // Extract text between stream and endstream markers
            preg_match_all('/stream\s*(.*?)\s*endstream/s', $content, $matches);
            
            foreach ($matches[1] as $match) {
                // Remove binary data and keep text
                $cleanText = preg_replace('/[^a-zA-Z0-9\s\.\,\-\:]/', ' ', $match);
                $text .= ' ' . $cleanText;
            }
            
            // Also extract text from content areas
            preg_match_all('/\[(.*?)\]/', $content, $textMatches);
            foreach ($textMatches[1] as $match) {
                $text .= ' ' . $match;
            }

        } catch (\Exception $e) {
            Log::error('PDF text extraction error: ' . $e->getMessage());
        }

        return trim($text);
    }

    /**
     * Extract text from image file (basic OCR simulation)
     */
    private function extractTextFromImage($filePath): string
    {
        $text = '';
        
        try {
            // Note: This is a simplified version
            // In production, you'd use proper OCR libraries like Tesseract
            $imageInfo = getimagesize($filePath);
            
            if ($imageInfo) {
                // For now, return empty as proper OCR requires external libraries
                // This would need integration with services like Google Vision API or Tesseract
                Log::warning('OCR not implemented - would require external service');
            }

        } catch (\Exception $e) {
            Log::error('Image text extraction error: ' . $e->getMessage());
        }

        return $text;
    }

    /**
     * Calculate text similarity using cosine similarity
     */
    private function calculateTextSimilarity($text1, $text2): float
    {
        if (empty($text1) || empty($text2)) {
            return 0;
        }

        try {
            // Normalize text
            $text1 = strtolower(preg_replace('/[^a-zA-Z0-9\s]/', ' ', $text1));
            $text2 = strtolower(preg_replace('/[^a-zA-Z0-9\s]/', ' ', $text2));
            
            // Create word frequency vectors
            $words1 = array_count_values(str_word_count($text1, 1));
            $words2 = array_count_values(str_word_count($text2, 1));
            
            // Get all unique words
            $allWords = array_unique(array_merge(array_keys($words1), array_keys($words2)));
            
            // Create vectors
            $vector1 = [];
            $vector2 = [];
            
            foreach ($allWords as $word) {
                $vector1[] = $words1[$word] ?? 0;
                $vector2[] = $words2[$word] ?? 0;
            }
            
            // Calculate cosine similarity
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

        } catch (\Exception $e) {
            Log::error('Similarity calculation error: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Check for metadata similarity
     */
    public function checkMetadataSimilarity($newInternshipData, $candidateId): array
    {
        $matches = [];
        
        try {
            $internships = Internship::where('candidate_id', '!=', $candidateId)
                ->with('candidate:id,name,email')
                ->get();

            foreach ($internships as $internship) {
                $similarity = $this->calculateMetadataSimilarity($newInternshipData, [
                    'company_name' => $internship->company_name,
                    'duration' => $internship->duration,
                    'supervisor_email' => $internship->supervisor_email
                ]);
                
                if ($similarity >= 0.9) { // High threshold for metadata
                    $matches[] = [
                        'type' => 'metadata_duplicate',
                        'candidate_id' => $internship->candidate_id,
                        'candidate_name' => $internship->candidate->name ?? 'Unknown',
                        'candidate_email' => $internship->candidate->email ?? 'Unknown',
                        'company_name' => $internship->company_name,
                        'duration' => $internship->duration,
                        'similarity' => $similarity,
                        'internship_id' => $internship->id
                    ];
                }
            }

        } catch (\Exception $e) {
            Log::error('Metadata similarity check error: ' . $e->getMessage());
        }

        return $matches;
    }

    /**
     * Calculate metadata similarity
     */
    private function calculateMetadataSimilarity($data1, $data2): float
    {
        $similarities = [];
        
        // Company name similarity
        if (isset($data1['company_name']) && isset($data2['company_name'])) {
            $similarities[] = $this->calculateTextSimilarity($data1['company_name'], $data2['company_name']);
        }
        
        // Duration similarity
        if (isset($data1['duration']) && isset($data2['duration'])) {
            $similarities[] = $data1['duration'] === $data2['duration'] ? 1.0 : 0.0;
        }
        
        // Supervisor email similarity
        if (isset($data1['supervisor_email']) && isset($data2['supervisor_email'])) {
            $similarities[] = $data1['supervisor_email'] === $data2['supervisor_email'] ? 1.0 : 0.0;
        }
        
        return empty($similarities) ? 0 : array_sum($similarities) / count($similarities);
    }

    /**
     * Log duplicate detection to fraud logs
     */
    private function logDuplicateDetection($candidateId, $duplicateType, $result): FraudLog
    {
        $description = "Duplicate internship certificate detected ({$duplicateType}). ";
        $description .= "Similarity: {$result['similarity_score']}. ";
        
        if (!empty($result['matches'])) {
            $matchInfo = [];
            foreach ($result['matches'] as $match) {
                $matchInfo[] = "Candidate: {$match['candidate_name']} ({$match['candidate_email']}), "
                    . "Company: {$match['company_name']}, Similarity: {$match['similarity']}";
            }
            $description .= "Matches: " . implode('; ', $matchInfo);
        }

        return FraudLog::create([
            'type' => 'duplicate_internship_certificate',
            'reference_id' => $candidateId,
            'description' => $description,
            'status' => 'open'
        ]);
    }

    /**
     * Update similarity threshold
     */
    public function setSimilarityThreshold(float $threshold): void
    {
        $this->similarityThreshold = max(0.1, min(1.0, $threshold));
    }
}
