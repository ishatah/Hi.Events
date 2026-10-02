<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Domain\Auth;

use HiEvents\Models\User;
use HiEvents\Services\Infrastructure\TokenGenerator\EmailedTokenHasher;
use HiEvents\Services\Infrastructure\TokenGenerator\TokenGeneratorService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class EmailedTokenStorageTest extends TestCase
{
    use DatabaseTransactions;

    public function test_a_password_reset_token_is_not_stored_in_plaintext(): void
    {
        Mail::fake();

        $email = 'reset-'.Str::lower(Str::random(10)).'@example.test';
        User::factory()->withAccount()->create(['email' => $email]);

        $this->postJson('/auth/forgot-password', ['email' => $email])->assertSuccessful();

        $stored = DB::table('password_reset_tokens')->where('email', $email)->value('token');

        $this->assertNotNull($stored, 'Expected the reset request to store a token row.');

        $this->assertSame(
            64,
            strlen((string) $stored),
            'A stored reset token must be a sha256 hash, not the token that was emailed: '
            .'anyone able to read the table, a backup or a query log could otherwise take '
            .'over any account that had requested a reset.'
        );

        $this->assertStringNotContainsString('rp', (string) $stored);
    }

    public function test_a_token_that_was_emailed_still_validates_against_the_stored_hash(): void
    {
        $email = 'reset-valid-'.Str::lower(Str::random(10)).'@example.test';
        User::factory()->withAccount()->create(['email' => $email]);

        $emailedToken = app(TokenGeneratorService::class)->generateToken(prefix: 'rp');

        DB::table('password_reset_tokens')->insert([
            'email' => $email,
            'token' => app(EmailedTokenHasher::class)->hash($emailedToken),
            'created_at' => now(),
        ]);

        $this->getJson('/auth/reset-password/'.$emailedToken)->assertSuccessful();
    }

    public function test_the_stored_hash_cannot_be_replayed_as_a_token(): void
    {
        $email = 'reset-replay-'.Str::lower(Str::random(10)).'@example.test';
        User::factory()->withAccount()->create(['email' => $email]);

        $emailedToken = app(TokenGeneratorService::class)->generateToken(prefix: 'rp');
        $storedHash = app(EmailedTokenHasher::class)->hash($emailedToken);

        DB::table('password_reset_tokens')->insert([
            'email' => $email,
            'token' => $storedHash,
            'created_at' => now(),
        ]);

        $response = $this->getJson('/auth/reset-password/'.$storedHash);

        $this->assertNotSame(
            200,
            $response->status(),
            'The stored value must not itself work as a token, or hashing buys nothing.'
        );
    }
}
