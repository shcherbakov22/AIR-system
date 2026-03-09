<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EdgeClientHeartbeatTest extends TestCase
{
    use RefreshDatabase;

    public function test_edge_client_heartbeat_is_recorded_with_a_valid_shared_token(): void
    {
        config()->set('services.edge_clients.shared_token', 'test-edge-token');

        $response = $this->withHeader('X-Edge-Client-Token', 'test-edge-token')
            ->postJson(route('api.edge-clients.heartbeat'), [
                'client_key' => 'extension-dev',
                'client_type' => 'browser_extension',
                'label' => 'Extension Dev',
                'version' => '0.1.0',
                'capabilities' => ['heartbeat'],
                'meta' => [
                    'reason' => 'test',
                ],
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('accepted', true)
            ->assertJsonPath('edge_client.client_key', 'extension-dev')
            ->assertJsonPath('edge_client.client_type', 'browser_extension');

        $this->assertDatabaseHas('edge_clients', [
            'client_key' => 'extension-dev',
            'client_type' => 'browser_extension',
            'label' => 'Extension Dev',
        ]);

        $this->assertDatabaseCount('edge_client_heartbeats', 1);
    }

    public function test_edge_client_heartbeat_is_rejected_with_an_invalid_shared_token(): void
    {
        config()->set('services.edge_clients.shared_token', 'test-edge-token');

        $this->withHeader('X-Edge-Client-Token', 'wrong-token')
            ->postJson(route('api.edge-clients.heartbeat'), [
                'client_key' => 'extension-dev',
                'client_type' => 'browser_extension',
                'label' => 'Extension Dev',
            ])
            ->assertForbidden();
    }
}
