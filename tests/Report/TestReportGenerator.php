<?php

namespace Tests\Report;

use Tests\TestCase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

class TestReportGenerator
{
    private $reportDir;
    private $testResults = [];

    public function __construct()
    {
        $this->reportDir = storage_path('app/test-reports');
        $this->ensureReportDirectoryExists();
    }

    public function addTestResult($testName, $status, $duration, $message = '', $details = [])
    {
        $this->testResults[] = [
            'test_name' => $testName,
            'status' => $status, // 'passed', 'failed', 'skipped'
            'duration' => $duration,
            'message' => $message,
            'details' => $details,
            'timestamp' => now()->toISOString(),
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true)
        ];
    }

    public function generateHtmlReport($reportName = null)
    {
        $reportName = $reportName ?: 'comprehensive_test_report_' . date('Y-m-d_H-i-s');
        $reportFile = $this->reportDir . '/' . $reportName . '.html';
        
        $html = $this->generateHtmlTemplate();
        $html = $this->replacePlaceholders($html);
        
        File::put($reportFile, $html);
        return $reportFile;
    }

    public function generateJsonReport($reportName = null)
    {
        $reportName = $reportName ?: 'test_report_' . date('Y-m-d_H-i-s');
        $reportFile = $this->reportDir . '/' . $reportName . '.json';
        
        $reportData = [
            'test_run_info' => [
                'report_name' => $reportName,
                'generated_at' => now()->toISOString(),
                'total_tests' => count($this->testResults),
                'passed_tests' => $this->countPassedTests(),
                'failed_tests' => $this->countFailedTests(),
                'skipped_tests' => $this->countSkippedTests(),
                'success_rate' => $this->calculateSuccessRate(),
                'total_duration' => $this->calculateTotalDuration(),
                'average_duration' => $this->calculateAverageDuration(),
                'peak_memory_usage' => $this->getPeakMemoryUsage(),
                'environment' => [
                    'php_version' => phpversion(),
                    'laravel_version' => app()->version(),
                    'database_connection' => config('database.default'),
                    'cache_driver' => config('cache.default'),
                    'queue_driver' => config('queue.default'),
                ]
            ],
            'test_results' => $this->testResults,
            'performance_metrics' => $this->generatePerformanceMetrics(),
            'security_issues' => $this->identifySecurityIssues(),
            'recommendations' => $this->generateRecommendations()
        ];
        
        File::put($reportFile, json_encode($reportData, JSON_PRETTY_PRINT));
        return $reportFile;
    }

    public function generateCsvReport($reportName = null)
    {
        $reportName = $reportName ?: 'test_report_' . date('Y-m-d_H-i-s');
        $reportFile = $this->reportDir . '/' . $reportName . '.csv';
        
        $csv = "Test Name,Status,Duration (ms),Memory Usage (MB),Message\n";
        
        foreach ($this->testResults as $result) {
            $csv .= sprintf(
                '"%s","%s",%.2f,%.2f,"%s"' . "\n",
                $result['test_name'],
                $result['status'],
                $result['duration'],
                $result['memory_usage'] / 1024 / 1024,
                str_replace('"', '""', $result['message'])
            );
        }
        
        File::put($reportFile, $csv);
        return $reportFile;
    }

    public function sendEmailReport($recipients = [], $reportName = null)
    {
        if (empty($recipients)) {
            return false;
        }
        
        $reportFile = $this->generateHtmlReport($reportName);
        $summary = $this->generateSummary();
        
        foreach ($recipients as $recipient) {
            try {
                \Mail::send('emails.test-report', [
                    'summary' => $summary,
                    'reportLink' => asset('storage/test-reports/' . basename($reportFile))
                ], function($message) use ($recipient, $summary) {
                    $message->to($recipient)
                           ->subject('Test Report: ' . $summary['title']);
                });
                
                Log::info("Test report sent to {$recipient}");
            } catch (\Exception $e) {
                Log::error("Failed to send test report to {$recipient}: " . $e->getMessage());
            }
        }
        
        return true;
    }

    public function generateExecutiveSummary()
    {
        $summary = [
            'title' => 'نظام اختبار شامل - تقرير تنفيذي',
            'executive_overview' => $this->generateExecutiveOverview(),
            'key_findings' => $this->generateKeyFindings(),
            'critical_issues' => $this->identifyCriticalIssues(),
            'performance_analysis' => $this->analyzePerformance(),
            'security_assessment' => $this->assessSecurity(),
            'recommendations' => $this->generateExecutiveRecommendations(),
            'risk_assessment' => $this->assessRisks(),
            'deployment_readiness' => $this->assessDeploymentReadiness()
        ];
        
        return $summary;
    }

    private function generateHtmlTemplate()
    {
        return '
        <!DOCTYPE html>
        <html lang="ar" dir="rtl">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>{{TITLE}}</title>
            <style>
                body { font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
                .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 0 20px rgba(0,0,0,0.1); }
                .header { text-align: center; border-bottom: 3px solid #007bff; padding-bottom: 20px; margin-bottom: 30px; }
                .header h1 { color: #333; margin: 0; }
                .header .subtitle { color: #666; font-size: 14px; margin-top: 10px; }
                .metrics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 30px 0; }
                .metric-card { background: #f8f9fa; padding: 20px; border-radius: 8px; border-left: 4px solid #007bff; }
                .metric-card.passed { border-left-color: #28a745; }
                .metric-card.failed { border-left-color: #dc3545; }
                .metric-card.warning { border-left-color: #ffc107; }
                .metric-value { font-size: 2em; font-weight: bold; color: #333; }
                .metric-label { color: #666; font-size: 14px; margin-top: 5px; }
                .test-results { margin: 30px 0; }
                .test-category { margin: 20px 0; }
                .test-category h3 { background: #007bff; color: white; padding: 10px 15px; margin: 0; border-radius: 5px; }
                .test-item { padding: 15px; border: 1px solid #dee2e6; margin: 10px 0; border-radius: 5px; }
                .test-item.passed { background: #d4edda; border-color: #c3e6cb; }
                .test-item.failed { background: #f8d7da; border-color: #f5c6cb; }
                .test-item.warning { background: #fff3cd; border-color: #ffeaa7; }
                .status-badge { display: inline-block; padding: 3px 8px; border-radius: 12px; color: white; font-size: 12px; font-weight: bold; }
                .status-badge.passed { background: #28a745; }
                .status-badge.failed { background: #dc3545; }
                .status-badge.warning { background: #ffc107; color: #333; }
                .performance-chart { margin: 30px 0; }
                .security-assessment { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; }
                .recommendations { background: #e7f3ff; padding: 20px; border-radius: 8px; margin: 20px 0; }
                .recommendation-item { margin: 10px 0; padding: 10px; background: white; border-radius: 5px; border-left: 4px solid #007bff; }
                .footer { text-align: center; margin-top: 40px; padding-top: 20px; border-top: 1px solid #dee2e6; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>{{TITLE}}</h1>
                    <div class="subtitle">{{SUBTITLE}}</div>
                </div>
                
                <div class="metrics-grid">
                    {{METRICS_CARDS}}
                </div>
                
                <div class="performance-chart">
                    <h2>تحليل الأداء</h2>
                    {{PERFORMANCE_ANALYSIS}}
                </div>
                
                <div class="security-assessment">
                    <h2>تقييم الأمان</h2>
                    {{SECURITY_ANALYSIS}}
                </div>
                
                <div class="recommendations">
                    <h2>التوصيات</h2>
                    {{RECOMMENDATIONS}}
                </div>
                
                <div class="test-results">
                    <h2>تفاصيل الاختبارات</h2>
                    {{TEST_DETAILS}}
                </div>
                
                <div class="footer">
                    تم إنشاء هذا التقرير بواسطة نظام الاختبار الشامل | {{TIMESTAMP}}
                </div>
            </div>
        </body>
        </html>';
    }

    private function replacePlaceholders($html)
    {
        $summary = $this->generateSummary();
        
        $html = str_replace('{{TITLE}}', $summary['title'], $html);
        $html = str_replace('{{SUBTITLE}}', $summary['subtitle'], $html);
        $html = str_replace('{{TIMESTAMP}}', $summary['timestamp'], $html);
        $html = str_replace('{{METRICS_CARDS}}', $this->generateMetricsCards(), $html);
        $html = str_replace('{{PERFORMANCE_ANALYSIS}}', $this->generatePerformanceAnalysis(), $html);
        $html = str_replace('{{SECURITY_ANALYSIS}}', $this->generateSecurityAnalysis(), $html);
        $html = str_replace('{{RECOMMENDATIONS}}', $this->generateRecommendationsList(), $html);
        $html = str_replace('{{TEST_DETAILS}}', $this->generateTestDetails(), $html);
        
        return $html;
    }

    private function generateMetricsCards()
    {
        $summary = $this->generateSummary();
        $cards = '';
        
        foreach ($summary['metrics'] as $metric) {
            $cardClass = $metric['status'] === 'good' ? 'passed' : ($metric['status'] === 'warning' ? 'warning' : 'failed');
            $cards .= "
                <div class='metric-card {$cardClass}'>
                    <div class='metric-value'>{$metric['value']}</div>
                    <div class='metric-label'>{$metric['label']}</div>
                </div>
            ";
        }
        
        return $cards;
    }

    private function ensureReportDirectoryExists()
    {
        if (!File::exists($this->reportDir)) {
            File::makeDirectory($this->reportDir, 0755, true);
        }
    }

    private function countPassedTests() { return count(array_filter($this->testResults, fn($t) => $t['status'] === 'passed')); }
    private function countFailedTests() { return count(array_filter($this->testResults, fn($t) => $t['status'] === 'failed')); }
    private function countSkippedTests() { return count(array_filter($this->testResults, fn($t) => $t['status'] === 'skipped')); }
    
    private function calculateSuccessRate() {
        $total = count($this->testResults);
        return $total > 0 ? ($this->countPassedTests() / $total) * 100 : 0;
    }
    
    private function calculateTotalDuration() {
        return array_sum(array_column($this->testResults, 'duration'));
    }
    
    private function calculateAverageDuration() {
        $total = count($this->testResults);
        return $total > 0 ? $this->calculateTotalDuration() / $total : 0;
    }
    
    private function getPeakMemoryUsage() {
        return max(array_column($this->testResults, 'peak_memory')) ?: 0;
    }
    
    private function generateSummary() {
        return [
            'title' => 'تقرير الاختبار الشامل للنظام',
            'subtitle' => 'تقرير تفصيلي عن حالة النظام ومؤشرات الأداء',
            'timestamp' => now()->format('Y-m-d H:i:s'),
            'metrics' => [
                ['label' => 'إجمالي الاختبارات', 'value' => count($this->testResults), 'status' => 'info'],
                ['label' => 'اختبارات ناجحة', 'value' => $this->countPassedTests(), 'status' => 'good'],
                ['label' => 'اختبارات فاشلة', 'value' => $this->countFailedTests(), 'status' => $this->countFailedTests() > 0 ? 'bad' : 'good'],
                ['label' => 'معدل النجاح (%)', 'value' => round($this->calculateSuccessRate(), 1), 'status' => $this->calculateSuccessRate() >= 90 ? 'good' : 'warning'],
                ['label' => 'إجمالي الوقت (ثانية)', 'value' => round($this->calculateTotalDuration() / 1000, 2), 'status' => 'info'],
                ['label' => 'متوسط وقت الاختبار (مللي ثانية)', 'value' => round($this->calculateAverageDuration(), 1), 'status' => 'info']
            ]
        ];
    }
    
    private function generatePerformanceAnalysis() {
        return '<p>تحليل الأداء يعرض مؤشرات سرعة الاستجابة واستهلاك الذاكرة والكفاءة العامة للنظام.</p>';
    }
    
    private function generateSecurityAnalysis() {
        return '<p>تقييم الأمان يركز على الثغرات الأمنية واختبارات الاختراق والامتثال لمعايير الأمان.</p>';
    }
    
    private function generateRecommendationsList() {
        return '<div class="recommendation-item">التحسينات المقترحة ستظهر هنا حسب نتائج الاختبارات</div>';
    }
    
    private function generateTestDetails() {
        $details = '<h3>تفاصيل كل اختبار</h3>';
        foreach ($this->testResults as $test) {
            $statusClass = $test['status'] === 'passed' ? 'passed' : ($test['status'] === 'failed' ? 'failed' : 'warning');
            $details .= "
                <div class='test-item {$statusClass}'>
                    <strong>{$test['test_name']}</strong>
                    <span class='status-badge {$statusClass}'>{$test['status']}</span>
                    <br>
                    <small>المدة: {$test['duration']}ms | الذاكرة: " . round($test['memory_usage'] / 1024 / 1024, 1) . "MB</small>
                    <br>
                    <small>{$test['message']}</small>
                </div>
            ";
        }
        return $details;
    }
    
    private function generatePerformanceMetrics() { return []; }
    private function identifySecurityIssues() { return []; }
    private function generateRecommendations() { return []; }
    private function generateExecutiveOverview() { return ''; }
    private function generateKeyFindings() { return []; }
    private function identifyCriticalIssues() { return []; }
    private function analyzePerformance() { return ''; }
    private function assessSecurity() { return ''; }
    private function generateExecutiveRecommendations() { return []; }
    private function assessRisks() { return ''; }
    private function assessDeploymentReadiness() { return ''; }
}