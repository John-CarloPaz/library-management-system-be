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
        Schema::create('acquisitions', function (Blueprint $table) {
            $table->id();
            $table->string("title")->nullable();
            $table->string("author")->nullable();
            $table->string("edition")->nullable();
            $table->string("isbn")->nullable();
            $table->string("publisher")->nullable();
            $table->string("place_of_publication")->nullable();
            $table->year("year_of_publication")->nullable();
            $table->integer("quantity_requested")->nullable();
            $table->foreignId("procurement_id")->nullable()->constrained('procurements')->nullOnDelete();
            $table->enum("acquisition_method", ['book_fair', 'supplier', 'donation'])->default('supplier');
            $table->string("supplier_name")->nullable();
            $table->integer("cost")->nullable();
            $table->float("total_cost")->nullable();
            $table->foreignId("branch_id")->nullable()->constrained('branches')->onDelete('cascade');
            $table->date("date_acquired")->nullable();
            $table->integer("quantity_acquired")->nullable();
            $table->enum("acquisition_status", ['received', 'partial', 'missing', 'cancelled', 'pending'])->default('pending');
            $table->string("received_by")->nullable();
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
        Schema::dropIfExists('acquisitions');
    }
};
