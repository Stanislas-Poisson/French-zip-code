<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table) {
            $table->increments('id');
            $table->string('code', 3)->unique();
            $table->string('name', 255);
            $table->string('slug', 255);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regions');
    }
};
