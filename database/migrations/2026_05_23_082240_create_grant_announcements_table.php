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
        Schema::create('grant_announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grant_batch_id')->unique()->constrained('grant_batches')->cascadeOnDelete();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('message');
            $table->string('program_name');
            $table->string('semester');
            $table->string('school_year');
            $table->string('venue')->nullable();
            $table->unsignedInteger('total_beneficiaries')->default(0);
            $table->string('created_by_name')->nullable();
            $table->timestamps();

            $table->index(['program_name', 'school_year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grant_announcements');
    }
};
