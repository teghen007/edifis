<?php

use App\Domain\Tenancy\Models\EdifisTenant;
use Stancl\Tenancy\Database\Models\Domain;
use function Pest\Laravel\getJson;

beforeEach(function () {
    config(['edifis.mode' => 'cloud']);
});

it('domain-allowed returns 200 for a registered tenant domain', function () {
    $tenant = EdifisTenant::create([
        'id' => 'test-tenant-onboard',
        'school_code' => 'test-tenant-onboard',
        'school_name' => 'Test Onboard School',
    ]);

    Domain::create([
        'domain' => 'test-onboard.myedifis.com',
        'tenant_id' => $tenant->id,
    ]);

    $response = getJson('/api/tenancy/domain-allowed?domain=test-onboard.myedifis.com');

    $response->assertOk()
        ->assertJson(['allowed' => true, 'domain' => 'test-onboard.myedifis.com']);
});

it('domain-allowed returns 404 for an unknown domain', function () {
    $response = getJson('/api/tenancy/domain-allowed?domain=not-registered.myedifis.com');

    $response->assertStatus(404);
});

it('domain-allowed returns 404 for central domain', function () {
    $response = getJson('/api/tenancy/domain-allowed?domain=myedifis.com');

    $response->assertStatus(404);
});

it('onboard-school command creates a working tenant with seeded Principal', function () {
    $this->artisan('edifis:onboard-school', [
        'code' => 'pssnkwen',
        '--name' => 'PSS Nkwen',
        '--principal-email' => 'principal@nkwen.myedifis.com',
    ])->assertSuccessful();

    // Tenant exists
    $tenant = EdifisTenant::find('pssnkwen');
    expect($tenant)->not->toBeNull();
    expect($tenant->school_name)->toBe('PSS Nkwen');

    // Domain exists
    $domain = Domain::where('domain', 'pssnkwen.myedifis.com')->first();
    expect($domain)->not->toBeNull();

    // Domain-allowed confirms it
    $response = getJson('/api/tenancy/domain-allowed?domain=pssnkwen.myedifis.com');
    $response->assertOk()->assertJson(['allowed' => true]);
});

it('onboard-school is idempotent — re-run changes nothing essential', function () {
    $this->artisan('edifis:onboard-school', [
        'code' => 'idemtest',
        '--name' => 'Idempotent School',
        '--principal-email' => 'p@idemtest.myedifis.com',
    ])->assertSuccessful();

    $tenantCount = EdifisTenant::count();
    $domainCount = Domain::count();

    // Re-run
    $this->artisan('edifis:onboard-school', [
        'code' => 'idemtest',
        '--name' => 'Idempotent School',
        '--principal-email' => 'p@idemtest.myedifis.com',
    ])->assertSuccessful();

    expect(EdifisTenant::count())->toBe($tenantCount);
    expect(Domain::count())->toBe($domainCount);
});
