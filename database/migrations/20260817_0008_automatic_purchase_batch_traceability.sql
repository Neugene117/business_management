-- Automatically batch every purchase line and prepare existing unbatched stock
-- for server-side FEFO allocation during sales.

START TRANSACTION;

CREATE TEMPORARY TABLE tmp_purchase_batch_map (
    purchase_item_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    business_id BIGINT UNSIGNED NOT NULL,
    purchase_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    lot_number VARCHAR(100) NOT NULL,
    batch_id BIGINT UNSIGNED NULL,
    UNIQUE KEY uq_tmp_purchase_batch_lot (business_id, lot_number)
);

INSERT INTO tmp_purchase_batch_map (purchase_item_id,business_id,purchase_id,product_id,lot_number)
SELECT pi.id,pi.business_id,pi.purchase_id,pi.product_id,
       CONCAT('AUTO-B',pi.business_id,'-P',pi.purchase_id,'-I',pi.id)
FROM purchase_items pi
WHERE pi.batch_id IS NULL;

INSERT IGNORE INTO product_batches (business_id,product_id,lot_number,expires_at,created_at)
SELECT m.business_id,m.product_id,m.lot_number,pi.expiry_date,pi.created_at
FROM tmp_purchase_batch_map m
JOIN purchase_items pi ON pi.id=m.purchase_item_id AND pi.business_id=m.business_id;

UPDATE tmp_purchase_batch_map m
JOIN product_batches pb
  ON pb.business_id=m.business_id
 AND pb.product_id=m.product_id
 AND pb.lot_number=m.lot_number
SET m.batch_id=pb.id;

UPDATE purchase_items pi
JOIN tmp_purchase_batch_map m
  ON m.purchase_item_id=pi.id AND m.business_id=pi.business_id
SET pi.batch_id=m.batch_id;

UPDATE purchase_return_items pri
JOIN purchase_items pi
  ON pi.id=pri.purchase_item_id AND pi.business_id=pri.business_id
SET pri.batch_id=pi.batch_id
WHERE pri.batch_id IS NULL;

UPDATE inventory_movements im
JOIN purchase_items pi
  ON pi.id=im.purchase_item_id AND pi.business_id=im.business_id
SET im.batch_id=pi.batch_id
WHERE im.batch_id IS NULL;

UPDATE inventory_movements im
JOIN purchase_return_items pri
  ON pri.id=im.purchase_return_item_id AND pri.business_id=im.business_id
SET im.batch_id=pri.batch_id
WHERE im.batch_id IS NULL;

UPDATE inventory_cost_layers icl
JOIN purchase_items pi
  ON pi.id=icl.purchase_item_id AND pi.business_id=icl.business_id
SET icl.batch_id=pi.batch_id
WHERE icl.batch_id IS NULL;

-- Estimate the remaining quantity from historical unbatched purchases using
-- FIFO history: current stock is assigned to the newest unconsumed receipts.
CREATE TEMPORARY TABLE tmp_auto_batch_capacity AS
SELECT m.business_id,p.location_id,m.product_id,m.batch_id,m.purchase_item_id,
       p.purchase_date,
       GREATEST(pi.received_quantity-COALESCE(r.returned_quantity,0),0) capacity
FROM tmp_purchase_batch_map m
JOIN purchase_items pi
  ON pi.id=m.purchase_item_id AND pi.business_id=m.business_id
JOIN purchases p
  ON p.id=pi.purchase_id AND p.business_id=pi.business_id
LEFT JOIN (
    SELECT pri.business_id,pri.purchase_item_id,SUM(pri.quantity) returned_quantity
    FROM purchase_return_items pri
    JOIN purchase_returns pr
      ON pr.id=pri.purchase_return_id AND pr.business_id=pri.business_id
    WHERE pr.status='COMPLETED'
    GROUP BY pri.business_id,pri.purchase_item_id
) r ON r.business_id=pi.business_id AND r.purchase_item_id=pi.id
WHERE m.batch_id IS NOT NULL;

CREATE TEMPORARY TABLE tmp_unassigned_stock AS
SELECT ib.business_id,ib.location_id,ib.product_id,
       GREATEST(ib.quantity_on_hand-COALESCE(existing.existing_batch_quantity,0),0) unassigned_quantity
FROM inventory_balances ib
LEFT JOIN (
    SELECT bib.business_id,bib.location_id,pb.product_id,SUM(bib.quantity_on_hand) existing_batch_quantity
    FROM batch_inventory_balances bib
    JOIN product_batches pb ON pb.id=bib.batch_id AND pb.business_id=bib.business_id
    GROUP BY bib.business_id,bib.location_id,pb.product_id
) existing
  ON existing.business_id=ib.business_id
 AND existing.location_id=ib.location_id
 AND existing.product_id=ib.product_id
WHERE ib.quantity_on_hand>COALESCE(existing.existing_batch_quantity,0);

CREATE TEMPORARY TABLE tmp_auto_batch_allocation AS
SELECT ranked.business_id,ranked.location_id,ranked.product_id,ranked.batch_id,
       LEAST(ranked.capacity,
             GREATEST(ranked.unassigned_quantity-(ranked.running_capacity-ranked.capacity),0)) quantity_on_hand
FROM (
    SELECT c.*,u.unassigned_quantity,
           SUM(c.capacity) OVER (
               PARTITION BY c.business_id,c.location_id,c.product_id
               ORDER BY c.purchase_date DESC,c.purchase_item_id DESC
               ROWS UNBOUNDED PRECEDING
           ) running_capacity
    FROM tmp_auto_batch_capacity c
    JOIN tmp_unassigned_stock u
      ON u.business_id=c.business_id
     AND u.location_id=c.location_id
     AND u.product_id=c.product_id
) ranked
WHERE LEAST(ranked.capacity,
            GREATEST(ranked.unassigned_quantity-(ranked.running_capacity-ranked.capacity),0))>0;

