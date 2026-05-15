<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_guardian', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guardian_id')->constrained()->cascadeOnDelete();
            $table->string('relationship', 40)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['player_id', 'guardian_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_guardian');
    }
};
