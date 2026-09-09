-- ============================================================================
--  Track whether the order-confirmation invoice email was sent.
--  ADDITIVE. One nullable column. Run once in phpMyAdmin.
-- ============================================================================

ALTER TABLE `orders`
  ADD COLUMN `invoice_emailed_at` DATETIME DEFAULT NULL
  COMMENT 'when the confirmation invoice email was successfully sent; NULL = not sent';
