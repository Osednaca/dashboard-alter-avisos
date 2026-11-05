<?php

namespace App\Http\Controllers;

use App\Domain\Operations\Services\AndroidUpdatePublisher;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AndroidUpdateDownloadController extends Controller
{
    public function show(string $filename, AndroidUpdatePublisher $publisher): BinaryFileResponse
    {
        $json = $filename === 'latest.json';
        abort_unless($json || preg_match('/\Asignage-[A-Za-z0-9][A-Za-z0-9._-]{0,63}\.apk\z/D', $filename), 404);
        $path = $publisher->directory().'/'.$filename;
        abort_unless(is_file($path) && ! is_link($path), 404);

        return response()->download($path, $filename, [
            'Content-Type' => $json ? 'application/json' : 'application/vnd.android.package-archive',
            'Cache-Control' => $json ? 'no-store, max-age=0' : 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
