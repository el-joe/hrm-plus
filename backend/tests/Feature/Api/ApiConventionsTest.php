<?php

use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * GET the employee's login history (the reference list endpoint) with a token.
 */
function listLogins(User $employee, string $query = '')
{
    return test()->withToken($employee->createToken('phone', ['employee'])->plainTextToken)
        ->getJson('/api/v1/auth/login-history'.$query);
}

describe('list conventions', function () {
    it('paginates with the pagination meta and only lists the employee\'s own records', function () {
        $employee = User::factory()->employee()->create();
        LoginHistory::factory()->count(3)->for($employee)->create();
        LoginHistory::factory()->count(2)->for(User::factory()->employee())->create();

        listLogins($employee, '?per_page=2&page=2')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.pagination', ['current_page' => 2, 'per_page' => 2, 'total' => 3, 'last_page' => 2]);
    });

    it('caps per_page at the maximum', function () {
        $employee = User::factory()->employee()->create();

        listLogins($employee, '?per_page=5000')->assertJsonPath('meta.pagination.per_page', 100);
    });

    it('applies an allowed filter', function () {
        $employee = User::factory()->employee()->create();
        LoginHistory::factory()->for($employee)->create(['successful' => true]);
        LoginHistory::factory()->count(2)->for($employee)->create(['successful' => false, 'failure_reason' => 'invalid_credentials']);

        listLogins($employee, '?filter[successful]=false')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.successful', false);
    });

    it('sorts newest first by default and ascending on request', function () {
        $employee = User::factory()->employee()->create();
        $older = LoginHistory::factory()->for($employee)->create(['created_at' => now()->subDay()]);
        $newer = LoginHistory::factory()->for($employee)->create(['created_at' => now()]);

        listLogins($employee)->assertJsonPath('data.*.id', [$newer->id, $older->id]);
        listLogins($employee, '?sort=created_at')->assertJsonPath('data.*.id', [$older->id, $newer->id]);
    });

    it('rejects a filter or sort that is not allowed', function (string $query, string $field) {
        listLogins(User::factory()->employee()->create(), $query)
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_error')
            ->assertJsonValidationErrors([$field]);
    })->with([
        'unknown filter' => ['?filter[user_id]=1', 'filter'],
        'unknown sort' => ['?sort=email', 'sort'],
    ]);
});

describe('error envelope', function () {
    beforeEach(function () {
        Route::middleware('api')->prefix('api/v1/_test')->group(function () {
            Route::get('boom', fn () => throw new RuntimeException('SQLSTATE secret details'));
        });
    });

    it('hides the exception message of a server error', function () {
        config(['app.debug' => false]);

        $this->getJson('/api/v1/_test/boom')
            ->assertInternalServerError()
            ->assertExactJson(['success' => false, 'message' => __('api.server_error'), 'code' => 'server_error']);
    });

    it('answers unknown endpoints and wrong methods in the envelope', function () {
        $this->getJson('/api/v1/nothing-here')
            ->assertNotFound()
            ->assertJsonPath('code', 'not_found');

        $this->getJson('/api/v1/auth/login')
            ->assertMethodNotAllowed()
            ->assertJsonPath('code', 'method_not_allowed');
    });

    it('answers in JSON even when the client asks for HTML', function () {
        $this->get('/api/v1/auth/me', ['Accept' => 'text/html'])
            ->assertUnauthorized()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('code', 'unauthenticated');
    });

    it('rate limits the API per client', function () {
        foreach (range(1, 60) as $request) {
            $this->getJson('/api/v1/auth/me')->assertUnauthorized();
        }

        $this->getJson('/api/v1/auth/me')
            ->assertTooManyRequests()
            ->assertJsonPath('code', 'too_many_requests');
    });
});
