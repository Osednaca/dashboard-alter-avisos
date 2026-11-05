<?php

namespace App\Http\Controllers\Business;

use App\Domain\Devices\Models\Device;
use App\Http\Controllers\Business\Concerns\AuthorizesBusiness;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    use AuthorizesBusiness;

    public function __invoke(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));

        if (mb_strlen($term) < 2) {
            return response()->json(['groups' => []]);
        }

        $user = $request->user();
        $like = '%'.$term.'%';
        $groups = [];

        if ($user->hasPermission('business.media.view')) {
            $media = $this->businessMediaQuery()
                ->where('filename', 'like', $like)
                ->latest()
                ->limit(5)
                ->get()
                ->map(fn ($asset) => [
                    'id' => $asset->id,
                    'title' => $asset->filename,
                    'subtitle' => $asset->type?->label(),
                    'href' => '/business/library',
                ]);

            if ($media->isNotEmpty()) {
                $groups[] = ['label' => 'Biblioteca', 'icon' => 'media', 'items' => $media->values()->all()];
            }
        }

        if ($user->hasPermission('business.devices.view')) {
            $screens = $this->business()->devices()
                ->where('name', 'like', $like)
                ->with('location')
                ->limit(5)
                ->get()
                ->map(fn (Device $device) => [
                    'id' => $device->id,
                    'title' => $device->name,
                    'subtitle' => $device->location?->name,
                    'href' => '/business/screens/'.$device->id,
                ]);

            if ($screens->isNotEmpty()) {
                $groups[] = ['label' => 'Pantallas', 'icon' => 'device', 'items' => $screens->values()->all()];
            }
        }

        if ($user->hasPermission('business.schedules.view')) {
            $schedules = $this->business()->schedules()
                ->where('name', 'like', $like)
                ->limit(5)
                ->get()
                ->map(fn ($schedule) => [
                    'id' => $schedule->id,
                    'title' => $schedule->name ?: 'Programación',
                    'subtitle' => 'Programación',
                    'href' => '/business/schedule?edit='.$schedule->id,
                ]);

            if ($schedules->isNotEmpty()) {
                $groups[] = ['label' => 'Programación', 'icon' => 'schedule', 'items' => $schedules->values()->all()];
            }
        }

        return response()->json(['groups' => $groups]);
    }
}
