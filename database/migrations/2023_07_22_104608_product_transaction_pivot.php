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
        //
        Schema::create('product_transaction', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger("product_id");
            $table->unsignedBigInteger("transaction_id");
            $table->integer("quantity");
            $table->decimal("price", 10, 2);

            $table->foreign("product_id")->references("id")->on("products");
            $table->foreign("transaction_id")->references("id")->on("transactions");
            $table->unique(["product_id", "transaction_id"]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
        Schema::dropIfExists('product_transaction');
    }
};
