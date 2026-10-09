单独做一个报告页面
地址/report
overview和products两个导航，左侧导航

Overview 页面默认显示今天的录入和库存处理情况，以洛杉矶时间为准。可以按日期筛选，也可以快速查看昨天或全部日期的数据。

页面展示录入记录数、仓储位数、今天录入已处理库存的


Entries by Location 展示所选日期内各仓储位的录入记录数，按数量从多到少排列，方便查看当天录入主要集中在哪些位置。

统计的是录入记录条数，不是产品件数，也不代表该位置已完成清

Location Summary（仓储位汇总） 按仓储位展示所选日期内的录入记录数、已写入库存数、未写入库存数及最后录入时间，方便查看各位置的处理进度。
例如，S3-A8-A2 共录入 47 条，已写入库存 46 条，还有 1 条待处理；其他三个位置的记录均已写入库存。

Products页面，主要根据stock_generation_logs表

筛选栏，主要是通过location筛选，日期，Search Carton, TPIN or SKU

Item表取ItemID，TPIN，SKU

SELECT
  i.ItemID,
  i.TPIN,
  i.SKU,
  i.Name,
  ii.FilePath
FROM
  Item i
  LEFT OUTER JOIN ItemImage (NOLOCK) ii ON ii.ItemID = i.ItemID
  AND ii.IsThumbnail = 1
  AND ii.Remove = 0
  AND ii.Exclude = 0
WHERE
  i.ItemID = 50030

portal_realtime_inventory_detail表的QtyOnHand和QtyAvailable


Quantity On Hand 取QtyOnHand
Quantity Available 取QtyAvailable
Quantity Scanned 取stock_generation_logs的quantity


CartonNumber取Carton表的CartonNumber和CartonID
产品图片为https://content.toolots.com/media/catalog/product +  ii.FilePath


