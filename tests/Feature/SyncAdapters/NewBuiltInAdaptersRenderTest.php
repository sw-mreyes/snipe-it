<?php

namespace Tests\Feature\SyncAdapters;

use App\Models\User;
use Tests\TestCase;

/**
 * Smoke coverage for the three newest built-in adapters (Mosyle, Meraki
 * Systems Manager, Omnissa Workspace ONE). Each is a schema-only
 * declaration on top of SyncAdapter. the storage / save /
 * encryption cycle is already covered by FleetSettingsPageTest,
 * AddigySettingsPageTest, and IntuneSettingsPageTest for the three
 * distinct schema shapes (single-secret, key-pair, mixed). This test
 * just verifies each new adapter is registered with the SyncAdapter::allTypes
 * and its schema-declared labels appear on the settings page.
 */
class NewBuiltInAdaptersRenderTest extends TestCase
{
    public function test_mosyle_renders_with_its_schema_label()
    {
        $html = $this->actingAs(User::factory()->superuser()->create())
            ->get(route('settings.adapters.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Mosyle', $html);
        // Schema field names from MosyleAdapter::settingsSchema() post-#19790.
        // Mosyle Manager v2 requires accessToken + email + password on login.
        $this->assertMatchesRegularExpression('/name="mosyle_access_token"/', $html);
        $this->assertMatchesRegularExpression('/name="mosyle_email"/', $html);
        $this->assertMatchesRegularExpression('/name="mosyle_password"/', $html);
    }

    public function test_meraki_systems_manager_renders_with_both_schema_fields()
    {
        $html = $this->actingAs(User::factory()->superuser()->create())
            ->get(route('settings.adapters.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Meraki Systems Manager', $html);
        // Secret schema field
        $this->assertMatchesRegularExpression('/name="meraki_sm_api_key"/', $html);
        // Non-secret schema field
        $this->assertMatchesRegularExpression('/name="meraki_sm_organization_id"/', $html);
    }

    public function test_workspace_one_renders_with_all_three_schema_fields()
    {
        $html = $this->actingAs(User::factory()->superuser()->create())
            ->get(route('settings.adapters.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Omnissa Workspace ONE', $html);
        $this->assertMatchesRegularExpression('/name="workspace_one_tenant_code"/', $html);
        $this->assertMatchesRegularExpression('/name="workspace_one_client_id"/', $html);
        $this->assertMatchesRegularExpression('/name="workspace_one_client_secret"/', $html);
    }
}
