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
        Schema::create('procurements', function (Blueprint $table) {
            $table->id();
            $table->string("title");
            $table->string("author");
            $table->string("edition")->nullable();
            $table->string("isbn")->nullable();
            $table->string("publisher")->nullable();
            $table->string("place_of_publication")->nullable();
            $table->year("year_of_publication")->nullable();
            $table->integer("quantity_requested");
            $table->foreignId("requested_by")->nullable()->constrained('users')->nullOnDelete();
            $table->enum("admin_approval", ['approved', 'rejected', 'pending']);
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
        Schema::dropIfExists('procurements');
    }
};
