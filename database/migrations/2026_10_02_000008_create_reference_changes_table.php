<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::dropIfExists('reference_changes');
    }

    public function up(): void
    {
        Schema::create('reference_changes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('snapshot_id')->constrained('snapshots')->cascadeOnDelete();
            $table->string('entity_type', 32);
            $table->string('entity_code', 16);
            $table->string('change_type', 16);
            $table->json('old_value')->nullable();
            $table->json('new_value')->nullable();
            $table->dateTime('detected_at');

            $table->index(['entity_type', 'entity_code']);
        });
    }
};
