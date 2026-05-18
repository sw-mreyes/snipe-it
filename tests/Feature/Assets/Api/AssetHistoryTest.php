<?php

namespace Tests\Feature\Assets\Api;

use App\Models\Asset;
use App\Models\CustomField;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class AssetHistoryTest extends TestCase
{
    public function test_encrypted_custom_field_values_are_html_encoded_in_history_for_admins()
    {
        $this->markIncompleteIfMySQL('Custom Fields tests do not work on MySQL');

        $field = CustomField::factory()->testEncrypted()->create();
        $asset = Asset::factory()->hasEncryptedCustomField($field)->create([
            $field->db_column => Crypt::encrypt('safe initial value'),
        ]);
        $superuser = User::factory()->superuser()->create();

        $this->actingAsForApi($superuser)
            ->patchJson(route('api.assets.update', $asset->id), [
                $field->db_column_name() => '<img src=x onerror=alert(1)>',
            ])
            ->assertOk();

        $response = $this->actingAsForApi($superuser)
            ->getJson(route('api.assets.history', ['asset' => $asset->id, 'action_type' => 'update']))
            ->assertOk()
            ->assertJsonPath('total', 1);

        $logMeta = $response->json('rows.0.log_meta');
        $this->assertNotNull($logMeta, 'log_meta should be present in history');

        $fieldEntry = $logMeta[$field->db_column] ?? null;
        $this->assertNotNull($fieldEntry, 'Encrypted field should appear in log_meta');

        $newValue = $fieldEntry['new'];
        $this->assertStringNotContainsString('<img', $newValue, 'Raw HTML tag must not appear in history log_meta');
        $this->assertStringNotContainsString('onerror', $newValue, 'Raw event handler must not appear in history log_meta');
        $this->assertStringContainsString('&lt;', $newValue, 'Value should be HTML-encoded in history log_meta');
    }

    public function test_encrypted_custom_field_values_are_masked_for_non_admins()
    {
        $this->markIncompleteIfMySQL('Custom Fields tests do not work on MySQL');

        $field = CustomField::factory()->testEncrypted()->create();
        $asset = Asset::factory()->hasEncryptedCustomField($field)->create([
            $field->db_column => Crypt::encrypt('safe initial value'),
        ]);
        $superuser = User::factory()->superuser()->create();

        $this->actingAsForApi($superuser)
            ->patchJson(route('api.assets.update', $asset->id), [
                $field->db_column_name() => '<img src=x onerror=alert(1)>',
            ])
            ->assertOk();

        $viewer = User::factory()->viewAssets()->viewAssetHistory()->create();

        $response = $this->actingAsForApi($viewer)
            ->getJson(route('api.assets.history', ['asset' => $asset->id, 'action_type' => 'update']))
            ->assertOk()
            ->assertJsonPath('total', 1);

        $logMeta = $response->json('rows.0.log_meta');
        $fieldEntry = $logMeta[$field->db_column] ?? null;

        if ($fieldEntry !== null) {
            $this->assertEquals('************', $fieldEntry['new'], 'Non-admin should see masked value for encrypted field changes');
            $this->assertStringNotContainsString('<img', $fieldEntry['new']);
        }
    }
}
