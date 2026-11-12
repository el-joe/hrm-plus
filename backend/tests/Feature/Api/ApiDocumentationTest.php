<?php

use App\Models\User;

/*
| OpenAPI documentation (Phase 10, dedoc/scramble at /docs/api and /docs/api.json): open in the local
| environment only; elsewhere (this test runs in "testing") a Super Admin signed in to the admin area.
*/

it('refuses guests, employees and admins without the Super Admin role', function () {
    seedRoles();

    $this->get('/docs/api')->assertForbidden();
    $this->get('/docs/api.json')->assertForbidden();

    $this->actingAs(User::factory()->employee()->create(), 'employee')->get('/docs/api')->assertForbidden();
    $this->actingAs(adminWithRoles('HR Manager'), 'admin')->get('/docs/api.json')->assertForbidden();
});

it('serves the UI and the OpenAPI document of /api/v1 to a Super Admin with the bearer scheme and the envelope', function () {
    $this->actingAs(superAdmin(), 'admin')->get('/docs/api')->assertOk();

    $document = $this->actingAs(superAdmin(), 'admin')->get('/docs/api.json')->assertOk()->json();

    expect($document['servers'][0]['url'])->toEndWith('/api/v1')
        ->and($document['paths'])->toHaveKeys(['/auth/login', '/auth/password/forgot', '/app/config', '/me/home', '/me/documents/all', '/me/profile'])
        ->and($document['components']['securitySchemes'])->not->toBeEmpty()
        // Public routes are documented without the bearer requirement.
        ->and($document['paths']['/app/config']['get']['security'])->toBe([])
        // ApiResponse::success(Resource) is documented with the resource schema inside the envelope.
        ->and($document['paths']['/me/profile']['get']['responses']['200']['content']['application/json']['schema']['properties']['data']['$ref'])
        ->toBe('#/components/schemas/EmployeeProfileResource');
});
