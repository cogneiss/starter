<?php

declare(strict_types=1);

use App\Http\Controllers\Api\CatalogueController;
use App\Http\Controllers\Api\ResourceController;
use App\Http\Middleware\ApplyRateTier;
use App\Http\Middleware\EnsureTokenMatchesOrganization;
use App\Http\Middleware\LogApiRequest;
use Illuminate\Support\Facades\Route;

/*
 * The read API. Three GET routes and nothing else, so any write verb is a 405
 * before it reaches a controller. Every request authenticates with a bearer
 * token, and EnsureTokenMatchesOrganization pins the request to the token's
 * organization — nothing in the request itself can move it.
 */
// LogApiRequest sits outside ApplyRateTier so a throttled request is still a
// logged request.
Route::middleware(['auth:sanctum', EnsureTokenMatchesOrganization::class, LogApiRequest::class, ApplyRateTier::class])->group(function (): void {
    Route::get('/', CatalogueController::class)->name('api.catalogue');
    Route::get('{resource}', [ResourceController::class, 'index'])->name('api.resources.index');
    Route::get('{resource}/{id}', [ResourceController::class, 'show'])->name('api.resources.show');
});
