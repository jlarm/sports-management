<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('divisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'name']);
            $table->index(['organization_id', 'display_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('divisions');
    }
};
