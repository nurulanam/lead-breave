<?php

namespace App\Enums;

/**
 * Where a piece of lead data came from. Manual values are never overwritten by a crawl.
 */
enum DataSource: string
{
    case Crawl = 'crawl';
    case Manual = 'manual';
    case Csv = 'csv';
}
