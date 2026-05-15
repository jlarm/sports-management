<?php

declare(strict_types=1);

use App\Enums\SubmissionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table): void {
            $table->string('status', 32)
                ->default(SubmissionStatus::Pending->value)
                ->after('data');

            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table): void {
            $table->dropIndex(['organization_id', 'status']);
            $table->dropColumn('status');
        });
    }
};
