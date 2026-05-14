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

        $driver = DB::connection()->getDriverName();

        if (in_array($driver, ['sqlite', 'pgsql'], true)) {
            // Native partial unique index.
            DB::statement(
                'CREATE UNIQUE INDEX seasons_one_active_per_org '
                .'ON seasons (organization_id) WHERE is_active = 1'
            );
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            // MySQL 8.0+/MariaDB 10.5+ functional index. The expression evaluates
            // to organization_id when the row is active and NULL otherwise; the
            // unique index ignores NULLs, so inactive seasons never collide.
            DB::statement(
                'CREATE UNIQUE INDEX seasons_one_active_per_org '
                .'ON seasons ((CASE WHEN is_active = 1 THEN organization_id END))'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('seasons');
    }
};
