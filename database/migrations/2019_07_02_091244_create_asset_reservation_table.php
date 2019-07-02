<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAssetReservationTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('asset_reservation', function (Blueprint $table) {
            $table->increments('id');

            $table->unsignedInteger('asset_id');
            $table->foreign('asset_id')->references('id')->on('assets')->onDelete('cascade');

            $table->unsignedInteger('reservation_id');
            $table->foreign('reservation_id')->references('id')->on('reservations')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('asset_reservation');
    }
}
