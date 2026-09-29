<?php

namespace HiEvents\DomainObjects\Enums;

enum BadgeElementType: string
{
    use BaseEnum;

    case TEXT = 'TEXT';
    case FIELD = 'FIELD';
    case QR = 'QR';
    case BARCODE = 'BARCODE';
    case PHOTO = 'PHOTO';
    case IMAGE = 'IMAGE';
    case SHAPE = 'SHAPE';
    case ZONE_COLOUR_BAR = 'ZONE_COLOUR_BAR';
}
