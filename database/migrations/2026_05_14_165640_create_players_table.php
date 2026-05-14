<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('players', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('first_name', 80);
            $table->string('last_name', 80);
            $table->date('dob');
            $table->smallInteger('graduation_year')->unsigned()->nullable();
            $table->string('gender', 30)->nullable();
            $table->string('bats', 1)->nullable();
            $table->string('throws', 1)->nullable();
            $table->string('school', 120)->nullable();
            $table->string('jersey_size', 20)->nullable();
            $table->text('medical_notes')->nullable();
            $table->string('external_id', 80)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'last_name', 'dob']);
            $table->unique(['organization_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('players');
    }
};
