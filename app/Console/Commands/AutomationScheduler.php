<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\SmartMaterialSelectionService;
use App\Services\AutomatedQualityControlService;
use App\Services\AutomatedInventoryService;
use App\Services\AutomatedReportingService;
use App\Services\WorkflowAutomationService;
use App\Services\MachineLearningService;

class AutomationScheduler extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'automation:schedule';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run automated tasks for material selection, quality checks, inventory management, reporting, workflow automation, IoT data collection, and maintenance predictions';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting automated tasks...');

        $this->runMaterialSelection();
        $this->runQualityChecks();
        $this->runInventoryManagement();
        $this->runReporting();
        $this->runWorkflowAutomation();
        $this->runIoTDataCollection();
        $this->runMaintenancePredictions();

        $this->info('All automated tasks completed.');
    }

    /**
     * Run automated material selection.
     */
    private function runMaterialSelection()
    {
        $this->info('Running material selection...');
        $service = new SmartMaterialSelectionService();
        $service->run();
        $this->info('Material selection completed.');
    }

    /**
     * Run automated quality checks.
     */
    private function runQualityChecks()
    {
        $this->info('Running quality checks...');
        $service = new AutomatedQualityControlService();
        $service->run();
        $this->info('Quality checks completed.');
    }

    /**
     * Run automated inventory management.
     */
    private function runInventoryManagement()
    {
        $this->info('Running inventory management...');
        $service = new AutomatedInventoryService();
        $service->run();
        $this->info('Inventory management completed.');
    }

    /**
     * Run automated reporting.
     */
    private function runReporting()
    {
        $this->info('Running automated reporting...');
        $service = new AutomatedReportingService();
        $service->run();
        $this->info('Automated reporting completed.');
    }

    /**
     * Run workflow automation.
     */
    private function runWorkflowAutomation()
    {
        $this->info('Running workflow automation...');
        $service = new WorkflowAutomationService();
        $service->run();
        $this->info('Workflow automation completed.');
    }

    /**
     * Run IoT data collection.
     */
    private function runIoTDataCollection()
    {
        $this->info('Running IoT data collection...');
        // Assuming IoT data collection is handled by MachineLearningService or a dedicated service
        $service = new MachineLearningService();
        $service->collectIoTData();
        $this->info('IoT data collection completed.');
    }

    /**
     * Run maintenance predictions.
     */
    private function runMaintenancePredictions()
    {
        $this->info('Running maintenance predictions...');
        $service = new MachineLearningService();
        $service->predictMaintenance();
        $this->info('Maintenance predictions completed.');
    }
}