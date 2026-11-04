-- ─────────────────────────────────────────────────────────────────────────
-- Rooto backend migration — 2026-11-04
-- Run ONCE in Hostinger phpMyAdmin (database u117099981_vfsportal)
-- BEFORE uploading the new PHP files. Take a backup (Export) first.
-- ─────────────────────────────────────────────────────────────────────────

-- 1) Stock in kg needs decimals: orders take 0.25 kg steps, and an INT column
--    silently rounds "30 - 0.25" back to 30.
ALTER TABLE `products`
  MODIFY `stock` DECIMAL(10,2) NOT NULL DEFAULT 0.00;

-- 2) Remember whether an order actually reduced stock, so cancelling an
--    order only gives back what it took. Existing orders = 0 (they never
--    reduced stock), so cancelling them no longer inflates stock.
ALTER TABLE `orders`
  ADD COLUMN `stock_deducted` TINYINT(1) NOT NULL DEFAULT 0 AFTER `payment_method`;

-- 3) Past orders were saved with an empty payment_method because the code
--    sent 'cod', which is not in ENUM('cash','online','upi'). Every order so
--    far was cash on delivery.
UPDATE `orders` SET `payment_method` = 'cash'
WHERE `payment_method` = '' OR `payment_method` IS NULL;

-- ─────────────────────────────────────────────────────────────────────────
-- 4) One account per email / phone.
--    The customers table has no UNIQUE index, so duplicates got in
--    (right now: 1 duplicated email, 4 duplicated phone numbers).
--    register.php now refuses duplicates, and login.php checks every match.
--
--    Step A — see the duplicates (run, look, decide which account to keep):
SELECT 'email' AS field, LOWER(email) AS value, COUNT(*) AS accounts, GROUP_CONCAT(id ORDER BY id) AS customer_ids
FROM customers WHERE email IS NOT NULL AND email <> ''
GROUP BY LOWER(email) HAVING COUNT(*) > 1
UNION ALL
SELECT 'phone', phone, COUNT(*), GROUP_CONCAT(id ORDER BY id)
FROM customers GROUP BY phone HAVING COUNT(*) > 1;

--    Step B — for each duplicate, keep one account and either delete the
--    others or change their email/phone. Then add the indexes (this fails
--    while any duplicate is left, which is safe):
-- ALTER TABLE `customers`
--   ADD UNIQUE KEY `uniq_customers_email` (`email`),
--   ADD UNIQUE KEY `uniq_customers_phone` (`phone`);

-- ─────────────────────────────────────────────────────────────────────────
-- 5) Delivery areas. serviceable_pincodes is empty, so every pincode in
--    India is treated as deliverable. Add the pincodes you actually deliver
--    to; as soon as one active row exists, all other pincodes are refused.
--    Example (edit before running):
-- INSERT INTO `serviceable_pincodes` (pincode, city, state, cod_available, delivery_days, is_active) VALUES
--   ('600100', 'Chennai', 'Tamil Nadu', 1, 1, 1),
--   ('600129', 'Chennai', 'Tamil Nadu', 1, 1, 1);
