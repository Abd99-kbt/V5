<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class SystemMonitor
{
    /**
     * Cache key prefix for system metrics
     */
    protected string $cachePrefix = 'system_monitor_';

    /**
     * Alert thresholds for system resources
     */
    protected array $thresholds = [
        'cpu_usage' => 80, // percentage
        'memory_usage' => 85, // percentage
        'disk_usage' => 90, // percentage
        'load_average' => 2.0, // per core
        'free_memory_mb' => 512, // minimum free memory
        'response_time_ms' => 1000, // maximum response time
    ];

    /**
     * Get comprehensive system health status
     */
    public function getHealthStatus(): array
    {
        $status = [
            'overall' => 'healthy',
            'timestamp' => now()->toISOString(),
            'checks' => []
        ];

        try {
            // CPU monitoring
            $status['checks']['cpu'] = $this->checkCpuUsage();
            
            // Memory monitoring
            $status['checks']['memory'] = $this->checkMemoryUsage();
            
            // Disk monitoring
            $status['checks']['disk'] = $this->checkDiskUsage();
            
            // Network monitoring
            $status['checks']['network'] = $this->checkNetworkStatus();
            
            // Load average monitoring
            $status['checks']['load'] = $this->checkLoadAverage();
            
            // System services monitoring
            $status['checks']['services'] = $this->checkSystemServices();
            
            // Process monitoring
            $status['checks']['processes'] = $this->checkSystemProcesses();
            
            // Temperature monitoring (if available)
            $status['checks']['temperature'] = $this->checkSystemTemperature();

            // Determine overall status
            $criticalIssues = collect($status['checks'])->filter(fn($check) => $check['status'] === 'critical')->count();
            $warningIssues = collect($status['checks'])->filter(fn($check) => $check['status'] === 'warning')->count();
            
            if ($criticalIssues > 0) {
                $status['overall'] = 'critical';
            } elseif ($warningIssues > 0) {
                $status['overall'] = 'warning';
            }

            // Cache the health status
            $this->cacheHealthStatus($status);

            // Check for alerts
            $this->checkSystemAlerts($status);

        } catch (\Exception $e) {
            Log::error('System health check failed', [
                'error' => $e->getMessage()
            ]);

            $status['overall'] = 'error';
            $status['error'] = $e->getMessage();
        }

        return $status;
    }

    /**
     * Check CPU usage and performance
     */
    protected function checkCpuUsage(): array
    {
        try {
            $load = sys_getloadavg();
            $cpuUsage = $this->getCpuUsage();
            
            $status = 'healthy';
            $issues = [];

            // Check load average
            if ($load[0] > $this->thresholds['load_average'] * $this->getCpuCores()) {
                $status = 'warning';
                $issues[] = "High load average: {$load[0]}";
            }

            if ($load[0] > $this->thresholds['load_average'] * $this->getCpuCores() * 2) {
                $status = 'critical';
                $issues[] = "Critical load average: {$load[0]}";
            }

            // Check CPU usage percentage
            if ($cpuUsage > $this->thresholds['cpu_usage']) {
                $status = 'warning';
                $issues[] = "High CPU usage: {$cpuUsage}%";
            }

            if ($cpuUsage > 95) {
                $status = 'critical';
                $issues[] = "Critical CPU usage: {$cpuUsage}%";
            }

            return [
                'status' => $status,
                'metrics' => [
                    'load_average_1min' => $load[0],
                    'load_average_5min' => $load[1],
                    'load_average_15min' => $load[2],
                    'cpu_usage_percent' => $cpuUsage,
                    'cpu_cores' => $this->getCpuCores(),
                    'cpu_model' => $this->getCpuModel(),
                ],
                'issues' => $issues,
                'message' => empty($issues) ? 'CPU usage normal' : implode(', ', $issues)
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'critical',
                'message' => 'CPU monitoring failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Check memory usage and availability
     */
    protected function checkMemoryUsage(): array
    {
        try {
            $memory = $this->getMemoryInfo();
            $swap = $this->getSwapInfo();
            
            $status = 'healthy';
            $issues = [];

            $memoryUsagePercent = ($memory['used'] / $memory['total']) * 100;
            
            if ($memoryUsagePercent > $this->thresholds['memory_usage']) {
                $status = 'warning';
                $issues[] = "High memory usage: {$memoryUsagePercent}%";
            }

            if ($memoryUsagePercent > 95) {
                $status = 'critical';
                $issues[] = "Critical memory usage: {$memoryUsagePercent}%";
            }

            $freeMemoryMB = ($memory['available'] ?? $memory['free']) / 1024 / 1024;
            if ($freeMemoryMB < $this->thresholds['free_memory_mb']) {
                $status = 'warning';
                $issues[] = "Low free memory: " . round($freeMemoryMB, 2) . "MB";
            }

            return [
                'status' => $status,
                'metrics' => [
                    'total_mb' => round($memory['total'] / 1024 / 1024, 2),
                    'used_mb' => round($memory['used'] / 1024 / 1024, 2),
                    'free_mb' => round(($memory['available'] ?? $memory['free']) / 1024 / 1024, 2),
                    'usage_percent' => round($memoryUsagePercent, 2),
                    'swap_total_mb' => round($swap['total'] / 1024 / 1024, 2),
                    'swap_used_mb' => round($swap['used'] / 1024 / 1024, 2),
                    'swap_percent' => round(($swap['used'] / max($swap['total'], 1)) * 100, 2),
                ],
                'issues' => $issues,
                'message' => empty($issues) ? 'Memory usage normal' : implode(', ', $issues)
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'critical',
                'message' => 'Memory monitoring failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Check disk usage and I/O performance
     */
    protected function checkDiskUsage(): array
    {
        try {
            $disks = $this->getDiskInfo();
            $status = 'healthy';
            $issues = [];
            $diskMetrics = [];

            foreach ($disks as $mount => $disk) {
                $usagePercent = ($disk['used'] / $disk['total']) * 100;
                
                if ($usagePercent > $this->thresholds['disk_usage']) {
                    $status = 'warning';
                    $issues[] = "High disk usage on {$mount}: {$usagePercent}%";
                }

                if ($usagePercent > 95) {
                    $status = 'critical';
                    $issues[] = "Critical disk usage on {$mount}: {$usagePercent}%";
                }

                $diskMetrics[$mount] = [
                    'total_gb' => round($disk['total'] / 1024 / 1024 / 1024, 2),
                    'used_gb' => round($disk['used'] / 1024 / 1024 / 1024, 2),
                    'free_gb' => round($disk['free'] / 1024 / 1024 / 1024, 2),
                    'usage_percent' => round($usagePercent, 2),
                ];
            }

            return [
                'status' => $status,
                'metrics' => $diskMetrics,
                'issues' => $issues,
                'message' => empty($issues) ? 'Disk usage normal' : implode(', ', $issues)
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'critical',
                'message' => 'Disk monitoring failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Check network connectivity and performance
     */
    protected function checkNetworkStatus(): array
    {
        try {
            $interfaces = $this->getNetworkInterfaces();
            $externalConnectivity = $this->checkExternalConnectivity();
            
            $status = 'healthy';
            $issues = [];

            if (!$externalConnectivity['success']) {
                $status = 'warning';
                $issues[] = 'External connectivity issues';
            }

            return [
                'status' => $status,
                'metrics' => [
                    'interfaces' => $interfaces,
                    'external_connectivity' => $externalConnectivity,
                ],
                'issues' => $issues,
                'message' => empty($issues) ? 'Network status normal' : implode(', ', $issues)
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'critical',
                'message' => 'Network monitoring failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Check system load average
     */
    protected function checkLoadAverage(): array
    {
        $load = sys_getloadavg();
        $cores = $this->getCpuCores();
        
        $status = 'healthy';
        $issues = [];

        $loadPerCore = [
            '1min' => $load[0] / $cores,
            '5min' => $load[1] / $cores,
            '15min' => $load[2] / $cores,
        ];

        if ($loadPerCore['1min'] > $this->thresholds['load_average']) {
            $status = 'warning';
        }

        if ($loadPerCore['1min'] > $this->thresholds['load_average'] * 2) {
            $status = 'critical';
        }

        return [
            'status' => $status,
            'metrics' => [
                'load_average' => $load,
                'load_per_core' => $loadPerCore,
                'cpu_cores' => $cores,
            ],
            'issues' => $issues,
            'message' => empty($issues) ? 'Load average normal' : implode(', ', $issues)
        ];
    }

    /**
     * Check system services status
     */
    protected function checkSystemServices(): array
    {
        $criticalServices = [
            'nginx',
            'mysql',
            'redis',
            'php-fpm'
        ];
        
        $serviceStatus = [];
        $issues = [];
        $status = 'healthy';

        foreach ($criticalServices as $service) {
            $isRunning = $this->isServiceRunning($service);
            $serviceStatus[$service] = $isRunning ? 'running' : 'stopped';
            
            if (!$isRunning) {
                $issues[] = "Service {$service} is not running";
                $status = 'critical';
            }
        }

        return [
            'status' => $status,
            'metrics' => [
                'services' => $serviceStatus,
            ],
            'issues' => $issues,
            'message' => empty($issues) ? 'All critical services running' : implode(', ', $issues)
        ];
    }

    /**
     * Check system processes
     */
    protected function checkSystemProcesses(): array
    {
        try {
            $processes = $this->getTopProcesses();
            $zombieProcesses = $this->getZombieProcesses();
            
            $status = 'healthy';
            $issues = [];

            if (count($zombieProcesses) > 0) {
                $status = 'warning';
                $issues[] = count($zombieProcesses) . ' zombie processes detected';
            }

            return [
                'status' => $status,
                'metrics' => [
                    'total_processes' => $this->getTotalProcesses(),
                    'running_processes' => $this->getRunningProcesses(),
                    'zombie_processes' => count($zombieProcesses),
                    'top_processes' => $processes,
                ],
                'issues' => $issues,
                'message' => empty($issues) ? 'Process count normal' : implode(', ', $issues)
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'warning',
                'message' => 'Process monitoring unavailable: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Check system temperature (if available)
     */
    protected function checkSystemTemperature(): array
    {
        try {
            $sensors = $this->getTemperatureSensors();
            
            if (empty($sensors)) {
                return [
                    'status' => 'unknown',
                    'message' => 'Temperature sensors not available'
                ];
            }

            $highTemp = false;
            $issues = [];

            foreach ($sensors as $sensor => $temp) {
                if ($temp > 80) {
                    $highTemp = true;
                    $issues[] = "High temperature on {$sensor}: {$temp}°C";
                }
            }

            return [
                'status' => $highTemp ? 'warning' : 'healthy',
                'metrics' => $sensors,
                'issues' => $issues,
                'message' => empty($issues) ? 'Temperature normal' : implode(', ', $issues)
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unknown',
                'message' => 'Temperature monitoring unavailable: ' . $e->getMessage()
            ];
        }
    }

    // Helper methods for system information collection

    protected function getCpuUsage(): float
    {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return min($load[0] * 100 / $this->getCpuCores(), 100);
        }
        return 0;
    }

    protected function getCpuCores(): int
    {
        return (int) shell_exec('nproc') ?: 1;
    }

    protected function getCpuModel(): string
    {
        return trim(shell_exec("grep -m1 'model name' /proc/cpuinfo | cut -d: -f2") ?: 'Unknown');
    }

    protected function getMemoryInfo(): array
    {
        $meminfo = file_get_contents('/proc/meminfo');
        $memory = [];
        
        preg_match('/MemTotal:\s+(\d+)/', $meminfo, $total);
        preg_match('/MemFree:\s+(\d+)/', $meminfo, $free);
        preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $available);
        preg_match('/Buffers:\s+(\d+)/', $meminfo, $buffers);
        preg_match('/Cached:\s+(\d+)/', $meminfo, $cached);
        
        $memory['total'] = (int) ($total[1] ?? 0);
        $memory['free'] = (int) ($free[1] ?? 0);
        $memory['available'] = (int) ($available[1] ?? 0);
        $memory['buffers'] = (int) ($buffers[1] ?? 0);
        $memory['cached'] = (int) ($cached[1] ?? 0);
        $memory['used'] = $memory['total'] - $memory['free'] - $memory['buffers'] - $memory['cached'];
        
        return $memory;
    }

    protected function getSwapInfo(): array
    {
        $meminfo = file_get_contents('/proc/meminfo');
        
        preg_match('/SwapTotal:\s+(\d+)/', $meminfo, $total);
        preg_match('/SwapFree:\s+(\d+)/', $meminfo, $free);
        
        $swap['total'] = (int) ($total[1] ?? 0);
        $swap['free'] = (int) ($free[1] ?? 0);
        $swap['used'] = $swap['total'] - $swap['free'];
        
        return $swap;
    }

    protected function getDiskInfo(): array
    {
        $disks = [];
        $output = shell_exec('df -h | grep -vE "tmpfs|devtmpfs|udev"');
        
        if ($output) {
            $lines = explode("\n", trim($output));
            foreach ($lines as $line) {
                $parts = preg_split('/\s+/', $line);
                if (count($parts) >= 6) {
                    $mount = $parts[5] ?? $parts[0];
                    $disks[$mount] = [
                        'total' => $this->parseSize($parts[1]),
                        'used' => $this->parseSize($parts[2]),
                        'free' => $this->parseSize($parts[3]),
                    ];
                }
            }
        }
        
        return $disks;
    }

    protected function getNetworkInterfaces(): array
    {
        $interfaces = [];
        $output = shell_exec('ip addr show | grep "^[0-9]" | cut -d: -f2');
        
        if ($output) {
            $lines = explode("\n", trim($output));
            foreach ($lines as $interface) {
                $interface = trim($interface);
                if (!empty($interface)) {
                    $interfaces[] = $interface;
                }
            }
        }
        
        return $interfaces;
    }

    protected function checkExternalConnectivity(): array
    {
        try {
            $start = microtime(true);
            $response = Http::timeout(5)->get('https://www.google.com');
            $responseTime = (microtime(true) - $start) * 1000;
            
            return [
                'success' => $response->successful(),
                'response_time_ms' => round($responseTime, 2),
                'status_code' => $response->status(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    protected function isServiceRunning(string $service): bool
    {
        $output = shell_exec("systemctl is-active $service 2>/dev/null");
        return trim($output) === 'active';
    }

    protected function getTopProcesses(): array
    {
        $output = shell_exec("ps aux --sort=-%cpu | head -n 6");
        $processes = [];
        
        if ($output) {
            $lines = explode("\n", trim($output));
            foreach (array_slice($lines, 1) as $line) {
                $parts = preg_split('/\s+/', $line);
                if (count($parts) >= 11) {
                    $processes[] = [
                        'user' => $parts[0],
                        'pid' => $parts[1],
                        'cpu' => $parts[2],
                        'memory' => $parts[3],
                        'command' => implode(' ', array_slice($parts, 10)),
                    ];
                }
            }
        }
        
        return array_slice($processes, 0, 5);
    }

    protected function getZombieProcesses(): array
    {
        $output = shell_exec("ps aux | grep 'Z' | grep -v grep");
        return explode("\n", trim($output));
    }

    protected function getTotalProcesses(): int
    {
        $output = shell_exec("ps aux | wc -l");
        return (int) trim($output) - 1; // Subtract header line
    }

    protected function getRunningProcesses(): int
    {
        $output = shell_exec("ps aux | grep -v 'R' | grep -v grep | wc -l");
        return (int) trim($output);
    }

    protected function getTemperatureSensors(): array
    {
        $sensors = [];
        
        // Try to read from /sys/class/thermal/thermal_zone*
        $thermalZones = glob('/sys/class/thermal/thermal_zone*');
        foreach ($thermalZones as $zone) {
            $temp = @file_get_contents($zone . '/temp');
            if ($temp) {
                $tempCelsius = (int) trim($temp) / 1000; // Convert to Celsius
                $zoneName = basename($zone);
                $sensors[$zoneName] = round($tempCelsius, 1);
            }
        }
        
        return $sensors;
    }

    protected function parseSize(string $size): int
    {
        $size = trim($size);
        $last = strtolower($size[strlen($size)-1]);
        $value = (int) $size;

        switch ($last) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }

        return $value;
    }

    /**
     * Check for system alerts
     */
    protected function checkSystemAlerts(array $status): void
    {
        foreach ($status['checks'] as $checkName => $check) {
            if ($check['status'] === 'critical' || $check['status'] === 'warning') {
                Log::channel('system_alerts')->alert("System Alert: {$checkName}", [
                    'check' => $checkName,
                    'status' => $check['status'],
                    'message' => $check['message'],
                    'metrics' => $check['metrics'] ?? [],
                    'timestamp' => now()->toISOString()
                ]);
            }
        }
    }

    /**
     * Cache health status for performance
     */
    protected function cacheHealthStatus(array $status): void
    {
        $cacheKey = $this->cachePrefix . 'health';
        Cache::put($cacheKey, $status, now()->addMinutes(5));
    }

    /**
     * Get cached health status
     */
    public function getCachedHealthStatus(): ?array
    {
        $cacheKey = $this->cachePrefix . 'health';
        return Cache::get($cacheKey);
    }

    /**
     * Generate system performance report
     */
    public function generateSystemReport(): array
    {
        return [
            'generated_at' => now()->toISOString(),
            'health_status' => $this->getHealthStatus(),
            'recommendations' => $this->generateRecommendations()
        ];
    }

    /**
     * Generate optimization recommendations
     */
    protected function generateRecommendations(): array
    {
        $recommendations = [];
        $healthStatus = $this->getHealthStatus();
        
        foreach ($healthStatus['checks'] as $checkName => $check) {
            if ($check['status'] === 'warning' || $check['status'] === 'critical') {
                switch ($checkName) {
                    case 'cpu':
                        $recommendations[] = 'Consider scaling CPU resources or optimizing CPU-intensive processes';
                        break;
                    case 'memory':
                        $recommendations[] = 'Consider adding more RAM or optimizing memory usage';
                        break;
                    case 'disk':
                        $recommendations[] = 'Clean up disk space or consider adding more storage';
                        break;
                    case 'load':
                        $recommendations[] = 'High system load detected - investigate resource-intensive processes';
                        break;
                }
            }
        }
        
        return $recommendations;
    }
}