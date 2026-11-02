<?php

namespace App\Console\Commands;

use App\Models\Entry;
use App\Models\StockGenerationLog;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

#[Signature('stock:clear {locations* : Explicit area prefixes, for example N1 N2} {--execute : Clear stock after interactive confirmation; otherwise preview only}')]
#[Description('Preview or clear old inventory in selected areas, recording changes in the default database')]
class ClearStockCommand extends Command
{
    public function handle(): int
    {
        $operationId = null;
        $portalCommitted = false;

        try {
            $locations = array_values(array_unique(array_map(
                fn (string $location): string => strtoupper(trim($location)),
                $this->argument('locations'),
            )));
            sort($locations);
            foreach ($locations as $location) {
                if (! preg_match('/^[A-Z][A-Z0-9]*$/D', $location)) {
                    throw new RuntimeException('区域必须为 N1、N2 等前缀，不能包含通配符或连字符。');
                }
            }

            if ($this->option('execute') && ! $this->input->isInteractive()) {
                throw new RuntimeException('实际清除必须交互式二次确认，不允许 --no-interaction。');
            }

            $preserved = $this->preservedCartons($locations);
            $details = $this->candidates($this->inventoryQuery($locations)->get(), $preserved);
            $this->info('本次范围：'.implode(', ', $locations).'（仅匹配各区域前缀加连字符）。');
            $this->showDetails($details);

            if (! $this->option('execute')) {
                $this->info('仅预览，未修改库存。实际执行需显式指定 --execute。');

                return self::SUCCESS;
            }

            if ($details->isEmpty()) {
                $this->info('没有需要清除的库存。');

                return self::SUCCESS;
            }

            $this->ensureEntriesGenerated($locations);
            if (! Schema::hasTable('stock_cleanup_logs')) {
                throw new RuntimeException('默认数据库缺少 stock_cleanup_logs，请先运行对应迁移。');
            }

            $confirmation = 'CLEAR '.implode(',', $locations).' '.$details->count();
            $this->warn('将清零上述库存并重置 ItemExtend 同步标记，请暂停所选区域的录入和库存生成后核对。');
            if ($this->ask('确认清除请输入：'.$confirmation) !== $confirmation) {
                $this->info('确认不匹配，已取消，未修改库存。');

                return self::FAILURE;
            }

            $operationId = (string) Str::uuid();
            $fingerprint = $this->fingerprint($details, $preserved);
            $count = DB::connection('portal')->transaction(function () use ($locations, $operationId, $fingerprint): int {
                $preserved = $this->preservedCartons($locations);
                $this->ensureEntriesGenerated($locations);
                $details = $this->candidates($this->inventoryQuery($locations)->lockForUpdate()->get(), $preserved);

                if ($this->fingerprint($details, $preserved) !== $fingerprint) {
                    throw new RuntimeException('预览后库存或保留名单发生变化，已中止，请重新预览并确认。');
                }

                $itemIds = $details->pluck('ItemId')->unique()->sort()->values();
                $itemExtends = collect();
                foreach ($itemIds->chunk(500) as $chunk) {
                    $itemExtends = $itemExtends->concat(DB::connection('portal')->table('ItemExtend')
                        ->whereIn('ItemId', $chunk->all())->lockForUpdate()->get());
                }

                if ($itemIds->diff($itemExtends->pluck('ItemId'))->isNotEmpty()) {
                    throw new RuntimeException('部分商品缺少 ItemExtend，已中止清理。');
                }

                $timestamp = now('UTC');
                $itemExtendsById = $itemExtends->keyBy('ItemId');
                DB::transaction(function () use ($details, $operationId, $locations, $preserved, $fingerprint, $timestamp, $itemExtendsById): void {
                    foreach ($details->chunk(50) as $chunk) {
                        $logs = $chunk->map(function (object $detail) use ($operationId, $locations, $preserved, $fingerprint, $timestamp, $itemExtendsById): array {
                            $inventorySnapshot = (array) $detail;
                            unset($inventorySnapshot['CartonNumber'], $inventorySnapshot['BinNumber'], $inventorySnapshot['CartonID'], $inventorySnapshot['TPIN']);

                            return [
                                'operation_id' => $operationId,
                                'inventory_detail_id' => $detail->Id,
                                'location_code' => explode('-', $detail->BinNumber, 2)[0],
                                'bin_id' => $detail->BinId,
                                'bin_number' => $detail->BinNumber,
                                'carton_id' => $detail->CartonID,
                                'carton_number' => $detail->CartonNumber,
                                'item_id' => $detail->ItemId,
                                'tpin' => $detail->TPIN,
                                'qty_on_hand_before' => $detail->QtyOnHand,
                                'qty_on_hand_after' => 0,
                                'qty_available_before' => $detail->QtyAvailable,
                                'qty_available_after' => 0,
                                'inventory_snapshot' => json_encode($inventorySnapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                                'item_extend_snapshot' => json_encode($itemExtendsById->get($detail->ItemId), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                                'confirmation_context' => json_encode(['locations' => $locations, 'preserved_cartons' => $preserved, 'fingerprint' => $fingerprint], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                                'status' => 'prepared',
                                'created_at' => $timestamp,
                                'completed_at' => null,
                            ];
                        });
                        DB::table('stock_cleanup_logs')->insert($logs->all());
                    }
                });

                foreach ($details->pluck('Id')->chunk(500) as $chunk) {
                    DB::connection('portal')->table('portal_realtime_inventory_detail')
                        ->whereIn('Id', $chunk->all())
                        ->update([
                            'QtyOnHand' => 0,
                            'QtyAvailable' => 0,
                            'LastModificationTime' => $timestamp,
                        ]);
                }

                foreach ($itemIds->chunk(500) as $chunk) {
                    DB::connection('portal')->table('ItemExtend')->whereIn('ItemId', $chunk->all())
                        ->update(['IsSyncStockToBolton' => 0, 'IsSyncStockToMagento' => 0]);
                }

                return $details->count();
            });
            $portalCommitted = true;
            DB::table('stock_cleanup_logs')->where('operation_id', $operationId)->update([
                'status' => 'completed',
                'completed_at' => now('UTC'),
            ]);

            $this->info("事务已提交，清零 {$count} 条库存明细，并重置相关商品同步标记。批次：{$operationId}");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());
            if ($operationId !== null) {
                $this->warn('清除批次：'.$operationId.'。默认库中 prepared 状态仅表示已保存清除前快照，不代表清除成功，请核对 Portal 库存。');
            }
            if ($portalCommitted) {
                $this->warn('Portal 库存事务已经提交，完成状态更新失败，请核对日志，勿直接重试清除。');
            }

            return self::FAILURE;
        }
    }

    /** @param list<string> $locations */
    private function inventoryQuery(array $locations): Builder
    {
        return DB::connection('portal')->table('portal_realtime_inventory_detail as pd')
            ->join('Bin as b', 'b.BinID', '=', 'pd.BinId')
            ->join('Carton as c', 'c.CartonID', '=', 'b.CartonID')
            ->join('Item as i', 'i.ItemID', '=', 'pd.ItemId')
            ->where(function (Builder $query) use ($locations): void {
                foreach ($locations as $location) {
                    $query->orWhere('b.BinNumber', 'like', $location.'-%');
                }
            })
            ->where(function (Builder $query): void {
                $query->where('pd.QtyOnHand', '>', 0)->orWhere('pd.QtyAvailable', '>', 0);
            })
            ->select('pd.*', 'c.CartonNumber', 'b.BinNumber', 'c.CartonID', 'i.TPIN')
            ->orderBy('pd.Id');
    }

    private function normalizeCartonNumber(string $cartonNumber): string
    {
        return mb_strtoupper(rtrim($cartonNumber));
    }

    /** @param Collection<int, object> $details */
    private function showDetails(Collection $details): void
    {
        $this->table(['区域', '明细数', 'QtyOnHand', 'QtyAvailable'], $details
            ->groupBy(fn (object $detail): string => explode('-', $detail->BinNumber, 2)[0])
            ->map(fn (Collection $rows, string $location): array => [$location, $rows->count(), $rows->sum('QtyOnHand'), $rows->sum('QtyAvailable')])
            ->values()->all());
        $this->table(
            ['InventoryDetailId', 'CartonNumber', 'BinNumber', 'ItemId', 'TPIN', 'QtyOnHand', 'QtyAvailable'],
            $details->map(fn (object $detail): array => [
                $detail->Id, $detail->CartonNumber, $detail->BinNumber, $detail->ItemId,
                $detail->TPIN, $detail->QtyOnHand, $detail->QtyAvailable,
            ])->all(),
        );
        $this->info("待清零 {$details->count()} 条，QtyOnHand 合计 {$details->sum('QtyOnHand')}，QtyAvailable 合计 {$details->sum('QtyAvailable')}。");
    }

    /** @param list<string> $locations
     * @return array{ids: array<int|string, true>, numbers: array<int|string, true>}
     */
    private function preservedCartons(array $locations): array
    {
        $preserved = ['ids' => [], 'numbers' => []];
        foreach ($locations as $location) {
            $logs = StockGenerationLog::query()->where('location_code', 'like', $location.'-%')
                ->get(['type', 'code', 'carton_id']);
            $hasCarton = false;
            foreach ($logs as $log) {
                if ($log->carton_id !== null) {
                    $preserved['ids'][(string) $log->carton_id] = true;
                    $hasCarton = true;
                }
                if ($log->type === Entry::TYPE_CARTON && $this->normalizeCartonNumber($log->code) !== '') {
                    $preserved['numbers'][$this->normalizeCartonNumber($log->code)] = true;
                    $hasCarton = true;
                }
            }
            if (! $hasCarton) {
                throw new RuntimeException("{$location} 没有有效的库存生成保留记录，已中止。请核对区域。");
            }
        }
        ksort($preserved['ids']);
        ksort($preserved['numbers']);

        return $preserved;
    }

    /** @param list<string> $locations */
    private function ensureEntriesGenerated(array $locations): void
    {
        foreach ($locations as $location) {
            if (Entry::query()->where('location_code', 'like', $location.'-%')
                ->where('is_deleted', false)->where('stock_generated', false)->exists()) {
                throw new RuntimeException("{$location} 仍有未成功生成库存的录入记录，请先处理后再清理。");
            }
        }
    }

    /**
     * @param  Collection<int, object>  $details
     * @param  array{ids: array<int|string, true>, numbers: array<int|string, true>}  $preserved
     * @return Collection<int, object>
     */
    private function candidates(Collection $details, array $preserved): Collection
    {
        return $details->reject(fn (object $detail): bool => isset($preserved['ids'][(string) $detail->CartonID])
            || isset($preserved['numbers'][$this->normalizeCartonNumber($detail->CartonNumber)]))->values();
    }

    /**
     * @param  Collection<int, object>  $details
     * @param  array{ids: array<int|string, true>, numbers: array<int|string, true>}  $preserved
     */
    private function fingerprint(Collection $details, array $preserved): string
    {
        return hash('sha256', json_encode([$details->all(), $preserved], JSON_THROW_ON_ERROR));
    }
}
