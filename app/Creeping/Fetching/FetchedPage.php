<?php

namespace App\Creeping\Fetching;

/**
 * One page, as it was actually retrieved.
 */
final readonly class FetchedPage
{
    public function __construct(
        /** Where the page was finally served from, after any redirects. */
        public string $url,
        public string $html,
        public string $contentType,
        public int $redirects,
        public int $bytes,
    ) {}
}
