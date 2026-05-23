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
        Schema::create('grant_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scholarship_program_id')->constrained('scholarship_programs')->cascadeOnDelete();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('semester');
            $table->string('school_year');
            $table->decimal('amount', 12, 2)->default(0);
            $table->date('claiming_start_date');
            $table->date('claiming_end_date')->nullable();
            $table->string('venue');
            $table->unsignedInteger('daily_limit')->default(30);
            $table->text('remarks')->nullable();
            $table->string('status')->default('Draft');
            $table->timestamps();

            $table->index(['scholarship_program_id', 'status']);
            $table->index(['claiming_start_date', 'claiming_end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grant_batches');
    }
};
