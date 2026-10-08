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
        Schema::create('stock_generation_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('operation_id')->index();
            $table->foreignId('entry_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16);
            $table->string('code');
            $table->string('action', 16);
            $table->unsignedInteger('carton_id')->nullable();
            $table->unsignedInteger('bin_id')->nullable();
            $table->unsignedInteger('item_id')->nullable();
            $table->uuid('inventory_detail_id')->nullable();
            $table->string('tpin')->nullable();
            $table->string('location_code', 16);
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('qty_on_hand_before')->nullable();
            $table->unsignedInteger('qty_on_hand_after')->nullable();
            $table->unsignedInteger('qty_available_before')->nullable();
            $table->unsignedInteger('qty_available_after')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_generation_logs');
    }
};
