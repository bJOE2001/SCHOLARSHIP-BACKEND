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
        Schema::table('grant_announcements', function (Blueprint $table): void {
            $table->text('remarks')->nullable()->after('venue');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('grant_announcements', function (Blueprint $table): void {
            $table->dropColumn('remarks');
        });
    }
};
