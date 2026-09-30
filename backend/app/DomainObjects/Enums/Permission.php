<?php

namespace HiEvents\DomainObjects\Enums;

enum Permission: string
{
    use BaseEnum;

    case EVENT_VIEW = 'event.view';
    case EVENT_CREATE = 'event.create';
    case EVENT_UPDATE = 'event.update';
    case EVENT_PUBLISH = 'event.publish';
    case EVENT_DELETE = 'event.delete';

    case PRODUCT_VIEW = 'product.view';
    case PRODUCT_MANAGE = 'product.manage';

    case ORDER_VIEW = 'order.view';
    case ORDER_REFUND = 'order.refund';
    case ORDER_EXPORT = 'order.export';

    case ATTENDEE_VIEW = 'attendee.view';
    case ATTENDEE_CHECKIN = 'attendee.checkin';
    case ATTENDEE_EDIT = 'attendee.edit';
    case ATTENDEE_EXPORT = 'attendee.export';

    case ACCREDITATION_VIEW = 'accreditation.view';
    case ACCREDITATION_APPROVE = 'accreditation.approve';
    case ACCREDITATION_REJECT = 'accreditation.reject';

    case CREDENTIAL_ISSUE = 'credential.issue';
    case CREDENTIAL_REVOKE = 'credential.revoke';

    case BADGE_PRINT = 'badge.print';
    case BADGE_REPRINT = 'badge.reprint';
    case BADGE_VOID = 'badge.void';

    case ACCESS_OVERRIDE = 'access.override';
    case ACCESS_LOGS_VIEW = 'access.logs.view';

    case ZONE_MANAGE = 'zone.manage';
    case VENUE_MANAGE = 'venue.manage';

    case SESSION_MANAGE = 'session.manage';
    case SPEAKER_MANAGE = 'speaker.manage';

    case EXHIBITOR_MANAGE = 'exhibitor.manage';
    case LEAD_VIEW = 'lead.view';

    case GUEST_LIST_MANAGE = 'guest_list.manage';
    case SPONSOR_MANAGE = 'sponsor.manage';
    case RAFFLE_MANAGE = 'raffle.manage';

    case DEVICE_MANAGE = 'device.manage';
    case DEVICE_SUBMIT_SCAN = 'device.submit_scan';

    case REPORT_VIEW = 'report.view';
    case REPORT_EXPORT = 'report.export';

    case STAFF_MANAGE = 'staff.manage';
    case INCIDENT_MANAGE = 'incident.manage';

    case ACCOUNT_MANAGE = 'account.manage';
    case USER_MANAGE = 'user.manage';
    case APIKEY_MANAGE = 'apikey.manage';

    case MESSAGE_SEND = 'message.send';
    case PROMO_CODE_MANAGE = 'promo_code.manage';
    case QUESTION_MANAGE = 'question.manage';
    case WEBHOOK_MANAGE = 'webhook.manage';
}
