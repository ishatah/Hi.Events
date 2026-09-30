<?php

namespace HiEvents\DomainObjects\Enums;

enum SystemRole: string
{
    use BaseEnum;

    case SUPERADMIN = 'SUPERADMIN';
    case ADMIN = 'ADMIN';
    case ORGANIZER = 'ORGANIZER';
    case EVENT_MANAGER = 'EVENT_MANAGER';
    case CHECKIN_OPERATOR = 'CHECKIN_OPERATOR';
    case ACCREDITATION_OFFICER = 'ACCREDITATION_OFFICER';
    case BADGE_OPERATOR = 'BADGE_OPERATOR';
    case EXHIBITOR = 'EXHIBITOR';
    case SPEAKER = 'SPEAKER';
    case VIEWER = 'VIEWER';

    /**
     * Roles that can be granted per event via `event_users`. The three account-level roles
     * are deliberately excluded: granting ADMIN on a single event would imply an account
     * scope the grant cannot express.
     */
    public static function eventScopedRoles(): array
    {
        return [
            self::EVENT_MANAGER,
            self::CHECKIN_OPERATOR,
            self::ACCREDITATION_OFFICER,
            self::BADGE_OPERATOR,
            self::EXHIBITOR,
            self::SPEAKER,
            self::VIEWER,
        ];
    }

    /**
     * @return array<int, Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            // SUPERADMIN and ADMIN hold everything. Today's ADMIN is unrestricted within
            // its account, and ORGANIZER is too: validateUserRole() has no ORGANIZER branch,
            // so the role gate is a no-op for it. Reproducing that exactly is what keeps
            // this migration behaviourally inert — tightening ORGANIZER is a separate,
            // deliberate change, not a side effect of introducing roles.
            self::SUPERADMIN, self::ADMIN, self::ORGANIZER => Permission::cases(),

            self::EVENT_MANAGER => [
                Permission::EVENT_VIEW, Permission::EVENT_UPDATE, Permission::EVENT_PUBLISH,
                Permission::PRODUCT_VIEW, Permission::PRODUCT_MANAGE,
                Permission::ORDER_VIEW, Permission::ORDER_REFUND, Permission::ORDER_EXPORT,
                Permission::ATTENDEE_VIEW, Permission::ATTENDEE_CHECKIN,
                Permission::ATTENDEE_EDIT, Permission::ATTENDEE_EXPORT,
                Permission::ACCREDITATION_VIEW, Permission::ACCREDITATION_APPROVE,
                Permission::ACCREDITATION_REJECT,
                Permission::CREDENTIAL_ISSUE, Permission::CREDENTIAL_REVOKE,
                Permission::BADGE_PRINT, Permission::BADGE_REPRINT, Permission::BADGE_VOID,
                Permission::ACCESS_OVERRIDE, Permission::ACCESS_LOGS_VIEW,
                Permission::ZONE_MANAGE, Permission::VENUE_MANAGE,
                Permission::SESSION_MANAGE, Permission::SPEAKER_MANAGE,
                Permission::EXHIBITOR_MANAGE, Permission::LEAD_VIEW,
                Permission::GUEST_LIST_MANAGE, Permission::SPONSOR_MANAGE,
                Permission::DEVICE_MANAGE,
                Permission::REPORT_VIEW, Permission::REPORT_EXPORT,
                Permission::STAFF_MANAGE, Permission::INCIDENT_MANAGE,
                Permission::MESSAGE_SEND, Permission::PROMO_CODE_MANAGE,
                Permission::QUESTION_MANAGE,
            ],

            // The concrete answer to "a scanner operator who can check in but not refund".
            self::CHECKIN_OPERATOR => [
                Permission::EVENT_VIEW,
                Permission::ATTENDEE_VIEW, Permission::ATTENDEE_CHECKIN,
                Permission::ACCESS_LOGS_VIEW,
            ],

            self::ACCREDITATION_OFFICER => [
                Permission::EVENT_VIEW,
                Permission::ACCREDITATION_VIEW, Permission::ACCREDITATION_APPROVE,
                Permission::ACCREDITATION_REJECT,
                Permission::CREDENTIAL_ISSUE, Permission::CREDENTIAL_REVOKE,
            ],

            self::BADGE_OPERATOR => [
                Permission::EVENT_VIEW,
                Permission::ATTENDEE_VIEW,
                Permission::BADGE_PRINT, Permission::BADGE_REPRINT, Permission::BADGE_VOID,
            ],

            self::EXHIBITOR => [
                Permission::EVENT_VIEW,
                Permission::LEAD_VIEW,
            ],

            self::SPEAKER => [
                Permission::EVENT_VIEW,
                Permission::SESSION_MANAGE,
            ],

            self::VIEWER => [
                Permission::EVENT_VIEW, Permission::PRODUCT_VIEW, Permission::ORDER_VIEW,
                Permission::ATTENDEE_VIEW, Permission::REPORT_VIEW,
            ],
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::SUPERADMIN => __('Platform administrator'),
            self::ADMIN => __('Account administrator'),
            self::ORGANIZER => __('Event organizer'),
            self::EVENT_MANAGER => __('Manages a single event end to end'),
            self::CHECKIN_OPERATOR => __('Checks attendees in at the door'),
            self::ACCREDITATION_OFFICER => __('Reviews and approves accreditation applications'),
            self::BADGE_OPERATOR => __('Prints and reprints badges'),
            self::EXHIBITOR => __('Exhibitor with access to their own leads'),
            self::SPEAKER => __('Speaker with access to their own sessions'),
            self::VIEWER => __('Read-only access'),
        };
    }
}
