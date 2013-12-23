CREATE TABLE PREFIX_conversations
(
  id              INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id         INT(10) UNSIGNED NOT NULL,
  subject         VARCHAR(120)     NOT NULL,
  last_message_id INT(10) UNSIGNED NOT NULL DEFAULT '0',
  created_at      DATETIME         NOT NULL
);
