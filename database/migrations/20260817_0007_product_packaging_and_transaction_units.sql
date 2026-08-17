-- Optional product packaging with transaction-unit snapshots.
-- Inventory balances, movements, batches, and cost layers remain in base units.

ALTER TABLE products
    ADD COLUMN package_uom_id BIGINT UNSIGNED NULL AFTER uom_id,
    ADD COLUMN units_per_package DECIMAL(18,4) NULL AFTER package_uom_id,
    ADD COLUMN package_sale_price DECIMAL(19,4) NULL AFTER sale_price,
    ADD KEY idx_products_package_uom (package_uom_id),
    ADD CONSTRAINT fk_products_package_uom FOREIGN KEY (package_uom_id) REFERENCES units_of_measure (id) ON DELETE RESTRICT,
    ADD CONSTRAINT chk_products_package_configuration CHECK (
        (package_uom_id IS NULL AND units_per_package IS NULL AND package_sale_price IS NULL)
        OR
        (package_uom_id IS NOT NULL AND units_per_package > 0 AND package_uom_id <> uom_id AND (package_sale_price IS NULL OR package_sale_price >= 0))
    );

ALTER TABLE purchase_items
    ADD COLUMN purchase_uom_id BIGINT UNSIGNED NULL AFTER batch_id,
    ADD COLUMN purchase_quantity DECIMAL(18,4) NULL AFTER purchase_uom_id,
    ADD COLUMN conversion_factor_to_base DECIMAL(18,4) NULL AFTER purchase_quantity,
    ADD COLUMN purchase_unit_cost DECIMAL(19,4) NULL AFTER conversion_factor_to_base,
    ADD COLUMN package_selling_price DECIMAL(19,4) NULL AFTER unit_selling_price;

UPDATE purchase_items pi
JOIN products p ON p.business_id = pi.business_id AND p.id = pi.product_id
SET pi.purchase_uom_id = p.uom_id,
    pi.purchase_quantity = pi.ordered_quantity,
    pi.conversion_factor_to_base = 1.0000,
    pi.purchase_unit_cost = pi.unit_cost;

ALTER TABLE purchase_items
    MODIFY purchase_uom_id BIGINT UNSIGNED NOT NULL,
    MODIFY purchase_quantity DECIMAL(18,4) NOT NULL,
    MODIFY conversion_factor_to_base DECIMAL(18,4) NOT NULL,
    MODIFY purchase_unit_cost DECIMAL(19,4) NOT NULL,
    ADD KEY idx_purchase_items_purchase_uom (purchase_uom_id),
    ADD CONSTRAINT fk_purchase_items_purchase_uom FOREIGN KEY (purchase_uom_id) REFERENCES units_of_measure (id) ON DELETE RESTRICT,
    ADD CONSTRAINT chk_purchase_items_transaction_quantity CHECK (purchase_quantity > 0),
    ADD CONSTRAINT chk_purchase_items_conversion_factor CHECK (conversion_factor_to_base > 0),
    ADD CONSTRAINT chk_purchase_items_transaction_cost CHECK (purchase_unit_cost >= 0),
    ADD CONSTRAINT chk_purchase_items_package_price CHECK (package_selling_price IS NULL OR package_selling_price >= 0);

ALTER TABLE sale_items
    ADD COLUMN sale_uom_id BIGINT UNSIGNED NULL AFTER batch_id,
    ADD COLUMN sale_quantity DECIMAL(18,4) NULL AFTER sale_uom_id,
    ADD COLUMN conversion_factor_to_base DECIMAL(18,4) NULL AFTER sale_quantity,
    ADD COLUMN sale_unit_price DECIMAL(19,4) NULL AFTER conversion_factor_to_base;

UPDATE sale_items si
JOIN products p ON p.business_id = si.business_id AND p.id = si.product_id
SET si.sale_uom_id = p.uom_id,
    si.sale_quantity = si.quantity,
    si.conversion_factor_to_base = 1.0000,
    si.sale_unit_price = si.unit_price;

ALTER TABLE sale_items
    MODIFY sale_uom_id BIGINT UNSIGNED NOT NULL,
    MODIFY sale_quantity DECIMAL(18,4) NOT NULL,
    MODIFY conversion_factor_to_base DECIMAL(18,4) NOT NULL,
    MODIFY sale_unit_price DECIMAL(19,4) NOT NULL,
    ADD KEY idx_sale_items_sale_uom (sale_uom_id),
    ADD CONSTRAINT fk_sale_items_sale_uom FOREIGN KEY (sale_uom_id) REFERENCES units_of_measure (id) ON DELETE RESTRICT,
    ADD CONSTRAINT chk_sale_items_transaction_quantity CHECK (sale_quantity > 0),
    ADD CONSTRAINT chk_sale_items_conversion_factor CHECK (conversion_factor_to_base > 0),
    ADD CONSTRAINT chk_sale_items_transaction_price CHECK (sale_unit_price >= 0);
