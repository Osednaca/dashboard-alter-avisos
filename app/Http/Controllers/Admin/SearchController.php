<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Advertisers\Models\Advertiser;
use App\Domain\Businesses\Models\Business;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Devices\Models\Device;
use App\Domain\Locations\Models\City;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class SearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));

        if (mb_strlen($term) < 2) {
            return response()->json(['groups' => []]);
        }

        $user = $request->user();
        $like = '%'.$term.'%';
        $groups = [];

        if ($user->hasPermission('businesses.view')) {
            $groups[] = $this->group('Negocios', 'business', Business::query()
                ->where('slug', '!=', Business::INTERNAL_SLUG)
                ->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('contact_name', 'like', $like))
                ->limit(5)->get()
                ->map(fn (Business $business) => ['id' => $business->id, 'title' => $business->name,
                    'subtitle' => null, 'href' => "/admin/businesses/{$business->id}"]));
        }

        if ($user->hasPermission('devices.view')) {
            $groups[] = $this->group('Avisos LED', 'device', Device::query()
                ->with('cities:id,name')
                ->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('uuid', 'like', $like)->orWhere('activation_code', 'like', $like))
                ->limit(5)->get()
                ->map(fn (Device $d) => ['id' => $d->id, 'title' => $d->name, 'subtitle' => $d->cities->pluck('name')->join(', '), 'href' => "/admin/devices/{$d->id}"]));
        }

        if ($user->hasPermission('campaigns.view')) {
            $groups[] = $this->group('Campañas', 'campaign', Campaign::query()
                ->with('advertiser:id,name')
                ->where('name', 'like', $like)
                ->limit(5)->get()
                ->map(fn (Campaign $c) => ['id' => $c->id, 'title' => $c->name, 'subtitle' => $c->advertiser?->name, 'href' => "/admin/campaigns/{$c->id}"]));
        }

        if ($user->hasPermission('advertisers.view')) {
            $groups[] = $this->group('Anunciantes', 'advertiser', Advertiser::query()
                ->where('name', 'like', $like)
                ->limit(5)->get()
                ->map(fn (Advertiser $a) => ['id' => $a->id, 'title' => $a->name, 'subtitle' => $a->status?->label(), 'href' => "/admin/advertisers/{$a->id}"]));
        }

        if ($user->hasPermission('locations.view')) {
            $groups[] = $this->group('Ciudades', 'location', City::query()
                ->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('state', 'like', $like))
                ->limit(5)->get()
                ->map(fn (City $city) => ['id' => $city->id, 'title' => $city->name, 'subtitle' => $city->state, 'href' => "/admin/locations/{$city->id}"]));
        }

        if ($user->hasPermission('users.manage')) {
            $groups[] = $this->group('Usuarios', 'user', User::query()
                ->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('email', 'like', $like))
                ->limit(5)->get()
                ->map(fn (User $u) => ['id' => $u->id, 'title' => $u->name, 'subtitle' => $u->email, 'href' => '/admin/users']));
        }

        $groups = array_values(array_filter($groups, fn ($group) => count($group['items']) > 0));

        return response()->json(['groups' => $groups]);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    protected function group(string $label, string $icon, $items): array
    {
        return ['label' => $label, 'icon' => $icon, 'items' => $items->values()->all()];
    }
}
