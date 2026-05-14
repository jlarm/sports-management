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
        Schema::create('team_player', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('jersey_number')->nullable();
            $table->string('primary_position', 10)->nullable();
            $table->boolean('is_captain')->default(false);
            $table->timestamps();

            $table->unique(['team_id', 'player_id']);
        });

        $driver = DB::connection()->getDriverName();

        if (in_array($driver, ['sqlite', 'pgsql'], true)) {
            DB::statement(
                'CREATE UNIQUE INDEX team_player_jersey_per_team '
                .'ON team_player (team_id, jersey_number) WHERE jersey_number IS NOT NULL'
            );
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement(
                'CREATE UNIQUE INDEX team_player_jersey_per_team '
                .'ON team_player (team_id, (CASE WHEN jersey_number IS NOT NULL THEN jersey_number END))'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('team_player');
    }
};
