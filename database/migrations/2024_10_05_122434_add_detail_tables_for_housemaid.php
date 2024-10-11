<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('housemaid_questions',function(Blueprint $table) {
            $table->id();
            $table->foreignId('role_user_id')->constrained('role_user')->onDelete('cascade');
            $table->string('question');
            $table->string('answer');
        });

        Schema::create('housemaid_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_user_id')->constrained('role_user')->onDelete('cascade');
            $table->string('day');
            $table->string('start_time');
            $table->string('end_time');
        });

        Schema::create('housemaid_documents', function (Blueprint $table) {
            $table->id(); // Surrogate key
            $table->foreignId('role_user_id')->constrained('role_user')->onDelete('cascade');
            $table->string('document_type');
            $table->string('document_path');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
        Schema::dropIfExists('schedules');
        Schema::dropIfExists('answers');
    }
};
