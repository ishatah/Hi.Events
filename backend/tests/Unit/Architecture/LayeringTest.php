<?php

namespace Tests\Unit\Architecture;

use Tests\TestCase;

/**
 * Enforces the layering described in CLAUDE.md:
 *
 *   Action -> Handler -> Domain Service -> Repository
 *
 * Eloquent belongs in repositories only. These tests do not fix the existing
 * violations; they pin them so the count cannot grow, and so each one can be
 * removed deliberately.
 *
 * @see docs/arzo-master-plan/02-current-state-audit.md finding F10 and section 1
 */
class LayeringTest extends TestCase
{
    /**
     * Files that imported an Eloquent model above the repository layer when this
     * test was written. Shrink this list; never add to it.
     */
    private const ELOQUENT_ABOVE_REPOSITORY_BASELINE = [
        'Services/Application/Handlers/Admin/DeleteFailedJobHandler.php',
        'Services/Application/Handlers/Admin/GetAllFailedJobsHandler.php',
        'Services/Application/Handlers/Admin/GetAllMessagesForAdminHandler.php',
        'Services/Application/Handlers/Admin/RetryFailedJobHandler.php',
        'Services/Application/Handlers/Admin/StartImpersonationHandler.php',
        'Services/Application/Handlers/Admin/StopImpersonationHandler.php',
        'Services/Application/Handlers/Announcement/GetAllAnnouncementsHandler.php',
        'Services/Application/Handlers/CapacityAssignment/DeleteCapacityAssignmentHandler.php',
        'Services/Domain/Account/AccountHardDeletionService.php',
        'Services/Domain/Account/Anonymization/Anonymizers/UserAnonymizer.php',
        'Services/Domain/Auth/AuthUserService.php',
    ];

    public function test_http_actions_do_not_import_eloquent_models(): void
    {
        $offenders = $this->filesImportingEloquentModels('Http/Actions');

        $this->assertSame(
            [],
            $offenders,
            "HTTP actions must not import Eloquent models. Move the query into a repository.\n"
            .implode("\n", $offenders)
        );
    }

    public function test_http_actions_do_not_use_the_db_facade(): void
    {
        $offenders = [];

        foreach ($this->phpFilesIn('Http/Actions') as $relativePath => $contents) {
            if (preg_match('/\buse Illuminate\\\\Support\\\\Facades\\\\DB;/', $contents) === 1) {
                $offenders[] = $relativePath;
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "HTTP actions must not use the DB facade.\n".implode("\n", $offenders)
        );
    }

    public function test_eloquent_usage_above_the_repository_layer_does_not_grow(): void
    {
        $offenders = array_merge(
            $this->filesImportingEloquentModels('Services/Application/Handlers'),
            $this->filesImportingEloquentModels('Services/Domain'),
        );

        sort($offenders);

        $baseline = self::ELOQUENT_ABOVE_REPOSITORY_BASELINE;
        sort($baseline);

        $new = array_values(array_diff($offenders, $baseline));

        $this->assertSame(
            [],
            $new,
            "New Eloquent usage above the repository layer. Move the query into a repository.\n"
            .implode("\n", $new)
        );

        $fixed = array_values(array_diff($baseline, $offenders));

        $this->assertSame(
            [],
            $fixed,
            'These files no longer import Eloquent models. Remove them from '
            ."ELOQUENT_ABOVE_REPOSITORY_BASELINE so the baseline keeps shrinking.\n"
            .implode("\n", $fixed)
        );
    }

    /**
     * @return array<int, string>
     */
    private function filesImportingEloquentModels(string $directory): array
    {
        $offenders = [];

        foreach ($this->phpFilesIn($directory) as $relativePath => $contents) {
            if (preg_match('/\buse HiEvents\\\\Models\\\\/', $contents) === 1) {
                $offenders[] = $relativePath;
            }
        }

        return $offenders;
    }

    /**
     * @return array<string, string> relative path => file contents
     */
    private function phpFilesIn(string $directory): array
    {
        $root = base_path('app');
        $path = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $directory);

        if (! is_dir($path)) {
            $this->fail("Expected directory to exist: {$path}");
        }

        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = str_replace(
                [$root.DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR],
                ['', '/'],
                $file->getPathname()
            );

            $files[$relativePath] = (string) file_get_contents($file->getPathname());
        }

        return $files;
    }
}
