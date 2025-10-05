<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DocumentProcessing;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Команда для исправления формата поля result в DocumentProcessing.
 *
 * До исправления result мог быть просто строкой, сохраненной как JSON.
 * После исправления result должен быть массивом ['content' => $markdown].
 */
class FixDocumentResults extends Command
{
    protected $signature = 'documents:fix-results
                            {--dry-run : Only show what would be fixed without making changes}
                            {--force : Force fixing even if result looks correct}';

    protected $description = 'Fix DocumentProcessing result field format to use content key';

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $isForce = $this->option('force');

        $this->info('Checking DocumentProcessing results...');
        $this->newLine();

        $documents = DocumentProcessing::where('status', 'completed')
            ->whereNotNull('result')
            ->get();

        if ($documents->isEmpty()) {
            $this->info('No completed documents found.');

            return self::SUCCESS;
        }

        $this->info("Found {$documents->count()} completed documents");
        $this->newLine();

        $needFix = [];
        $alreadyCorrect = 0;
        $errors = [];

        foreach ($documents as $doc) {
            // result is always array due to cast in model
            $result = $doc->result; // array<string, mixed>|null

            // Check if result is in correct format
            if (is_array($result) && isset($result['content']) && is_string($result['content'])) {
                if (!$isForce) {
                    ++$alreadyCorrect;

                    continue;
                }
            }

            $needFix[] = $doc;
        }

        $this->table(
            ['Status', 'Count'],
            [
                ['Already correct', $alreadyCorrect],
                ['Need fixing', count($needFix)],
            ]
        );

        if (empty($needFix)) {
            $this->info('✅ All documents are in correct format!');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->warn('Documents that need fixing:');

        foreach ($needFix as $doc) {
            $result = $doc->result; // array<string, mixed>|null due to cast
            $type = is_array($result) ? 'ARRAY' : gettype($result);
            $hasContent = is_array($result) && isset($result['content']);

            $this->line("  - Document #{$doc->id} ({$doc->original_filename}): type={$type}, has_content={$hasContent}");
        }

        if ($isDryRun) {
            $this->newLine();
            $this->info('🔍 Dry run mode - no changes made');

            return self::SUCCESS;
        }

        $this->newLine();

        if (!$this->confirm('Do you want to fix these documents?', true)) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('Fixing documents...');

        $fixed = 0;
        $failed = 0;

        foreach ($needFix as $doc) {
            try {
                DB::transaction(function () use ($doc): void {
                    // Read raw value from database to handle string results
                    $rawResult = DB::table('document_processings')
                        ->where('id', $doc->id)
                        ->value('result');

                    // If result is null or empty, set default empty content
                    if ($rawResult === null || $rawResult === '') {
                        $doc->result = ['content' => ''];
                        $doc->save();

                        return;
                    }

                    // If it's a string (old format), wrap it in content key
                    if (is_string($rawResult)) {
                        // Try to decode as JSON first
                        $decoded = json_decode($rawResult, true);

                        if (is_array($decoded)) {
                            // It's JSON - check if it has content key
                            if (isset($decoded['content']) && is_string($decoded['content'])) {
                                // Already correct format
                                return;
                            }

                            // JSON without content key - try to extract content
                            $content = $decoded['translated_content'] ?? $decoded['text'] ?? '';

                            if (empty($content) && isset($decoded['sections'])) {
                                // Old format with sections - extract markdown if possible
                                $content = $decoded['content'] ?? 'Document processed but content unavailable';
                            }

                            $doc->result = ['content' => $content];
                        } else {
                            // Plain string (markdown) - wrap it
                            $doc->result = ['content' => $rawResult];
                        }

                        $doc->save();

                        return;
                    }

                    // If it's already array (from cast), check structure
                    $result = $doc->result;

                    if (is_array($result) && !isset($result['content'])) {
                        // Array but missing content key
                        $content = $result['translated_content'] ?? $result['text'] ?? '';

                        if (empty($content) && isset($result['sections'])) {
                            $content = 'Document processed but content unavailable';
                        }

                        $doc->result = ['content' => $content];
                        $doc->save();
                    }
                });

                ++$fixed;
                $this->info("  ✅ Fixed document #{$doc->id}");
            } catch (\Exception $e) {
                ++$failed;
                $errors[] = "Document #{$doc->id}: {$e->getMessage()}";
                $this->error("  ❌ Failed to fix document #{$doc->id}: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->table(
            ['Status', 'Count'],
            [
                ['Fixed', $fixed],
                ['Failed', $failed],
                ['Errors', count($errors)],
            ]
        );

        if (!empty($errors)) {
            $this->newLine();
            $this->error('Errors occurred:');

            foreach ($errors as $error) {
                $this->line("  - {$error}");
            }
        }

        $this->newLine();
        $this->info('✅ Done!');

        return self::SUCCESS;
    }
}
