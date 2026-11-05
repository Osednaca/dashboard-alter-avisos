<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Media\Models\MediaAsset;
use App\Domain\Media\Services\LiveSourceParser;
use App\Domain\Operations\Actions\RecordAudit;
use App\Http\Controllers\Controller;
use App\Http\Presenters\EntityPresenter;
use App\Http\Requests\Admin\LiveStreamRequest;
use Illuminate\Support\Facades\URL;

class LiveStreamController extends Controller
{
    public function detect(LiveStreamRequest $request, LiveSourceParser $parser)
    {
        $source = $parser->parse($request->validated('url'));

        return response()->json(['source' => $source, 'preview_url' => $source['provider'] === 'hls' ? null
            : URL::temporarySignedRoute('live.preview', now()->addMinutes(30), ['url' => $source['original_url']])]);
    }

    public function store(LiveStreamRequest $request, LiveSourceParser $parser, RecordAudit $audit)
    {
        $source = $parser->parse($request->validated('url'));
        $asset = MediaAsset::create([
            'type' => 'live_stream', 'filename' => $request->validated('name') ?: strtoupper($source['provider']).' · '.$source['source_id'],
            'storage_path' => '', 'mime_type' => 'application/x-live-stream',
            'checksum' => hash('sha256', $source['original_url']), 'processing_status' => 'ready',
            'metadata' => ['live' => $source, 'created_by' => $request->user()->id],
        ]);
        $audit->handle('live_stream.created', $asset, [], ['provider' => $source['provider']]);

        return response()->json(['media' => EntityPresenter::mediaAsset($asset)], 201);
    }
}
