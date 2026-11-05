<?php

namespace App\Domain\Media\Services;

use App\Domain\Media\Enums\MediaType;
use App\Domain\Media\Models\MediaAsset;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Facades\URL;
use LogicException;

class LiveSourcePayload
{
    public function forAsset(MediaAsset $asset): ?array
    {
        if ($asset->type !== MediaType::LiveStream) {
            return null;
        }
        $source = app(LiveSourceParser::class)->parse($asset->metadata['live']['original_url']);

        return $source + [
            'allowed_hosts' => config('live.provider_hosts.'.$source['provider'], []),
            'embed_url' => $this->signedEmbedUrl($asset, false),
            'embed_audio_url' => $this->signedEmbedUrl($asset, true),
            'hls_hosts' => config('live.hls_hosts'),
            'connect_timeout_seconds' => max(5, min(120, config('live.connect_timeout_seconds'))),
            'retry_seconds' => max(10, config('live.retry_seconds')),
            'max_retry_seconds' => max(30, config('live.max_retry_seconds')),
            'unverified_seconds' => max(30, config('live.unverified_seconds')),
        ];
    }

    /**
     * Detect persisted live embed links that cannot be trusted by the configured player origin.
     * Static-only manifests do not require an HTTPS application URL.
     *
     * @param  array<string, mixed>  $payload
     */
    public function hasNoncanonicalEmbedUrls(array $payload): bool
    {
        $origin = null;

        foreach ($payload['assets'] ?? [] as $asset) {
            $live = $asset['live'] ?? null;
            if (! is_array($live)) {
                continue;
            }

            $origin ??= $this->canonicalHttpsOrigin();
            foreach (['embed_url', 'embed_audio_url'] as $key) {
                if (! is_string($live[$key] ?? null) || ! $this->matchesCanonicalOrigin($live[$key], $origin)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function signedEmbedUrl(MediaAsset $asset, bool $audio): string
    {
        /** @var UrlGenerator $generator */
        $generator = clone URL::getFacadeRoot();
        $generator->useOrigin($this->canonicalHttpsOrigin());
        $generator->forceHttps();

        return $generator->signedRoute('live.embed', ['media' => $asset->id, 'audio' => $audio ? 1 : 0]);
    }

    private function canonicalHttpsOrigin(): string
    {
        $configured = trim((string) config('app.url'));
        $parts = parse_url($configured);
        $path = is_array($parts) ? ($parts['path'] ?? '') : null;

        if (! is_array($parts)
            || ! filter_var($configured, FILTER_VALIDATE_URL)
            || strtolower($parts['scheme'] ?? '') !== 'https'
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || ! in_array($path, ['', '/'], true)
            || (isset($parts['port']) && $parts['port'] !== 443)) {
            throw new LogicException('Live embed signing requires config(app.url) to be an HTTPS origin.');
        }

        return 'https://'.strtolower($parts['host']);
    }

    private function matchesCanonicalOrigin(string $url, string $origin): bool
    {
        $candidate = parse_url($url);
        $canonical = parse_url($origin);

        return is_array($candidate)
            && is_array($canonical)
            && strtolower($candidate['scheme'] ?? '') === 'https'
            && strtolower($candidate['host'] ?? '') === strtolower($canonical['host'] ?? '')
            && in_array($candidate['port'] ?? 443, [443], true)
            && ! isset($candidate['user'])
            && ! isset($candidate['pass'])
            && preg_match('#^/live/embed/[0-9]+$#D', $candidate['path'] ?? '') === 1;
    }
}
