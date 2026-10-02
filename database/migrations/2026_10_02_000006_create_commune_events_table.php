<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::dropIfExists('commune_events');
    }

    public function up(): void
    {
        Schema::create('commune_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('snapshot_id')->constrained('snapshots')->cascadeOnDelete();
            $table->unsignedSmallInteger('modality');
            $table->date('effective_date');
            $table->string('kind_before', 4)->nullable();
            $table->string('code_before', 5)->nullable();
            $table->string('name_before')->nullable();
            $table->string('kind_after', 4)->nullable();
            $table->string('code_after', 5)->nullable();
            $table->string('name_after')->nullable();

            $table->index(['code_before', 'effective_date']);
            $table->index(['code_after', 'effective_date']);
        });
    }
};
