ALTER TABLE payments
  MODIFY COLUMN status ENUM('Paid','Unpaid') DEFAULT 'Unpaid',
  ADD COLUMN approval_status ENUM('Pending','Approved','Rejected') NULL AFTER status,
  ADD COLUMN payment_reference VARCHAR(100) NULL AFTER payment_provider,
  ADD COLUMN payment_proof VARCHAR(255) NULL AFTER payment_reference;
