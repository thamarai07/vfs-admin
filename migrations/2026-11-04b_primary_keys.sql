-- ─────────────────────────────────────────────────────────────────────────
-- Rooto migration — 2026-11-04 (b): give every table a real primary key
-- Run in Hostinger phpMyAdmin (u117099981_vfsportal). Take an Export backup first.
--
-- WHY: these tables were created without PRIMARY KEY / AUTO_INCREMENT, so
-- almost every row was saved with id = 0. Any query "WHERE id = ?" then hit
-- many rows at once:
--   * cart: re-adding an item rewrote EVERY customer's cart rows, and the
--     website (which keys cart rows by id) could hide items that were still
--     in the cart — they then went into the order.
--   * customer_addresses: deleting / defaulting one address touched others.
--   * order_items: every item had the same id, so order screens mixed items.
--
-- WHAT IT DOES: drops the broken `id` column and adds a fresh auto-numbered
-- primary key (1, 2, 3 …). No row is deleted and no other column changes.
-- Nothing else stores these ids, so renumbering is safe. (Customers may need
-- to pick their saved address again once at checkout.)
-- ─────────────────────────────────────────────────────────────────────────

-- Make sure we are in the shop database (phpMyAdmin may have information_schema selected).
USE `u117099981_vfsportal`;

ALTER TABLE `cart`
  DROP COLUMN `id`,
  ADD COLUMN `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST;

ALTER TABLE `order_items`
  DROP COLUMN `id`,
  ADD COLUMN `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST;

ALTER TABLE `customer_addresses`
  DROP COLUMN `id`,
  ADD COLUMN `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST;

ALTER TABLE `favorites`
  DROP COLUMN `id`,
  ADD COLUMN `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST;

ALTER TABLE `order_status_history`
  DROP COLUMN `id`,
  ADD COLUMN `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST;

ALTER TABLE `logo_master`
  DROP COLUMN `id`,
  ADD COLUMN `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST;

-- Empty tables: just add the key.
ALTER TABLE `banner_sections`
  MODIFY `section_id` INT(11) NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`section_id`);

ALTER TABLE `product_reviews`
  MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`);

-- Check: in every row total_rows = unique_ids and min_id = 1.
SELECT 'cart' AS tbl, COUNT(*) AS total_rows, COUNT(DISTINCT id) AS unique_ids, MIN(id) AS min_id FROM cart
UNION ALL SELECT 'order_items', COUNT(*), COUNT(DISTINCT id), MIN(id) FROM order_items
UNION ALL SELECT 'customer_addresses', COUNT(*), COUNT(DISTINCT id), MIN(id) FROM customer_addresses
UNION ALL SELECT 'favorites', COUNT(*), COUNT(DISTINCT id), MIN(id) FROM favorites
UNION ALL SELECT 'order_status_history', COUNT(*), COUNT(DISTINCT id), MIN(id) FROM order_status_history
UNION ALL SELECT 'logo_master', COUNT(*), COUNT(DISTINCT id), MIN(id) FROM logo_master;
