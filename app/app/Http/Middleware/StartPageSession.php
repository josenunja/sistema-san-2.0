<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Session\Store;

class StartPageSession extends StartSession
{
    /** @param Store $session */
    protected function storeCurrentUrl(Request $request, $session): void
    {
        if (in_array($request->header('Sec-Fetch-Dest'), ['image', 'font', 'style', 'script'], true)
            || $request->routeIs('product.image', 'store.logo', 'store.banner', 'manage.onboarding.logo', 'manage.onboarding.banner')) {
            return;
        }
        parent::storeCurrentUrl($request, $session);
    }
}
