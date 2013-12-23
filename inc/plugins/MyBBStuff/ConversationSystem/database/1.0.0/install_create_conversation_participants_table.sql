CREATE TABLE IF NOT EXISTS PREFIX_conversation_participants
(
  id               INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  conversation_id  INT(10) UNSIGNED NOT NULL,
  user_id          INT(10) UNSIGNED NOT NULL,
  inviting_user_id INT(10) UNSIGNED NOT NULL DEFAULT '0',
  created_at       DATETIME         NOT NULL
);
