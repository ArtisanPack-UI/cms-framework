<?php

namespace ArtisanPackUI\CMSFramework\Console\Commands;

use ArtisanPackUI\CMSFramework\Services\SearchService;
use Illuminate\Console\Command;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * SearchReindexCommand.
 *
 * Artisan command to reindex all searchable content for the search functionality.
 * This command clears the existing search index and rebuilds it from scratch.
 *
 * @link    https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package ArtisanPackUI\CMSFramework\Console\Commands
 * @since   1.2.0
 */
class SearchReindexCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cms:search:reindex 
                            {--batch-size=100 : Number of items to process in each batch}
                            {--types=* : Specific model types to reindex (e.g. content,term)}
                            {--dry-run : Show what would be indexed without actually doing it}
                            {--force : Force reindex without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reindex all searchable content for the search functionality';

    /**
     * SearchService instance.
     *
     * @var SearchService
     */
    protected SearchService $searchService;

    /**
     * Progress bar instance.
     *
     * @var ProgressBar|null
     */
    protected ?ProgressBar $progressBar = null;

    /**
     * Total items indexed counter.
     *
     * @var int
     */
    protected int $totalIndexed = 0;

    /**
     * Create a new command instance.
     *
     * @since 1.2.0
     *
     * @param SearchService $searchService
     */
    public function __construct(SearchService $searchService)
    {
        parent::__construct();
        $this->searchService = $searchService;
    }

    /**
     * Execute the console command.
     *
     * @since 1.2.0
     *
     * @return int
     */
    public function handle(): int
    {
        // Check if search is enabled
        if (!config('cms.search.enabled', true)) {
            $this->error('Search functionality is disabled in configuration.');
            return Command::FAILURE;
        }

        $isDryRun = $this->option('dry-run');
        $batchSize = (int) $this->option('batch-size');
        $specificTypes = $this->option('types');
        $force = $this->option('force');

        // Validate batch size
        if ($batchSize < 1 || $batchSize > 1000) {
            $this->error('Batch size must be between 1 and 1000.');
            return Command::FAILURE;
        }

        // Show configuration
        $this->info('Search Reindex Configuration:');
        $this->table([], [
            ['Setting', 'Value'],
            ['Dry Run', $isDryRun ? 'Yes' : 'No'],
            ['Batch Size', $batchSize],
            ['Types', empty($specificTypes) ? 'All' : implode(', ', $specificTypes)],
            ['Force', $force ? 'Yes' : 'No'],
        ]);

        // Confirmation unless forced or dry run
        if (!$isDryRun && !$force) {
            if (!$this->confirm('This will clear the existing search index and rebuild it. Continue?')) {
                $this->info('Reindex cancelled.');
                return Command::SUCCESS;
            }
        }

        if ($isDryRun) {
            $this->warn('DRY RUN: No actual indexing will be performed.');
        }

        $startTime = microtime(true);
        $this->info('Starting search reindex...');

        try {
            if ($isDryRun) {
                $this->performDryRun($batchSize, $specificTypes);
            } else {
                $this->performReindex($batchSize, $specificTypes);
            }

            $executionTime = round(microtime(true) - $startTime, 2);
            
            if ($isDryRun) {
                $this->info("Dry run completed in {$executionTime} seconds.");
                $this->info("Would have indexed {$this->totalIndexed} items.");
            } else {
                $this->info("Reindex completed successfully in {$executionTime} seconds.");
                $this->info("Total items indexed: {$this->totalIndexed}");
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Reindex failed: ' . $e->getMessage());
            
            if ($this->getOutput()->isVerbose()) {
                $this->error('Stack trace:');
                $this->error($e->getTraceAsString());
            }

            return Command::FAILURE;
        }
    }

    /**
     * Perform the actual reindex operation.
     *
     * @since 1.2.0
     *
     * @param int $batchSize
     * @param array $specificTypes
     * @return void
     */
    protected function performReindex(int $batchSize, array $specificTypes): void
    {
        // Update configuration for this operation
        config(['cms.search.indexing.batch_size' => $batchSize]);

        // Setup progress callback
        $progressCallback = function (string $type, int $indexed) {
            if (!$this->progressBar) {
                // We can't know total beforehand with chunked processing
                $this->progressBar = $this->output->createProgressBar();
                $this->progressBar->setFormat('verbose');
            }

            $this->progressBar->setMessage("Indexing {$type} (#{$indexed})");
            $this->progressBar->advance();
            $this->totalIndexed = $indexed;
        };

        // Perform reindex
        $totalIndexed = $this->searchService->reindexAll($progressCallback);

        if ($this->progressBar) {
            $this->progressBar->finish();
            $this->newLine();
        }

        $this->totalIndexed = $totalIndexed;
    }

    /**
     * Perform a dry run to show what would be indexed.
     *
     * @since 1.2.0
     *
     * @param int $batchSize
     * @param array $specificTypes
     * @return void
     */
    protected function performDryRun(int $batchSize, array $specificTypes): void
    {
        $indexableModels = config('cms.search.indexing.indexable_models', []);

        $this->info('Models that would be indexed:');
        
        foreach ($indexableModels as $modelClass) {
            if (!empty($specificTypes)) {
                $modelName = class_basename($modelClass);
                if (!in_array(strtolower($modelName), array_map('strtolower', $specificTypes))) {
                    continue;
                }
            }

            $count = $modelClass::count();
            $batches = ceil($count / $batchSize);
            
            $this->info("- {$modelClass}: {$count} items ({$batches} batches)");
            $this->totalIndexed += $count;
        }
    }

    /**
     * Get the console command arguments.
     *
     * @since 1.2.0
     *
     * @return array
     */
    protected function getArguments(): array
    {
        return [];
    }

    /**
     * Get the console command options.
     *
     * @since 1.2.0
     *
     * @return array
     */
    protected function getOptions(): array
    {
        return [
            ['batch-size', 'b', 'optional', 'Number of items to process in each batch', 100],
            ['types', 't', 'optional|array', 'Specific model types to reindex'],
            ['dry-run', null, 'none', 'Show what would be indexed without actually doing it'],
            ['force', 'f', 'none', 'Force reindex without confirmation'],
        ];
    }
}