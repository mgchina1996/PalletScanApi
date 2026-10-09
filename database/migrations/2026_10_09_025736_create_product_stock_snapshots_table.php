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
        Schema::create('product_stock_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('product_id')->index();
            $table->string('merchant_id');
            $table->string('tpin', 100);
            $table->string('sku');
            $table->string('approval', 50)->nullable();
            $table->smallInteger('status')->nullable();
            $table->string('visibility')->nullable();
            $table->decimal('price', 18, 4)->nullable();
            $table->decimal('special_price', 18, 4)->nullable();
            $table->boolean('has_image');
            $table->boolean('is_bundle_child');
            $table->string('bundle_parent_tpin', 100)->nullable();
            $table->boolean('bundle_parent_visible')->nullable();
            $table->boolean('is_parts');
            $table->decimal('qty_available', 18, 4);
            $table->date('ss_date')->index();

            $table->index(['ss_date', 'tpin']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_stock_snapshots');
    }
};
