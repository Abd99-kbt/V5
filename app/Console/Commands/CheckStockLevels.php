<?php

namespace App\Console\Commands;

use App\Services\StockAlertService;
use Illuminate\Console\Command;

class CheckStockLevels extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'warehouse:check-stock-levels {--dry-run : Show what would be done without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check stock levels and create alerts for low stock, expired products, etc.';

    /**
     * Execute the console command.
     */
    public function handle(StockAlertService $stockAlertService)
    {
        $this->info('🔍 Checking stock levels...');

        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('🔍 Running in dry-run mode - no changes will be made');
        }

        try {
            $result = $stockAlertService->checkStockLevels();

            $this->info('✅ Stock check completed successfully!');
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Alerts Created', $result['alerts_created']],
                    ['Alerts Resolved', $result['alerts_resolved']],
                ]
            );

            if ($dryRun) {
                $this->info('🔍 Dry run completed - no actual changes were made');
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Error checking stock levels: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
