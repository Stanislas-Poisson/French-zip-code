<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::dropIfExists('regions');
    }

    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 3);
            $table->string('name');
            $table->string('slug');
            $table->date('valid_from');
            $table->date('valid_to')->nullable();

            $table->unique(['code', 'valid_from']);
        });
    }
};
