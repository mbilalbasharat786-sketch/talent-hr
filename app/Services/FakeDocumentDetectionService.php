<?php

namespace App\Services;

use App\Models\FraudLog;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class FakeDocumentDetectionService
{
    private $confidenceThreshold = 0.7;

    /**
     * Detect fake documents in uploaded files
     */
    public function detectFakeDocument($file, $candidateId, $documentType = 'certificate'): array
    {
        $result = [
            'is_fake' => false,
            'confidence' => 0,
            'reasons' => [],
            'fraud_log_id' => null
        ];

        try {
            // Check file existence and basic validation
            if (!$file || !file_exists($file->getPathname())) {
                return $result;
            }

            // Get file content for analysis
            $filePath = $file->getPathname();
            $fileContent = file_get_contents($filePath);
            $mimeType = $file->getMimeType();

            // Perform various detection checks
            $checks = $this->performDocumentChecks($fileContent, $mimeType, $file);
            
            // Calculate overall confidence
            $result['confidence'] = $this->calculateConfidence($checks);
            $result['reasons'] = $checks['reasons'];
            $result['is_fake'] = $result['confidence'] >= $this->confidenceThreshold;

            // Log fraud detection if suspicious
            if ($result['is_fake']) {
                $fraudLog = $this->logFraudDetection($candidateId, $documentType, $result);
                $result['fraud_log_id'] = $fraudLog->id;
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('Fake document detection error: ' . $e->getMessage());
            return $result;
        }
    }

    /**
     * Perform various document validation checks
     */
    private function performDocumentChecks($content, $mimeType, $file): array
    {
        $checks = [
            'reasons' => [],
            'suspicion_score' => 0
        ];

        // Check 1: File type validation
        if (!$this->isValidDocumentType($mimeType)) {
            $checks['reasons'][] = 'Invalid document type';
            $checks['suspicion_score'] += 0.3;
        }

        // Check 2: File size analysis
        if ($this->isSuspiciousFileSize($file->getSize())) {
            $checks['reasons'][] = 'Suspicious file size';
            $checks['suspicion_score'] += 0.2;
        }

        // Check 3: Content analysis for PDFs
        if (str_contains($mimeType, 'pdf')) {
            $pdfAnalysis = $this->analyzePdfContent($content);
            $checks['reasons'] = array_merge($checks['reasons'], $pdfAnalysis['reasons']);
            $checks['suspicion_score'] += $pdfAnalysis['score'];
        }

        // Check 4: Image analysis for image documents
        if (str_contains($mimeType, 'image')) {
            $imageAnalysis = $this->analyzeImageContent($content);
            $checks['reasons'] = array_merge($checks['reasons'], $imageAnalysis['reasons']);
            $checks['suspicion_score'] += $imageAnalysis['score'];
        }

        // Check 5: Metadata analysis
        $metadataAnalysis = $this->analyzeDocumentMetadata($file);
        $checks['reasons'] = array_merge($checks['reasons'], $metadataAnalysis['reasons']);
        $checks['suspicion_score'] += $metadataAnalysis['score'];

        // Check 6: Pattern recognition for common fake templates
        $patternAnalysis = $this->analyzeFakePatterns($content);
        $checks['reasons'] = array_merge($checks['reasons'], $patternAnalysis['reasons']);
        $checks['suspicion_score'] += $patternAnalysis['score'];

        return $checks;
    }

    /**
     * Validate document type
     */
    private function isValidDocumentType($mimeType): bool
    {
        $validTypes = [
            'application/pdf',
            'image/jpeg',
            'image/jpg',
            'image/png',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];

        return in_array($mimeType, $validTypes);
    }

    /**
     * Check for suspicious file sizes
     */
    private function isSuspiciousFileSize($size): bool
    {
        // Too small (<10KB) or too large (>10MB) files are suspicious
        return $size < 10240 || $size > 10485760;
    }

    /**
     * Analyze PDF content for signs of tampering
     */
    private function analyzePdfContent($content): array
    {
        $result = ['reasons' => [], 'score' => 0];

        try {
            // Check for common fake PDF patterns
            $suspiciousPatterns = [
                '/certificate\s+of\s+completion/i',
                '/certificate\s+template/i',
                '/sample\s+certificate/i',
                '/fake\s+certificate/i',
                '/template\s+only/i'
            ];

            foreach ($suspiciousPatterns as $pattern) {
                if (preg_match($pattern, $content)) {
                    $result['reasons'][] = 'Contains template/fake indicators';
                    $result['score'] += 0.4;
                    break;
                }
            }

            // Check for lack of proper PDF structure
            if (!str_contains($content, '%PDF') || !str_contains($content, 'endobj')) {
                $result['reasons'][] = 'Invalid PDF structure';
                $result['score'] += 0.3;
            }

        } catch (\Exception $e) {
            Log::error('PDF analysis error: ' . $e->getMessage());
        }

        return $result;
    }

    /**
     * Analyze image content for signs of tampering
     */
    private function analyzeImageContent($content): array
    {
        $result = ['reasons' => [], 'score' => 0];

        try {
            // Check image dimensions
            $imageInfo = getimagesizefromstring($content);
            if ($imageInfo) {
                [$width, $height] = $imageInfo;
                
                // Suspicious dimensions (too small for certificates)
                if ($width < 500 || $height < 300) {
                    $result['reasons'][] = 'Suspicious image dimensions';
                    $result['score'] += 0.2;
                }

                // Check for common certificate aspect ratios
                $aspectRatio = $width / $height;
                if ($aspectRatio < 1.2 || $aspectRatio > 2.5) {
                    $result['reasons'][] = 'Unusual aspect ratio for certificate';
                    $result['score'] += 0.1;
                }
            }

        } catch (\Exception $e) {
            Log::error('Image analysis error: ' . $e->getMessage());
        }

        return $result;
    }

    /**
     * Analyze document metadata
     */
    private function analyzeDocumentMetadata($file): array
    {
        $result = ['reasons' => [], 'score' => 0];

        try {
            // Check for missing or suspicious metadata
            $filename = $file->getClientOriginalName();
            
            // Generic template names
            $suspiciousNames = [
                'certificate_template',
                'sample_certificate',
                'blank_certificate',
                'template',
                'example'
            ];

            foreach ($suspiciousNames as $name) {
                if (str_contains(strtolower($filename), $name)) {
                    $result['reasons'][] = 'Suspicious filename pattern';
                    $result['score'] += 0.3;
                    break;
                }
            }

            // Check for recent creation (within last hour suggests template)
            $uploadTime = now();
            $fileTime = $file->getMTime();
            $timeDiff = $uploadTime->timestamp - $fileTime;
            
            if ($timeDiff < 3600) { // Less than 1 hour
                $result['reasons'][] = 'Very recent file creation';
                $result['score'] += 0.2;
            }

        } catch (\Exception $e) {
            Log::error('Metadata analysis error: ' . $e->getMessage());
        }

        return $result;
    }

    /**
     * Analyze content for common fake document patterns
     */
    private function analyzeFakePatterns($content): array
    {
        $result = ['reasons' => [], 'score' => 0];

        try {
            // Common fake certificate indicators
            $fakeIndicators = [
                'THIS IS A SAMPLE',
                'TEMPLATE ONLY',
                'NOT A REAL CERTIFICATE',
                'FOR DEMONSTRATION PURPOSES',
                'PLACEHOLDER TEXT',
                'Lorem ipsum',
                'Your Name Here',
                'Date Here',
                'Company Name Here'
            ];

            foreach ($fakeIndicators as $indicator) {
                if (str_contains(strtoupper($content), strtoupper($indicator))) {
                    $result['reasons'][] = 'Contains placeholder/template text';
                    $result['score'] += 0.5;
                    break;
                }
            }

            // Check for lack of specific details
            if (!preg_match('/\d{4}/', $content)) { // No year found
                $result['reasons'][] = 'Missing date information';
                $result['score'] += 0.2;
            }

            if (!preg_match('/[A-Z][a-z]+\s+[A-Z][a-z]+/', $content)) { // No proper names
                $result['reasons'][] = 'Missing recipient name';
                $result['score'] += 0.2;
            }

        } catch (\Exception $e) {
            Log::error('Pattern analysis error: ' . $e->getMessage());
        }

        return $result;
    }

    /**
     * Calculate overall confidence score
     */
    private function calculateConfidence($checks): float
    {
        $maxScore = 2.0; // Maximum possible suspicion score
        $score = min($checks['suspicion_score'], $maxScore);
        
        return round($score / $maxScore, 2);
    }

    /**
     * Log fraud detection to database
     */
    private function logFraudDetection($candidateId, $documentType, $result): FraudLog
    {
        return FraudLog::create([
            'type' => 'fake_document',
            'reference_id' => $candidateId,
            'description' => "Suspicious {$documentType} detected with {$result['confidence']} confidence. Reasons: " . implode(', ', $result['reasons']),
            'status' => 'open'
        ]);
    }

    /**
     * Update confidence threshold
     */
    public function setConfidenceThreshold(float $threshold): void
    {
        $this->confidenceThreshold = max(0.1, min(1.0, $threshold));
    }
}
