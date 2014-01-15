CREATE TABLE IF NOT EXISTS PREFIX_conversation_message_views
(
  id                      INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id                 INT(10) UNSIGNED NOT NULL,
  conversation_message_id INT(10) UNSIGNED NOT NULL,
  created_at              DATETIME         NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_message_view_unique` (`user_id`, `conversation_message_id`)
);
