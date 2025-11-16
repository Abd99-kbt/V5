<?php

namespace App\Services;

use App\Models\OrderProcessing;
use Illuminate\Support\Facades\Log;

class AutomatedQualityControlService
{
    /**
     * Perform automated quality check for the processing stage
     */
    public function performAutomatedQualityCheck(OrderProcessing $processing): array
    {
        try {
            $results = [
                'dimensions_check' => $this->checkDimensionsAutomatically($processing),
                'weight_balance_check' => $this->checkWeightBalance($processing),
                'visual_analysis' => $this->performVisualQualityAnalysis($processing),
                'specifications_validation' => $this->validateSpecifications($processing),
                'overall_score' => $this->calculateOverallQualityScore($processing),
                'requires_human_review' => $this->requiresHumanReview($processing),
                'measured_dimensions' => $this->getMeasuredDimensions($processing),
                'timestamp' => now(),
                'processing_id' => $processing->id
            ];

            // Log the automated quality check
            Log::info('Automated quality check performed', [
                'processing_id' => $processing->id,
                'overall_score' => $results['overall_score'],
                'requires_human_review' => $results['requires_human_review']
            ]);

            return $results;

        } catch (\Exception $e) {
            Log::error('Automated quality check failed', [
                'processing_id' => $processing->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'error' => 'Quality check failed: ' . $e->getMessage(),
                'requires_human_review' => true,
                'overall_score' => 0
            ];
        }
    }

    /**
     * Check dimensions automatically
     */
    public function checkDimensionsAutomatically(OrderProcessing $processing): bool
    {
        $measured = $this->getMeasuredDimensions($processing);
        $specs = $processing->order->specifications ?? [];

        // Check length
        if (isset($specs['length']) && isset($measured['length'])) {
            $tolerance = $specs['length'] * 0.02; // 2% tolerance
            if (abs($measured['length'] - $specs['length']) > $tolerance) {
                return false;
            }
        }

        // Check width
        if (isset($specs['width']) && isset($measured['width'])) {
            $tolerance = $specs['width'] * 0.02;
            if (abs($measured['width'] - $specs['width']) > $tolerance) {
                return false;
            }
        }

        // Check thickness
        if (isset($specs['thickness']) && isset($measured['thickness'])) {
            $tolerance = $specs['thickness'] * 0.05; // 5% tolerance for thickness
            if (abs($measured['thickness'] - $specs['thickness']) > $tolerance) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check weight balance
     */
    public function checkWeightBalance(OrderProcessing $processing): bool
    {
        // For warehouse stages
        if ($processing->isWarehouseStage()) {
            if ($processing->weight_received <= 0) {
                return false;
            }

            if ($processing->weight_transferred > $processing->weight_received) {
                return false;
            }

            // Allow small tolerance for weight balance (0.1% tolerance)
            $tolerance = $processing->weight_received * 0.001;
            return abs($processing->weight_balance) <= $tolerance;
        }

        // For sorting stages
        if ($processing->isSortingStage()) {
            return $processing->isSortingWeightBalanced();
        }

        // For cutting stages - weight balance is less critical
        return true;
    }

    /**
     * Perform visual quality analysis
     */
    public function performVisualQualityAnalysis(OrderProcessing $processing): array
    {
        $issues = [];

        // Check for sorting stage visual defects
        if ($processing->isSortingStage()) {
            if ($processing->sorting_waste_weight > $processing->weight_received * 0.15) {
                $issues[] = 'High waste percentage detected';
            }

            if (!$processing->sortingResults()->exists()) {
                $issues[] = 'No sorting results recorded';
            }
        }

        // Check for cutting stage defects
        if ($processing->isCuttingStage()) {
            if (!$processing->cuttingResults()->exists()) {
                $issues[] = 'No cutting results recorded';
            }

            // Check cutting precision (simulated)
            $precisionScore = $this->calculateCuttingPrecision($processing);
            if ($precisionScore < 0.8) {
                $issues[] = 'Low cutting precision detected';
            }
        }

        return [
            'passed' => empty($issues),
            'issues' => $issues,
            'defect_rate' => count($issues) / 10 // Simulated defect rate
        ];
    }

    /**
     * Validate specifications
     */
    public function validateSpecifications(OrderProcessing $processing): bool
    {
        // Check delivery specifications validation
        $specErrors = $processing->validateDeliverySpecificationsForStage();
        if (!empty($specErrors)) {
            return false;
        }

        // Additional specification checks
        $order = $processing->order;
        if (!$order) {
            return false;
        }

        // Check material specifications
        if (isset($order->material_type) && $processing->material_received != $order->material_type) {
            return false;
        }

        // Check quality grade
        if (isset($order->quality_grade)) {
            $measuredGrade = $this->assessQualityGrade($processing);
            if ($measuredGrade < $order->quality_grade) {
                return false;
            }
        }

        return true;
    }

    /**
     * Calculate overall quality score
     */
    public function calculateOverallQualityScore(OrderProcessing $processing): float
    {
        $score = 0;
        $totalWeight = 100;

        // Dimensions check (30%)
        $dimensionsScore = $this->checkDimensionsAutomatically($processing) ? 30 : 0;
        $score += $dimensionsScore;

        // Weight balance check (20%)
        $weightScore = $this->checkWeightBalance($processing) ? 20 : 0;
        $score += $weightScore;

        // Visual analysis (25%)
        $visualResult = $this->performVisualQualityAnalysis($processing);
        $visualScore = $visualResult['passed'] ? 25 : (25 * (1 - $visualResult['defect_rate']));
        $score += $visualScore;

        // Specifications validation (25%)
        $specsScore = $this->validateSpecifications($processing) ? 25 : 0;
        $score += $specsScore;

        return min(100, max(0, $score));
    }

    /**
     * Determine if human review is required
     */
    public function requiresHumanReview(OrderProcessing $processing): bool
    {
        $score = $this->calculateOverallQualityScore($processing);

        // Require human review if score is below 80
        if ($score < 80) {
            return true;
        }

        // Require human review for high-priority orders
        if ($processing->order && $processing->order->priority === 'high') {
            return true;
        }

        // Require human review if there are any critical issues
        $visualResult = $this->performVisualQualityAnalysis($processing);
        if (count($visualResult['issues']) > 2) {
            return true;
        }

        // Require human review for cutting stages with precision issues
        if ($processing->isCuttingStage()) {
            $precisionScore = $this->calculateCuttingPrecision($processing);
            if ($precisionScore < 0.9) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get measured dimensions
     */
    public function getMeasuredDimensions(OrderProcessing $processing): array
    {
        // In a real implementation, this would interface with measurement sensors
        // For now, return stored or simulated dimensions

        return [
            'length' => $processing->measured_length ?? $processing->order->length ?? 0,
            'width' => $processing->measured_width ?? $processing->order->width ?? 0,
            'thickness' => $processing->measured_thickness ?? $processing->order->thickness ?? 0,
            'weight' => $processing->weight_received ?? 0,
            'measurement_method' => 'automated_sensor',
            'timestamp' => $processing->updated_at ?? now()
        ];
    }

    /**
     * Calculate cutting precision (helper method)
     */
    private function calculateCuttingPrecision(OrderProcessing $processing): float
    {
        if (!$processing->isCuttingStage()) {
            return 1.0;
        }

        // Simulated precision calculation based on cutting results
        $results = $processing->cuttingResults;
        if ($results->isEmpty()) {
            return 0.0;
        }

        // Calculate precision based on dimensional accuracy
        $totalResults = $results->count();
        $accurateResults = 0;

        foreach ($results as $result) {
            if (isset($result->actual_length, $result->target_length)) {
                $deviation = abs($result->actual_length - $result->target_length);
                $tolerance = $result->target_length * 0.02; // 2% tolerance
                if ($deviation <= $tolerance) {
                    $accurateResults++;
                }
            }
        }

        return $totalResults > 0 ? $accurateResults / $totalResults : 0.0;
    }

    /**
     * Assess quality grade (helper method)
     */
    private function assessQualityGrade(OrderProcessing $processing): int
    {
        $score = $this->calculateOverallQualityScore($processing);

        // Map score to grade (1-5 scale)
        if ($score >= 90) return 5;
        if ($score >= 80) return 4;
        if ($score >= 70) return 3;
        if ($score >= 60) return 2;
        return 1;
    }

    /**
     * Run automated quality checks for pending processings
     */
    public function run(): array
    {
        try {
            $pendingProcessings = OrderProcessing::where('status', 'in_progress')
                                               ->whereNull('quality_checked_at')
                                               ->get();

            $results = [];
            foreach ($pendingProcessings as $processing) {
                $result = $this->performAutomatedQualityCheck($processing);

                // Update processing with quality check results
                $processing->update([
                    'quality_score' => $result['overall_score'],
                    'quality_checked_at' => now(),
                    'requires_human_review' => $result['requires_human_review'],
                    'quality_check_data' => $result
                ]);

                $results[] = [
                    'processing_id' => $processing->id,
                    'overall_score' => $result['overall_score'],
                    'requires_human_review' => $result['requires_human_review'],
                    'passed' => $result['overall_score'] >= 80
                ];
            }

            return [
                'success' => true,
                'processings_checked' => count($pendingProcessings),
                'results' => $results,
                'timestamp' => now()
            ];

        } catch (\Exception $e) {
            Log::error('Automated quality control run failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'processings_checked' => 0,
                'results' => []
            ];
        }
    }
}