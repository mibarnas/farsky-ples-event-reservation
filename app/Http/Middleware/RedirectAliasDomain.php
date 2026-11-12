<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every request arriving on an alias domain (e.g. farskyplesciernavoda.sk) is
 * redirected to the event chosen in the admin dashboard, on the main APP_URL
 * domain. Without a chosen event it falls back to the main domain's homepage.
 */
class RedirectAliasDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower($request->getHost());
        $appUrl = rtrim((string) config('app.url'), '/');

        if (! in_array($host, config('app.alias_domains'), true)
            || $host === strtolower((string) parse_url($appUrl, PHP_URL_HOST))) {
            return $next($request);
        }

        $event = Setting::aliasEvent();

        // Temporary (302) and never cached on purpose: the target changes whenever
        // the admin picks another event, so no browser or proxy may remember it.
        return redirect()->away($event ? $appUrl.'/event/'.rawurlencode($event->url_slug) : $appUrl)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }
}
