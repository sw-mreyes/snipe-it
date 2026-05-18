<?php

namespace Tests\Feature\Maintenances\Api;

use App\Models\Actionlog;
use App\Models\Maintenance;
use App\Models\User;
use Tests\TestCase;

class CompleteMaintenanceTest extends TestCase
{
    public function test_requires_permission()
    {
        $maintenance = Maintenance::factory()->create();

        $this->actingAsForApi(User::factory()->create())
            ->postJson(route('api.maintenances.complete', $maintenance))
            ->assertForbidden();
    }

    public function test_can_mark_maintenance_complete()
    {
        $actor = User::factory()->superuser()->create();
        $maintenance = Maintenance::factory()->create();

        $this->actingAsForApi($actor)
            ->postJson(route('api.maintenances.complete', $maintenance))
            ->assertOk()
            ->assertStatusMessageIs('success');

        $maintenance->refresh();
        $this->assertNotNull($maintenance->completed_at);
        $this->assertEquals($actor->id, $maintenance->completed_by);

        $this->assertHasTheseActionLogs($maintenance, ['create', 'completed']);
    }

    public function test_marking_complete_does_not_create_update_log()
    {
        $actor = User::factory()->superuser()->create();
        $maintenance = Maintenance::factory()->create();

        $this->actingAsForApi($actor)
            ->postJson(route('api.maintenances.complete', $maintenance));

        $updateLogs = Actionlog::where('item_type', Maintenance::class)
            ->where('item_id', $maintenance->id)
            ->where('action_type', 'update')
            ->count();

        $this->assertEquals(0, $updateLogs);
    }

    public function test_cannot_mark_already_completed_maintenance_complete()
    {
        $actor = User::factory()->superuser()->create();
        $maintenance = Maintenance::factory()->create(['completed_at' => now()->subDay(), 'completed_by' => $actor->id]);

        $this->actingAsForApi($actor)
            ->postJson(route('api.maintenances.complete', $maintenance))
            ->assertStatusMessageIs('error');

        $this->assertHasTheseActionLogs($maintenance, ['create']);
    }
}
