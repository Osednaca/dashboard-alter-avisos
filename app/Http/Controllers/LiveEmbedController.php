<?php

namespace App\Http\Controllers;

use App\Domain\Media\Enums\MediaType;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\Media\Services\LiveSourceParser;
use Illuminate\Http\Request;

class LiveEmbedController extends Controller
{
    public function show(Request $request, MediaAsset $media, LiveSourceParser $parser)
    {
        abort_unless($media->type === MediaType::LiveStream && $media->processing_status->value === 'ready', 404);

        return $this->page($parser->parse($media->metadata['live']['original_url']), $request->boolean('audio'));
    }

    public function preview(Request $request, LiveSourceParser $parser)
    {
        $data = $request->validate(['url' => ['required', 'string', 'max:2048']]);

        return $this->page($parser->parse($data['url']), false);
    }

    private function page(array $source, bool $audio)
    {
        abort_if($source['provider'] === 'hls', 404);
        $nonce = base64_encode(random_bytes(24));
        $origin = rtrim(config('app.url'), '/');
        $parents = config('live.twitch_parents');
        abort_if($source['provider'] === 'twitch' && empty($parents), 503, 'Configura LIVE_TWITCH_PARENTS.');
        $script = $source['provider'] === 'youtube' ? 'https://www.youtube.com https://s.ytimg.com' : 'https://player.twitch.tv';
        $frame = match ($source['provider']) {
            'youtube' => 'https://www.youtube.com', 'twitch' => 'https://player.twitch.tv', 'kick' => 'https://player.kick.com',
        };

        return response()->view('live.embed', compact('source', 'audio', 'nonce', 'origin', 'parents'))
            ->header('Content-Security-Policy', "default-src 'none'; script-src 'nonce-{$nonce}' {$script}; style-src 'nonce-{$nonce}'; frame-src {$frame}; img-src 'none'; connect-src 'none'; base-uri 'none'; object-src 'none'; form-action 'none'; frame-ancestors 'self'")
            ->header('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('Cache-Control', 'private, no-store')
            ->header('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), autoplay=(self "'.$frame.'")');
    }
}
