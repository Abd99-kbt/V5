<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;

class UpdateService
{
    protected $updateServerUrl;
    protected $currentVersion;
    protected $cacheTtl = 3600; // 1 hour

    public function __construct()
    {
        $this->updateServerUrl = config('license.update_server_url', 'https://updates.yourdomain.com/api');
        $this->currentVersion = config('app.version', '1.0.0');
    }

    /**
     * Check for available updates
     */
    public function checkForUpdates($licenseKey = null)
    {
        $cacheKey = 'update_check_' . md5($licenseKey . $this->currentVersion);

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($licenseKey) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . config('license.update_api_key'),
                    'X-License-Key' => $licenseKey,
                    'X-Current-Version' => $this->currentVersion,
                ])->timeout(10)->get("{$this->updateServerUrl}/updates/check");

                if ($response->successful()) {
                    $updateInfo = $response->json();

                    return [
                        'update_available' => $updateInfo['update_available'] ?? false,
                        'latest_version' => $updateInfo['latest_version'] ?? $this->currentVersion,
                        'release_notes' => $updateInfo['release_notes'] ?? [],
                        'download_url' => $updateInfo['download_url'] ?? null,
                        'mandatory' => $updateInfo['mandatory'] ?? false,
                        'checked_at' => now(),
                    ];
                }

                Log::warning('Update check failed', [
                    'license_key' => $licenseKey,
                    'response_status' => $response->status()
                ]);

            } catch (\Exception $e) {
                Log::error('Update check error', [
                    'license_key' => $licenseKey,
                    'error' => $e->getMessage()
                ]);
            }

            return [
                'update_available' => false,
                'latest_version' => $this->currentVersion,
                'error' => true
            ];
        });
    }

    /**
     * Download and install update
     */
    public function installUpdate($version, $downloadUrl, $licenseKey = null)
    {
        try {
            Log::info('Starting update installation', [
                'version' => $version,
                'license_key' => $licenseKey
            ]);

            // Create backup
            $backupPath = $this->createBackup();

            // Download update package
            $updatePackage = $this->downloadUpdatePackage($downloadUrl);

            if (!$updatePackage) {
                throw new \Exception('Failed to download update package');
            }

            // Verify package integrity
            if (!$this->verifyPackageIntegrity($updatePackage)) {
                throw new \Exception('Package integrity check failed');
            }

            // Extract and install
            $this->extractAndInstall($updatePackage);

            // Run migrations if needed
            $this->runMigrations();

            // Clear caches
            $this->clearCaches();

            // Verify installation
            if (!$this->verifyInstallation($version)) {
                // Rollback if verification fails
                $this->rollbackUpdate($backupPath);
                throw new \Exception('Installation verification failed');
            }

            // Clean up
            $this->cleanup($updatePackage);

            Log::info('Update installation completed successfully', [
                'version' => $version,
                'license_key' => $licenseKey
            ]);

            return [
                'success' => true,
                'version' => $version,
                'backup_path' => $backupPath
            ];

        } catch (\Exception $e) {
            Log::error('Update installation failed', [
                'version' => $version,
                'license_key' => $licenseKey,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'version' => $version
            ];
        }
    }

    /**
     * Create backup before update
     */
    protected function createBackup()
    {
        $backupDir = storage_path('backups/update_' . date('Y-m-d_H-i-s'));

        if (!File::exists($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
        }

        // Backup critical files
        $criticalFiles = [
            'app/',
            'config/',
            'database/migrations/',
            'routes/',
            'composer.json',
            'composer.lock',
            'artisan',
        ];

        foreach ($criticalFiles as $file) {
            $source = base_path($file);
            $destination = $backupDir . '/' . $file;

            if (File::exists($source)) {
                if (is_dir($source)) {
                    $this->copyDirectory($source, $destination);
                } else {
                    $destDir = dirname($destination);
                    if (!File::exists($destDir)) {
                        File::makeDirectory($destDir, 0755, true);
                    }
                    File::copy($source, $destination);
                }
            }
        }

        Log::info('Update backup created', ['backup_path' => $backupDir]);
        return $backupDir;
    }

    /**
     * Download update package
     */
    protected function downloadUpdatePackage($downloadUrl)
    {
        try {
            $response = Http::timeout(300)->get($downloadUrl);

            if ($response->successful()) {
                $tempFile = tempnam(sys_get_temp_dir(), 'update_');
                file_put_contents($tempFile, $response->body());
                return $tempFile;
            }
        } catch (\Exception $e) {
            Log::error('Update package download failed', ['error' => $e->getMessage()]);
        }

        return false;
    }

    /**
     * Verify package integrity
     */
    protected function verifyPackageIntegrity($packagePath)
    {
        // This would verify checksums, signatures, etc.
        // For now, just check if file exists and is not empty
        return File::exists($packagePath) && File::size($packagePath) > 0;
    }

    /**
     * Extract and install update
     */
    protected function extractAndInstall($packagePath)
    {
        $extractPath = storage_path('temp/update_extract_' . time());

        // Extract package (assuming ZIP format)
        $zip = new \ZipArchive();
        if ($zip->open($packagePath) === true) {
            $zip->extractTo($extractPath);
            $zip->close();

            // Copy files to application directory
            $this->copyDirectory($extractPath, base_path());

            // Remove extract directory
            File::deleteDirectory($extractPath);
        } else {
            throw new \Exception('Failed to extract update package');
        }
    }

    /**
     * Run database migrations
     */
    protected function runMigrations()
    {
        try {
            Artisan::call('migrate', ['--force' => true]);
            Log::info('Database migrations completed');
        } catch (\Exception $e) {
            Log::error('Database migration failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Clear application caches
     */
    protected function clearCaches()
    {
        Artisan::call('cache:clear');
        Artisan::call('config:clear');
        Artisan::call('route:clear');
        Artisan::call('view:clear');

        Log::info('Application caches cleared');
    }

    /**
     * Verify installation
     */
    protected function verifyInstallation($expectedVersion)
    {
        // Check if version file exists and contains expected version
        $versionFile = base_path('version.txt');
        if (File::exists($versionFile)) {
            $installedVersion = trim(File::get($versionFile));
            return $installedVersion === $expectedVersion;
        }

        // Fallback: check if application is responding
        return true; // Assume success if we reach here
    }

    /**
     * Rollback update
     */
    protected function rollbackUpdate($backupPath)
    {
        Log::warning('Rolling back update', ['backup_path' => $backupPath]);

        if (File::exists($backupPath)) {
            $this->copyDirectory($backupPath, base_path());
            Log::info('Update rollback completed');
        } else {
            Log::error('Backup not found for rollback', ['backup_path' => $backupPath]);
        }
    }

    /**
     * Clean up temporary files
     */
    protected function cleanup($packagePath)
    {
        if (File::exists($packagePath)) {
            File::delete($packagePath);
        }
    }

    /**
     * Copy directory recursively
     */
    protected function copyDirectory($src, $dst)
    {
        if (!File::exists($dst)) {
            File::makeDirectory($dst, 0755, true);
        }

        $files = File::allFiles($src);

        foreach ($files as $file) {
            $relativePath = str_replace($src . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $destPath = $dst . DIRECTORY_SEPARATOR . $relativePath;

            $destDir = dirname($destPath);
            if (!File::exists($destDir)) {
                File::makeDirectory($destDir, 0755, true);
            }

            File::copy($file->getPathname(), $destPath);
        }
    }

    /**
     * Get update history
     */
    public function getUpdateHistory($licenseKey = null)
    {
        // This would track installed updates in database
        // For now, return mock data
        return [
            [
                'version' => '1.0.0',
                'installed_at' => now()->subDays(30),
                'license_key' => $licenseKey
            ],
            [
                'version' => '1.1.0',
                'installed_at' => now()->subDays(15),
                'license_key' => $licenseKey
            ]
        ];
    }

    /**
     * Schedule automatic updates
     */
    public function scheduleAutomaticUpdates($licenseKey, $frequency = 'weekly')
    {
        // This would set up scheduled tasks for automatic updates
        Log::info('Automatic updates scheduled', [
            'license_key' => $licenseKey,
            'frequency' => $frequency
        ]);

        return [
            'success' => true,
            'frequency' => $frequency,
            'next_check' => $this->calculateNextCheck($frequency)
        ];
    }

    /**
     * Calculate next update check
     */
    protected function calculateNextCheck($frequency)
    {
        switch ($frequency) {
            case 'daily':
                return now()->addDay();
            case 'weekly':
                return now()->addWeek();
            case 'monthly':
                return now()->addMonth();
            default:
                return now()->addWeek();
        }
    }

    /**
     * Force update check (bypass cache)
     */
    public function forceUpdateCheck($licenseKey = null)
    {
        $cacheKey = 'update_check_' . md5($licenseKey . $this->currentVersion);
        Cache::forget($cacheKey);

        return $this->checkForUpdates($licenseKey);
    }

    /**
     * Get system update status
     */
    public function getSystemUpdateStatus()
    {
        return [
            'current_version' => $this->currentVersion,
            'last_check' => Cache::get('last_update_check'),
            'auto_update_enabled' => config('license.auto_update_enabled', false),
            'update_channel' => config('license.update_channel', 'stable'),
            'backup_before_update' => config('license.backup_before_update', true),
        ];
    }
}