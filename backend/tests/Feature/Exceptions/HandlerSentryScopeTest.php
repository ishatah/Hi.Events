<?php

declare(strict_types=1);

namespace Tests\Feature\Exceptions;

use HiEvents\Exceptions\Handler;
use HiEvents\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use RuntimeException;
use Sentry\Event;
use Sentry\State\Hub;
use Sentry\State\HubInterface;
use Sentry\State\Scope;
use Tests\TestCase;

class HandlerSentryScopeTest extends TestCase
{
    use DatabaseTransactions;

    private const EMAIL = 'scope-probe@arzo.test';

    private const FIRST_NAME = 'Scope';

    private const LAST_NAME = 'Probe';

    private const CLIENT_IP = '203.0.113.7';

    public function test_the_reported_scope_identifies_the_user_by_id(): void
    {
        $user = $this->authenticateUser();

        $sentryUser = $this->reportAndReadScope()->getUser();

        $this->assertSame((string) $user->id, (string) $sentryUser?->getId());
    }

    public function test_the_reported_scope_carries_no_email_name_or_ip(): void
    {
        $this->authenticateUser();

        $sentryUser = $this->reportAndReadScope()->getUser();

        $this->assertNull(
            $sentryUser?->getEmail(),
            'Sending the organizer email to Sentry puts personal data into a third-party '
            .'processor on every exception, which send_default_pii => false implies it does not.'
        );

        $this->assertNull(
            $sentryUser?->getUsername(),
            'The username carried the organizer first and last name concatenated.'
        );

        $this->assertNull(
            $sentryUser?->getIpAddress(),
            'A request IP is personal data under GDPR and PDPL and is not needed to triage a report.'
        );
    }

    public function test_no_personal_data_survives_anywhere_in_the_scope(): void
    {
        $this->authenticateUser();

        $applied = $this->reportAndReadScope()->applyToEvent(Event::createEvent());

        $serialised = json_encode([
            'user' => $applied?->getUser()?->getMetadata(),
            'id' => $applied?->getUser()?->getId(),
            'email' => $applied?->getUser()?->getEmail(),
            'username' => $applied?->getUser()?->getUsername(),
            'ip' => $applied?->getUser()?->getIpAddress(),
            'tags' => $applied?->getTags(),
            'extra' => $applied?->getExtra(),
        ]);

        foreach ([self::EMAIL, self::FIRST_NAME, self::LAST_NAME, self::CLIENT_IP] as $personalData) {
            $this->assertStringNotContainsString($personalData, (string) $serialised);
        }
    }

    public function test_the_user_id_survives_an_unparseable_token(): void
    {
        $user = User::factory()->create([
            'email' => self::EMAIL,
            'first_name' => self::FIRST_NAME,
            'last_name' => self::LAST_NAME,
        ]);

        auth()->guard('api')->setUser($user);

        $scope = $this->reportAndReadScope();

        $this->assertSame(
            (string) $user->id,
            (string) $scope->getUser()?->getId(),
            'Reading the JWT payload threw when no token could be parsed, and the bare catch '
            .'in report() discarded the whole scope along with the user id.'
        );
    }

    private function authenticateUser(): User
    {
        $user = User::factory()->create([
            'email' => self::EMAIL,
            'first_name' => self::FIRST_NAME,
            'last_name' => self::LAST_NAME,
        ]);

        $token = auth()->guard('api')->login($user);

        request()->headers->set('Authorization', 'Bearer '.$token);
        request()->server->set('REMOTE_ADDR', self::CLIENT_IP);

        return $user;
    }

    private function reportAndReadScope(): Scope
    {
        $scope = new Scope;

        $hub = Mockery::mock(Hub::class)->makePartial();
        $hub->shouldReceive('configureScope')
            ->andReturnUsing(function (callable $callback) use ($scope): void {
                $callback($scope);
            });
        $hub->shouldReceive('captureException')->andReturnNull();

        app()->instance('sentry', $hub);
        app()->instance(HubInterface::class, $hub);

        app(Handler::class)->report(new RuntimeException('scope probe'));

        return $scope;
    }
}
