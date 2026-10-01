<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::dropIfExists('communes');
    }

    public function up(): void
    {
        Schema::create('communes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('department_id')->nullable()->constrained('departments')->restrictOnDelete();
            $table->string('insee_code', 5);
            $table->string('kind', 4);
            $table->string('name');
            $table->string('slug');
            $table->double('centre_latitude')->nullable();
            $table->double('centre_longitude')->nullable();
            $table->date('valid_from');
            $table->date('valid_to')->nullable();

            $table->unique(['insee_code', 'valid_from']);
            $table->index('insee_code');
        });
    }
};
