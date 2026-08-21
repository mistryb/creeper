<?php

namespace App\Creeping\Fetching;

use App\Creeping\Exceptions\PageFetchFailed;
use App\Rules\PublicUrl;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Fetches a page Creeper has been pointed at, defensively.
 *
 * Users paste arbitrary URLs and this runs inside the application, so every
 * hop is treated as hostile: the address is re-validated and pinned before
 * connecting, redirects are followed by hand so they cannot escape that check,
 * and the response is read under a hard byte ceiling.
 */
final class PageFetcher
{
    /**
     * Content types worth handing to a model.
     *
     * @var array<int, string>
     */
    private const READABLE = ['text/html', 'application/xhtml+xml', 'text/plain'];

    /**
     * How much of the body to pull off the wire at a time.
     */
    private const CHUNK = 65536;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(private array $config) {}

    /**
     * @throws PageFetchFailed
     */
    public function fetch(string $url): FetchedPage
    {
        $deadline = microtime(true) + (float) ($this->config['fetch_timeout'] ?? 15);
        $limit = (int) ($this->config['max_redirects'] ?? 3);
        $redirects = 0;

        while (true) {
            $response = $this->get($url, $this->guard($url), $deadline);

            if (! $response->redirect()) {
                return $this->page($response, $url, $redirects);
            }

            if (++$redirects > $limit) {
                throw PageFetchFailed::definitely('The page redirected too many times.');
            }

            $url = $this->follow($url, $response);
        }
    }

    /**
     * Confirm we are willing to connect to this URL, and return the single
     * address we validated.
     *
     * Pinning that address is the point: {@see PublicUrl} can only speak for
     * the moment it looked, and DNS can answer differently a millisecond
     * later. Resolving once and forcing the connection to the address we
     * checked closes that window.
     *
     * @throws PageFetchFailed
     */
    private function guard(string $url): ?string
    {
        if (! PublicUrl::permits($url)) {
            throw PageFetchFailed::definitely("[{$url}] is not a public address Creeper will fetch.");
        }

        $host = (string) (parse_url($url, PHP_URL_HOST) ?: '');

        if ($host === '') {
            throw PageFetchFailed::definitely("[{$url}] has no host.");
        }

        $literal = trim($host, '[]');

        if (filter_var($literal, FILTER_VALIDATE_IP) !== false) {
            return $literal;
        }

        $addresses = $this->resolve($host);

        // An unresolvable host is unreachable anyway, and DNS is flaky enough
        // that failing hard here would be worse than the risk. Let the
        // connection attempt be the thing that fails.
        if ($addresses === []) {
            return null;
        }

        foreach ($addresses as $address) {
            if (! $this->public($address)) {
                throw PageFetchFailed::definitely(
                    "[{$host}] resolves to [{$address}], which is not a public address."
                );
            }
        }

        return $addresses[0];
    }

    /**
     * Every address a host stands for, over both families.
     *
     * `PublicUrl` only looks up A records, so this is where the IPv6 half of
     * the question gets asked.
     *
     * @return array<int, string>
     */
    private function resolve(string $host): array
    {
        $addresses = gethostbynamel($host) ?: [];

        $records = @dns_get_record($host, DNS_AAAA) ?: [];

        foreach ($records as $record) {
            if (isset($record['ipv6']) && is_string($record['ipv6'])) {
                $addresses[] = $record['ipv6'];
            }
        }

        return array_values(array_unique($addresses));
    }

