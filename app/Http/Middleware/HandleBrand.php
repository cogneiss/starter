<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Support\BrandPalette;
use App\Support\OrganizationContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Puts the current organization's derived palette in the root template, the
 * same way the appearance cookie gets there.
 *
 * It has to be the root template rather than a shared Inertia prop: props
 * arrive with the page, so a palette applied from one would repaint the
 * interface a moment after the reader already saw it in the wrong colours.
 */
final readonly class HandleBrand
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $organization = resolve(OrganizationContext::class)->get();

        View::share('brand', BrandPalette::from(
            $organization instanceof Organization && $organization->brand_primary_color !== null
                ? $organization->brand_primary_color
                : BrandPalette::DEFAULT_PRIMARY,
            $organization instanceof Organization && $organization->brand_accent_color !== null
                ? $organization->brand_accent_color
                : BrandPalette::DEFAULT_ACCENT,
        ));

        return $next($request);
    }
}
