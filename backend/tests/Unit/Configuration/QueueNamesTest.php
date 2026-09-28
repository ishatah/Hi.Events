<?php

namespace Tests\Unit\Configuration;

use Tests\TestCase;

/**
 * A queue name that no worker consumes silently strands every job pushed to it.
 *
 * Before this test, occurrences_queue_name had no default (so it resolved to null and
 * jobs landed on `default` by accident), and webhook_queue_name defaulted to the
 * *connection* name, so webhooks were pushed to a queue literally called "sync".
 * Neither failure is visible at runtime — the jobs just never run.
 *
 * @see docs/arzo-master-plan/02-current-state-audit.md finding F13
 */
class QueueNamesTest extends TestCase
{
    private const SUPERVISOR_CONFIG = 'docker/all-in-one/supervisor/supervisord.conf';

    /**
     * The queues the deployed workers consume. Kept here because the supervisor config
     * lives outside the backend mount and so is not readable from the test container.
     *
     * Changing a --queue list in docker/all-in-one/supervisor/supervisord.conf or
     * docker/development/start-dev.sh must be mirrored here.
     */
    private const QUEUES_CONSUMED_BY_WORKERS = ['default', 'webhook-queue', 'occurrences'];

    public function test_named_queues_are_not_connection_names(): void
    {
        $connections = array_keys(config('queue.connections'));

        foreach ($this->namedQueues() as $key => $name) {
            $this->assertNotEmpty($name, "queue.{$key} must not be empty.");

            $this->assertNotContains(
                $name,
                $connections,
                "queue.{$key} is set to '{$name}', which is a queue *connection* name. "
                .'It must be a queue name that a worker consumes.'
            );
        }
    }

    public function test_every_named_queue_is_consumed_by_a_worker(): void
    {
        $consumed = self::QUEUES_CONSUMED_BY_WORKERS;

        foreach ($this->namedQueues() as $key => $name) {
            $this->assertContains(
                $name,
                $consumed,
                "queue.{$key} is '{$name}' but the production worker in "
                .self::SUPERVISOR_CONFIG.' does not consume it. Jobs pushed there '
                .'would never run. Consumed: '.implode(', ', $consumed)
            );
        }
    }

    public function test_workers_consume_the_default_queue(): void
    {
        $this->assertContains(
            'default',
            self::QUEUES_CONSUMED_BY_WORKERS,
            'The workers must consume the default queue.'
        );
    }

    public function test_supervisor_config_matches_the_declared_queue_list(): void
    {
        $path = base_path('../'.self::SUPERVISOR_CONFIG);

        if (! is_file($path)) {
            $this->markTestSkipped(
                'Supervisor config is outside the backend mount; covered by '
                .'QUEUES_CONSUMED_BY_WORKERS and asserted in CI where the full repo is present.'
            );
        }

        $contents = (string) file_get_contents($path);

        if (preg_match('/queue:work\s+--queue=([^\s]+)/', $contents, $matches) !== 1) {
            $this->fail('Could not find a queue:work --queue= directive in '.self::SUPERVISOR_CONFIG);
        }

        $actual = array_map('trim', explode(',', $matches[1]));

        $this->assertSame(
            self::QUEUES_CONSUMED_BY_WORKERS,
            $actual,
            'The supervisor --queue list drifted from QUEUES_CONSUMED_BY_WORKERS. '
            .'Update whichever is wrong.'
        );
    }

    /**
     * @return array<string, string>
     */
    private function namedQueues(): array
    {
        return [
            'webhook_queue_name' => (string) config('queue.webhook_queue_name'),
            'occurrences_queue_name' => (string) config('queue.occurrences_queue_name'),
        ];
    }
}
