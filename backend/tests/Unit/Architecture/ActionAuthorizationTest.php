<?php

namespace Tests\Unit\Architecture;

use Tests\TestCase;

/**
 * Authorization in this codebase is imperative: every non-public action must call
 * isActionAuthorized() or minimumAllowedRole(), or scope its query to the
 * authenticated account. Nothing structural enforces that, so a forgotten call is
 * a cross-tenant read.
 *
 * This test makes the convention mechanical. It is the gate the RBAC migration
 * depends on, because that change touches every authorization call site.
 *
 * @see docs/arzo-master-plan/02-current-state-audit.md findings F11 and F12
 * @see docs/arzo-master-plan/09-permissions-and-roles.md
 */
class ActionAuthorizationTest extends TestCase
{
    /**
     * Actions that deliberately perform no authorization because they are reachable
     * without a session. Adding a path here is a security decision: it asserts the
     * endpoint is safe for anyone on the internet to call.
     */
    private const INTENTIONALLY_UNAUTHENTICATED = [
        // Account and organizer creation: the caller has no account yet, or is
        // creating one under their own authenticated account id.
        'Accounts/CreateAccountAction.php',
        'Organizers/CreateOrganizerAction.php',

        // Authentication and account recovery.
        'Auth/AcceptInvitationAction.php',
        'Auth/BaseAuthAction.php',
        'Auth/ForgotPasswordAction.php',
        'Auth/GetUserInvitationAction.php',
        'Auth/LoginAction.php',
        'Auth/LogoutAction.php',
        'Auth/RefreshTokenAction.php',
        'Auth/ResetPasswordAction.php',
        'Auth/ValidateResetPasswordTokenAction.php',
        'Admin/Users/StopImpersonationAction.php',

        // Platform announcements for the signed-in user.
        'Announcements/DismissAnnouncementAction.php',
        'Announcements/GetActiveAnnouncementsAction.php',

        // Public storefront and checkout.
        'Attendees/GetAttendeeActionPublic.php',
        'Events/BasePublicEventAction.php',
        'Events/GetEventPublicAction.php',
        'Events/GetOrganizerEventsPublicAction.php',
        'EventOccurrences/GetEventOccurrencesPublicAction.php',
        'Organizers/GetPublicOrganizerAction.php',
        'Organizers/Public/SendOrganizerContactMessagePublicAction.php',
        'PromoCodes/GetPromoCodePublic.php',
        'Questions/GetQuestionsPublicAction.php',
        'Orders/Public/AbandonOrderActionPublic.php',
        'Orders/Public/CompleteOrderActionPublic.php',
        'Orders/Public/CreateOrderActionPublic.php',
        'Orders/Public/DownloadOrderInvoicePublicAction.php',
        'Orders/Public/GetOrderActionPublic.php',
        'Orders/Public/TransitionOrderToOfflinePaymentPublicAction.php',
        'Orders/Payment/Stripe/CreatePaymentIntentActionPublic.php',
        'Orders/Payment/Stripe/GetPaymentIntentActionPublic.php',
        'Waitlist/Public/CancelWaitlistEntryActionPublic.php',
        'Waitlist/Public/CreateWaitlistEntryActionPublic.php',

        // Capability-URL surfaces: the unguessable short id is the credential.
        // Tracked as finding F3 — these need device and operator identity.
        'CheckInLists/Public/CreateAttendeeCheckInPublicAction.php',
        'CheckInLists/Public/DeleteAttendeeCheckInPublicAction.php',
        'CheckInLists/Public/GetCheckInListAttendeeDetailPublicAction.php',
        'CheckInLists/Public/GetCheckInListAttendeePublicAction.php',
        'CheckInLists/Public/GetCheckInListAttendeesPublicAction.php',
        'CheckInLists/Public/GetCheckInListPublicAction.php',
        'CheckInLists/Public/GetCheckInListStatsPublicAction.php',
        'SelfService/EditAttendeePublicAction.php',
        'SelfService/EditOrderPublicAction.php',
        'SelfService/ResendAttendeeTicketPublicAction.php',
        'SelfService/ResendOrderConfirmationPublicAction.php',
        'TicketLookup/GetOrdersByLookupTokenAction.php',
        'TicketLookup/SendTicketLookupEmailAction.php',

        // Incoming provider webhook, verified by signature rather than session.
        'Common/Webhooks/StripeIncomingWebhookAction.php',

        // Static or non-tenant data.
        'Common/GetColorThemesAction.php',
        'Locations/GetGeoStatusAction.php',
        'Sitemap/GetSitemapEventsAction.php',
        'Sitemap/GetSitemapIndexAction.php',
        'Sitemap/GetSitemapOrganizersAction.php',

        // Abstract bases; concrete subclasses authorize.
        'EmailTemplates/BaseEmailTemplateAction.php',
        'EmailTemplates/GetAvailableTokensAction.php',
        'EmailTemplates/GetDefaultEmailTemplateAction.php',

        // Event creation is scoped to the authenticated account id.
        'Events/CreateEventAction.php',

        // Operate on the authenticated user's own record only.
        'Users/ConfirmEmailWithCodeAction.php',
        'Users/GetMeAction.php',
        'Users/ResendEmailConfirmationAction.php',
    ];

