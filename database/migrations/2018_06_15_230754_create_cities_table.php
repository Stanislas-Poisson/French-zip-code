<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCitiesTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('cities', function (Blueprint $table) {
            $table->increments('id');
            $table->string('department_code', 3);
            $table->string('insee_code', 5)->nullable();
            $table->string('zip_code', 5)->nullable();
            $table->string('name', 255);
            $table->string('slug', 255);
            $table->float('gps_lat', 16, 14);
            $table->float('gps_lng', 17, 14);
            $table->boolean('multi')->default(false);

            $table->foreign('department_code')
                ->references('code')->on('departments')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('cities');
    }
}
