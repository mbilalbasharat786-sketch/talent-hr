<?php

namespace App\Services;

use App\Models\AssessmentSession;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class AdvancedDeviceDetectionService
{
    private $maxConcurrentSessions = 2;
    private $suspicionThreshold = 3;
    private $cacheTimeout = 3600; // 1 hour

    /**
     * Detect suspicious device/tab activity during assessment
     */
    public function detectSuspiciousActivity($sessionId, $candidateId, $eventData): array
    {
        try {
            $result = [
                'is_suspicious' => false,
                'suspicion_score' => 0,
                'violations' => [],
                'recommendations' => []
            ];

            // Get current session
            $session = AssessmentSession::find($sessionId);
            if (!$session) {
                return $result;
            }

            // Track device fingerprint
            $this->trackDeviceFingerprint($session, $candidateId, $eventData);

            // Check for multiple devices/tabs
            $deviceCheck = $this->checkMultipleDevices($candidateId, $sessionId, $eventData);
            if ($deviceCheck['suspicious']) {
                $result['is_suspicious'] = true;
                $result['suspicion_score'] += $deviceCheck['score'];
                $result['violations'] = array_merge($result['violations'], $deviceCheck['violations']);
            }

            // Check for rapid tab switching
            $tabSwitchCheck = $this->checkRapidTabSwitching($session, $eventData);
            if ($tabSwitchCheck['suspicious']) {
                $result['is_suspicious'] = true;
                $result['suspicion_score'] += $tabSwitchCheck['score'];
                $result['violations'] = array_merge($result['violations'], $tabSwitchCheck['violations']);
            }

            // Check for window focus/blur patterns
            $focusCheck = $this->checkFocusPatterns($session, $eventData);
            if ($focusCheck['suspicious']) {
                $result['is_suspicious'] = true;
                $result['suspicion_score'] += $focusCheck['score'];
                $result['violations'] = array_merge($result['violations'], $focusCheck['violations']);
            }

            // Check for device inconsistencies
            $inconsistencyCheck = $this->checkDeviceInconsistencies($session, $eventData);
            if ($inconsistencyCheck['suspicious']) {
                $result['is_suspicious'] = true;
                $result['suspicion_score'] += $inconsistencyCheck['score'];
                $result['violations'] = array_merge($result['violations'], $inconsistencyCheck['violations']);
            }

            // Generate recommendations
            $result['recommendations'] = $this->generateRecommendations($result['suspicion_score'], $result['violations']);

            // Update session with detection results
            $this->updateSessionWithDetectionResults($session, $result);

            return $result;

        } catch (\Exception $e) {
            Log::error('Advanced device detection error: ' . $e->getMessage());
            return [
                'is_suspicious' => false,
                'suspicion_score' => 0,
                'violations' => [],
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Track and analyze device fingerprint
     */
    private function trackDeviceFingerprint($session, $candidateId, $eventData): void
    {
        $fingerprint = $this->generateDeviceFingerprint($eventData);
        $cacheKey = "device_fingerprint_{$candidateId}";

        $existingFingerprints = Cache::get($cacheKey, []);
        
        if (!in_array($fingerprint, $existingFingerprints)) {
            $existingFingerprints[] = $fingerprint;
            Cache::put($cacheKey, $existingFingerprints, $this->cacheTimeout);
        }

        // Store detailed device info
        $deviceInfo = [
            'fingerprint' => $fingerprint,
            'user_agent' => $eventData['user_agent'] ?? '',
            'screen_resolution' => $eventData['screen_resolution'] ?? '',
            'timezone' => $eventData['timezone'] ?? '',
            'language' => $eventData['language'] ?? '',
            'ip_address' => $eventData['ip_address'] ?? '',
            'timestamp' => now()
        ];

        $session->update([
            'device_info' => json_encode($deviceInfo)
        ]);
    }

    /**
     * Generate unique device fingerprint
     */
    private function generateDeviceFingerprint($eventData): string
    {
        $components = [
            $eventData['user_agent'] ?? '',
            $eventData['screen_resolution'] ?? '',
            $eventData['timezone'] ?? '',
            $eventData['language'] ?? '',
            $eventData['platform'] ?? '',
            $eventData['browser'] ?? ''
        ];

        return hash('sha256', implode('|', $components));
    }

    /**
     * Check for multiple devices/tabs being used
     */
    private function checkMultipleDevices($candidateId, $currentSessionId, $eventData): array
    {
        $result = ['suspicious' => false, 'score' => 0, 'violations' => []];

        // Get active sessions for this candidate
        $activeSessions = AssessmentSession::where('candidate_id', $candidateId)
            ->where('status', 'in_progress')
            ->where('id', '!=', $currentSessionId)
            ->where('expires_at', '>', now())
            ->get();

        if ($activeSessions->count() >= $this->maxConcurrentSessions) {
            $result['suspicious'] = true;
            $result['score'] = 0.8;
            $result['violations'][] = [
                'type' => 'multiple_sessions',
                'description' => 'Multiple concurrent assessment sessions detected',
                'severity' => 'high'
            ];
        }

        // Check cache for recent device changes
        $cacheKey = "recent_devices_{$candidateId}";
        $recentDevices = Cache::get($cacheKey, []);

        $currentFingerprint = $this->generateDeviceFingerprint($eventData);
        
        if (count($recentDevices) > 1 && !in_array($currentFingerprint, $recentDevices)) {
            $result['suspicious'] = true;
            $result['score'] += 0.6;
            $result['violations'][] = [
                'type' => 'device_change',
                'description' => 'Device fingerprint changed during session',
                'severity' => 'medium'
            ];
        }

        // Update recent devices cache
        if (!in_array($currentFingerprint, $recentDevices)) {
            $recentDevices[] = $currentFingerprint;
            Cache::put($cacheKey, $recentDevices, $this->cacheTimeout);
        }

        return $result;
    }

    /**
     * Check for rapid tab switching patterns
     */
    private function checkRapidTabSwitching($session, $eventData): array
    {
        $result = ['suspicious' => false, 'score' => 0, 'violations' => []];

        $eventType = $eventData['event_type'] ?? '';
        $timestamp = $eventData['timestamp'] ?? now();

        if ($eventType === 'tab_switch') {
            // Get recent tab switches from cache
            $cacheKey = "tab_switches_{$session->id}";
            $recentSwitches = Cache::get($cacheKey, []);

            // Add current switch
            $recentSwitches[] = $timestamp;
            
            // Keep only last 10 switches
            if (count($recentSwitches) > 10) {
                $recentSwitches = array_slice($recentSwitches, -10);
            }

            Cache::put($cacheKey, $recentSwitches, 1800); // 30 minutes

            // Check for rapid switching (more than 5 switches in 2 minutes)
            $twoMinutesAgo = now()->subMinutes(2);
            $recentCount = count(array_filter($recentSwitches, function($time) use ($twoMinutesAgo) {
                return $time >= $twoMinutesAgo;
            }));

            if ($recentCount >= 5) {
                $result['suspicious'] = true;
                $result['score'] = 0.7;
                $result['violations'][] = [
                    'type' => 'rapid_tab_switching',
                    'description' => 'Excessive tab switching detected',
                    'severity' => 'medium'
                ];
            }
        }

        return $result;
    }

    /**
     * Check for suspicious focus/blur patterns
     */
    private function checkFocusPatterns($session, $eventData): array
    {
        $result = ['suspicious' => false, 'score' => 0, 'violations' => []];

        $eventType = $eventData['event_type'] ?? '';
        $timestamp = $eventData['timestamp'] ?? now();

        if (in_array($eventType, ['window_blur', 'window_focus'])) {
            $cacheKey = "focus_events_{$session->id}";
            $focusEvents = Cache::get($cacheKey, []);

            $focusEvents[] = [
                'type' => $eventType,
                'timestamp' => $timestamp
            ];

            // Keep only last 20 events
            if (count($focusEvents) > 20) {
                $focusEvents = array_slice($focusEvents, -20);
            }

            Cache::put($cacheKey, $focusEvents, 1800);

            // Check for pattern: blur followed quickly by focus (possible alt-tab)
            if ($eventType === 'window_focus' && count($focusEvents) >= 2) {
                $previousEvent = $focusEvents[count($focusEvents) - 2];
                
                if ($previousEvent['type'] === 'window_blur') {
                    $timeDiff = $timestamp->diffInSeconds($previousEvent['timestamp']);
                    
                    if ($timeDiff < 2) { // Very quick blur/focus cycle
                        $result['suspicious'] = true;
                        $result['score'] = 0.4;
                        $result['violations'][] = [
                            'type' => 'suspicious_focus_pattern',
                            'description' => 'Rapid window blur/focus detected',
                            'severity' => 'low'
                        ];
                    }
                }
            }

            // Check for excessive window blurring
            $blursInLastMinute = 0;
            $oneMinuteAgo = now()->subMinute();
            
            foreach ($focusEvents as $event) {
                if ($event['type'] === 'window_blur' && $event['timestamp'] >= $oneMinuteAgo) {
                    $blursInLastMinute++;
                }
            }

            if ($blursInLastMinute >= 10) {
                $result['suspicious'] = true;
                $result['score'] += 0.5;
                $result['violations'][] = [
                    'type' => 'excessive_window_blur',
                    'description' => 'Excessive window blurring detected',
                    'severity' => 'medium'
                ];
            }
        }

        return $result;
    }

    /**
     * Check for device inconsistencies
     */
    private function checkDeviceInconsistencies($session, $eventData): array
    {
        $result = ['suspicious' => false, 'score' => 0, 'violations' => []];

        $currentDeviceInfo = json_decode($session->device_info ?? '{}', true);
        
        if (!empty($currentDeviceInfo)) {
            // Check for IP address change
            if (isset($currentDeviceInfo['ip_address']) && 
                isset($eventData['ip_address']) &&
                $currentDeviceInfo['ip_address'] !== $eventData['ip_address']) {
                
                $result['suspicious'] = true;
                $result['score'] = 0.9;
                $result['violations'][] = [
                    'type' => 'ip_address_change',
                    'description' => 'IP address changed during session',
                    'severity' => 'high'
                ];
            }

            // Check for screen resolution change
            if (isset($currentDeviceInfo['screen_resolution']) && 
                isset($eventData['screen_resolution']) &&
                $currentDeviceInfo['screen_resolution'] !== $eventData['screen_resolution']) {
                
                $result['suspicious'] = true;
                $result['score'] += 0.3;
                $result['violations'][] = [
                    'type' => 'screen_resolution_change',
                    'description' => 'Screen resolution changed during session',
                    'severity' => 'low'
                ];
            }

            // Check for timezone change
            if (isset($currentDeviceInfo['timezone']) && 
                isset($eventData['timezone']) &&
                $currentDeviceInfo['timezone'] !== $eventData['timezone']) {
                
                $result['suspicious'] = true;
                $result['score'] += 0.6;
                $result['violations'][] = [
                    'type' => 'timezone_change',
                    'description' => 'Timezone changed during session',
                    'severity' => 'medium'
                ];
            }
        }

        return $result;
    }

    /**
     * Generate recommendations based on suspicion score
     */
    private function generateRecommendations(float $score, array $violations): array
    {
        $recommendations = [];

        if ($score >= 0.8) {
            $recommendations[] = 'Consider auto-submitting assessment due to high suspicious activity';
            $recommendations[] = 'Flag candidate for manual review';
        } elseif ($score >= 0.6) {
            $recommendations[] = 'Issue warning to candidate';
            $recommendations[] = 'Monitor session more closely';
        } elseif ($score >= 0.4) {
            $recommendations[] = 'Log suspicious activity for review';
        }

        // Specific recommendations based on violation types
        foreach ($violations as $violation) {
            switch ($violation['type']) {
                case 'multiple_sessions':
                    $recommendations[] = 'Terminate extra sessions immediately';
                    break;
                case 'ip_address_change':
                    $recommendations[] = 'Verify candidate identity immediately';
                    break;
                case 'rapid_tab_switching':
                    $recommendations[] = 'Display warning message about tab switching';
                    break;
            }
        }

        return array_unique($recommendations);
    }

    /**
     * Update session with detection results
     */
    private function updateSessionWithDetectionResults($session, array $result): void
    {
        $session->update([
            'warning_count' => $session->warning_count + ($result['is_suspicious'] ? 1 : 0),
            'violation_count' => $session->violation_count + count($result['violations']),
            'cheating_flag' => $result['suspicion_score'] >= 0.8 ? 'cheating_detected' : 
                             ($result['suspicion_score'] >= 0.6 ? 'suspicious' : 'none')
        ]);
    }

    /**
     * Get comprehensive device activity report
     */
    public function getDeviceActivityReport($candidateId): array
    {
        try {
            $report = [
                'candidate_id' => $candidateId,
                'total_sessions' => 0,
                'unique_devices' => 0,
                'suspicious_activities' => [],
                'device_fingerprints' => [],
                'recommendations' => []
            ];

            // Get all assessment sessions for candidate
            $sessions = AssessmentSession::where('candidate_id', $candidateId)->get();
            $report['total_sessions'] = $sessions->count();

            // Analyze device fingerprints
            $fingerprints = [];
            foreach ($sessions as $session) {
                if ($session->device_info) {
                    $deviceInfo = json_decode($session->device_info, true);
                    if (isset($deviceInfo['fingerprint'])) {
                        $fingerprints[] = $deviceInfo['fingerprint'];
                    }
                }
            }
            $report['unique_devices'] = count(array_unique($fingerprints));
            $report['device_fingerprints'] = array_unique($fingerprints);

            // Get suspicious activities from cache
            $cacheKeys = [
                "tab_switches_",
                "focus_events_",
                "recent_devices_"
            ];

            foreach ($cacheKeys as $keyPrefix) {
                $cachedData = Cache::get($keyPrefix . $candidateId, []);
                if (!empty($cachedData)) {
                    $report['suspicious_activities'][] = [
                        'type' => str_replace('_', '', $keyPrefix),
                        'data' => $cachedData
                    ];
                }
            }

            return $report;

        } catch (\Exception $e) {
            Log::error('Error generating device activity report: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Clear device tracking data for a candidate
     */
    public function clearDeviceTrackingData($candidateId): void
    {
        $cacheKeys = [
            "device_fingerprint_{$candidateId}",
            "recent_devices_{$candidateId}",
            "tab_switches_",
            "focus_events_"
        ];

        foreach ($cacheKeys as $key) {
            if (str_ends_with($key, '_')) {
                // Clear all keys with this prefix
                $keys = Cache::getRedis()?->keys("{$key}*") ?? [];
                foreach ($keys as $fullKey) {
                    Cache::forget($fullKey);
                }
            } else {
                Cache::forget($key);
            }
        }
    }

    /**
     * Update detection thresholds
     */
    public function setThresholds(int $maxSessions, int $suspicionThreshold): void
    {
        $this->maxConcurrentSessions = $maxSessions;
        $this->suspicionThreshold = $suspicionThreshold;
    }
}
