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
        Schema::create('borrow_reminder_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('borrow_id')->constrained('borrows')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();

            $table->string('type');
            $table->string('channel');
            $table->string('provider')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->timestamp('sent_at');

            $table->timestamps();

            $table->unique(['borrow_id', 'type', 'channel']);
            $table->index(['student_id', 'type', 'channel']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('borrow_reminder_logs');
    }
};
