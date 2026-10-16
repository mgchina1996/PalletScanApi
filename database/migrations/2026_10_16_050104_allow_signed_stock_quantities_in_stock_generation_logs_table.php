<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private array $quantityColumns = [
        'qty_on_hand_before',
        'qty_on_hand_after',
        'qty_available_before',
        'qty_available_after',
    ];

    public function up(): void
    {
        Schema::table('stock_generation_logs', function (Blueprint $table): void {
            foreach ($this->quantityColumns as $column) {
                $table->bigInteger($column)->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        foreach ($this->quantityColumns as $column) {
            if (DB::table('stock_generation_logs')->where($column, '<', 0)->orWhere($column, '>', 4294967295)->exists()) {
                throw new RuntimeException('Cannot restore unsigned integers: stock logs contain values outside the original range.');
            }
        }

        Schema::table('stock_generation_logs', function (Blueprint $table): void {
            foreach ($this->quantityColumns as $column) {
                $table->unsignedInteger($column)->nullable()->change();
            }
        });
    }
};