    /**
     * PHP's own filter knows the private and reserved ranges of both families,
     * including IPv4-mapped IPv6 such as `::ffff:169.254.169.254`.
     */
    private function public(string $address): bool
    {
        return filter_var(
            $address,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
    }

    /**
     * @throws PageFetchFailed
     */
    private function get(string $url, ?string $address, float $deadline): Response
    {
        $remaining = (int) ceil($deadline - microtime(true));

        if ($remaining < 1) {
            throw PageFetchFailed::temporarily('Fetching the page took too long.');
        }

        try {
            return $this->request($url, $address, $remaining)->get($url);
        } catch (ConnectionException $exception) {
            throw PageFetchFailed::temporarily(
                'Could not reach the page: '.$exception->getMessage(),
                $exception,
            );
        }
    }

    private function request(string $url, ?string $address, int $timeout): PendingRequest
    {
        $agent = $this->config['user_agent'] ?? 'CreeperBot/1.0';

        $request = Http::withoutRedirecting()
            ->withHeaders([
                'Accept' => 'text/html,application/xhtml+xml',
                'Accept-Language' => 'en',
                'User-Agent' => is_string($agent) ? $agent : 'CreeperBot/1.0',
            ])
            ->connectTimeout(min(5, $timeout))
            ->timeout($timeout)
            // Streamed so the body can be read under a ceiling rather than
            // pulled into memory whole.
            ->withOptions(['stream' => true]);

        if ($address !== null && ($this->config['pin_address'] ?? true)) {
            $request = $request->withOptions([
                'curl' => [CURLOPT_RESOLVE => [$this->pin($url, $address)]],
            ]);
        }

        return $request;
    }

    /**
     * The curl instruction that forces this host to the address we validated.
     */
    private function pin(string $url, string $address): string
    {
        $host = (string) (parse_url($url, PHP_URL_HOST) ?: '');
        $port = parse_url($url, PHP_URL_PORT)
            ?: (parse_url($url, PHP_URL_SCHEME) === 'http' ? 80 : 443);

        return $host.':'.$port.':'.$address;
    }

    /**
     * Where a redirect is sending us, resolved against where we already are.
     *
     * @throws PageFetchFailed
     */
    private function follow(string $from, Response $response): string
    {
        $location = trim((string) $response->header('Location'));

        if ($location === '') {
            throw PageFetchFailed::definitely('The page redirected without saying where to.');
        }

        try {
            return (string) UriResolver::resolve(new Uri($from), new Uri($location));
        } catch (Throwable $exception) {
            throw PageFetchFailed::definitely(
                "The page redirected to something unusable: [{$location}].",
                $exception,
            );
        }
    }

    /**
     * Turn a final response into a page, or decide why it isn't one.
     *
     * @throws PageFetchFailed
     */
    private function page(Response $response, string $url, int $redirects): FetchedPage
    {
        $this->check($response, $url);

        $type = mb_strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));

        // A shop that sends no Content-Type at all is unusual but not wrong,
        // and the body is very often HTML regardless.
        if ($type !== '' && ! in_array($type, self::READABLE, true)) {
            throw PageFetchFailed::definitely("[{$url}] served [{$type}], which is not a readable page.");
        }

        $body = $this->read($response, $url);

        return new FetchedPage(
            url: $url,
            html: $this->utf8($body, (string) $response->header('Content-Type')),
            contentType: $type,
            redirects: $redirects,
            bytes: strlen($body),
        );
    }

    /**
     * @throws PageFetchFailed
     */
    private function check(Response $response, string $url): void
    {
        if ($response->successful()) {
            return;
        }

        $status = $response->status();

        // Rate limiting and bot-blocking are the shop's mood, not a verdict on
        // the page, so they are worth the queue's backoff.
        if ($status === 429 || $status === 403 || $response->serverError()) {
            throw PageFetchFailed::temporarily("[{$url}] answered {$status}.");
        }

        throw PageFetchFailed::definitely("[{$url}] answered {$status}.");
    }

    /**
     * Read the body, and no more of it than we agreed to.
     *
     * @throws PageFetchFailed
     */
    private function read(Response $response, string $url): string
    {
        $max = (int) ($this->config['max_bytes'] ?? 2097152);
        $declared = (int) $response->header('Content-Length');

        if ($declared > $max) {
            throw PageFetchFailed::definitely("[{$url}] is {$declared} bytes, which is more than Creeper will read.");
        }

        $stream = $response->toPsrResponse()->getBody();
        $body = '';

        while (! $stream->eof()) {
            $body .= $stream->read(self::CHUNK);

            if (strlen($body) > $max) {
                $stream->close();

                throw PageFetchFailed::definitely("[{$url}] is larger than the {$max} bytes Creeper will read.");
            }
        }

        $stream->close();

        return $body;
    }

    /**
     * Normalise to UTF-8, so the digest and the prompt are clean whatever the
     * shop's encoding happens to be.
     */
    private function utf8(string $body, string $contentType): string
    {
        if (preg_match('/charset=["\']?([\w-]+)/i', $contentType, $matches) !== 1) {
            return $body;
        }

        $charset = mb_strtoupper($matches[1]);

        if ($charset === 'UTF-8' || ! in_array($charset, array_map('mb_strtoupper', mb_list_encodings()), true)) {
            return $body;
        }

        $converted = mb_convert_encoding($body, 'UTF-8', $charset);

        return $converted === false ? $body : $converted;
    }
}
