<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submission_decisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('player_action', 20);
            $table->foreignId('player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->string('guardian_action', 20);
            $table->foreignId('guardian_id')->nullable()->constrained('guardians')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('decided_at')->useCurrent();
            $table->timestamps();

            $table->index(['organization_id', 'submission_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_decisions');
    }
};
