ALTER TABLE payments
  ADD COLUMN due_date DATE NULL AFTER date_paid;
