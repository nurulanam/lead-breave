<?php

namespace App\Services\Website;

use RuntimeException;

/**
 * The site could not be fetched: it timed out, refused the connection,
 * redirected too often or sent too much.
 */
class FetchFailed extends RuntimeException {}
