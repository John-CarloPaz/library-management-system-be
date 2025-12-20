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
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->foreignId("catalogue_id")->constrained();
            $table->foreignId("branch_id")->constrained('branches')->onDelete('cascade');
            $table->integer("copy_number");
            $table->string("reference_number");
            $table->string("qr_code");
            $table->boolean('is_archived')->default(false);
            $table->string('created_by');
            $table->string('updated_by');
            $table->date('expiration_date')->nullable();
            $table->enum('book_status', ['active', 'for_archiving', 'lost', 'damaged', 'under_repair', 'borrowed'])->default('active');
            $table->timestamps();
            $table->unique(['catalogue_id', 'copy_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
