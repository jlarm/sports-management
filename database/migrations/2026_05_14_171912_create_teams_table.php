<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->foreignId('division_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('slug', 120);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'season_id', 'slug']);
            $table->index(['organization_id', 'season_id']);
            $table->index(['organization_id', 'season_id', 'division_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};
