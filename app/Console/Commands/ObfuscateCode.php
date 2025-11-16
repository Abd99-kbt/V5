<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\CodeObfuscationService;
use App\Services\DatabaseEncryptionService;

class ObfuscateCode extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'code:obfuscate
                            {action : Action to perform (obfuscate|deobfuscate|encrypt-db|decrypt-db|backup-db|restore-db|status)}
                            {--path= : Path to obfuscate (default: app directory)}
                            {--backup-file= : Backup file for database operations}
                            {--force : Force operation without confirmation}';

    /**
     * The console command description.
     */
    protected $description = 'Obfuscate code and encrypt database for protection';

    protected $codeObfuscationService;
    protected $databaseEncryptionService;

    public function __construct(
        CodeObfuscationService $codeObfuscationService,
        DatabaseEncryptionService $databaseEncryptionService
    ) {
        parent::__construct();
        $this->codeObfuscationService = $codeObfuscationService;
        $this->databaseEncryptionService = $databaseEncryptionService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $action = $this->argument('action');

        switch ($action) {
            case 'obfuscate':
                $this->obfuscateCode();
                break;
            case 'deobfuscate':
                $this->deobfuscateCode();
                break;
            case 'encrypt-db':
                $this->encryptDatabase();
                break;
            case 'decrypt-db':
                $this->decryptDatabase();
                break;
            case 'backup-db':
                $this->backupDatabase();
                break;
            case 'restore-db':
                $this->restoreDatabase();
                break;
            case 'status':
                $this->showStatus();
                break;
            default:
                $this->error('Invalid action. Use: obfuscate, deobfuscate, encrypt-db, decrypt-db, backup-db, restore-db, or status');
                return 1;
        }

        return 0;
    }

    protected function obfuscateCode()
    {
        if (!$this->option('force')) {
            if (!$this->confirm('This will obfuscate your code. Make sure you have a backup. Continue?')) {
                return;
            }
        }

        $path = $this->option('path') ?: app_path();

        $this->info('Starting code obfuscation...');
        $this->info("Target path: {$path}");

        $result = $this->codeObfuscationService->obfuscateCode($path);

        if ($result['success']) {
            $this->info('✅ Code obfuscation completed successfully!');
            $this->info("Files processed: {$result['files_processed']}");
        } else {
            $this->error('❌ Code obfuscation failed!');
        }
    }

    protected function deobfuscateCode()
    {
        $this->error('Deobfuscation is not implemented yet. Use backup to restore original code.');
        return;
    }

    protected function encryptDatabase()
    {
        if (!$this->option('force')) {
            if (!$this->confirm('This will encrypt sensitive data in your database. Continue?')) {
                return;
            }
        }

        $this->info('Starting database encryption...');

        $result = $this->databaseEncryptionService->encryptDatabase();

        if ($result['success']) {
            $this->info('✅ Database encryption completed successfully!');

            foreach ($result['results'] as $table => $tableResult) {
                $this->line("{$table}: {$tableResult['fields_encrypted']} fields encrypted from {$tableResult['records_processed']} records");
            }
        } else {
            $this->error('❌ Database encryption failed: ' . ($result['message'] ?? 'Unknown error'));
        }
    }

    protected function decryptDatabase()
    {
        if (!$this->option('force')) {
            if (!$this->confirm('This will decrypt sensitive data in your database. Continue?')) {
                return;
            }
        }

        $this->info('Starting database decryption...');

        $result = $this->databaseEncryptionService->decryptDatabase();

        if ($result['success']) {
            $this->info('✅ Database decryption completed successfully!');

            foreach ($result['results'] as $table => $tableResult) {
                $this->line("{$table}: {$tableResult['fields_decrypted']} fields decrypted from {$tableResult['records_processed']} records");
            }
        } else {
            $this->error('❌ Database decryption failed: ' . ($result['message'] ?? 'Unknown error'));
        }
    }

    protected function backupDatabase()
    {
        $this->info('Creating encrypted database backup...');

        $result = $this->databaseEncryptionService->createEncryptedBackup();

        if ($result['success']) {
            $this->info('✅ Database backup created successfully!');
            $this->info("Backup path: {$result['path']}");
        } else {
            $this->error('❌ Database backup failed: ' . ($result['message'] ?? 'Unknown error'));
        }
    }

    protected function restoreDatabase()
    {
        $backupFile = $this->option('backup-file');

        if (!$backupFile) {
            $this->error('Backup file is required. Use --backup-file option.');
            return;
        }

        $backupPath = storage_path('backups/' . $backupFile);

        if (!$this->option('force')) {
            if (!$this->confirm("This will restore database from {$backupPath}. Continue?")) {
                return;
            }
        }

        $this->info('Restoring database from backup...');

        $result = $this->databaseEncryptionService->restoreEncryptedBackup($backupPath);

        if ($result['success']) {
            $this->info('✅ Database restored successfully!');
            $this->info("Tables restored: {$result['tables_restored']}");
        } else {
            $this->error('❌ Database restore failed: ' . ($result['message'] ?? 'Unknown error'));
        }
    }

    protected function showStatus()
    {
        $this->info('=== Code Obfuscation Status ===');

        // Check if code is obfuscated
        $sampleFiles = [
            app_path('Models/User.php'),
            app_path('Services/LicenseService.php'),
            app_path('Http/Controllers/Controller.php'),
        ];

        $obfuscatedCount = 0;
        foreach ($sampleFiles as $file) {
            if (file_exists($file) && $this->codeObfuscationService->isObfuscated($file)) {
                $obfuscatedCount++;
            }
        }

        $this->info("Sample files checked: " . count($sampleFiles));
        $this->info("Obfuscated files: {$obfuscatedCount}");

        $this->info("\n=== Database Encryption Status ===");

        $status = $this->databaseEncryptionService->getEncryptionStatus();

        foreach ($status as $table => $fields) {
            $this->info("Table: {$table}");

            foreach ($fields as $field => $fieldStatus) {
                $percentage = $fieldStatus['percentage'];
                $status = $percentage > 0 ? ($percentage == 100 ? 'Fully Encrypted' : 'Partially Encrypted') : 'Not Encrypted';
                $this->line("  {$field}: {$fieldStatus['encrypted']}/{$fieldStatus['total']} ({$percentage}%) - {$status}");
            }
        }

        $this->info("\n=== Configuration Status ===");
        $this->info("Code obfuscation enabled: " . (config('license.obfuscation.enabled') ? 'Yes' : 'No'));
        $this->info("Database encryption enabled: " . (config('license.database_encryption.enabled') ? 'Yes' : 'No'));
        $this->info("License protection enabled: " . (config('license.enabled') ? 'Yes' : 'No'));
    }
}