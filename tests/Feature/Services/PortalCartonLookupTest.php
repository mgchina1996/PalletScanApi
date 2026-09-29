<?php

namespace Tests\Feature\Services;

use App\Services\PortalCartonLookup;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PortalCartonLookupTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.portal', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        DB::purge('portal');

        Schema::connection('portal')->create('Carton', function (Blueprint $table): void {
            $table->integer('CartonID')->primary();
            $table->string('CartonNumber');
        });
        Schema::connection('portal')->create('CartonLineConfirm', function (Blueprint $table): void {
            $table->integer('CartonID');
            $table->integer('ItemID');
        });
        Schema::connection('portal')->create('Item', function (Blueprint $table): void {
            $table->integer('ItemID')->primary();
            $table->string('TPIN');
        });
    }

    protected function tearDown(): void
    {
        DB::disconnect('portal');

        parent::tearDown();
    }

    public function test_returns_carton_and_products_matching_number_or_id(): void
    {
        DB::connection('portal')->table('Carton')->insert([
            ['CartonID' => 503645, 'CartonNumber' => 'CTN80H4TGQZ'],
            ['CartonID' => 700001, 'CartonNumber' => 'CTNIGNORED1'],
        ]);
        DB::connection('portal')->table('Item')->insert([
            ['ItemID' => 10, 'TPIN' => 'EN8ZTQCWL'],
            ['ItemID' => 20, 'TPIN' => '8UVYSEBNL'],
        ]);
        DB::connection('portal')->table('CartonLineConfirm')->insert([
            ['CartonID' => 503645, 'ItemID' => 10],
            ['CartonID' => 503645, 'ItemID' => 20],
            ['CartonID' => 700001, 'ItemID' => 10],
        ]);

        $cartons = (new PortalCartonLookup)->findByRecognizedValues([
            'CTN80H4TGQZ',
            '503645',
        ]);

        $this->assertSame([
            [
                'cartonID' => 503645,
                'cartonNumber' => 'CTN80H4TGQZ',
                'products' => [
                    ['itemID' => 20, 'tpin' => '8UVYSEBNL'],
                    ['itemID' => 10, 'tpin' => 'EN8ZTQCWL'],
                ],
            ],
        ], $cartons);
    }
}
