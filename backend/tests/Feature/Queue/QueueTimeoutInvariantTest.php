<?php

declare(strict_types=1);

namespace Tests\Feature\Queue;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class QueueTimeoutInvariantTest extends TestCase
{
    public function test_retry_after_exceeds_the_longest_declared_job_timeout(): void
    {
        $longestJobTimeout = $this->longestDeclaredJobTimeout();

        $this->assertGreaterThan(
            $longestJobTimeout,
            (int) config('queue.connections.redis.retry_after'),
            sprintf(
                'A job may run for %ds, so a shorter retry_after lets the queue release it '
                .'while it is still running and a second worker picks up the same job.',
                $longestJobTimeout
            )
        );
    }

    public function test_retry_after_leaves_headroom_over_the_documented_worker_timeout(): void
    {
        $this->assertGreaterThan(
            (int) config('queue.worker_timeout'),
            (int) config('queue.connections.redis.retry_after'),
            'retry_after must leave the worker time to fail a timed-out job before the '
            .'queue hands that job to another worker.'
        );
    }

    public function test_the_worker_timeout_covers_the_longest_declared_job_timeout(): void
    {
        $longestJobTimeout = $this->longestDeclaredJobTimeout();

        $this->assertGreaterThanOrEqual(
            $longestJobTimeout,
            (int) config('queue.worker_timeout'),
            sprintf(
                'A worker that kills a job after %ds can never finish the job declaring %ds.',
                (int) config('queue.worker_timeout'),
                $longestJobTimeout
            )
        );
    }

    private function longestDeclaredJobTimeout(): int
    {
        $timeouts = [];

        foreach (File::allFiles(app_path()) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            if (preg_match('/public\s+(?:int\s+)?\$timeout\s*=\s*(\d+)/', $file->getContents(), $matches)) {
                $timeouts[] = (int) $matches[1];
            }
        }

        $this->assertNotEmpty($timeouts, 'Expected at least one job to declare a $timeout.');

        return max($timeouts);
    }
}
