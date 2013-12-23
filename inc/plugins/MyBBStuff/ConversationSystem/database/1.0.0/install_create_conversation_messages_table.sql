CREATE TABLE IF NOT EXISTS PREFIX_conversation_messages (
  id                INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id           INT(10) UNSIGNED NOT NULL,
  conversation_id   INT(10) UNSIGNED NOT NULL,
  message           TEXT             NOT NULL,
  include_signature INT(1)           NOT NULL DEFAULT '1',
  created_at        DATETIME         NOT NULL,
  updated_at        DATETIME         NOT NULL
);
