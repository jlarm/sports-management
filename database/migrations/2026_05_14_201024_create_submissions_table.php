<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('form_id')->constrained()->cascadeOnDelete();
            $table->foreignId('submitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('schema_snapshot');
            $table->unsignedInteger('schema_version');
            $table->json('data');
            $table->timestamp('submitted_at')->useCurrent();
            $table->timestamps();

            $table->index(['form_id', 'submitted_at']);
            $table->index(['organization_id', 'form_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submissions');
    }
};
