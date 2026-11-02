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
        Schema::create('stock_cleanup_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('operation_id')->index();
            $table->uuid('inventory_detail_id')->index();
            $table->string('location_code', 32)->index();
            $table->bigInteger('bin_id');
            $table->string('bin_number');
            $table->bigInteger('carton_id');
            $table->string('carton_number');
            $table->bigInteger('item_id')->index();
            $table->string('tpin')->nullable();
            $table->integer('qty_on_hand_before');
            $table->integer('qty_on_hand_after');
            $table->integer('qty_available_before');
            $table->integer('qty_available_after');
            $table->longText('inventory_snapshot');
            $table->longText('item_extend_snapshot');
            $table->longText('confirmation_context');
            $table->string('status', 16)->default('prepared')->index();
            $table->dateTime('created_at');
            $table->dateTime('completed_at')->nullable();
            $table->unique(['operation_id', 'inventory_detail_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_cleanup_logs');
    }
};
