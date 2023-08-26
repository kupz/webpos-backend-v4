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
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->string("table_name");
            $table->string("object_id")->nullable();
            $table->string("description");
            $table->string("label");
            $table->longText("properties")->nullable();
            $table->unsignedBigInteger("user_id")->nullable();
            $table->string('ip');
            $table->timestamp("created_at")->useCurrent();

            $table->foreign("user_id")->references("id")->on("users");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
