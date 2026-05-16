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
        Schema::create('scholarship_programs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('provider');
            $table->string('category');
            $table->string('type');
            $table->text('description');
            $table->text('eligibility_summary')->nullable();
            $table->string('status')->default('Closed');
            $table->unsignedInteger('slots')->default(0);
            $table->unsignedInteger('used_slots')->default(0);
            $table->unsignedBigInteger('budget')->nullable();
            $table->json('schedule')->nullable();
            $table->json('eligibility')->nullable();
            $table->json('requirements')->nullable();
            $table->json('scoring_criteria')->nullable();
            $table->json('renewal_rules')->nullable();
            $table->json('assigned_admin_ids')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scholarship_programs');
    }
};
