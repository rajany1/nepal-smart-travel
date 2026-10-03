<?php

namespace App\Services\Wikimedia;

use RuntimeException;

/**
 * Thrown when the shared Wikimedia rate budget is spent. Discovery jobs use
 * this to release themselves (delayed) instead of burning a retry attempt —
 * the work is not broken, it is merely not allowed to run yet.
 */
class RateLimitExceededException extends RuntimeException
{
}
