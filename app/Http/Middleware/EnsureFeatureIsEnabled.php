<?php

namespace App\Http\Middleware;

use App\Enums\Feature;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Block the routes of a feature the school turned off.
 *
 * The records stay. Only the way in closes, so a school can turn a feature
 * back on and find its history where it left it.
 */
class EnsureFeatureIsEnabled
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $case = Feature::tryFrom($feature);

        // An unknown feature slug is a missing page, not a server fault.
        abort_if($case === null, 404);
        abort_unless(feature_enabled($case), 404);

        return $next($request);
    }
}
