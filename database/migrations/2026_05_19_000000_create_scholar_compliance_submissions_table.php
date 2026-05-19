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
        Schema::create('scholar_compliance_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scholar_id')->constrained('scholars')->cascadeOnDelete();
            $table->foreignId('scholarship_application_id')->nullable()->constrained('scholarship_applications')->nullOnDelete();
            $table->string('semester')->nullable();
            $table->string('academic_year')->nullable();
            $table->string('status')->default('Submitted');
            $table->string('coe_status')->default('Submitted');
            $table->string('cor_status')->default('Submitted');
            $table->string('grades_status')->default('Submitted');
            $table->decimal('gpa', 4, 2)->nullable();
            $table->json('submissions')->nullable();
            $table->json('grades')->nullable();
            $table->text('officer_notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scholar_compliance_submissions');
    }
};