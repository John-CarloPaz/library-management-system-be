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
        Schema::create('catalogues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('acquisition_id')->nullable()->constrained();
            $table->integer("number_of_copies")->nullable();
            $table->string("dewey")->nullable();
            $table->string('cutter_number')->nullable();
            $table->string('call_number')->nullable();
            $table->string("title")->nullable();
            $table->string("author")->nullable();
            $table->string("edition")->nullable();
            $table->string("isbn")->nullable();
            $table->string("publisher")->nullable();
            $table->string("place_of_publication")->nullable();
            $table->year("year_of_publication")->nullable();
            $table->boolean("is_provisional");
            $table->boolean("is_archived");
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->enum("cataloging_status", ['pending', 'in_progress', 'cataloged', 'ready_for_labeling', 'available', 'on_hold', 'archived'])->default('pending');
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
        Schema::dropIfExists('catalogues');
    }
};
