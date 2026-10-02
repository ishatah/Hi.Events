<?php

declare(strict_types=1);

namespace Tests\Feature\Config;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class RegistrationDefaultTest extends TestCase
{
    public function test_registration_is_closed_when_no_environment_value_is_set(): void
    {
        $appConfig = File::get(config_path('app.php'));

        preg_match(
            "/'disable_registration'\s*=>\s*env\(\s*'APP_DISABLE_REGISTRATION'\s*,\s*(true|false)\s*\)/",
            $appConfig,
            $matches
        );

        $this->assertSame(
            'true',
            $matches[1] ?? null,
            'With this defaulting to false, an installation that never set the variable let any '
            .'stranger register, be auto-verified, and publish an event whose card payments land '
            .'in the installation own Stripe account.'
        );
    }

    public function test_the_handler_refuses_to_create_an_account_when_registration_is_disabled(): void
    {
        config(['app.disable_registration' => true]);

        $response = $this->postJson('/auth/register', [
            'first_name' => 'Stranger',
            'last_name' => 'Signup',
            'email' => 'stranger-'.uniqid().'@example.test',
            'password' => 'CorrectHorse1!',
            'password_confirmation' => 'CorrectHorse1!',
            'timezone' => 'UTC',
        ]);

        $this->assertSame(
            403,
            $response->status(),
            'The gate must refuse over HTTP, not only inside the handler.'
        );
    }
}
