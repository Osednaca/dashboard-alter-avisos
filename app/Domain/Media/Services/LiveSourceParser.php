<?php

namespace App\Domain\Media\Services;

use Illuminate\Validation\ValidationException;

class LiveSourceParser
{
    public function parse(string $url): array
    {
        $url = trim($url);
        $p = parse_url($url);
        if (! $p || ! filter_var($url, FILTER_VALIDATE_URL) || ($p['scheme'] ?? '') !== 'https'
            || isset($p['user']) || isset($p['pass']) || isset($p['fragment'])
            || (isset($p['port']) && $p['port'] !== 443) || preg_match('/[\x00-\x20\\\\]/', $url)) {
            $this->invalid();
        }
        $host = strtolower($p['host'] ?? '');
        $path = $p['path'] ?? '/';
        parse_str($p['query'] ?? '', $query);
        $provider = null;
        $id = null;
        if (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtu.be'], true)) {
            $provider = 'youtube';
            if ($host === 'youtu.be') {
                $id = trim($path, '/');
            } elseif ($path === '/watch') {
                $id = $query['v'] ?? null;
            } elseif (preg_match('#^/(?:live|embed|shorts)/([A-Za-z0-9_-]{11})/?$#', $path, $m)) {
                $id = $m[1];
            }
            if (! is_string($id) || ! preg_match('/^[A-Za-z0-9_-]{11}$/D', $id)) {
                $this->invalid();
            }
            $url = 'https://www.youtube.com/watch?v='.$id;
        } elseif (in_array($host, ['twitch.tv', 'www.twitch.tv', 'kick.com', 'www.kick.com'], true)) {
            $provider = str_contains($host, 'twitch') ? 'twitch' : 'kick';
            if (! preg_match('#^/([A-Za-z0-9_-]{1,50})/?$#D', $path, $m)
                || in_array(strtolower($m[1]), ['directory', 'videos', 'settings', 'search', 'login', 'signup', 'categories'], true)) {
                $this->invalid();
            }
            $id = strtolower($m[1]);
            $url = 'https://'.($provider === 'twitch' ? 'www.twitch.tv' : 'kick.com').'/'.$id;
        } elseif (str_ends_with(strtolower($path), '.m3u8')) {
            if (! in_array($host, config('live.hls_hosts'), true) || filter_var($host, FILTER_VALIDATE_IP) || ! str_contains($host, '.')) {
                throw ValidationException::withMessages(['url' => 'El dominio HLS debe estar aprobado en LIVE_HLS_HOSTS.']);
            }
            $provider = 'hls';
            $id = hash('sha256', $url);
        } else {
            $this->invalid();
        }

        return ['provider' => $provider, 'original_url' => $url, 'source_id' => $id, 'status' => 'configured'];
    }

    private function invalid(): never
    {
        throw ValidationException::withMessages(['url' => 'Usa una URL HTTPS de YouTube (video o directo), un canal de Twitch/Kick o un HLS .m3u8 aprobado.']);
    }
}
