<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::dropIfExists('commune_successions');
    }

    public function up(): void
    {
        Schema::create('commune_successions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('commune_event_id')->nullable()->constrained('commune_events')->cascadeOnDelete();
            $table->string('from_code', 5);
            $table->string('to_code', 5)->nullable();
            $table->string('kind', 32);
            $table->date('effective_date');

            $table->index(['from_code', 'effective_date']);
        });
    }
};
