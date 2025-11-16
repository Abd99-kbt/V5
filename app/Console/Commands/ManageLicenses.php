<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\LicenseService;
use App\Models\License;
use Carbon\Carbon;

class ManageLicenses extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'license:manage
                            {action : Action to perform (generate|activate|deactivate|extend|list|status)}
                            {--license_key= : License key for specific operations}
                            {--email= : Customer email}
                            {--name= : Customer name}
                            {--type=trial : License type (trial|basic|professional|enterprise)}
                            {--days=30 : Number of days for trial/extension}
                            {--users= : Maximum users}
                            {--installations= : Maximum installations}';

    /**
     * The console command description.
     */
    protected $description = 'Manage software licenses';

    protected $licenseService;

    public function __construct(LicenseService $licenseService)
    {
        parent::__construct();
        $this->licenseService = $licenseService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $action = $this->argument('action');

        switch ($action) {
            case 'generate':
                $this->generateLicense();
                break;
            case 'activate':
                $this->activateLicense();
                break;
            case 'deactivate':
                $this->deactivateLicense();
                break;
            case 'extend':
                $this->extendLicense();
                break;
            case 'list':
                $this->listLicenses();
                break;
            case 'status':
                $this->showLicenseStatus();
                break;
            default:
                $this->error('Invalid action. Use: generate, activate, deactivate, extend, list, or status');
                return 1;
        }

        return 0;
    }

    protected function generateLicense()
    {
        $email = $this->option('email');
        $name = $this->option('name');
        $type = $this->option('type');
        $days = (int) $this->option('days');
        $maxUsers = $this->option('users') ? (int) $this->option('users') : null;
        $maxInstallations = $this->option('installations') ? (int) $this->option('installations') : null;

        if (!$email || !$name) {
            $this->error('Email and name are required for license generation');
            return;
        }

        $licenseData = [
            'customer_email' => $email,
            'customer_name' => $name,
            'license_type' => $type,
            'max_users' => $maxUsers ?? config("license.types.{$type}.max_users", 10),
            'max_installations' => $maxInstallations ?? config("license.types.{$type}.max_installations", 1),
        ];

        // Add expiration for trial licenses
        if ($type === 'trial') {
            $licenseData['expires_at'] = now()->addDays($days);
        }

        $license = $this->licenseService->generateLicense($licenseData);

        $this->info('License generated successfully!');
        $this->table(
            ['Field', 'Value'],
            [
                ['License Key', $license->license_key],
                ['Customer', $license->customer_name . ' (' . $license->customer_email . ')'],
                ['Type', $license->license_type],
                ['Max Users', $license->max_users],
                ['Max Installations', $license->max_installations],
                ['Expires At', $license->expires_at ? $license->expires_at->format('Y-m-d H:i:s') : 'Never'],
                ['Status', $license->is_active ? 'Active' : 'Inactive'],
            ]
        );
    }

    protected function activateLicense()
    {
        $licenseKey = $this->option('license_key');

        if (!$licenseKey) {
            $this->error('License key is required');
            return;
        }

        $result = $this->licenseService->activateLicense($licenseKey);

        if ($result['success']) {
            $this->info('License activated successfully!');
        } else {
            $this->error('Failed to activate license: ' . $result['message']);
        }
    }

    protected function deactivateLicense()
    {
        $licenseKey = $this->option('license_key');

        if (!$licenseKey) {
            $this->error('License key is required');
            return;
        }

        $reason = $this->ask('Reason for deactivation (optional)');

        $result = $this->licenseService->deactivateLicense($licenseKey, $reason);

        if ($result['success']) {
            $this->info('License deactivated successfully!');
        } else {
            $this->error('Failed to deactivate license: ' . $result['message']);
        }
    }

    protected function extendLicense()
    {
        $licenseKey = $this->option('license_key');
        $days = (int) $this->option('days');

        if (!$licenseKey) {
            $this->error('License key is required');
            return;
        }

        $license = License::where('license_key', $licenseKey)->first();

        if (!$license) {
            $this->error('License not found');
            return;
        }

        $newExpiration = $license->expires_at
            ? $license->expires_at->addDays($days)
            : now()->addDays($days);

        $result = $this->licenseService->extendLicense($licenseKey, $newExpiration);

        if ($result['success']) {
            $this->info("License extended by {$days} days!");
            $this->info('New expiration: ' . $newExpiration->format('Y-m-d H:i:s'));
        } else {
            $this->error('Failed to extend license: ' . $result['message']);
        }
    }

    protected function listLicenses()
    {
        $licenses = License::orderBy('created_at', 'desc')->get();

        if ($licenses->isEmpty()) {
            $this->info('No licenses found.');
            return;
        }

        $this->table(
            ['License Key', 'Customer', 'Type', 'Status', 'Expires', 'Activations'],
            $licenses->map(function ($license) {
                return [
                    $license->license_key,
                    $license->customer_name,
                    $license->license_type,
                    $license->is_active ? 'Active' : 'Inactive',
                    $license->expires_at ? $license->expires_at->format('Y-m-d') : 'Never',
                    $license->activation_count . '/' . $license->max_installations,
                ];
            })->toArray()
        );
    }

    protected function showLicenseStatus()
    {
        $licenseKey = $this->option('license_key');

        if (!$licenseKey) {
            $this->error('License key is required');
            return;
        }

        $status = $this->licenseService->getLicenseStatus($licenseKey);

        if (!$status) {
            $this->error('License not found');
            return;
        }

        $this->table(
            ['Field', 'Value'],
            [
                ['License Key', $status['license_key']],
                ['Customer Email', $status['customer_email']],
                ['License Type', $status['license_type']],
                ['Status', $status['is_active'] ? 'Active' : 'Inactive'],
                ['Expires At', $status['expires_at'] ? Carbon::parse($status['expires_at'])->format('Y-m-d H:i:s') : 'Never'],
                ['Activation Count', $status['activation_count']],
                ['Max Installations', $status['max_installations']],
                ['Features', implode(', ', $status['features'] ?? [])],
            ]
        );
    }
}