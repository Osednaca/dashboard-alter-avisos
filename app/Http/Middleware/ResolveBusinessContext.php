<?php

namespace App\Http\Middleware;

use App\Support\BusinessAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveBusinessContext
{
    /**
     * Resolve the business for the authenticated user and reject users without
     * any business membership. All business queries are scoped to this context,
     * so a business can never reach another business by changing an ID.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        $businesses = $user->businesses()->get();

        if ($businesses->isEmpty()) {
            abort(403, 'Tu usuario no está asociado a ningún negocio.');
        }

        $sessionId = (int) $request->session()->get('business_id', 0);

        $current = $businesses->firstWhere('id', $sessionId)
            ?? $businesses->first(fn ($business) => (bool) $business->pivot->is_primary)
            ?? $businesses->first();

        $request->attributes->set('current_business', $current);
        BusinessAccess::set($current);

        $request->attributes->set('business_context', [
            'current' => [
                'id' => $current->id,
                'name' => $current->name,
                'slug' => $current->slug,
                'category' => $current->category?->value,
                'category_label' => $current->category?->label(),
                'timezone' => $current->timezone,
                'logo_url' => $current->logo_url,
            ],
            'available' => $businesses->map(fn ($business) => [
                'id' => $business->id,
                'name' => $business->name,
                'is_primary' => (bool) $business->pivot->is_primary,
            ])->values()->all(),
        ]);

        return $next($request);
    }
}
