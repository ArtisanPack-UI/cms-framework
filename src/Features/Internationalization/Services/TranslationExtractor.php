<?php

namespace ArtisanPackUI\CMSFramework\Features\Internationalization\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Exception;

/**
 * Translation Extractor Service
 *
 * Extracts translatable strings from PHP files, Blade templates, and JavaScript files
 * for the ArtisanPack UI CMS Framework internationalization system.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package    ArtisanPackUI\CMSFramework\Features\Internationalization\Services
 * @since      1.5.0
 */
class TranslationExtractor
{
    /**
     * Patterns for extracting translation keys from different file types.
     *
     * @since 1.5.0
     *
     * @var array
     */
    protected array $patterns = [
        'php' => [
            // Laravel translation functions
            '/__\s*\(\s*[\'"]([^\'"]+)[\'"]\s*[\),]/',
            '/trans\s*\(\s*[\'"]([^\'"]+)[\'"]\s*[\),]/',
            '/trans_choice\s*\(\s*[\'"]([^\'"]+)[\'"]\s*[\),]/',
            '/@lang\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/',
            
            // Custom CMS translation functions
            '/cms_trans\s*\(\s*[\'"]([^\'"]+)[\'"]\s*[\),]/',
            '/cms__\s*\(\s*[\'"]([^\'"]+)[\'"]\s*[\),]/',
        ],
        'blade' => [
            // Blade translation directives
            '/@lang\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/',
            '/\{\{\s*__\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)\s*\}\}/',
            '/\{\{\s*trans\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)\s*\}\}/',
            '/\{\!\!\s*__\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)\s*!!\}/',
            
            // Custom Blade directives
            '/@cms_trans\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/',
        ],
        'javascript' => [
            // Common JavaScript translation patterns
            '/trans\s*\(\s*[\'"]([^\'"]+)[\'"]\s*[\),]/',
            '/__\s*\(\s*[\'"]([^\'"]+)[\'"]\s*[\),]/',
            '/\$t\s*\(\s*[\'"]([^\'"]+)[\'"]\s*[\),]/',
            '/i18n\s*\.\s*t\s*\(\s*[\'"]([^\'"]+)[\'"]\s*[\),]/',
        ],
        'vue' => [
            // Vue.js translation patterns
            '/\$t\s*\(\s*[\'"]([^\'"]+)[\'"]\s*[\),]/',
            '/v-t\s*=\s*[\'"]([^\'"]+)[\'"]/',
            '/<i18n[^>]*>\s*([^<]+)\s*<\/i18n>/',
        ],
    ];

    /**
     * File extensions to scan for each file type.
     *
     * @since 1.5.0
     *
     * @var array
     */
    protected array $fileExtensions = [
        'php' => ['php'],
        'blade' => ['blade.php'],
        'javascript' => ['js', 'ts', 'jsx', 'tsx'],
        'vue' => ['vue'],
    ];

    /**
     * Directories to exclude from scanning.
     *
     * @since 1.5.0
     *
     * @var array
     */
    protected array $excludeDirectories = [
        'vendor',
        'node_modules',
        'storage',
        'bootstrap/cache',
        '.git',
        'public/build',
        'dist',
    ];

    /**
     * Extract translation keys from specified paths.
     *
     * @since 1.5.0
     *
     * @param array $paths Paths to scan
     * @param array $options Extraction options
     * @return Collection Extracted translation keys with metadata
     */
    public function extract(array $paths, array $options = []): Collection
    {
        $results = collect();
        
        foreach ($paths as $path) {
            if (is_file($path)) {
                $results = $results->merge($this->extractFromFile($path, $options));
            } elseif (is_dir($path)) {
                $results = $results->merge($this->extractFromDirectory($path, $options));
            }
        }

        return $this->processResults($results, $options);
    }

