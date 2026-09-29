<?php

namespace HiEvents\DomainObjects\Status;

use HiEvents\DomainObjects\Enums\BaseEnum;

enum LeadStatus: string
{
    use BaseEnum;

    case NEW = 'NEW';
    case CONTACTED = 'CONTACTED';
    case QUALIFIED = 'QUALIFIED';
    case DISQUALIFIED = 'DISQUALIFIED';
}
