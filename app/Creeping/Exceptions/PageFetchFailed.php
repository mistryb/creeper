<?php

namespace App\Creeping\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Thrown when a page could not be fetched.
 *
 * Carries whether the answer is final. A shop having a bad minute deserves the
 * queue's backoff; a page that is gone, or an address we refuse to connect to,
 * deserves to end the run. The distinction lives here rather than being
 * re-derived from message text at the call site.
 */
class PageFetchFailed extends RuntimeException
{
    private function __construct(
        string $message,
        public readonly bool $definite,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * The page cannot be fetched, and asking again won't change that.
     */
    public static function definitely(string $why, ?Throwable $previous = null): self
    {
        return new self($why, true, $previous);
    }

    /**
     * Something went wrong that might not go wrong next time.
     */
    public static function temporarily(string $why, ?Throwable $previous = null): self
    {
        return new self($why, false, $previous);
    }
}
