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
        Schema::create('semester_requirement_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('scholar_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('scholarship_application_id')->nullable()->constrained('scholarship_applications')->nullOnDelete();
            $table->string('status')->default('Draft');
            $table->json('grades')->nullable();
            $table->decimal('computed_average', 5, 2)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('semester_requirement_drafts');
    }
};
