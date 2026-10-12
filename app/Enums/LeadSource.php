<?php

namespace App\Enums;

enum LeadSource: string
{
    case Places = 'places';
    case Manual = 'manual';
    case Csv = 'csv';
}
