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
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('middle_name')->nullable();
            $table->string('suffix')->nullable();

            $table->string('email')->unique();
            $table->integer('student_id')->unique();
            $table->string('program');
            $table->integer('year_level');
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');

            $table->string('qr_code')->unique()->nullable();
            $table->date('expiration_date')->nullable();
            //Audit fields
            $table->boolean('is_archived')->default(false);
            $table->string('created_by');
            $table->string('updated_by');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
