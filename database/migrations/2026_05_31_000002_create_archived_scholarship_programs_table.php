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
        Schema::create('archived_scholarship_programs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('original_scholarship_program_id')->nullable()->index();
            $table->foreignId('archived_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('provider')->nullable();
            $table->string('category')->nullable();
            $table->string('type')->nullable();
            $table->text('description')->nullable();
            $table->text('eligibility_summary')->nullable();
            $table->string('status')->default('Archived');
            $table->unsignedInteger('slots')->default(0);
            $table->unsignedInteger('used_slots')->default(0);
            $table->unsignedBigInteger('budget')->nullable();
            $table->decimal('maintaining_grade', 5, 2)->nullable();
            $table->json('schedule')->nullable();
            $table->json('eligibility')->nullable();
            $table->json('requirements')->nullable();
            $table->json('requirement_rules')->nullable();
            $table->json('scoring_criteria')->nullable();
            $table->json('renewal_rules')->nullable();
            $table->json('assigned_officer_ids')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('archived_scholarship_programs');
    }
};
