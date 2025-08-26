<?php

namespace ArtisanPackUI\CMSFramework\Console\Commands;

use ArtisanPackUI\CMSFramework\Models\Content;
use ArtisanPackUI\CMSFramework\Models\User;
use Illuminate\Console\Command;
use Carbon\Carbon;

/**
 * Content cleanup command
 * 
 * This command cleans up old drafts, expired content, and orphaned content records.
 * It provides various cleanup options with confirmation prompts.
 */
class ContentCleanupCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'cms:content:cleanup 
                           {--type=all : Cleanup type (drafts, expired, orphaned, all)}
                           {--days=30 : Number of days to keep old drafts}
                           {--dry-run : Show what would be cleaned without actually deleting}
                           {--force : Skip confirmation prompts}';

    /**
     * The console command description.
     */
    protected $description = 'Clean up old drafts, expired content, and orphaned records';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $type = $this->option('type');
            $dryRun = $this->option('dry-run');
            
            $this->info('Starting content cleanup...');
            if ($dryRun) {
                $this->warn('DRY RUN MODE - No content will actually be deleted');
            }
            
            $totalCleaned = 0;
            
            // Perform cleanup based on type
            switch ($type) {
                case 'drafts':
                    $totalCleaned += $this->cleanupOldDrafts($dryRun);
                    break;
                    
                case 'expired':
                    $totalCleaned += $this->cleanupExpiredContent($dryRun);
                    break;
                    
                case 'orphaned':
                    $totalCleaned += $this->cleanupOrphanedContent($dryRun);
                    break;
                    
                case 'all':
                    $totalCleaned += $this->cleanupOldDrafts($dryRun);
                    $totalCleaned += $this->cleanupExpiredContent($dryRun);
                    $totalCleaned += $this->cleanupOrphanedContent($dryRun);
                    break;
                    
                default:
                    $this->error("Invalid cleanup type '{$type}'.");
                    $this->info('Valid types: drafts, expired, orphaned, all');
                    return Command::FAILURE;
            }
            
            $this->info("✅ Content cleanup completed!");
            $this->info("   Total items " . ($dryRun ? 'found' : 'cleaned') . ": {$totalCleaned}");
            
            if ($dryRun && $totalCleaned > 0) {
                $this->info('Run without --dry-run to actually perform the cleanup.');
            }
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('Error during content cleanup: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
    
    /**
     * Clean up old draft content
     */
    private function cleanupOldDrafts(bool $dryRun = false): int
    {
        $days = (int) $this->option('days');
        $cutoffDate = Carbon::now()->subDays($days);
        
        $this->info("Looking for drafts older than {$days} days (before {$cutoffDate->format('Y-m-d')})...");
        
        $query = Content::where('status', 'draft')
            ->where('updated_at', '<', $cutoffDate);
        
        $count = $query->count();
        
        if ($count === 0) {
            $this->info('No old drafts found.');
            return 0;
        }
        
        $drafts = $query->get();
        
        // Show what will be cleaned
        $this->info("Found {$count} old draft(s):");
        $this->table(
            ['ID', 'Title', 'Author', 'Last Updated'],
            $drafts->map(function ($draft) {
                return [
                    $draft->id,
                    \Illuminate\Support\Str::limit($draft->title, 40),
                    $draft->author ? $draft->author->username : 'Unknown',
                    $draft->updated_at->format('Y-m-d H:i'),
                ];
            })->toArray()
        );
        
        if ($dryRun) {
            return $count;
        }
        
        // Confirm deletion
        if (!$this->option('force')) {
            if (!$this->confirm("Delete these {$count} old draft(s)?", false)) {
                $this->info('Old drafts cleanup cancelled.');
                return 0;
            }
        }
        
        // Delete the drafts
        $deleted = $query->delete();
        $this->info("Deleted {$deleted} old draft(s).");
        
        return $deleted;
    }
    
    /**
     * Clean up expired scheduled content
     */
    private function cleanupExpiredContent(bool $dryRun = false): int
    {
        $this->info('Looking for expired scheduled content...');
        
        $query = Content::where('status', 'scheduled')
            ->where('published_at', '<', Carbon::now()->subDays(7)); // 7 days past scheduled time
        
        $count = $query->count();
        
        if ($count === 0) {
            $this->info('No expired scheduled content found.');
            return 0;
        }
        
        $expired = $query->get();
        
        // Show what will be cleaned
        $this->info("Found {$count} expired scheduled content(s):");
        $this->table(
            ['ID', 'Title', 'Scheduled For', 'Status'],
            $expired->map(function ($content) {
                return [
                    $content->id,
                    \Illuminate\Support\Str::limit($content->title, 40),
                    $content->published_at ? $content->published_at->format('Y-m-d H:i') : 'N/A',
                    $content->status,
                ];
            })->toArray()
        );
        
        if ($dryRun) {
            return $count;
        }
        
        // Confirm action - convert to draft instead of deleting
        if (!$this->option('force')) {
            if (!$this->confirm("Convert these {$count} expired scheduled content(s) to drafts?", true)) {
                $this->info('Expired content cleanup cancelled.');
                return 0;
            }
        }
        
        // Convert to drafts instead of deleting
        $updated = $query->update([
            'status' => 'draft',
            'published_at' => null,
        ]);
        
        $this->info("Converted {$updated} expired scheduled content(s) to drafts.");
        
        return $updated;
    }
    
    /**
     * Clean up orphaned content (content without valid authors)
     */
    private function cleanupOrphanedContent(bool $dryRun = false): int
    {
        $this->info('Looking for orphaned content (content with missing authors)...');
        
        // Find content where author_id doesn't exist in users table
        $orphanedContent = Content::whereNotNull('author_id')
            ->whereNotIn('author_id', User::pluck('id'))
            ->get();
        
        $count = $orphanedContent->count();
        
        if ($count === 0) {
            $this->info('No orphaned content found.');
            return 0;
        }
        
        // Show what will be cleaned
        $this->info("Found {$count} orphaned content item(s):");
        $this->table(
            ['ID', 'Title', 'Missing Author ID', 'Status', 'Created'],
            $orphanedContent->map(function ($content) {
                return [
                    $content->id,
                    \Illuminate\Support\Str::limit($content->title, 40),
                    $content->author_id,
                    $content->status,
                    $content->created_at->format('Y-m-d'),
                ];
            })->toArray()
        );
        
        if ($dryRun) {
            return $count;
        }
        
        // Ask what to do with orphaned content
        $action = $this->choice(
            "What should we do with these {$count} orphaned content item(s)?",
            [
                'assign' => 'Assign to an existing user',
                'delete' => 'Delete the content',
                'skip' => 'Skip (do nothing)',
            ],
            'assign'
        );
        
        if ($action === 'skip') {
            $this->info('Orphaned content cleanup skipped.');
            return 0;
        }
        
        if ($action === 'assign') {
            // Get a user to assign to
            $users = User::select('id', 'username', 'email')->get();
            
            if ($users->isEmpty()) {
                $this->error('No users found to assign orphaned content to.');
                return 0;
            }
            
            $this->info('Available users:');
            $this->table(
                ['ID', 'Username', 'Email'],
                $users->map(function ($user) {
                    return [$user->id, $user->username, $user->email];
                })->toArray()
            );
            
            $userId = $this->ask('Enter user ID to assign orphaned content to');
            $assignUser = $users->where('id', $userId)->first();
            
            if (!$assignUser) {
                $this->error('Invalid user ID.');
                return 0;
            }
            
            // Assign orphaned content to the selected user
            $updated = Content::whereIn('id', $orphanedContent->pluck('id'))
                ->update(['author_id' => $assignUser->id]);
            
            $this->info("Assigned {$updated} orphaned content item(s) to {$assignUser->username}.");
            return $updated;
        }
        
        if ($action === 'delete') {
            if (!$this->option('force')) {
                if (!$this->confirm("Are you sure you want to delete these {$count} orphaned content item(s)? This cannot be undone.", false)) {
                    $this->info('Orphaned content deletion cancelled.');
                    return 0;
                }
            }
            
            $deleted = Content::whereIn('id', $orphanedContent->pluck('id'))->delete();
            $this->info("Deleted {$deleted} orphaned content item(s).");
            return $deleted;
        }
        
        return 0;
    }
}