INSERT INTO batch_inventory_balances
    (business_id,location_id,batch_id,quantity_on_hand,reserved_quantity,updated_at)
SELECT business_id,location_id,batch_id,quantity_on_hand,0,UTC_TIMESTAMP(6)
FROM tmp_auto_batch_allocation
ON DUPLICATE KEY UPDATE
    quantity_on_hand=VALUES(quantity_on_hand),
    updated_at=UTC_TIMESTAMP(6);

-- Any remaining opening/adjustment stock receives an internal legacy batch so
-- future sales are still fully traceable and never require manual batch input.
CREATE TEMPORARY TABLE tmp_legacy_stock AS
SELECT u.business_id,u.location_id,u.product_id,
       GREATEST(u.unassigned_quantity-COALESCE(a.allocated_quantity,0),0) quantity_on_hand
FROM tmp_unassigned_stock u
LEFT JOIN (
    SELECT business_id,location_id,product_id,SUM(quantity_on_hand) allocated_quantity
    FROM tmp_auto_batch_allocation
    GROUP BY business_id,location_id,product_id
) a
  ON a.business_id=u.business_id
 AND a.location_id=u.location_id
 AND a.product_id=u.product_id
WHERE u.unassigned_quantity>COALESCE(a.allocated_quantity,0);

INSERT IGNORE INTO product_batches (business_id,product_id,lot_number,created_at)
SELECT DISTINCT legacy.business_id,legacy.product_id,
       CONCAT('AUTO-LEGACY-B',legacy.business_id,'-P',legacy.product_id),
       UTC_TIMESTAMP(6)
FROM (
    SELECT business_id,product_id
    FROM tmp_legacy_stock
    WHERE quantity_on_hand>0
    UNION
    SELECT business_id,product_id
    FROM inventory_movements
    WHERE batch_id IS NULL
    UNION
    SELECT business_id,product_id
    FROM inventory_cost_layers
    WHERE batch_id IS NULL
) legacy;

INSERT INTO batch_inventory_balances
    (business_id,location_id,batch_id,quantity_on_hand,reserved_quantity,updated_at)
SELECT l.business_id,l.location_id,pb.id,l.quantity_on_hand,0,UTC_TIMESTAMP(6)
FROM tmp_legacy_stock l
JOIN product_batches pb
  ON pb.business_id=l.business_id
 AND pb.product_id=l.product_id
 AND pb.lot_number=CONCAT('AUTO-LEGACY-B',l.business_id,'-P',l.product_id)
WHERE l.quantity_on_hand>0
ON DUPLICATE KEY UPDATE
    quantity_on_hand=VALUES(quantity_on_hand),
    updated_at=UTC_TIMESTAMP(6);

-- Historical movements that cannot be attributed to one purchase remain
-- explicitly traceable through the internal legacy batch. Future purchases
-- never use this fallback; they retain their own generated purchase batch.
UPDATE inventory_movements im
JOIN product_batches pb
  ON pb.business_id=im.business_id
 AND pb.product_id=im.product_id
 AND pb.lot_number=CONCAT('AUTO-LEGACY-B',im.business_id,'-P',im.product_id)
SET im.batch_id=pb.id
WHERE im.batch_id IS NULL;

UPDATE inventory_cost_layers icl
JOIN product_batches pb
  ON pb.business_id=icl.business_id
 AND pb.product_id=icl.product_id
 AND pb.lot_number=CONCAT('AUTO-LEGACY-B',icl.business_id,'-P',icl.product_id)
SET icl.batch_id=pb.id
WHERE icl.batch_id IS NULL;

UPDATE sale_items si
JOIN product_batches pb
  ON pb.business_id=si.business_id
 AND pb.product_id=si.product_id
 AND pb.lot_number=CONCAT('AUTO-LEGACY-B',si.business_id,'-P',si.product_id)
SET si.batch_id=pb.id
WHERE si.batch_id IS NULL;

UPDATE sale_return_items sri
JOIN product_batches pb
  ON pb.business_id=sri.business_id
 AND pb.product_id=sri.product_id
 AND pb.lot_number=CONCAT('AUTO-LEGACY-B',sri.business_id,'-P',sri.product_id)
SET sri.batch_id=pb.id
WHERE sri.batch_id IS NULL;

UPDATE stock_adjustment_items sai
JOIN product_batches pb
  ON pb.business_id=sai.business_id
 AND pb.product_id=sai.product_id
 AND pb.lot_number=CONCAT('AUTO-LEGACY-B',sai.business_id,'-P',sai.product_id)
SET sai.batch_id=pb.id
WHERE sai.batch_id IS NULL;

UPDATE stock_take_items sti
JOIN product_batches pb
  ON pb.business_id=sti.business_id
 AND pb.product_id=sti.product_id
 AND pb.lot_number=CONCAT('AUTO-LEGACY-B',sti.business_id,'-P',sti.product_id)
SET sti.batch_id=pb.id
WHERE sti.batch_id IS NULL;

DROP TEMPORARY TABLE tmp_legacy_stock;
DROP TEMPORARY TABLE tmp_auto_batch_allocation;
DROP TEMPORARY TABLE tmp_unassigned_stock;
DROP TEMPORARY TABLE tmp_auto_batch_capacity;
DROP TEMPORARY TABLE tmp_purchase_batch_map;

COMMIT;
