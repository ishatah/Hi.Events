<?php

namespace HiEvents\Services\Domain\Account\Anonymization\Anonymizers;

use HiEvents\DomainObjects\Enums\AnonymizationStrategy;
use HiEvents\Services\Domain\Account\Anonymization\AccountAnonymizerInterface;
use HiEvents\Services\Domain\Account\Anonymization\AnonymizationContext;
use HiEvents\Services\Domain\Account\Anonymization\AnonymizationExecutor;
use Illuminate\Database\DatabaseManager;

/**
 * Erases the identity records the accreditation and access subsystems hold.
 *
 * Anonymizing orders and users is not enough once an account has run an accredited event: the
 * same people also exist as `persons`, carrying an identity document number, a date of birth
 * and a nationality, and as invitations carrying a name and an email. Deleting the account
 * while leaving those behind is the defect ARZ-323 records — the most sensitive personal data
 * on the platform surviving the deletion that was supposed to remove it.
 *
 * Access logs are deliberately left in place. They are append-only evidence of who passed
 * which door, and the link to a named individual is severed here by scrubbing the person
 * rather than by rewriting the log — a log with rows removed can no longer be reconciled, and
 * reconciliation is the reason it exists.
 *
 * @see docs/arzo-master-plan/136-master-backlog.md ARZ-323
 * @see docs/arzo-master-plan/65-privacy-gdpr.md
 */
class IdentityAnonymizer implements AccountAnonymizerInterface
{
    public function __construct(
        private readonly AnonymizationExecutor $executor,
        private readonly DatabaseManager $databaseManager,
    ) {}

    public function anonymize(AnonymizationContext $context): array
    {
        $results = [
            $this->executor->scrub(
                query: $this->databaseManager->table('persons')->where('account_id', $context->accountId),
                entity: 'persons',
                columnStrategies: [
                    'first_name' => AnonymizationStrategy::SCRUB_TEXT,
                    'last_name' => AnonymizationStrategy::SCRUB_TEXT,
                    'email' => AnonymizationStrategy::SCRUB_EMAIL,
                    'phone' => AnonymizationStrategy::NULLIFY,
                    'company' => AnonymizationStrategy::NULLIFY,
                    'job_title' => AnonymizationStrategy::NULLIFY,

                    // Nullified rather than scrubbed: a scrubbed identity document number is
                    // still a claim that one existed, and these are the fields a regulator
                    // asks about first.
                    'id_document_type' => AnonymizationStrategy::NULLIFY,
                    'id_document_number' => AnonymizationStrategy::NULLIFY,
                    'date_of_birth' => AnonymizationStrategy::NULLIFY,
                    'nationality' => AnonymizationStrategy::NULLIFY,
                ],
                context: $context,
            ),
        ];

        if ($context->eventIds === []) {
            return $results;
        }

        $results[] = $this->executor->scrub(
            query: $this->databaseManager->table('invitations')->whereIn('event_id', $context->eventIds),
            entity: 'invitations',
            columnStrategies: [
                'first_name' => AnonymizationStrategy::SCRUB_TEXT,
                'last_name' => AnonymizationStrategy::SCRUB_TEXT,
                'email' => AnonymizationStrategy::SCRUB_EMAIL_UNIQUE,
                'phone' => AnonymizationStrategy::NULLIFY,

                // The hash is a live credential: whoever holds the token can still answer for
                // the invitee, so it goes with the rest.
                'token_hash' => AnonymizationStrategy::NULLIFY,
            ],
            context: $context,
        );

        $results[] = $this->executor->scrub(
            query: $this->databaseManager->table('leads')->whereIn('event_id', $context->eventIds),
            entity: 'leads',
            columnStrategies: [
                'notes' => AnonymizationStrategy::NULLIFY,
                'qualification' => AnonymizationStrategy::NULLIFY,
            ],
            context: $context,
        );

        // The capture-time snapshot is a copy of the person, so scrubbing the person alone
        // would leave their details readable here. Emptied rather than nullified because the
        // column is NOT NULL: a lead with no snapshot at all is a different shape from one
        // whose snapshot has been cleared, and the exports that read it expect an object.
        $this->databaseManager->table('leads')
            ->whereIn('event_id', $context->eventIds)
            ->update(['shared_fields' => '{}']);

        $results[] = $this->executor->scrub(
            query: $this->databaseManager->table('networking_profiles')->whereIn('event_id', $context->eventIds),
            entity: 'networking_profiles',
            columnStrategies: [
                'headline' => AnonymizationStrategy::NULLIFY,
                'interests' => AnonymizationStrategy::NULLIFY,
                'looking_for' => AnonymizationStrategy::NULLIFY,
            ],
            context: $context,
        );

        $results[] = $this->executor->scrub(
            query: $this->databaseManager->table('connections')->whereIn('event_id', $context->eventIds),
            entity: 'connections',
            columnStrategies: [
                'note' => AnonymizationStrategy::NULLIFY,
            ],
            context: $context,
        );

        return $results;
    }
}
