<?php

namespace Tests\Feature;

use App\Contracts\ReadinessCheckerInterface;
use Tests\TestCase;

class HealthEndpointsTest extends TestCase
{
    public function test_health_returns_ok_with_meta(): void
    {
        $res = $this->getJson('/api/health');

        $res->assertOk()
            ->assertJsonStructure(['status', 'version', 'commit'])
            ->assertJson(['status' => 'ok']);
    }

    public function test_live_always_200(): void
    {
        $this->getJson('/api/live')->assertOk()->assertJson(['status' => 'alive']);
    }

    public function test_ready_returns_200_when_dependencies_ok(): void
    {
        $this->app->bind(ReadinessCheckerInterface::class, function () {
            return new class () implements ReadinessCheckerInterface {
                public function check(): array
                {
                    return [
                        'ok'      => true,
                        'reasons' => [],
                        'details' => [
                            'db'    => ['status' => 'up'],
                            'redis' => ['status' => 'up'],
                        ],
                    ];
                }
            };
        });

        $this->getJson('/api/ready')
            ->assertOk()
            ->assertJson([
                'status'  => 'ok',
                'details' => [
                    'db'    => ['status' => 'up'],
                    'redis' => ['status' => 'up'],
                ],
            ]);
    }

    public function test_ready_returns_503_when_dependency_down(): void
    {
        $this->app->bind(ReadinessCheckerInterface::class, function () {
            return new class () implements ReadinessCheckerInterface {
                public function check(): array
                {
                    return [
                        'ok'      => false,
                        'reasons' => ['db' => 'database unavailable'],
                        'details' => [
                            'db' => ['status' => 'down', 'error' => 'SQLSTATE[HY000] Connection refused'],
                        ],
                    ];
                }
            };
        });

        $this->getJson('/api/ready')
            ->assertStatus(503)
            ->assertJson([
                'status' => 'error',
                'reason' => 'database unavailable',
            ])
            ->assertJsonStructure([
                'status', 'reason', 'details' => ['db' => ['status', 'error']]
            ]);
    }
}
