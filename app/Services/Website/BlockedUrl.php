<?php

namespace App\Services\Website;

use RuntimeException;

/**
 * A URL LeadBreave refuses to fetch: not http(s), not a public host, or a
 * name that resolves to a private address.
 */
class BlockedUrl extends RuntimeException {}
