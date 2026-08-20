-- ============================================================
--  SoulServe — Add donation_id to food_donations & cloth_donations
--  Safe to run multiple times (uses IF NOT EXISTS)
-- ============================================================
USE adhaar_db;

-- Add donation_id to food_donations
ALTER TABLE food_donations
  ADD COLUMN IF NOT EXISTS donation_id VARCHAR(30) DEFAULT NULL AFTER id,
  ADD UNIQUE KEY IF NOT EXISTS uq_food_donation_id (donation_id);

-- Add donation_id to cloth_donations
ALTER TABLE cloth_donations
  ADD COLUMN IF NOT EXISTS donation_id VARCHAR(30) DEFAULT NULL AFTER id,
  ADD UNIQUE KEY IF NOT EXISTS uq_cloth_donation_id (donation_id);

-- Back-fill existing records that have no donation_id
UPDATE food_donations
  SET donation_id = CONCAT('DON-FOOD-', LPAD(id, 6, '0'))
  WHERE donation_id IS NULL OR donation_id = '';

UPDATE cloth_donations
  SET donation_id = CONCAT('DON-CLO-', LPAD(id, 6, '0'))
  WHERE donation_id IS NULL OR donation_id = '';

SELECT 'donation_id columns added and back-filled.' AS status;
