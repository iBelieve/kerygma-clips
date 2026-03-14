<?php

namespace App\Enums;

enum VideoType: string
{
    case Sermon = 'sermon';
    case Upload = 'upload';
    case Import = 'import';
}
