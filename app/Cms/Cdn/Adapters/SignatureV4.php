<?php

namespace App\Cms\Cdn\Adapters;

/**
 * AWS Signature Version 4, for the S3 REST API.
 *
 * This exists so the CMS can talk to S3, R2, Spaces, GCS, Wasabi, Backblaze
 * and MinIO without aws/aws-sdk-php, which is larger than this entire
 * application and cannot be installed by a buyer with no shell access.
 *
 * Only the header-signing variant is implemented, because that is all an
 * upload needs. Presigned query URLs are deliberately absent: the media
 * library serves public files, and a signed URL that expires would break
 * every cached page that embedded it.
 */
class SignatureV4
{
    private const ALGORITHM = 'AWS4-HMAC-SHA256';

    public function __construct(
        private string $key,
        private string $secret,
        private string $region,
        private string $service = 's3',
    ) {}

    /**
     * Returns the headers to send, including Authorization.
     *
     * @param  string  $method   GET, PUT, HEAD, DELETE
     * @param  string  $url      the full request URL
     * @param  array<string,string>  $headers  headers that are part of the request
     * @param  string  $payloadHash  hex sha256 of the body ('' hashes to the empty digest)
     * @param  ?int  $time  signing time; only ever passed by the tests, to reproduce AWS's worked example
     * @return array<string,string>
     */
    public function headers(string $method, string $url, array $headers, string $payloadHash, ?int $time = null): array
    {
        $parts = parse_url($url);
        $host = $parts['host'] ?? '';

        if (isset($parts['port'])) {
            $host .= ':'.$parts['port'];
        }

        $timestamp = gmdate('Ymd\THis\Z', $time ?? time());
        $date = substr($timestamp, 0, 8);

        // S3 requires these two on every signed request, and they have to be
        // part of the signature or the service rejects them as tampered with.
        $headers = array_merge($headers, [
            'Host' => $host,
            'x-amz-date' => $timestamp,
            'x-amz-content-sha256' => $payloadHash,
        ]);

        // Canonical headers: lowercase names, sorted, whitespace collapsed.
        $canonical = [];

        foreach ($headers as $name => $value) {
            $canonical[strtolower(trim($name))] = preg_replace('/\s+/', ' ', trim((string) $value));
        }

        ksort($canonical);

        $signedHeaders = implode(';', array_keys($canonical));

        $canonicalHeaders = '';

        foreach ($canonical as $name => $value) {
            $canonicalHeaders .= $name.':'.$value."\n";
        }

        $canonicalRequest = implode("\n", [
            strtoupper($method),
            $this->canonicalPath($parts['path'] ?? '/'),
            $this->canonicalQuery($parts['query'] ?? ''),
            $canonicalHeaders,
            $signedHeaders,
            $payloadHash,
        ]);

        $scope = "{$date}/{$this->region}/{$this->service}/aws4_request";

        $stringToSign = implode("\n", [
            self::ALGORITHM,
            $timestamp,
            $scope,
            hash('sha256', $canonicalRequest),
        ]);

        $signature = hash_hmac('sha256', $stringToSign, $this->signingKey($date));

        $headers['Authorization'] = self::ALGORITHM
            ." Credential={$this->key}/{$scope},"
            ." SignedHeaders={$signedHeaders},"
            ." Signature={$signature}";

        // Host is set by the HTTP client from the URL; sending it twice makes
        // some proxies unhappy, and it is already covered by the signature.
        unset($headers['Host']);

        return $headers;
    }

    /**
     * Each path segment is percent-encoded once, and the slashes between them
     * are left alone. S3 is the one AWS service that must NOT have its path
     * normalised or double-encoded - a key may legitimately contain "..".
     */
    private function canonicalPath(string $path): string
    {
        if ($path === '') {
            return '/';
        }

        return implode('/', array_map(rawurlencode(...), explode('/', $path)));
    }

    private function canonicalQuery(string $query): string
    {
        if ($query === '') {
            return '';
        }

        $pairs = [];

        foreach (explode('&', $query) as $pair) {
            [$name, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $pairs[rawurlencode(rawurldecode($name))] = rawurlencode(rawurldecode($value));
        }

        ksort($pairs);

        return implode('&', array_map(fn ($k, $v) => "{$k}={$v}", array_keys($pairs), $pairs));
    }

    /** The date/region/service-scoped key, derived fresh each day. */
    private function signingKey(string $date): string
    {
        $key = hash_hmac('sha256', $date, 'AWS4'.$this->secret, true);
        $key = hash_hmac('sha256', $this->region, $key, true);
        $key = hash_hmac('sha256', $this->service, $key, true);

        return hash_hmac('sha256', 'aws4_request', $key, true);
    }

    /** The digest of an empty body, which GET and DELETE always carry. */
    public static function emptyPayloadHash(): string
    {
        return hash('sha256', '');
    }
}
