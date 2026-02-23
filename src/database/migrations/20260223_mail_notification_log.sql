CREATE TABLE IF NOT EXISTS mail_notification_log (
  id INT NOT NULL AUTO_INCREMENT,
  event_code VARCHAR(64) NOT NULL,
  event_key VARCHAR(191) NOT NULL,
  recipient_email VARCHAR(150) NOT NULL,
  status ENUM('sent','failed','skipped') NOT NULL DEFAULT 'sent',
  error_message TEXT DEFAULT NULL,
  payload JSON DEFAULT NULL,
  sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mail_event_recipient (event_code, event_key, recipient_email),
  KEY idx_mail_sent_at (sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;