# 库存生成命令实现方案

## Context

项目需要把 `entries` 表中 `stock_generated=0` 的记录同步到 portal（SQL Server）库存系统，生成库存明细。1.md 已定义完整流程和日志表结构。命令处理两种 type：

- **carton 类型**：按 `CartonNumber=code` 查 portal 已有 Carton → 更新已有 `RealtimeInventoryDetail` 的 `QtyOnHand/QtyAvailable`。查不到 Carton → 记 error 跳过。
- **tpin 类型**：`code=tpin`，总是新建 Carton+Bin+CartonLineConfirm+RealtimeInventoryDetail。

成功时写 `stock_generation_logs`（只记成功），更新 `entries.stock_generated=1`、`entries.carton_id`；失败写 `entries.error`，`stock_generated` 保持 0 以便重跑。

## 现有可复用资产

- **Portal Eloquent 模型（均已存在，无需新建）**：
  - [Carton.php](file:///www/wwwroot/pallet-scan-api/app/Models/Portal/Carton.php)：`$connection='portal'`，表 `Carton`，PK `CartonID`，fillable 含 CartonNumber/Position/IsConfirmed/WarehouseID/ConfirmedBy/ConfirmedOn
  - [Bin.php](file:///www/wwwroot/pallet-scan-api/app/Models/Portal/Bin.php)：表 `Bin`，PK `BinID`，fillable 含 BinNumber/LocationID/CartonID/IsDynamic/CreatedOn
  - [Item.php](file:///www/wwwroot/pallet-scan-api/app/Models/Portal/Item.php)：表 `Item`，PK `ItemID`，无 fillable（用 `->where('TPIN',$t)->value('ItemID')` 查询）
  - [CartonLineConfirm.php](file:///www/wwwroot/pallet-scan-api/app/Models/Portal/CartonLineConfirm.php)：表 `CartonLineConfirm`，PK `CartonLineConfirmID`，fillable 含 CartonID/ItemID/Quantity
  - [RealtimeInventoryDetail.php](file:///www/wwwroot/pallet-scan-api/app/Models/Portal/RealtimeInventoryDetail.php)：表 `portal_realtime_inventory_detail`，PK `Id`(UUID, HasUuids 自动生成)，fillable 含 ItemId/LocationId/BinId/QtyOnHand/QtyAvailable/CreationTime/LastModificationTime
- **应用模型**：[Entry.php](file:///www/wwwroot/pallet-scan-api/app/Models/Entry.php)（TYPE_CARTON/TYPE_TPIN 常量，`products()` HasMany），`stock_generated`/`error`/`carton_id` 均不在 fillable（系统写入，用 `->update()` 或属性赋值）
- **命令注册**：Laravel 11+ 风格，通过 `routes/console.php`（见 [console.php](file:///www/wwwroot/pallet-scan-api/routes/console.php)），命令类放 `app/Console/Commands/` 自动发现
- **事务先例**：[CartonEntryController.php](file:///www/wwwroot/pallet-scan-api/app/Http/Controllers/Api/V1/CartonEntryController.php) 用 `DB::transaction` 包裹默认连接写操作

## 实现步骤

### 1. 创建日志表迁移

`php artisan make:migration create_stock_generation_logs_table`

按 1.md lines 76-96 定义列。关键点：
- `id` 主键
- `operation_id` uuid + index（每次处理一个 entry 生成一个 UUID，用于关联该 entry 的所有日志行）
- `entry_id` foreignId constrained cascadeOnDelete
- `type` string(16)、`code` string、`action` string(16)（created/updated）
- `carton_id`、`bin_id`、`item_id` unsignedInteger nullable
- `inventory_detail_id` uuid nullable（portal RealtimeInventoryDetail.Id 是 UUID 字符串）
- `tpin` string nullable、`location_code` string(16)
- `quantity` unsignedInteger
- `qty_on_hand_before/after`、`qty_available_before/after` unsignedInteger nullable
- `created_at` timestamp（只此一个时间列，无 updated_at）

### 2. 创建 StockGenerationLog 模型

`app/Models/StockGenerationLog.php`：
- `$fillable` 含上表除 id/created_at 外所有字段
- `const UPDATED_AT = null;`（只管理 created_at，存 UTC）

### 3. 创建 StockGenerationService

`app/Services/StockGenerationService.php`。包含：

- 类常量（1.md 硬编码值）：`LOCATION_ID=22`、`WAREHOUSE_ID=3`、`CONFIRMED_BY=491`、`POSITION=1`
- `generate(Entry $entry): void`：主入口，按 `entry->type` 分发到 `processCartonType` 或 `processTpinType`，外层 try/catch：成功设 `stock_generated=1`、`carton_id`；失败设 `error`
- `processCartonType(Entry $entry, string $operationId): void`：
  1. `$entry->load('products')` 取 tpin/quantity 数组
  2. 查 `Carton::where('CartonNumber',$entry->code)->first()`，不存在抛异常（外层记 error）
  3. 设 `entry->carton_id = carton->CartonID`
  4. 查 `Bin::where('CartonID',$carton->CartonID)->first()` 取 BinID
  5. 预查所有 ItemID：遍历 products，`Item::where('TPIN',$tpin)->value('ItemID')`，缺失抛异常
  6. **portal 事务** `DB::connection('portal')->transaction(function()...)`：
     - 对每个 product：查 `RealtimeInventoryDetail::where('BinId',$binId)->where('ItemId',$itemId)->first()`，记 before，update QtyOnHand/QtyAvailable=quantity，记 after
  7. **app 事务** `DB::transaction(function()...)`：批量写 `StockGenerationLog`（action=updated），update entry（stock_generated=1, carton_id, error=null）
- `processTpinType(Entry $entry, string $operationId): void`：
  1. `Item::where('TPIN',$entry->code)->value('ItemID')`，不存在抛异常
  2. 查 location Bin：`Bin::where('BinNumber',$entry->location_code)->where('LocationID',22)->where('IsDynamic',0)->value('BinID')`
  3. **portal 事务**：
     - `$carton = Carton::create([CartonNumber=>$this->getNewCartonNumber(), Position, IsConfirmed, WarehouseID, ConfirmedBy, ConfirmedOn])`
     - `$bin = Bin::create([BinNumber=>$entry->location_code.'-'.$carton->CartonID, LocationID, IsDynamic=1, CartonID=>$carton->CartonID, CreatedOn])`
     - `CartonLineConfirm::create([CartonID, ItemID, Quantity])`
     - `$rid = RealtimeInventoryDetail::create([ItemId, LocationId, BinId, QtyOnHand, QtyAvailable, CreationTime, LastModificationTime])`
  4. **app 事务**：写 `StockGenerationLog`（action=created，before=null，after=quantity，inventory_detail_id=$rid->Id），update entry（stock_generated=1, carton_id=$carton->CartonID, error=null）
- `getNewCartonNumber(): string`：`do { $r='CTN'.strtoupper(Str::random(8)); } while (Carton::where('CartonNumber',$r)->exists()); return $r;`

### 4. 创建 Artisan 命令

`php artisan make:command GenerateStockCommand`（`app/Console/Commands/GenerateStockCommand.php`）：
- signature：`stock:generate`
- handle()：构造 `StockGenerationService`，查 `Entry::where('stock_generated', false)->orderBy('id')->get()`，遍历每个 entry 调 `service->generate($entry)`，用 `$this->info/error` 报告进度与失败
- 每个 entry 独立 try/catch，一个失败不中断后续

### 5. 跨库事务策略

portal（sqlsrv）与默认（mysql）是不同连接，单一 `DB::transaction` 不能跨库。策略：
- portal 写操作包在 `DB::connection('portal')->transaction()`
- app 写操作（log + entry 更新）包在 `DB::transaction()`
- 先 portal 后 app：portal 成功才写 app。若 app 写失败而 portal 已提交，靠 `operation_id` + `entries.stock_generated` 仍为 0 在下次重跑时由日志定位已写库存（carton 类型重跑会按 CartonNumber 重新找到并再次更新，幂等；tpin 类型重跑会新建 carton——这是已知边界，1.md 的 operation_id 设计即为此提供恢复线索）

### 6. 格式化与执行

- 运行 `vendor/bin/pint --dirty --format agent`
- 运行 `php artisan migrate` 应用日志表迁移

## 验证

- `php artisan list` 确认 `stock:generate` 命令注册成功
- `php artisan stock:generate` 运行，观察输出：处理多少 entry、成功/失败数
- 用 `database-query` 或 `php artisan tinker` 查 `entries` 表验证 `stock_generated=1`、`carton_id` 已写、失败的有 `error`
- 查 `stock_generation_logs` 验证成功操作的 before/after 记录
- portal 侧查 `Carton`/`Bin`/`RealtimeInventoryDetail` 验证新建/更新数据

## 备注

- 1.md 硬编码值（LocationID=22 等）放在 service 类常量，环境无关则保持；如需可配置后续再抽到 config
- 不写测试用例（项目约定）
