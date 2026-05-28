<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('scholarship_programs', function (Blueprint $table): void {
            if (! Schema::hasColumn('scholarship_programs', 'maintaining_grade')) {
                $table->decimal('maintaining_grade', 5, 2)->nullable()->after('budget');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('scholarship_programs', function (Blueprint $table): void {
            if (Schema::hasColumn('scholarship_programs', 'maintaining_grade')) {
                $table->dropColumn('maintaining_grade');
            }
        });
    }
};
