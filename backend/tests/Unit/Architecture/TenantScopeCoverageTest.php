<?php

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * Every model that owns an account_id must carry the tenant scope, or be listed here with a
 * reason.
 *
 * This exists because the trait can be half-applied: the import survives while the `use`
 * statement inside the class body is lost, which leaves the model silently unscoped and the
 * file still mentioning BelongsToTenant. Grepping for the name is not enough, so this
 * asserts on the class body.
 *
 * @see docs/arzo-master-plan/08-multi-tenancy.md
 */
class TenantScopeCoverageTest extends TestCase
{
    /**
     * Models with an account_id that deliberately stay unscoped.
     *
     * @var array<string, string>
     */
    private const INTENTIONALLY_UNSCOPED = [
        'Account' => 'The tenant root itself, identified by id rather than account_id.',
        'AccountUser' => 'Read while resolving authorization; scoping it would make the scope depend on itself.',
        'PermissionRole' => 'System roles carry a null account_id and must stay visible to every tenant.',
        'AccountConfiguration' => 'Platform-level configuration, not tenant data.',
        'AccountAttribution' => 'Written at signup before a tenant context exists.',
        'AccountDeletionRequest' => 'Processed by a console command that runs with no tenant.',
        'AccountStripePlatform' => 'Read by Stripe webhooks, which arrive with no tenant context.',
        'AccountVatSetting' => 'Read by Stripe webhooks, which arrive with no tenant context.',
        'AccountMessagingTier' => 'Platform-level tier definitions shared across tenants.',
        'Location' => 'Reached by resource id from public storefront routes.',
        'Image' => 'Reached by resource id from public storefront routes.',
    ];

    public function test_every_account_owned_model_is_scoped_or_declared(): void
    {
        $unscoped = [];

        foreach ($this->modelsWithAccountId() as $class => $path) {
            $contents = (string) file_get_contents($path);

            if (preg_match('/^\s+use BelongsToTenant;$/m', $contents) === 1) {
                continue;
            }

            if (array_key_exists($class, self::INTENTIONALLY_UNSCOPED)) {
                continue;
            }

            $unscoped[] = $class;
        }

        $this->assertSame(
            [],
            $unscoped,
            "These models own an account_id but do not apply the tenant scope:\n  "
            .implode("\n  ", $unscoped)
            ."\nAdd `use BelongsToTenant;` inside the class body, or declare the model in "
            .'INTENTIONALLY_UNSCOPED with a reason.'
        );
    }

    public function test_the_declared_exemptions_still_exist(): void
    {
        $missing = [];

        foreach (array_keys(self::INTENTIONALLY_UNSCOPED) as $class) {
            if (! file_exists($this->modelDirectory().'/'.$class.'.php')) {
                $missing[] = $class;
            }
        }

        $this->assertSame(
            [],
            $missing,
            "INTENTIONALLY_UNSCOPED lists models that no longer exist. Remove them:\n  "
            .implode("\n  ", $missing)
        );
    }

    /**
     * A model owns a tenant column when its generated domain object declares ACCOUNT_ID.
     * That constant is generated from the live schema, which makes it the accurate signal;
     * matching the string "account_id" anywhere in the model instead catches relation
     * constraints and unrelated columns such as TARGET_ACCOUNT_IDS.
     *
     * The authoritative list is the database, but this test belongs to the DB-free unit
     * suite, so it reads the generated abstracts.
     *
     * @return array<string, string>
     */
    private function modelsWithAccountId(): array
    {
        $found = [];
        $generatedDirectory = dirname(__DIR__, 3).'/app/DomainObjects/Generated';

        foreach ((array) glob($this->modelDirectory().'/*.php') as $path) {
            $class = basename((string) $path, '.php');
            $abstract = $generatedDirectory.'/'.$class.'DomainObjectAbstract.php';

            if (! file_exists($abstract)) {
                continue;
            }

            $contents = (string) file_get_contents($abstract);

            if (preg_match("/const ACCOUNT_ID = 'account_id';/", $contents) === 1) {
                $found[$class] = (string) $path;
            }
        }

        return $found;
    }

    private function modelDirectory(): string
    {
        return dirname(__DIR__, 3).'/app/Models';
    }
}
