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
        Schema::create('grant_beneficiaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grant_batch_id')->constrained('grant_batches')->cascadeOnDelete();
            $table->foreignId('scholar_id')->constrained('scholars')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('released_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('scholar_identifier');
            $table->string('scholar_name');
            $table->string('barangay')->nullable();
            $table->string('course')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->date('assigned_claim_date')->nullable();
            $table->string('time_slot')->nullable();
            $table->string('claim_status')->default('On Hold');
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->string('released_by_name')->nullable();
            $table->string('reference_number')->unique();
            $table->string('claim_method')->nullable();
            $table->text('release_remarks')->nullable();
            $table->timestamps();

            $table->unique(['grant_batch_id', 'scholar_id']);
            $table->index(['grant_batch_id', 'claim_status']);
            $table->index('assigned_claim_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grant_beneficiaries');
    }
};
