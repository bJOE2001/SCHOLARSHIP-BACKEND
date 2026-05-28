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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('role')->default('student');
            $table->string('status')->default('Active');
            $table->string('avatar')->nullable();
            $table->string('department')->nullable();
            $table->string('student_id')->nullable()->unique();
            $table->date('birth_date')->nullable();
            $table->string('gender')->nullable();
            $table->string('civil_status')->nullable();
            $table->string('citizenship')->nullable();
            $table->text('address')->nullable();
            $table->string('barangay')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('contact_number')->nullable();
            $table->string('campus')->nullable();
            $table->string('school_name')->nullable();
            $table->string('course')->nullable();
            $table->string('year_level')->nullable();
            $table->string('semester')->nullable();
            $table->string('academic_year')->nullable();
            $table->decimal('gpa', 5, 2)->nullable();
            $table->decimal('family_income', 12, 2)->nullable();
            $table->string('enrollment_status')->nullable();
            $table->text('academic_awards')->nullable();
            $table->string('father_name')->nullable();
            $table->string('mother_name')->nullable();
            $table->string('guardian_name')->nullable();
            $table->string('parent_occupation')->nullable();
            $table->string('monthly_income')->nullable();
            $table->unsignedInteger('siblings')->nullable();
            $table->unsignedInteger('studying_siblings')->nullable();
            $table->string('income_bracket')->nullable();
            $table->json('assigned_program_ids')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
