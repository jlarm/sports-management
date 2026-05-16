<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forms', function (Blueprint $table): void {
            $table->json('custom_consents')->nullable()->after('required_consents');
        });

        Schema::table('consents', function (Blueprint $table): void {
            $table->string('consent_label')->nullable()->after('consent_type');
        });
    }

    public function down(): void
    {
        Schema::table('consents', function (Blueprint $table): void {
            $table->dropColumn('consent_label');
        });

        Schema::table('forms', function (Blueprint $table): void {
            $table->dropColumn('custom_consents');
        });
    }
};