    /**
     * Extract translation keys from a single file.
     *
     * @since 1.5.0
     *
     * @param string $filePath Path to the file
     * @param array $options Extraction options
     * @return Collection Extracted keys from the file
     */
    public function extractFromFile(string $filePath, array $options = []): Collection
    {
        if (!File::exists($filePath)) {
            return collect();
        }

        $content = File::get($filePath);
        $fileType = $this->determineFileType($filePath);
        $patterns = $this->patterns[$fileType] ?? [];

        $results = collect();

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[1] as $match) {
                    $key = $match[0];
                    $position = $match[1];
                    $lineNumber = substr_count(substr($content, 0, $position), "\n") + 1;

                    $results->push([
                        'key' => $key,
                        'file' => $filePath,
                        'line' => $lineNumber,
                        'type' => $fileType,
                        'pattern' => $pattern,
                        'context' => $this->extractContext($content, $position, 50),
                    ]);
                }
            }
        }

        return $results;
    }

    /**
     * Extract translation keys from all files in a directory.
     *
     * @since 1.5.0
     *
     * @param string $directoryPath Path to the directory
     * @param array $options Extraction options
     * @return Collection Extracted keys from all files in directory
     */
    public function extractFromDirectory(string $directoryPath, array $options = []): Collection
    {
        $results = collect();
        $recursive = $options['recursive'] ?? true;

        $files = $recursive 
            ? File::allFiles($directoryPath)
            : File::files($directoryPath);

        foreach ($files as $file) {
            $filePath = $file->getPathname();
            
            if ($this->shouldSkipFile($filePath)) {
                continue;
            }

            $results = $results->merge($this->extractFromFile($filePath, $options));
        }

        return $results;
    }

    /**
     * Extract translation keys from specific content.
     *
     * @since 1.5.0
     *
     * @param string $content Content to analyze
     * @param string $fileType Type of file content
     * @return Collection Extracted keys from content
     */
    public function extractFromContent(string $content, string $fileType = 'php'): Collection
    {
        $patterns = $this->patterns[$fileType] ?? [];
        $results = collect();

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[1] as $match) {
                    $key = $match[0];
                    $position = $match[1];
                    $lineNumber = substr_count(substr($content, 0, $position), "\n") + 1;

                    $results->push([
                        'key' => $key,
                        'file' => null,
                        'line' => $lineNumber,
                        'type' => $fileType,
                        'pattern' => $pattern,
                        'context' => $this->extractContext($content, $position, 50),
                    ]);
                }
            }
        }

        return $results;
    }

    /**
     * Group extracted keys by namespace and key structure.
     *
     * @since 1.5.0
     *
     * @param Collection $keys Extracted translation keys
     * @return Collection Grouped keys
     */
    public function groupKeys(Collection $keys): Collection
    {
        return $keys->groupBy(function ($item) {
            $key = $item['key'];
            
            if (Str::contains($key, '.')) {
                $parts = explode('.', $key);
                return $parts[0]; // Group by first part (namespace/file)
            }
            
            return 'default';
        })->map(function ($group) {
            return $group->keyBy('key');
        });
    }

    /**
     * Generate statistics about extracted keys.
     *
     * @since 1.5.0
     *
     * @param Collection $keys Extracted translation keys
     * @return array Statistics
     */
    public function generateStats(Collection $keys): array
    {
        $grouped = $this->groupKeys($keys);
        
        return [
            'total_keys' => $keys->count(),
            'unique_keys' => $keys->unique('key')->count(),
            'namespaces' => $grouped->count(),
            'files_scanned' => $keys->unique('file')->count(),
            'file_types' => $keys->groupBy('type')->map->count()->toArray(),
            'namespace_breakdown' => $grouped->map->count()->toArray(),
            'most_used_keys' => $keys->groupBy('key')
                ->map->count()
                ->sortDesc()
                ->take(10)
                ->toArray(),
        ];
    }

    /**
     * Export extracted keys to various formats.
     *
     * @since 1.5.0
     *
     * @param Collection $keys Extracted translation keys
     * @param string $format Export format (json, php, csv, pot)
     * @param array $options Export options
     * @return string Exported content
     */
    public function exportKeys(Collection $keys, string $format = 'json', array $options = []): string
    {
        $grouped = $this->groupKeys($keys);

        return match ($format) {
            'json' => json_encode($grouped->toArray(), JSON_PRETTY_PRINT),
            'php' => $this->exportToPHP($grouped, $options),
            'csv' => $this->exportToCSV($keys, $options),
            'pot' => $this->exportToPOT($keys, $options),
            default => throw new Exception("Unsupported export format: {$format}"),
        };
    }

    /**
     * Determine the file type based on file extension.
     *
     * @since 1.5.0
     *
     * @param string $filePath Path to the file
     * @return string File type
     */
    protected function determineFileType(string $filePath): string
    {
        $extension = File::extension($filePath);
        
        // Handle compound extensions like .blade.php
        if (Str::endsWith($filePath, '.blade.php')) {
            return 'blade';
        }

        foreach ($this->fileExtensions as $type => $extensions) {
            if (in_array($extension, $extensions)) {
                return $type;
            }
        }

        return 'php'; // Default fallback
    }

    /**
     * Check if a file should be skipped during extraction.
     *
     * @since 1.5.0
     *
     * @param string $filePath Path to the file
     * @return bool Whether to skip the file
     */
    protected function shouldSkipFile(string $filePath): bool
    {
        // Skip files in excluded directories
        foreach ($this->excludeDirectories as $excludeDir) {
            if (Str::contains($filePath, DIRECTORY_SEPARATOR . $excludeDir . DIRECTORY_SEPARATOR)) {
                return true;
            }
        }

        // Skip non-text files
        $supportedExtensions = collect($this->fileExtensions)->flatten()->toArray();
        $extension = File::extension($filePath);
        
        if (Str::endsWith($filePath, '.blade.php')) {
            return false;
        }

        return !in_array($extension, $supportedExtensions);
    }

    /**
     * Extract context around a translation key.
     *
     * @since 1.5.0
     *
     * @param string $content File content
     * @param int $position Position of the translation key
     * @param int $length Context length
     * @return string Context around the key
     */
    protected function extractContext(string $content, int $position, int $length = 50): string
    {
        $start = max(0, $position - $length);
        $end = min(strlen($content), $position + $length);
        
        return substr($content, $start, $end - $start);
    }

    /**
     * Process and clean up extraction results.
     *
     * @since 1.5.0
     *
     * @param Collection $results Raw extraction results
     * @param array $options Processing options
     * @return Collection Processed results
     */
    protected function processResults(Collection $results, array $options): Collection
    {
        // Remove duplicates if requested
        if ($options['unique'] ?? true) {
            $results = $results->unique('key');
        }

        // Sort results
        $sortBy = $options['sort'] ?? 'key';
        $results = $results->sortBy($sortBy);

        // Filter by namespace if specified
        if (isset($options['namespace'])) {
            $namespace = $options['namespace'];
            $results = $results->filter(function ($item) use ($namespace) {
                return Str::startsWith($item['key'], $namespace . '.');
            });
        }

        return $results;
    }

    /**
     * Export keys to PHP format.
     *
     * @since 1.5.0
     *
     * @param Collection $grouped Grouped translation keys
     * @param array $options Export options
     * @return string PHP format content
     */
    protected function exportToPHP(Collection $grouped, array $options): string
    {
        $output = "<?php\n\nreturn [\n";

        foreach ($grouped as $namespace => $keys) {
            $output .= "\n    // {$namespace}\n";
            foreach ($keys as $key => $data) {
                $cleanKey = str_replace($namespace . '.', '', $key);
                $output .= "    '{$cleanKey}' => '',\n";
            }
        }

        $output .= "];\n";
        
        return $output;
    }

    /**
     * Export keys to CSV format.
     *
     * @since 1.5.0
     *
     * @param Collection $keys Translation keys
     * @param array $options Export options
     * @return string CSV format content
     */
    protected function exportToCSV(Collection $keys, array $options): string
    {
        $output = "Key,File,Line,Type,Context\n";

        foreach ($keys as $item) {
            $context = str_replace(["\n", "\r", '"'], [' ', ' ', '""'], $item['context']);
            $output .= sprintf(
                '"%s","%s","%d","%s","%s"' . "\n",
                $item['key'],
                $item['file'] ?? '',
                $item['line'],
                $item['type'],
                $context
            );
        }

        return $output;
    }

    /**
     * Export keys to POT (Portable Object Template) format.
     *
     * @since 1.5.0
     *
     * @param Collection $keys Translation keys
     * @param array $options Export options
     * @return string POT format content
     */
    protected function exportToPOT(Collection $keys, array $options): string
    {
        $output = "# Translation template for ArtisanPack CMS\n";
        $output .= "# Generated on " . now()->toDateTimeString() . "\n\n";
        $output .= "msgid \"\"\n";
        $output .= "msgstr \"\"\n";
        $output .= "\"Content-Type: text/plain; charset=UTF-8\\n\"\n\n";

        foreach ($keys as $item) {
            if ($item['file']) {
                $output .= "#: {$item['file']}:{$item['line']}\n";
            }
            $output .= "msgid \"{$item['key']}\"\n";
            $output .= "msgstr \"\"\n\n";
        }

        return $output;
    }

    /**
     * Add custom pattern for extraction.
     *
     * @since 1.5.0
     *
     * @param string $fileType File type (php, blade, javascript, vue)
     * @param string $pattern Regular expression pattern
     * @return void
     */
    public function addPattern(string $fileType, string $pattern): void
    {
        if (!isset($this->patterns[$fileType])) {
            $this->patterns[$fileType] = [];
        }

        $this->patterns[$fileType][] = $pattern;
    }

    /**
     * Get all available patterns for a file type.
     *
     * @since 1.5.0
     *
     * @param string $fileType File type
     * @return array Patterns for the file type
     */
    public function getPatterns(string $fileType): array
    {
        return $this->patterns[$fileType] ?? [];
    }
}