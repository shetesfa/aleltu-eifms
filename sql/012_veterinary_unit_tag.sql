-- ============================================================================
-- Aleltu EIFMS -- Migration 012 (OPTIONAL but recommended): veterinary cost tag
--
-- WHY: fin_expenses.unit_tag and fin_transactions.unit_tag cannot say
--      'veterinary' today, so a vet bill cannot be booked to the veterinary
--      unit (it would be forced into another unit or "general_overhead").
--      Without this, veterinary "Treatment Costs" can never be reported.
-- SAFE: it only ADDS one allowed value at the end of the list. Existing rows
--       and their values are untouched. Re-runnable. Back up first.
-- AFTER running: apply patch P3 (adds 'veterinary' to FINANCE_UNIT_TAGS in
--       includes/functions.php) so the expense form offers the new choice.
-- ============================================================================
SET NAMES utf8mb4;

ALTER TABLE fin_expenses
  MODIFY COLUMN unit_tag ENUM('dairy','poultry','fish','feed_production','warehouse','distribution','general_overhead','veterinary') NOT NULL;

ALTER TABLE fin_transactions
  MODIFY COLUMN unit_tag ENUM('dairy','poultry','fish','feed_production','warehouse','distribution','general_overhead','veterinary') NOT NULL;
