<?php
/**
 * Class MyBBStuff_ConversationSystem_ConversationManager
 *
 * @author  Euan T <euan@euantor.com>
 * @version 1.0.0
 * @since   1.0.0
 */

class MyBBStuff_ConversationSystem_ConversationManager
{
	/**
	 * @var MyBB
	 */
	private $mybb;
	/**
	 * @var DB_MySQLi
	 */
	private $db;

	public function __construct(MyBB &$mybb, DB_MySQLi &$db)
	{
		$this->mybb = $mybb;
		$this->db   = $db;
	}

	public function getConversationsForUser($userId = 0)
	{
		$userId = (int) $userId;

		if (empty($userId)) {
			$userId = (int) $this->mybb->user['uid'];
		}
	}

	/**
	 * Create a new conversation.
	 *
	 * @param array  $participants The participants to include in the conversation.
	 * @param string $subject      The subject of the conversation.
	 * @param string $firstMessage The first message to add to the conversation.
	 *
	 * @return array The new conversation's details, including attached messages.
	 */
	public function createConversation(array $participants, $subject, $firstMessage = '')
	{
		$subject = $this->db->escape_string($subject);

		$now = new DateTime();

		$insertArray = array(
			'user_id'         => (int) $this->mybb->user['uid'],
			'subject'         => $subject,
			'last_message_id' => 0,
			'created_at'      => $this->db->escape_string($now->format('Y-m-d H:i:s')),
		);

		$conversationId    = $this->db->insert_query('conversations', $insertArray);
		$insertArray['id'] = $conversationId;

		$firstMessage            = $this->addMessageToConversation($conversationId, $firstMessage);
		$insertArray['messages'] = array($firstMessage);

		$this->updateLastMessageId($conversationId, $firstMessage['id']);
		$insertArray['last_message_id'] = $firstMessage['id'];

		return $insertArray;
	}

	/**
	 * Add a new message to a conversation.
	 *
	 * @param int    $conversationId The ID of the conversation to add the message to.
	 * @param string $message        The message to add.
	 * @param bool   $includeSig     Whether to include the user's signature in the message.
	 *
	 * @return array The new message's details.
	 */
	public function addMessageToConversation($conversationId = 0, $message, $includeSig = true)
	{
		$conversationId = (int) $conversationId;
		$message        = $this->db->escape_string($message);
		$includeSig     = (bool) $includeSig;

		$now = new DateTime();

		$insertArray = array(
			'user_id'           => (int) $this->mybb->user['uid'],
			'conversation_id'   => $conversationId,
			'message'           => $message,
			'include_signature' => (int) $includeSig,
			'created_at'        => $this->db->escape_string($now->format('Y-m-d H:i:s')),
			'updated_at'        => $this->db->escape_string($now->format('Y-m-d H:i:s')),
		);

		$conversationMessageId = $this->db->insert_query('conversation_messages', $insertArray);
		$insertArray['id']     = $conversationMessageId;

		return $insertArray;
	}

	/**
	 * Update the last message for a conversation.
	 *
	 * @param int $conversationId The ID of the conversation to update.
	 * @param int $lastMessageId  The ID of the last message for the conversation.
	 *
	 * @return bool Whether the update was successful.
	 */
	private function updateLastMessageId($conversationId, $lastMessageId)
	{
		$conversationId = (int) $conversationId;
		$lastMessageId  = (int) $lastMessageId;

		return (bool) $this->db->update_query(
			'conversations',
			array(
				'last_message_id' => $lastMessageId,
			),
			"id = '{$conversationId}'",
			1
		);
	}

}
