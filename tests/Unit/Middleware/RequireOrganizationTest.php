<?php

declare(strict_types=1);

use App\Http\Middleware\RequireOrganization;
use App\Models\Organization;
use App\Support\OrganizationContext;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

it('redirects to organization creation when nothing is bound', function (): void {
    $response = new RequireOrganization(new OrganizationContext)
        ->handle(Request::create('/dashboard'), fn (): Response => new Response);

    expect($response->getStatusCode())->toBe(302)
        ->and($response->headers->get('Location'))->toBe(route('organization.create'));
});

it('passes the request through when an organization is bound', function (): void {
    $context = new OrganizationContext;
    $context->set(Organization::factory()->create());

    $response = new RequireOrganization($context)
        ->handle(Request::create('/dashboard'), fn (): Response => new Response('ok'));

    expect($response->getContent())->toBe('ok');
});
