<?php

namespace Tests\Unit\DomainObjects\Enums;

use HiEvents\DomainObjects\Enums\AccessLogSource;
use Tests\TestCase;

class AccessLogSourceTest extends TestCase
{
    public function test_a_synced_record_was_decided_offline(): void
    {
        $this->assertTrue(AccessLogSource::OFFLINE_SYNC->isOfflineReplay());
    }

    public function test_a_live_scan_was_decided_by_the_server(): void
    {
        $this->assertFalse(
            AccessLogSource::SCAN->isOfflineReplay(),
            'A live scan was decided by the server in the first place, so there is nothing to '
            .'reconcile against it.'
        );
    }

    public function test_an_unknown_source_is_not_treated_as_offline(): void
    {
        $this->assertNull(
            AccessLogSource::tryFrom('SOMETHING_ELSE'),
            'Reconciliation reads this flag, so an unrecognised source must not silently '
            .'claim the device made the decision.'
        );
    }

    public function test_every_source_declares_whether_it_was_offline(): void
    {
        foreach (AccessLogSource::cases() as $source) {
            $this->assertIsBool(
                $source->isOfflineReplay(),
                sprintf(
                    '%s must state whether it was decided offline; reconciliation has no way '
                    .'to guess.',
                    $source->value
                )
            );
        }
    }
}
