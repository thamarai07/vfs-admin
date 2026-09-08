-- ============================================================================
--  Festive Shopping feature — schema additions
--  SAFE / ADDITIVE ONLY. Run once against the vfsportal database.
--
--  - Creates 2 new isolated tables (festivals, festival_products)
--  - Adds 3 NULLABLE columns to existing tables. Every existing row stays NULL,
--    so cart.php / create_order.php / the storefront behave exactly as before.
--  - No existing table is renamed/restructured, no column dropped or re-purposed,
--    no existing row updated or migrated.
--
--  Re-runnable: table creates use IF NOT EXISTS. The ALTERs will error with
--  "Duplicate column" if run twice — that is harmless, ignore it (or run the
--  guarded block at the bottom instead).
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 1. Festivals master
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `festivals` (
  `id`            INT(11) NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(100) NOT NULL,
  `slug`          VARCHAR(150) NOT NULL,
  `description`   TEXT DEFAULT NULL,
  `tagline`       VARCHAR(255) DEFAULT NULL,
  `banner_image`  VARCHAR(255) DEFAULT NULL,   -- filename in assets/images/uploads/
  `mobile_image`  VARCHAR(255) DEFAULT NULL,
  `theme_color`   VARCHAR(20)  DEFAULT NULL,   -- e.g. #0f9d58
  `display_order` INT(11) DEFAULT 0,
  `is_active`     TINYINT(1) DEFAULT 1,
  `start_date`    DATE DEFAULT NULL,
  `end_date`      DATE DEFAULT NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT current_timestamp(),
  `updated_at`    TIMESTAMP NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_festival_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 2. Festival <-> Product mapping. Points at the EXISTING products.id.
--    Product data itself is never duplicated here.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `festival_products` (
  `id`            INT(11) NOT NULL AUTO_INCREMENT,
  `festival_id`   INT(11) NOT NULL,
  `product_id`    INT(11) NOT NULL,
  `display_order` INT(11) DEFAULT 0,
  `is_active`     TINYINT(1) DEFAULT 1,
  `created_at`    TIMESTAMP NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_festival_product` (`festival_id`, `product_id`),
  KEY `idx_festival` (`festival_id`),
  KEY `idx_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 3. Nullable columns for optional KG + Piece dual pricing.
--    NULL  => product behaves exactly as it does today.
-- ----------------------------------------------------------------------------
ALTER TABLE `products`
  ADD COLUMN `piece_price` DECIMAL(10,2) DEFAULT NULL COMMENT 'per-piece price when product also sells by piece; NULL = single unit'
  AFTER `price_per_kg`;

ALTER TABLE `cart`
  ADD COLUMN `unit` VARCHAR(10) DEFAULT NULL COMMENT 'unit the buyer chose (kg|piece); NULL = product default'
  AFTER `product_id`;

ALTER TABLE `order_items`
  ADD COLUMN `unit` VARCHAR(10) DEFAULT NULL COMMENT 'unit ordered (kg|piece); NULL = legacy/kg'
  AFTER `product_name`;

-- ----------------------------------------------------------------------------
-- Optional: an example festival to smoke-test with (safe to delete afterwards)
-- ----------------------------------------------------------------------------
-- INSERT INTO `festivals` (`name`, `slug`, `tagline`, `theme_color`, `is_active`, `display_order`)
-- VALUES ('Diwali', 'diwali', 'Festival of Lights specials', '#c2410c', 1, 1);
