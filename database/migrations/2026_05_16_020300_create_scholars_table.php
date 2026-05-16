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
        Schema::create('scholars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('scholarship_program_id')->nullable()->constrained('scholarship_programs')->nullOnDelete();
            $table->foreignId('scholarship_application_id')->nullable()->constrained('scholarship_applications')->nullOnDelete();
            $table->string('scholar_id')->unique();
            $table->string('name');
            $table->string('avatar')->nullable();
            $table->string('program');
            $table->string('course')->nullable();
            $table->string('year_level')->nullable();
            $table->string('school')->nullable();
            $table->string('gender')->nullable();
            $table->date('birthdate')->nullable();
            $table->text('address')->nullable();
            $table->string('contact_number')->nullable();
            $table->string('email');
            $table->decimal('gpa', 4, 2)->nullable();
            $table->string('enrollment_status')->nullable();
            $table->string('academic_year')->nullable();
            $table->string('semester')->nullable();
            $table->string('scholarship_status')->nullable();
            $table->string('renewal_status')->default('Active');
            $table->date('date_approved')->nullable();
            $table->string('duration')->nullable();
            $table->string('compliance_status')->default('Complete');
            $table->unsignedTinyInteger('compliance_rate')->default(100);
            $table->string('risk_label')->default('Stable');
            $table->text('risk_reason')->nullable();
            $table->text('recommended_action')->nullable();
            $table->json('submissions')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scholars');
    }
};
