<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seasons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(false);
            $table->boolean('is_registration_open')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id']);
            $table->unique(['organization_id', 'name']);
        });

        DB::statement(
            'CREATE UNIQUE INDEX seasons_one_active_per_org '
            .'ON seasons (organization_id) WHERE is_active = 1'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('seasons');
    }
};
