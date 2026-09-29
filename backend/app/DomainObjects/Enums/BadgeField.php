<?php

namespace HiEvents\DomainObjects\Enums;

enum BadgeField: string
{
    use BaseEnum;

    case PERSON_FULL_NAME = 'person.full_name';
    case PERSON_FIRST_NAME = 'person.first_name';
    case PERSON_LAST_NAME = 'person.last_name';
    case PERSON_COMPANY = 'person.company';
    case PERSON_JOB_TITLE = 'person.job_title';
    case ACCREDITATION_TYPE = 'accreditation.type';
    case CREDENTIAL_IDENTIFIER = 'credential.identifier';
    case CREDENTIAL_TYPE = 'credential.type';
    case EVENT_NAME = 'event.name';
    case EVENT_DATES = 'event.dates';
    case ZONE_LIST = 'zone.list';
}