    public function test_every_action_authorizes_or_is_declared_public(): void
    {
        $unauthorized = [];

        foreach ($this->actionFiles() as $relativePath => $contents) {
            if ($this->authorizes($contents)
                || $this->scopesToAuthenticatedAccount($contents)
                || $this->scopesToApiPrincipal($contents)
            ) {
                continue;
            }

            $unauthorized[] = $relativePath;
        }

        $undeclared = array_values(array_diff($unauthorized, self::INTENTIONALLY_UNAUTHENTICATED));

        $this->assertSame(
            [],
            $undeclared,
            "These actions perform no authorization and are not declared public.\n"
            ."Call isActionAuthorized() or minimumAllowedRole(), scope the query to the\n"
            ."authenticated account, or add the path to INTENTIONALLY_UNAUTHENTICATED with\n"
            ."a comment explaining why anyone on the internet may call it.\n\n"
            .implode("\n", $undeclared)
        );
    }

    public function test_public_allowlist_has_no_stale_entries(): void
    {
        $actions = $this->actionFiles();

        $missing = array_values(array_filter(
            self::INTENTIONALLY_UNAUTHENTICATED,
            static fn (string $path): bool => ! array_key_exists($path, $actions)
        ));

        $this->assertSame(
            [],
            $missing,
            "INTENTIONALLY_UNAUTHENTICATED lists actions that no longer exist. Remove them.\n"
            .implode("\n", $missing)
        );
    }

    public function test_admin_actions_require_superadmin(): void
    {
        $offenders = [];

        foreach ($this->actionFiles() as $relativePath => $contents) {
            if (! str_starts_with($relativePath, 'Admin/')) {
                continue;
            }

            if (in_array($relativePath, self::INTENTIONALLY_UNAUTHENTICATED, true)) {
                continue;
            }

            if (! str_contains($contents, 'Role::SUPERADMIN')) {
                $offenders[] = $relativePath;
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Admin actions must gate on Role::SUPERADMIN. The /admin route group only applies\n"
            ."auth:api, so enforcement is per action.\n".implode("\n", $offenders)
        );
    }

    /**
     * Machine-facing actions authenticate by API key, not by JWT.
     *
     * The route carries an api-scope middleware that rejects a key without the right
     * permission, and the action constrains its query to the resolved principal's account.
     * Requiring isActionAuthorized() here instead would mean resolving a user that does not
     * exist for a key.
     */
    private function scopesToApiPrincipal(string $contents): bool
    {
        return str_contains($contents, 'ApiPrincipalContext')
            && preg_match('/\$principal->accountId/', $contents) === 1;
    }

    private function authorizes(string $contents): bool
    {
        return preg_match('/\b(isActionAuthorized|minimumAllowedRole)\s*\(/', $contents) === 1;
    }

    private function scopesToAuthenticatedAccount(string $contents): bool
    {
        return preg_match('/\bgetAuthenticated(AccountId|User)\s*\(/', $contents) === 1;
    }

    /**
     * @return array<string, string> relative path => file contents
     */
    private function actionFiles(): array
    {
        $root = base_path('app/Http/Actions');

        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
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
