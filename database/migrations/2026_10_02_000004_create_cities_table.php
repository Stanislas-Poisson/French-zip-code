<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::dropIfExists('cities');
    }

    public function up(): void
    {
        Schema::create('cities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('commune_id')->constrained('communes')->restrictOnDelete();
            $table->string('postal_code', 5);
            $table->string('label')->nullable();
            $table->double('latitude')->nullable();
            $table->double('longitude')->nullable();
            $table->unsignedInteger('address_count')->default(0);
            $table->string('coordinate_source', 32)->nullable();
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->foreignId('replaced_by_city_id')->nullable()->constrained('cities')->nullOnDelete();

            $table->unique(['commune_id', 'postal_code', 'valid_from']);
            $table->index('postal_code');
        });
    }
};
