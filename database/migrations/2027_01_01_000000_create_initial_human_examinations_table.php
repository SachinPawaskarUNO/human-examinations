<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('human_examinations', function (Blueprint $table): void {
            $table->id();
            $table->string('reference')->nullable()->index();
            $table->string('examiner')->nullable();
            $table->date('date')->nullable()->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('human_examination_observations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('examination_id')->constrained('human_examinations')->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->string('type')->default('Unspecified');
            $table->string('label')->nullable();
            $table->string('region')->nullable();
            $table->text('description')->nullable();
            $table->decimal('length_mm', 10, 2)->nullable();
            $table->decimal('width_mm', 10, 2)->nullable();
            $table->string('sketch_id')->nullable();
            $table->decimal('x', 8, 6)->nullable();
            $table->decimal('y', 8, 6)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['examination_id', 'number']);
            $table->index(['examination_id', 'type']);
            $table->index(['examination_id', 'sketch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('human_examination_observations');
        Schema::dropIfExists('human_examinations');
    }
};
