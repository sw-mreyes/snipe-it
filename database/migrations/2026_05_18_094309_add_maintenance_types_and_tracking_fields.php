<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Create maintenance_types lookup table
        Schema::create('maintenance_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        // Seed with the 8 built-in types
        $now = now();
        $types = [
            'Maintenance',
            'Repair',
            'Upgrade',
            'PAT Test',
            'Calibration',
            'Software Support',
            'Hardware Support',
            'Configuration Change',
        ];

        foreach ($types as $name) {
            DB::table('maintenance_types')->insert([
                'name' => $name,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Add new tracking columns and the maintenance_type FK to maintenances
        Schema::table('maintenances', function (Blueprint $table) {
            $table->unsignedBigInteger('maintenance_type_id')->nullable();
            $table->unsignedBigInteger('checked_out_to_id')->nullable();
            $table->string('checked_out_to_type')->nullable();
            $table->unsignedBigInteger('responsible_party_id')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('completed_by')->nullable();
        });

        // Map existing string values to new type IDs (best-effort, case-insensitive)
        $typeMap = [
            'maintenance' => 'Maintenance',
            'repair' => 'Repair',
            'upgrade' => 'Upgrade',
            'pat_test' => 'PAT Test',
            'calibration' => 'Calibration',
            'software_support' => 'Software Support',
            'hardware_support' => 'Hardware Support',
            'configuration_change' => 'Configuration Change',
        ];

        foreach ($typeMap as $oldValue => $newName) {
            $newId = DB::table('maintenance_types')->where('name', $newName)->value('id');
            if ($newId) {
                DB::table('maintenances')
                    ->where('asset_maintenance_type', $oldValue)
                    ->update(['maintenance_type_id' => $newId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('maintenances', function (Blueprint $table) {
            $table->dropColumn([
                'maintenance_type_id',
                'checked_out_to_id',
                'checked_out_to_type',
                'responsible_party_id',
                'completed_at',
                'completed_by',
            ]);
        });

        Schema::dropIfExists('maintenance_types');
    }
};
