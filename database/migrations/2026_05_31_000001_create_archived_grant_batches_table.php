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
        Schema::create('archived_grant_batches', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('original_grant_batch_id')->nullable()->index();
            $table->foreignId('scholarship_program_id')->nullable()->constrained('scholarship_programs')->nullOnDelete();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('archived_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('semester');
            $table->string('school_year');
            $table->decimal('amount', 12, 2)->default(0);
            $table->date('claiming_start_date')->nullable();
            $table->date('claiming_end_date')->nullable();
            $table->string('venue')->nullable();
            $table->unsignedInteger('daily_limit')->default(30);
            $table->text('remarks')->nullable();
            $table->string('status')->default('Archived');
            $table->json('beneficiaries')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['scholarship_program_id', 'semester', 'school_year'], 'archived_grant_batches_cycle_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('archived_grant_batches');
    }
};
