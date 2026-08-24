<?php

use Goldnead\StatamicConsent\Http\Controllers\RecordController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Route;

/*
 * The proof endpoint. Registered whatever the config says, and inert when the
 * record is switched off — a route that appears and disappears with a setting
 * makes `route:list` a poor description of the application.
 *
 * CSRF is dropped on purpose. The endpoint reads nothing from the request but
 * its own cookie, and SameSite=Lax keeps that cookie off a cross-site post, so
 * a forged request has nothing to forge with. A token would instead have to be
 * embedded in the page — and would be stale on any page served from a cache.
 */
Route::post('/!/statamic-consent/record', RecordController::class)
    ->middleware([ThrottleRequests::class.':'.(int) config('statamic-consent.record.rate_limit', 30).',1'])
    ->withoutMiddleware([
        // Drei Namen, nicht einer. In Laravel 12/13 steht in der `web`-Gruppe
        // `PreventRequestForgery`; `VerifyCsrfToken` ist dessen **Unterklasse**,
        // und `Router::resolveMiddleware()` entfernt nur, was Unterklasse des
        // Ausgeschlossenen ist — die Unterklasse auszuschliessen entfernt die
        // Oberklasse also nicht. Die Prüfung bleibt stehen, der Anbieter
        // schickt kein Token, und jede echte Zustellung endet mit 419.
        //
        // Im Testlauf ist das unsichtbar: `PreventRequestForgery::handle()`
        // steigt bei `runningUnitTests()` sofort aus. Deshalb prüft ein Test
        // die aufgesammelte Middleware-Liste statt eine Anfrage.
        //
        // Dieselben drei Namen schliesst Statamic selbst aus
        // (`vendor/statamic/cms/routes/web.php:106`).
        'App\Http\Middleware\VerifyCsrfToken',
        'Illuminate\Foundation\Http\Middleware\VerifyCsrfToken',
        'Illuminate\Foundation\Http\Middleware\ValidateCsrfToken',
        'Illuminate\Foundation\Http\Middleware\PreventRequestForgery',
    ])
    ->name('statamic-consent.record');
