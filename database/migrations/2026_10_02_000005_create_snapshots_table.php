<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::dropIfExists('snapshots');
    }

    public function up(): void
    {
        Schema::create('snapshots', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 64);
            $table->string('version', 64);
            $table->string('checksum', 64);
            $table->dateTime('fetched_at');
            $table->dateTime('imported_at')->nullable();
            $table->boolean('complete')->default(false);

            $table->unique(['source', 'version', 'checksum']);
        });
    }
};
