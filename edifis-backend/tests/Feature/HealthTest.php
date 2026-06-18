<?php

use function Pest\Laravel\getJson;

beforeEach(function () {
    config(['edifis.mode' => 'local']);
    config(['app.version' => '0.1.0']);
});

it('returns health with local mode', function () {
    $response = getJson('/api/health');

    $response->assertOk()
        ->assertJson([
            'status' => 'ok',
            'mode' => 'local',
            'version' => '0.1.0',
        ]);
});

it('returns health with cloud mode', function () {
    config(['edifis.mode' => 'cloud']);

    $response = getJson('/api/health');

    $response->assertOk()
        ->assertJson([
            'status' => 'ok',
            'mode' => 'cloud',
            'version' => '0.1.0',
        ]);
});
