<?php

namespace App\Creeping\Fetching;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * A page, boiled down to the parts worth paying a model to read.
 *
 * Raw HTML is mostly navigation, tracking and styling: expensive to send and
 * actively unhelpful. This keeps three layers, in descending order of how much
 * they can be trusted — schema.org data the site published deliberately, the
 * metadata it exposes for social cards, and finally the visible text — and
 * puts all three in one prompt so the model can reconcile them.
 *
 * Which parts of those layers survive is the {@see DigestProfile}'s business,
 * because a shop page and a changelog hide their truth in different places.
 */
final readonly class PageDigest
{
    /**
     * Elements that end a line of visible text.
     *
     * @var array<int, string>
     */
    private const BLOCKS = [
        'address', 'article', 'br', 'div', 'dd', 'dl', 'dt', 'fieldset',
        'figure', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hr', 'li', 'main',
        'ol', 'p', 'pre', 'section', 'table', 'td', 'th', 'tr', 'ul',
    ];

    /**
     * @param  array<int, array<string, mixed>>  $structuredData
     * @param  array<string, string>  $meta
     */
    private function __construct(
        public array $structuredData,
        public array $meta,
        public string $text,
        public bool $truncated,
    ) {}

    /**
     * Reduce a page to its digest.
     *
     * The profile decides what is worth keeping; a product page is assumed
     * when none is given, because that is the only kind of page there was.
     */
    public static function fromHtml(string $html, int $maxCharacters, ?DigestProfile $profile = null): self
    {
        $profile ??= DigestProfile::product();

        $document = self::parse($html);

        if (! $document instanceof DOMDocument) {
            return new self([], [], '', false);
        }

        $xpath = new DOMXPath($document);

        $structured = self::truncate(
            self::structuredData($xpath, $profile),
            $profile->structuredBudget,
        );

        $meta = self::metadata($xpath, $profile);

        $spent = mb_strlen(self::encode($structured)) + mb_strlen(self::flatten($meta));
        $remaining = max(0, $maxCharacters - $spent);

        $text = self::bodyText($document, $xpath, $profile);
        $truncated = mb_strlen($text) > $remaining;

        return new self(
            $structured,
            $meta,
            $truncated ? mb_substr($text, 0, $remaining) : $text,
            $truncated,
        );
    }

    /**
     * Whether there is anything here worth asking a model about.
     *
     * A page that yields no structured data, no metadata and barely any text
     * is a login wall, a cookie interstitial or a redirect stub. Sending it
     * would cost money to be told nothing.
     */
    public function isThin(): bool
    {
        return $this->structuredData === []
            && $this->meta === []
            && mb_strlen(trim($this->text)) < 200;
    }

    /**
     * The digest as one prompt, highest-signal section first.
     */
    public function toPrompt(string $url): string
    {
        $sections = ['URL: '.$url];

        if ($this->structuredData !== []) {
            $sections[] = "## Structured data (schema.org)\n".self::encode($this->structuredData);
        }

        if ($this->meta !== []) {
            $sections[] = "## Page metadata\n".self::flatten($this->meta);
        }

        if (trim($this->text) !== '') {
            $sections[] = "## Page text\n".$this->text
                .($this->truncated ? "\n[…truncated]" : '');
        }

        return implode("\n\n", $sections);
    }

    /**
     * A stable digest of the page's contents.
     *
     * Two runs that produce the same fingerprint saw the same page, which is
     * the cheapest possible signal that nothing has changed.
     */
    public function fingerprint(): string
    {
        return hash('sha256', self::encode($this->structuredData)."\0"
            .self::flatten($this->meta)."\0".$this->text);
    }

    /**
     * Parse HTML without letting it reach out to the network.
     *
     * `LIBXML_NONET` is what stops a hostile page's external entities being
     * resolved, so it is not optional. `LIBXML_DTDLOAD` and `LIBXML_NOENT`
     * would undo it and must never be added.
     */
    private static function parse(string $html): ?DOMDocument
    {
        if (trim($html) === '') {
            return null;
        }

        $previous = libxml_use_internal_errors(true);

        $document = new DOMDocument;

        // The prefixed declaration is how `loadHTML` is told the bytes are
        // UTF-8; without it libxml assumes ISO-8859-1 and mangles anything
        // that isn't ASCII — currency symbols very much included.
        $loaded = $document->loadHTML(
            '<?xml encoding="UTF-8">'.$html,
            LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_NONET,
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $loaded ? $document : null;
    }

    /**
     * Every schema.org node on the page of a type this profile wants.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function structuredData(DOMXPath $xpath, DigestProfile $profile): array
    {
        $wanted = $profile->structuredTypes;

        if ($wanted === []) {
            return [];
        }

        $found = [];

        $nodes = $xpath->query('//script[@type="application/ld+json"]');

        if ($nodes === false) {
            return [];
        }

        foreach ($nodes as $node) {
            if (! $node instanceof DOMNode) {
                continue;
            }

            /** @var mixed $decoded */
            $decoded = json_decode($node->textContent, true);

            // Broken JSON-LD is extremely common. A page having one bad block
            // is no reason to ignore its good ones.
            if (! is_array($decoded)) {
                continue;
            }

            foreach (self::walk($decoded) as $candidate) {
                if (in_array(self::typeOf($candidate), $wanted, true)) {
                    $found[] = $candidate;
                }
            }
        }

        return $found;
    }

    /**
     * Flatten the shapes JSON-LD arrives in — a bare object, a list, or a
     * `@graph` envelope — into the nodes inside them.
     *
     * @param  array<mixed>  $data
     * @return array<int, array<string, mixed>>
     */
    private static function walk(array $data): array
    {
        if (array_is_list($data)) {
            $nodes = [];

            foreach ($data as $item) {
                if (is_array($item)) {
                    $nodes = [...$nodes, ...self::walk($item)];
                }
            }

            return $nodes;
        }

        /** @var array<string, mixed> $data */
        if (isset($data['@graph']) && is_array($data['@graph'])) {
            return self::walk($data['@graph']);
        }

        return [$data];
    }

    /**
     * A node's schema.org type, normalised.
     *
     * `@type` may be a string, a list, or a full URL, and its casing is
     * whatever the shop felt like.
     *
     * @param  array<string, mixed>  $node
     */
    private static function typeOf(array $node): string
    {
        $type = $node['@type'] ?? null;

        if (is_array($type)) {
            $type = $type[0] ?? null;
        }

        if (! is_string($type)) {
            return '';
        }

        $basename = mb_strtolower(trim($type));
        $position = mb_strrpos($basename, '/');

        return $position === false ? $basename : mb_substr($basename, $position + 1);
    }

    /**
     * The page's title, canonical URL, social metadata and microdata.
     *
     * @return array<string, string>
     */
    private static function metadata(DOMXPath $xpath, DigestProfile $profile): array
    {
        $meta = [];

        foreach ($xpath->query('//title') ?: [] as $node) {
            if (! $node instanceof DOMNode) {
                continue;
            }

            $meta['title'] = self::collapse($node->textContent);

            break;
        }

        foreach ($xpath->query('//link[@rel="canonical"]/@href') ?: [] as $node) {
            $meta['canonical'] = self::collapse((string) $node->nodeValue);

            break;
        }

        foreach ($xpath->query('//meta[@property or @name]') ?: [] as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $key = mb_strtolower($node->getAttribute('property') ?: $node->getAttribute('name'));

            if (in_array($key, $profile->meta, true) && ! isset($meta[$key])) {
                $content = self::collapse($node->getAttribute('content'));

                if ($content !== '') {
                    $meta[$key] = $content;
                }
            }
        }

        foreach ($xpath->query('//*[@itemprop]') ?: [] as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $property = $node->getAttribute('itemprop');

            if (! in_array($property, $profile->itemProps, true)) {
                continue;
            }

            $key = 'itemprop:'.$property;

            if (isset($meta[$key])) {
                continue;
            }

            $value = self::collapse(
                $node->getAttribute('content')
                    ?: $node->getAttribute('href')
                    ?: (string) $node->textContent
            );

            if ($value !== '') {
                $meta[$key] = mb_substr($value, 0, 200);
            }
        }

        return self::within($meta, $profile->metaBudget);
    }

    /**
     * The visible text of the page, with the furniture stripped out.
     */
    private static function bodyText(DOMDocument $document, DOMXPath $xpath, DigestProfile $profile): string
    {
        foreach ($xpath->query('//comment()') ?: [] as $comment) {
            if ($comment instanceof DOMNode) {
                $comment->parentNode?->removeChild($comment);
            }
        }

        foreach ($profile->noise as $tag) {
            $nodes = $xpath->query('//'.$tag);

            if ($nodes === false) {
                continue;
            }

            // Snapshot the list first: removing while iterating a live
            // DOMNodeList skips nodes.
            foreach (iterator_to_array($nodes) as $node) {
                if ($node instanceof DOMNode) {
                    $node->parentNode?->removeChild($node);
                }
            }
        }

        $body = $document->getElementsByTagName('body')->item(0) ?? $document->documentElement;

        if (! $body instanceof DOMNode) {
            return '';
        }

        $lines = [];
        self::gather($body, $lines);

        $text = implode("\n", $lines);

        return trim((string) preg_replace("/\n{3,}/", "\n\n", $text));
    }

    /**
     * Walk the tree, emitting one line per block-level element.
     *
     * @param  array<int, string>  $lines
     */
    private static function gather(DOMNode $node, array &$lines): void
    {
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $value = self::collapse((string) $child->nodeValue);

                if ($value !== '') {
                    $lines[] = $value;
                }

                continue;
            }

            if (! $child instanceof DOMElement) {
                continue;
            }

            self::gather($child, $lines);

            if (in_array(mb_strtolower($child->tagName), self::BLOCKS, true)) {
                $lines[] = '';
            }
        }
    }

    /**
     * Keep whole entries until the budget runs out.
     *
     * @param  array<string, string>  $meta
     * @return array<string, string>
     */
    private static function within(array $meta, int $budget): array
    {
        $kept = [];
        $spent = 0;

        foreach ($meta as $key => $value) {
            $cost = mb_strlen($key) + mb_strlen($value) + 2;

            if ($spent + $cost > $budget) {
                break;
            }

            $kept[$key] = $value;
            $spent += $cost;
        }

        return $kept;
    }

    /**
     * Keep whole schema.org nodes until the budget runs out, largest last so
     * a single enormous node can't crowd out everything else.
     *
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array<int, array<string, mixed>>
     */
    private static function truncate(array $nodes, int $budget): array
    {
        $kept = [];
        $spent = 0;

        foreach ($nodes as $node) {
            $cost = mb_strlen(self::encode([$node]));

            if ($spent + $cost > $budget) {
                continue;
            }

            $kept[] = $node;
            $spent += $cost;
        }

        return $kept;
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     */
    private static function encode(array $nodes): string
    {
        if ($nodes === []) {
            return '';
        }

        return (string) json_encode(
            $nodes,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }

    /**
     * @param  array<string, string>  $meta
     */
    private static function flatten(array $meta): string
    {
        $lines = [];

        foreach ($meta as $key => $value) {
            $lines[] = $key.': '.$value;
        }

        return implode("\n", $lines);
    }

    private static function collapse(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }
}
