<?php

namespace HiEvents\DomainObjects\Enums;

enum LeadRating: string
{
    use BaseEnum;

    case HOT = 'HOT';
    case WARM = 'WARM';
    case COLD = 'COLD';
}
