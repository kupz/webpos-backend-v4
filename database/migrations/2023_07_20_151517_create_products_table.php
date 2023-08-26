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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string("sku")->nullable();
            $table->string("name");
            $table->string("description");
            $table->string("barcode")->nullable();
            $table->decimal('price', 12 ,2);
            $table->unsignedBigInteger("user_id");
            $table->string("extension")->nullable();
            $table->timestamps();

            $table->foreign("user_id")->references("id")->on("users");
            $table->unique(["sku", "user_id"]);
            $table->unique(["barcode", "user_id"]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
