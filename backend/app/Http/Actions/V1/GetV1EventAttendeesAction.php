<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\V1;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Infrastructure\ApiKey\ApiPrincipalContext;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Machine-facing attendee listing.
 *
 * Authenticated by API key rather than JWT, and deliberately narrow: an integration that
 * only needs the attendee roster should not need a user session to get it.
 *
 * @see docs/arzo-master-plan/48-api-platform.md
 */
class GetV1EventAttendeesAction extends BaseAction
{
    private const MAX_PER_PAGE = 200;

    public function __construct(
        private readonly DatabaseManager $databaseManager,
        private readonly ApiPrincipalContext $principalContext,
    ) {}

    public function __invoke(Request $request, int $eventId): JsonResponse
    {
        $principal = $this->principalContext->get();

        $perPage = min(
            self::MAX_PER_PAGE,
            max(1, (int) $request->query('per_page', '50'))
        );

        // A foreign event is 404, not an empty page. Returning 200 with no rows still
        // confirms the id exists, which lets a key holder enumerate another account's
        // events — the same reasoning that made cross-tenant reads 404 elsewhere.
        $eventBelongs = $this->databaseManager->table('events')
            ->where('id', $eventId)
            ->where('account_id', $principal->accountId)
            ->whereNull('deleted_at')
            ->exists();

        if (! $eventBelongs) {
            return $this->jsonResponse(['message' => __('Resource not found')], 404, false);
        }

        $attendees = $this->databaseManager->table('attendees')
            ->join('events', 'events.id', '=', 'attendees.event_id')
            ->where('attendees.event_id', $eventId)
            // The tenant scope does not reach the query builder, so account ownership is
            // asserted here rather than assumed from the key being valid.
            ->where('events.account_id', $principal->accountId)
            ->whereNull('attendees.deleted_at')
            ->orderBy('attendees.id')
            ->select([
                'attendees.public_id',
                'attendees.first_name',
                'attendees.last_name',
                'attendees.email',
                'attendees.status',
                'attendees.checked_in_at',
            ])
            ->paginate($perPage);

        return $this->jsonResponse([
            'data' => $attendees->items(),
            'meta' => [
                'current_page' => $attendees->currentPage(),
                'per_page' => $attendees->perPage(),
                'total' => $attendees->total(),
                'last_page' => $attendees->lastPage(),
            ],
        ]);
    }
}
