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
        Schema::create('entries', function (Blueprint $table) {
            $table->id();
            $table->string('location_code', 16);
            $table->string('type', 16);
            $table->string('code');
            $table->unsignedInteger('quantity')->nullable();
            $table->string('image_path')->nullable();
            $table->timestamps();

            $table->index(['location_code', 'type']);
            $table->index('code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entries');
    }
};
