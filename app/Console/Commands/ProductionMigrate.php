<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class ProductionMigrate extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'db:migrate:production 
                            {--step : Run migrations step by step with confirmation}
                            {--backup : Create backup before migration}
                            {--dry-run : Show what would be migrated without executing}
                            {--batch= : Run specific migration batch}
                            {--timeout=3600 : Migration timeout in seconds}
                            {--force : Skip confirmation prompts}
                            {--connection= : Database connection to use}
                            {--verify : Verify migration integrity after completion}';

    /**
     * The console command description.
     */
    protected $description = 'Run database migrations in production environment with safety features';

    /**
     * Migration timeout in seconds
     */
    protected int $timeout;

    /**
     * Database connection
     */
    protected string $connection;

    /**
     * Migration statistics
     */
    protected array $stats = [
        'started_at' => null,
        'completed_at' => null,
        'duration' => 0,
        'migrations_run' => 0,
        'migrations_failed' => 0,
        'migrations_rolled_back' => 0,
    ];

    public function __construct()
    {
        parent::__construct();
        // Initialize with default values, options will be set in handle()
        $this->timeout = 3600;
        $this->connection = config('database.default');
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Initialize options after command is fully loaded
        $this->timeout = (int) $this->option('timeout') ?: 3600;
        $this->connection = $this->option('connection') ?: config('database.default');

        $this->stats['started_at'] = now();

        try {
            // Pre-flight checks
            if (!$this->runPreflightChecks()) {
                return Command::FAILURE;
            }

            // Create backup if requested
            if ($this->option('backup') && !$this->createPreMigrationBackup()) {
                return Command::FAILURE;
            }

            // Run migrations based on options
            if ($this->option('dry-run')) {
                return $this->dryRun();
            }

            if ($this->option('step')) {
                return $this->stepByStepMigration();
            }

            if ($this->option('batch')) {
                return $this->batchMigration();
            }

            return $this->standardMigration();

        } catch (\Exception $e) {
            $this->error("Migration failed: " . $e->getMessage());
            Log::error('Production migration failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'stats' => $this->stats
            ]);
            return Command::FAILURE;
        } finally {
            $this->cleanup();
        }
    }

    /**
     * Run pre-flight checks before migration
     */
    protected function runPreflightChecks(): bool
    {
        $this->info('Running pre-flight checks...');

        // Check environment
        if (app()->environment('local') && !$this->option('force')) {
            if (!$this->confirm('You are running in local environment. Continue?')) {
                $this->info('Migration cancelled.');
                return false;
            }
        }

        // Check database connection
        try {
            DB::connection($this->connection)->getPdo();
            $this->info('✓ Database connection successful');
        } catch (\Exception $e) {
            $this->error('✗ Database connection failed: ' . $e->getMessage());
            return false;
        }

        // Check migrations table
        if (!Schema::connection($this->connection)->hasTable('migrations')) {
            $this->error('✗ Migrations table does not exist');
            return false;
        }

        // Check disk space
        $freeSpace = disk_free_space(storage_path());
        $requiredSpace = 100 * 1024 * 1024; // 100MB

        if ($freeSpace < $requiredSpace) {
            $this->error('✗ Insufficient disk space. Required: 100MB, Available: ' . 
                        round($freeSpace / 1024 / 1024, 2) . 'MB');
            return false;
        }

        $this->info('✓ Pre-flight checks completed successfully');
        return true;
    }

    /**
     * Create backup before migration
     */
    protected function createPreMigrationBackup(): bool
    {
        $this->info('Creating pre-migration backup...');

        try {
            $backupFile = storage_path('backups/pre_migration_' . date('Y-m-d_H-i-s') . '.sql');
            
            // Call backup script
            $exitCode = $this->call('db:backup', [
                '--file' => basename($backupFile),
                '--force' => true
            ]);

            if ($exitCode === Command::SUCCESS) {
                $this->info("✓ Backup created: $backupFile");
                return true;
            } else {
                $this->error('✗ Backup creation failed');
                return false;
            }
        } catch (\Exception $e) {
            $this->error('✗ Backup creation failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Dry run - show what would be migrated
     */
    protected function dryRun(): int
    {
        $this->info('Running migration dry-run...');
        $this->newLine();

        $pendingMigrations = $this->getPendingMigrations();

        if (empty($pendingMigrations)) {
            $this->info('No pending migrations found.');
            return Command::SUCCESS;
        }

        $this->info('Pending migrations (' . count($pendingMigrations) . '):');
        $this->newLine();

        foreach ($pendingMigrations as $migration) {
            $this->line("  • " . basename($migration, '.php'));
        }

        $this->newLine();
        $this->info('These migrations would be executed in production.');

        return Command::SUCCESS;
    }

    /**
     * Standard migration execution
     */
    protected function standardMigration(): int
    {
        $this->info('Running production migrations...');
        
        // Get pending migrations
        $pendingMigrations = $this->getPendingMigrations();
        
        if (empty($pendingMigrations)) {
            $this->info('No pending migrations to run.');
            return Command::SUCCESS;
        }

        $this->info('Found ' . count($pendingMigrations) . ' pending migrations.');
        
        if (!$this->option('force') && !$this->confirm('Proceed with migration?')) {
            $this->info('Migration cancelled.');
            return Command::SUCCESS;
        }

        // Execute migrations
        foreach ($pendingMigrations as $migration) {
            $this->executeMigration($migration);
        }

        return $this->completeMigration();
    }

    /**
     * Step-by-step migration with confirmation
     */
    protected function stepByStepMigration(): int
    {
        $pendingMigrations = $this->getPendingMigrations();

        if (empty($pendingMigrations)) {
            $this->info('No pending migrations to run.');
            return Command::SUCCESS;
        }

        foreach ($pendingMigrations as $migration) {
            $migrationName = basename($migration, '.php');
            $this->newLine();
            $this->info("Migration: $migrationName");
            
            if (!$this->confirm('Execute this migration?')) {
                $this->info("Skipped: $migrationName");
                continue;
            }

            $this->executeMigration($migration);

            if ($this->option('verify')) {
                if (!$this->verifyMigration($migrationName)) {
                    $this->error("Migration verification failed: $migrationName");
                    if ($this->confirm('Rollback this migration?')) {
                        $this->rollbackMigration($migrationName);
                        $this->stats['migrations_rolled_back']++;
                    }
                }
            }
        }

        return $this->completeMigration();
    }

    /**
     * Execute specific batch migration
     */
    protected function batchMigration(): int
    {
        $batch = $this->option('batch');
        $this->info("Running migration batch: $batch");

        // This would need to be implemented based on your batch organization
        // For now, we'll use the standard migration
        return $this->standardMigration();
    }

    /**
     * Execute a single migration with error handling
     */
    protected function executeMigration(string $migrationPath): void
    {
        $migrationName = basename($migrationPath, '.php');
        
        try {
            $this->line("Executing: $migrationName");
            
            // Load migration class
            require_once $migrationPath;
            $migrationClass = $this->getMigrationClass($migrationPath);
            
            if (!$migrationClass) {
                throw new \Exception("Could not load migration class from $migrationPath");
            }

            // Begin transaction for the migration
            DB::connection($this->connection)->beginTransaction();

            // Execute migration
            $migration = new $migrationClass();
            
            if (method_exists($migration, 'up')) {
                $migration->up();
            }

            // Record migration in database
            DB::connection($this->connection)->table('migrations')->insert([
                'migration' => $migrationName,
                'batch' => $this->getNextBatchNumber()
            ]);

            DB::connection($this->connection)->commit();

            $this->stats['migrations_run']++;
            $this->info("✓ Completed: $migrationName");
            
            Log::info('Migration executed successfully', [
                'migration' => $migrationName,
                'connection' => $this->connection
            ]);

        } catch (\Exception $e) {
            DB::connection($this->connection)->rollBack();
            
            $this->stats['migrations_failed']++;
            $this->error("✗ Failed: $migrationName - " . $e->getMessage());
            
            Log::error('Migration execution failed', [
                'migration' => $migrationName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            if (!$this->option('force')) {
                if (!$this->confirm('Migration failed. Continue with next migration?')) {
                    throw $e;
                }
            }
        }
    }

    /**
     * Rollback a migration
     */
    protected function rollbackMigration(string $migrationName): bool
    {
        try {
            $this->line("Rolling back: $migrationName");
            
            // Find migration file
            $migrationPath = database_path("migrations/{$migrationName}.php");
            
            if (!File::exists($migrationPath)) {
                throw new \Exception("Migration file not found: $migrationPath");
            }

            require_once $migrationPath;
            $migrationClass = $this->getMigrationClass($migrationPath);
            
            if (!$migrationClass) {
                throw new \Exception("Could not load migration class from $migrationPath");
            }

            // Begin transaction for rollback
            DB::connection($this->connection)->beginTransaction();

            // Execute rollback
            $migration = new $migrationClass();
            
            if (method_exists($migration, 'down')) {
                $migration->down();
            }

            // Remove migration record
            DB::connection($this->connection)->table('migrations')
                ->where('migration', $migrationName)
                ->delete();

            DB::connection($this->connection)->commit();

            $this->info("✓ Rolled back: $migrationName");
            Log::info('Migration rolled back successfully', [
                'migration' => $migrationName,
                'connection' => $this->connection
            ]);

            return true;

        } catch (\Exception $e) {
            DB::connection($this->connection)->rollBack();
            
            $this->error("✗ Rollback failed: $migrationName - " . $e->getMessage());
            
            Log::error('Migration rollback failed', [
                'migration' => $migrationName,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Verify migration integrity
     */
    protected function verifyMigration(string $migrationName): bool
    {
        try {
            $this->line("Verifying: $migrationName");
            
            // Basic verification - check if migration record exists
            $exists = DB::connection($this->connection)->table('migrations')
                ->where('migration', $migrationName)
                ->exists();

            if (!$exists) {
                $this->error("Migration record not found: $migrationName");
                return false;
            }

            $this->info("✓ Verified: $migrationName");
            return true;

        } catch (\Exception $e) {
            $this->error("Verification failed: $migrationName - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Complete migration and show statistics
     */
    protected function completeMigration(): int
    {
        $this->stats['completed_at'] = now();
        $this->stats['duration'] = $this->stats['completed_at']->diffInSeconds($this->stats['started_at']);

        $this->newLine();
        $this->info('Migration Summary:');
        $this->line('  Duration: ' . $this->stats['duration'] . ' seconds');
        $this->line('  Migrations run: ' . $this->stats['migrations_run']);
        $this->line('  Migrations failed: ' . $this->stats['migrations_failed']);
        $this->line('  Migrations rolled back: ' . $this->stats['migrations_rolled_back']);

        // Post-migration verification
        if ($this->option('verify')) {
            $this->runPostMigrationVerification();
        }

        // Update migration statistics cache
        $this->cacheMigrationStats();

        Log::info('Production migration completed', $this->stats);

        if ($this->stats['migrations_failed'] > 0) {
            $this->error('Migration completed with errors.');
            return Command::FAILURE;
        }

        $this->info('Migration completed successfully!');
        return Command::SUCCESS;
    }

    /**
     * Run post-migration verification checks
     */
    protected function runPostMigrationVerification(): void
    {
        $this->info('Running post-migration verification...');

        try {
            // Check database integrity
            if ($this->checkDatabaseIntegrity()) {
                $this->info('✓ Database integrity check passed');
            } else {
                $this->error('✗ Database integrity check failed');
            }

            // Check critical tables
            if ($this->checkCriticalTables()) {
                $this->info('✓ Critical tables verification passed');
            } else {
                $this->error('✗ Critical tables verification failed');
            }

            // Check indexes
            if ($this->checkIndexes()) {
                $this->info('✓ Index verification passed');
            } else {
                $this->error('✗ Index verification failed');
            }

        } catch (\Exception $e) {
            $this->error('Post-migration verification failed: ' . $e->getMessage());
        }
    }

    /**
     * Check database integrity
     */
    protected function checkDatabaseIntegrity(): bool
    {
        try {
            // Test basic queries on all critical tables
            $criticalTables = [
                'users', 'orders', 'products', 'customers', 
                'stocks', 'invoices', 'order_processings'
            ];

            foreach ($criticalTables as $table) {
                if (!Schema::connection($this->connection)->hasTable($table)) {
                    continue; // Skip if table doesn't exist
                }

                DB::connection($this->connection)->table($table)->limit(1)->count();
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Database integrity check failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Check critical tables exist and are accessible
     */
    protected function checkCriticalTables(): bool
    {
        try {
            $criticalTables = ['users', 'orders', 'products'];
            
            foreach ($criticalTables as $table) {
                if (!Schema::connection($this->connection)->hasTable($table)) {
                    $this->error("Critical table missing: $table");
                    return false;
                }
            }

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check indexes integrity
     */
    protected function checkIndexes(): bool
    {
        try {
            if ($this->isMySQL()) {
                return $this->checkMySQLIndexes();
            } elseif ($this->isPostgreSQL()) {
                return $this->checkPostgreSQLIndexes();
            }
            
            return true; // Skip if unknown database type
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check MySQL indexes
     */
    protected function checkMySQLIndexes(): bool
    {
        try {
            $indexes = DB::connection($this->connection)->select("
                SHOW INDEX FROM users
            ");
            
            return !empty($indexes);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check PostgreSQL indexes
     */
    protected function checkPostgreSQLIndexes(): bool
    {
        try {
            $indexes = DB::connection($this->connection)->select("
                SELECT indexname FROM pg_indexes 
                WHERE tablename = 'users' AND schemaname = 'public'
            ");
            
            return !empty($indexes);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get pending migrations
     */
    protected function getPendingMigrations(): array
    {
        $migrationPath = database_path('migrations');
        $files = File::glob($migrationPath . '/*.php');
        
        sort($files);
        
        $ran = DB::connection($this->connection)->table('migrations')
            ->pluck('migration')
            ->toArray();
        
        return array_filter($files, function($file) use ($ran) {
            $name = basename($file, '.php');
            return !in_array($name, $ran);
        });
    }

    /**
     * Get migration class name from file path
     */
    protected function getMigrationClass(string $filePath): ?string
    {
        $content = File::get($filePath);
        
        // Extract class name using regex
        if (preg_match('/class\s+(\w+)\s+extends\s+.*Migration/', $content, $matches)) {
            return $matches[1];
        }
        
        return null;
    }

    /**
     * Get next batch number
     */
    protected function getNextBatchNumber(): int
    {
        return DB::connection($this->connection)->table('migrations')
            ->max('batch') + 1;
    }

    /**
     * Cache migration statistics
     */
    protected function cacheMigrationStats(): void
    {
        $cacheKey = "migration_stats_{$this->connection}";
        Cache::put($cacheKey, $this->stats, now()->addDay());
    }

    /**
     * Cleanup after migration
     */
    protected function cleanup(): void
    {
        // Clear any cached data if needed
        Cache::tags(['migrations'])->flush();
    }

    /**
     * Check if current connection is MySQL
     */
    protected function isMySQL(): bool
    {
        return in_array($this->connection, ['mysql', 'mariadb']);
    }

    /**
     * Check if current connection is PostgreSQL
     */
    protected function isPostgreSQL(): bool
    {
        return in_array($this->connection, ['pgsql', 'postgres', 'postgresql']);
    }
}