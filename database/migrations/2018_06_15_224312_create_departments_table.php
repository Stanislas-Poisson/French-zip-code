<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->increments('id');
            $table->string('region_code', 3);
            $table->string('code', 3)->unique();
            $table->string('name', 255);
            $table->string('slug', 255);

            $table->foreign('region_code')
                ->references('code')->on('regions')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
