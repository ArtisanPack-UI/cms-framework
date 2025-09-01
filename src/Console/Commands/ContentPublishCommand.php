<?php

namespace ArtisanPackUI\CMSFramework\Console\Commands;

use ArtisanPackUI\CMSFramework\Models\Content;
use Illuminate\Console\Command;

/**
 * Content publishing command
 * 
 * This command manages content publishing status changes.
 * It can publish drafts, unpublish content, and schedule publishing.
 */
class ContentPublishCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'cms:content:publish 
                           {content : Content ID or slug}
                           {--action=publish : Action to perform (publish, unpublish, draft, schedule)}
                           {--schedule= : Schedule publishing date (Y-m-d H:i:s format)}
                           {--force : Skip confirmation prompts}';

    /**
     * The console command description.
     */
    protected $description = 'Manage content publishing status';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $contentIdentifier = $this->argument('content');
            $action = $this->option('action');
            
            // Find the content
            $content = $this->findContent($contentIdentifier);
            if (!$content) {
                return Command::FAILURE;
            }
            
            $this->info("Found content: {$content->title} (ID: {$content->id})");
            $this->info("Current status: {$content->status}");
            
            // Validate action
            if (!$this->validateAction($action)) {
                return Command::FAILURE;
            }
            
            // Handle scheduling
            $scheduledAt = null;
            if ($action === 'schedule') {
                $scheduledAt = $this->getScheduledDate();
                if (!$scheduledAt) {
                    return Command::FAILURE;
                }
            }
            
            // Show summary and confirm
            if (!$this->option('force')) {
                $this->displayStatusChangeSummary($content, $action, $scheduledAt);
                if (!$this->confirm('Apply status change?', true)) {
                    $this->info('Status change cancelled.');
                    return Command::SUCCESS;
                }
            }
            
            // Apply the status change
            $this->applyStatusChange($content, $action, $scheduledAt);
            
            $this->info("✅ Content status updated successfully!");
            $this->info("   Title: {$content->title}");
            $this->info("   New Status: {$content->status}");
            if ($content->published_at) {
                $this->info("   Published At: {$content->published_at}");
            }
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('Error updating content status: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
    
    /**
     * Find content by ID or slug
     */
    private function findContent(string $identifier): ?Content
    {
        // Try to find by ID first if numeric
        if (is_numeric($identifier)) {
            $content = Content::find($identifier);
            if ($content) {
                return $content;
            }
        }
        
        // Try to find by slug
        $content = Content::where('slug', $identifier)->first();
        if ($content) {
            return $content;
        }
        
        $this->error("Content '{$identifier}' not found.");
        return null;
    }
    
    /**
     * Validate the action
     */
    private function validateAction(string $action): bool
    {
        $validActions = ['publish', 'unpublish', 'draft', 'schedule'];
        
        if (!in_array($action, $validActions)) {
            $this->error("Invalid action '{$action}'.");
            $this->info('Valid actions: ' . implode(', ', $validActions));
            return false;
        }
        
        return true;
    }
    
    /**
     * Get scheduled date from option or prompt
     */
    private function getScheduledDate(): ?\Carbon\Carbon
    {
        $scheduleInput = $this->option('schedule');
        
        if (!$scheduleInput) {
            $scheduleInput = $this->ask('Enter schedule date and time (Y-m-d H:i:s format)', now()->addHour()->format('Y-m-d H:i:s'));
        }
        
        try {
            $scheduledAt = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $scheduleInput);
            
            if ($scheduledAt->isPast()) {
                $this->error('Scheduled date cannot be in the past.');
                return null;
            }
            
            return $scheduledAt;
            
        } catch (\Exception $e) {
            $this->error('Invalid date format. Please use Y-m-d H:i:s format (e.g., 2024-12-25 14:30:00).');
            return null;
        }
    }
    
    /**
     * Apply the status change
     */
    private function applyStatusChange(Content $content, string $action, ?\Carbon\Carbon $scheduledAt = null): void
    {
        switch ($action) {
            case 'publish':
                $content->status = 'published';
                $content->published_at = now();
                break;
                
            case 'unpublish':
                $content->status = 'draft';
                $content->published_at = null;
                break;
                
            case 'draft':
                $content->status = 'draft';
                $content->published_at = null;
                break;
                
            case 'schedule':
                $content->status = 'scheduled';
                $content->published_at = $scheduledAt;
                break;
        }
        
        $content->save();
    }
    
    /**
     * Display status change summary
     */
    private function displayStatusChangeSummary(Content $content, string $action, ?\Carbon\Carbon $scheduledAt = null): void
    {
        $this->info('Status Change Summary:');
        
        $data = [
            ['Content ID', $content->id],
            ['Title', $content->title],
            ['Current Status', $content->status],
            ['Action', ucfirst($action)],
        ];
        
        switch ($action) {
            case 'publish':
                $data[] = ['New Status', 'published'];
                $data[] = ['Published At', now()->format('Y-m-d H:i:s')];
                break;
                
            case 'unpublish':
            case 'draft':
                $data[] = ['New Status', 'draft'];
                $data[] = ['Published At', 'None (will be cleared)'];
                break;
                
            case 'schedule':
                $data[] = ['New Status', 'scheduled'];
                $data[] = ['Scheduled For', $scheduledAt->format('Y-m-d H:i:s')];
                break;
        }
        
        $this->table(['Field', 'Value'], $data);
    }
}