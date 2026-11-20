-- Pinapahintulutan ang staff na markahan ang reservation bilang hindi naaprubahan.
ALTER TABLE reservations
  MODIFY COLUMN status ENUM('Pending', 'Approved', 'Not Approved') DEFAULT 'Pending';
