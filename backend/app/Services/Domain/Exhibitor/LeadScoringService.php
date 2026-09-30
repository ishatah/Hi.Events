<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Exhibitor;

use HiEvents\DomainObjects\Enums\LeadRating;
use Illuminate\Database\DatabaseManager;

/**
 * Scores a lead from what the platform actually observed.
 *
 * Deliberately transparent arithmetic rather than a model. An exhibitor deciding who to call
 * on Monday needs to know why a lead scored 80, and a score nobody can explain gets ignored —
 * which is worse than no score. Every component is listed in the breakdown.
 *
 * The staff rating dominates on purpose: somebody who spoke to the visitor knows more than
 * any behavioural signal, and a system that overrules them will be distrusted.
 *
 * @see docs/arzo-master-plan/33-exhibitor-lead-capture.md
 */
class LeadScoringService
{
    private const MAX_SCORE = 100;

    public function __construct(
        private readonly DatabaseManager $databaseManager,
    ) {}

    /**
     * @return array{score: int, band: string, breakdown: array<int, array{factor: string, points: int, detail: string}>}|null
     */
    public function score(int $leadId): ?array
    {
        $lead = $this->databaseManager->table('leads')
            ->where('id', $leadId)
            ->whereNull('deleted_at')
            ->first();

        if ($lead === null) {
            return null;
        }

        $breakdown = [];

        $breakdown[] = $this->ratingComponent($lead);
        $breakdown[] = $this->revisitComponent($lead);
        $breakdown[] = $this->engagementComponent($lead);
        $breakdown[] = $this->profileComponent($lead);
        $breakdown[] = $this->sessionComponent($lead);

        $score = min(self::MAX_SCORE, array_sum(array_column($breakdown, 'points')));

        return [
            'score' => $score,
            'band' => $this->band($score),
            'breakdown' => $breakdown,
        ];
    }

    /**
     * Scores every lead for an exhibitor, highest first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function rankForExhibitor(int $eventExhibitorId): array
    {
        $leads = $this->databaseManager->table('leads')
            ->where('event_exhibitor_id', $eventExhibitorId)
            ->whereNull('deleted_at')
            ->pluck('id');

        $ranked = [];

        foreach ($leads as $leadId) {
            $scored = $this->score((int) $leadId);

            if ($scored === null) {
                continue;
            }

            $ranked[] = ['lead_id' => (int) $leadId] + $scored;
        }

        usort($ranked, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return $ranked;
    }

    /**
     * @return array{factor: string, points: int, detail: string}
     */
    private function ratingComponent(object $lead): array
    {
        $rating = $lead->rating !== null ? LeadRating::tryFrom((string) $lead->rating) : null;

        $points = match ($rating) {
            LeadRating::HOT => 40,
            LeadRating::WARM => 22,
            LeadRating::COLD => 4,
            // Unrated is not the same as cold. A busy booth rates nobody, and treating that
            // as a negative signal would bury every lead from the busiest stand.
            null => 14,
        };

        return [
            'factor' => 'staff_rating',
            'points' => $points,
            'detail' => $rating !== null
                ? __('Rated :rating at the booth', ['rating' => $rating->value])
                : __('Not rated at the booth'),
        ];
    }

    /**
     * @return array{factor: string, points: int, detail: string}
     */
    private function revisitComponent(object $lead): array
    {
        $captures = (int) $lead->capture_count;

        // Coming back is the strongest behavioural signal a booth gets, but it saturates:
        // a fifth visit does not mean five times the interest.
        $points = match (true) {
            $captures >= 4 => 20,
            $captures === 3 => 16,
            $captures === 2 => 10,
            default => 0,
        };

        return [
            'factor' => 'revisits',
            'points' => $points,
            'detail' => __(':count booth visit(s)', ['count' => $captures]),
        ];
    }

    /**
     * @return array{factor: string, points: int, detail: string}
     */
    private function engagementComponent(object $lead): array
    {
        $hasNotes = $lead->notes !== null && trim((string) $lead->notes) !== '';
        $qualification = $lead->qualification !== null
            ? (json_decode((string) $lead->qualification, true) ?: [])
            : [];

        $points = 0;
        $reasons = [];

        if ($hasNotes) {
            // Staff writing anything down at a busy booth is itself a signal.
            $points += 10;
            $reasons[] = __('notes taken');
        }

        if ($qualification !== []) {
            $points += 10;
            $reasons[] = __(':count qualification answer(s)', ['count' => count($qualification)]);
        }

        return [
            'factor' => 'engagement',
            'points' => $points,
            'detail' => $reasons !== [] ? implode(', ', $reasons) : __('No notes or answers'),
        ];
    }

    /**
     * @return array{factor: string, points: int, detail: string}
     */
    private function profileComponent(object $lead): array
    {
        // Read from the capture-time snapshot, not the live person row: the score must
        // reflect what the exhibitor actually received.
        $shared = $lead->shared_fields !== null
            ? (json_decode((string) $lead->shared_fields, true) ?: [])
            : [];

        $points = 0;
        $present = [];

        foreach (['company' => 8, 'job_title' => 7, 'email' => 5] as $field => $value) {
            if (! empty($shared[$field])) {
                $points += $value;
                $present[] = $field;
            }
        }

        return [
            'factor' => 'profile_completeness',
            'points' => $points,
            'detail' => $present !== []
                ? __('Shared: :fields', ['fields' => implode(', ', $present)])
                : __('No company, title or email shared'),
        ];
    }

    /**
     * @return array{factor: string, points: int, detail: string}
     */
    private function sessionComponent(object $lead): array
    {
        $sessionsAttended = $this->databaseManager->table('session_attendance')
            ->join('attendees', 'attendees.id', '=', 'session_attendance.attendee_id')
            ->join('credentials', 'credentials.attendee_id', '=', 'attendees.id')
            ->where('credentials.person_id', $lead->person_id)
            ->where('session_attendance.direction', 'ENTRY')
            ->distinct()
            ->count('session_attendance.session_id');

        $points = match (true) {
            $sessionsAttended >= 3 => 10,
            $sessionsAttended >= 1 => 6,
            default => 0,
        };

        return [
            'factor' => 'programme_engagement',
            'points' => $points,
            'detail' => __(':count session(s) attended', ['count' => $sessionsAttended]),
        ];
    }

    private function band(int $score): string
    {
        return match (true) {
            $score >= 70 => 'PRIORITY',
            $score >= 45 => 'FOLLOW_UP',
            default => 'NURTURE',
        };
    }
}
