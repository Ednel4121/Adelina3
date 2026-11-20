ALTER TABLE payments
  ADD COLUMN payment_method ENUM('Cash', 'Online Payment') NULL AFTER status,
  ADD COLUMN payment_provider VARCHAR(50) NULL AFTER payment_method;
