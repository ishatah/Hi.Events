<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Session;

use Carbon\Carbon;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;

/**
 * Builds an .ics feed for a session or a personal agenda.
 *
 * Times are emitted as UTC instants (the trailing Z) rather than local wall-clock, so a
 * delegate whose phone is in another timezone still sees the session at the right moment.
 *
 * @see docs/arzo-master-plan/27-sessions-tracks.md
 */
class SessionIcsExportService
{
    private const LINE_LIMIT = 75;

    public function __construct(
        private readonly DatabaseManager $databaseManager,
    ) {}

    public function forSession(int $sessionId): ?string
    {
        $session = $this->sessionQuery()->where('sessions.id', $sessionId)->first();

        if ($session === null) {
            return null;
        }

        return $this->buildCalendar(collect([$session]));
    }

    /**
     * Every published session the attendee is registered for, in start order.
     */
    public function forAttendeeAgenda(int $eventId, int $attendeeId): string
    {
        $sessions = $this->sessionQuery()
            ->join('session_registrations', 'session_registrations.session_id', '=', 'sessions.id')
            ->where('sessions.event_id', $eventId)
            ->where('session_registrations.attendee_id', $attendeeId)
            ->where('session_registrations.status', 'REGISTERED')
            ->whereNull('session_registrations.deleted_at')
            ->orderBy('sessions.starts_at')
            ->get();

        return $this->buildCalendar($sessions);
    }

    /**
     * Every published session on an event's programme.
     */
    public function forEventProgramme(int $eventId): string
    {
        $sessions = $this->sessionQuery()
            ->where('sessions.event_id', $eventId)
            ->orderBy('sessions.starts_at')
            ->get();

        return $this->buildCalendar($sessions);
    }

    private function sessionQuery(): Builder
    {
        return $this->databaseManager->table('sessions')
            ->leftJoin('rooms', 'rooms.id', '=', 'sessions.room_id')
            ->whereNull('sessions.deleted_at')
            ->where('sessions.is_published', true)
            ->select([
                'sessions.id',
                'sessions.short_id',
                'sessions.title',
                'sessions.description',
                'sessions.starts_at',
                'sessions.ends_at',
                'rooms.name as room_name',
            ]);
    }

    /**
     * @param  Collection<int, object>  $sessions
     */
    private function buildCalendar(Collection $sessions): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//ARZO//NONSGML Session Calendar//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
        ];

        $stamp = $this->formatDate(Carbon::now());

        foreach ($sessions as $session) {
            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:'.$session->short_id.'@arzo.qa';
            $lines[] = 'DTSTAMP:'.$stamp;
            $lines[] = 'DTSTART:'.$this->formatDate(Carbon::parse($session->starts_at));
            $lines[] = 'DTEND:'.$this->formatDate(Carbon::parse($session->ends_at));
            $lines[] = $this->fold('SUMMARY:'.$this->escape((string) $session->title));

            if ($session->description !== null && $session->description !== '') {
                $lines[] = $this->fold(
                    'DESCRIPTION:'.$this->escape($this->stripHtml((string) $session->description))
                );
            }

            if ($session->room_name !== null) {
                $lines[] = $this->fold('LOCATION:'.$this->escape((string) $session->room_name));
            }

            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines)."\r\n";
    }

    private function formatDate(Carbon $date): string
    {
        return $date->copy()->utc()->format('Ymd\THis\Z');
    }

    /**
     * Order matters: the backslash must be escaped before the characters whose escapes
     * introduce backslashes, or those get double-escaped.
     */
    private function escape(string $value): string
    {
        return str_replace(
            ['\\', ';', ',', "\r\n", "\n", "\r"],
            ['\\\\', '\\;', '\\,', '\\n', '\\n', '\\n'],
            $value
        );
    }

    private function stripHtml(string $value): string
    {
        return trim((string) preg_replace('/\s+/', ' ', strip_tags($value)));
    }

    /**
     * RFC 5545 limits a line to 75 octets, continued by a leading space. Folding on bytes
     * rather than characters would split a multi-byte character and corrupt the feed, so
     * this measures the encoded length of each character.
     */
    private function fold(string $line): string
    {
        if (strlen($line) <= self::LINE_LIMIT) {
            return $line;
        }

        $folded = '';
        $current = '';

        foreach (mb_str_split($line) as $character) {
            if (strlen($current) + strlen($character) > self::LINE_LIMIT) {
                $folded .= ($folded === '' ? '' : "\r\n ").$current;
                $current = '';
            }

            $current .= $character;
        }

        return $folded.($folded === '' ? '' : "\r\n ").$current;
    }
}
