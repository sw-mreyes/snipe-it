<?php

namespace Tests\Feature\SyncAdapters;

use App\Models\Asset;
use App\Models\AssetExternalSource;
use App\Models\AssetModel;
use App\Models\CustomField;
use App\Models\CustomFieldset;
use App\Models\Statuslabel;
use App\Models\SyncAdapterConfig;
use App\Models\SyncAdapterInstance;
use App\SyncAdapters\HostInventoryRecord;
use App\SyncAdapters\SyncAdapter;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;


class SilentSaveFailureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Statuslabel::factory()->rtd()->create();
    }

    public function test_update_path_throws_when_extra_mapping_fails_fieldset_validation(): void
    {
        // Build a fieldset with a MAC-format text custom field, attach
        // it to the AssetModel the sync will resolve, and pre-create
        // the asset + external_source row so the sync hits the UPDATE
        // branch (not the create-shell branch, which already has its
        // own guard).
        $macField = CustomField::factory()->create([
            'element' => 'text',
            'name' => 'Fleet MAC',
            'format' => 'MAC',
        ]);
        $fieldset = CustomFieldset::factory()->create();
        $fieldset->fields()->attach($macField->id, ['required' => 0, 'order' => 1]);

        $model = AssetModel::factory()->create([
            'name' => 'Generic Laptop',
            'fieldset_id' => $fieldset->id,
        ]);
        $asset = Asset::factory()->create([
            'name' => 'existing-host',
            'model_id' => $model->id,
        ]);

        $instance = $this->configuredFleetInstance();
        SyncAdapterConfig::put($instance->id, 'mapping.fleet_uuid', 'custom:'.$macField->id);

        AssetExternalSource::create([
            'asset_id' => $asset->id,
            'source' => 'fleet',
            'external_id' => 'fleet-update-record',
        ]);

        // Sync a record whose uuid extra will land on the MAC-format
        // custom field. The MAC format validation rejects
        // "not-a-mac-address", the ValidatingObserver aborts the
        // save with return false, and pre-fix the whole thing was
        // swallowed.
        $record = new HostInventoryRecord(
            sourceKey: 'fleet',
            sourceId: 'fleet-update-record',
            hostname: 'existing-host',
            hardwareSerial: 'SN-EXIST',
            hardwareModel: 'Generic Laptop',
            extra: ['fleet_uuid' => 'not-a-mac-address'],
        );

        $thrown = null;
        try {
            SyncAdapter::syncFromRecord($record);
        } catch (\Throwable $e) {
            $thrown = $e;
        }

        $this->assertNotNull($thrown, 'syncFromRecord must surface a save-failure on the update path so the pull loop counts it as an error rather than silently succeeding.');
        // The RuntimeException wraps the underlying validation
        // failure so the pull loop's log line names the offending
        // record and the reason. Prior to the fix, no exception
        // reached this point.
        $this->assertStringContainsString('could not update asset for fleet record fleet-update-record', $thrown->getMessage());

        // Invalid value must not have made it into the DB.
        $asset->refresh();
        $this->assertNotSame('not-a-mac-address', $asset->{$macField->db_column});
    }

    public function test_valid_update_path_still_persists(): void
    {
        // Sanity check that the guard did not turn valid updates into
        // errors. A record that would pass validation completes the
        // update and lands its mapped values.
        $model = AssetModel::factory()->create(['name' => 'Generic Laptop']);
        $asset = Asset::factory()->create([
            'name' => 'pre-existing-clean',
            'model_id' => $model->id,
        ]);

        $this->configuredFleetInstance();
        AssetExternalSource::create([
            'asset_id' => $asset->id,
            'source' => 'fleet',
            'external_id' => 'fleet-clean-update',
        ]);

        $record = new HostInventoryRecord(
            sourceKey: 'fleet',
            sourceId: 'fleet-clean-update',
            hostname: 'now-renamed',
            hardwareSerial: 'SN-EXIST',
            hardwareModel: 'Generic Laptop',
        );

        SyncAdapter::syncFromRecord($record);

        $asset->refresh();
        $this->assertSame('now-renamed', $asset->name);
    }

    private function configuredFleetInstance(): SyncAdapterInstance
    {
        $instance = SyncAdapterInstance::where('slug', 'fleet')->firstOrFail();
        SyncAdapterConfig::put($instance->id, 'url', 'https://example.com/fleet');
        SyncAdapterConfig::put($instance->id, 'token', Crypt::encrypt('fake-token'));

        return $instance->fresh();
    }
}
