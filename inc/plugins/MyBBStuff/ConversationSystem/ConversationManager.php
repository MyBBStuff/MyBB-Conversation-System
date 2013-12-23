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

	/**
	 * Get the number of conversations the current user is involved in.
	 *
	 * @return int The number of conversations the user has either created or is a participant within.
	 */
	public function getNumInvolvedConversations()
	{
		$queryString = <<<SQL
	SELECT COUNT(*) AS count FROM PREFIX_conversations c LEFT JOIN PREFIX_conversation_participants cp ON (c.id = cp.conversation_id) WHERE cp.user_id = '{$this->mybb->user['uid']}' OR c.user_id = '{$this->mybb->user['uid']}';
SQL;
		$queryString = str_replace('PREFIX_', TABLE_PREFIX, $queryString);

		$query = $this->db->write_query($queryString);

		$numConversations = (int) $this->db->fetch_field($query, 'count');

		return $numConversations;
	}

	/**
	 * Get a list of conversations.
	 *
	 * @param int $start   The start index to use when fetching conversations. Defaults to 0.
	 * @param int $perPage The number of conversations to fetch. Defaults to 10.
	 *
	 * @return array The most recent conversations, sorted by the creation date of the last message.
	 */
	public function getConversations($start = 0, $perPage = 10)
	{
		$start   = (int) $start;
		$perPage = (int) $perPage;

		$conversations = array();

		$queryString = <<<SQL
	SELECT
	  c.id,
	  c.subject,
	  c.created_at                   AS conversation_created_at,
	  creating_user.username         AS creator_username,
	  creating_user.usergroup        AS creator_usergroup,
	  creating_user.displaygroup     AS creator_displaygroup,
	  creating_user.avatar           AS creator_avatar,
	  last_message_user.uid          AS last_message_uid,
	  last_message.updated_at        AS last_message_date,
	  last_message_user.username     AS last_message_username,
	  last_message_user.avatar       AS last_message_avatar,
	  last_message_user.usergroup    AS last_message_usergroup,
	  last_message_user.displaygroup AS last_message_displaygroup
	FROM PREFIX_conversations c INNER JOIN PREFIX_users creating_user
		ON (c.user_id = creating_user.uid)
	  INNER JOIN PREFIX_conversation_messages last_message
		ON (c.last_message_id = last_message.id)
	  INNER JOIN PREFIX_users last_message_user
		ON (last_message.user_id = last_message_user.uid)
	  LEFT JOIN PREFIX_conversation_participants cp
		ON (c.id = cp.conversation_id)
	WHERE cp.user_id = '{$this->mybb->user['uid']}' OR c.user_id = '{$this->mybb->user['uid']}'
	ORDER BY last_message_date DESC, conversation_created_at DESC LIMIT {$start}, {$perPage};
SQL;

		$queryString = str_replace('PREFIX_', TABLE_PREFIX, $queryString);

		$query = $this->db->write_query($queryString);

		while ($conversation = $this->db->fetch_array($query)) {
			$conversations[] = $conversation;
		}

		return $conversations;
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

		$participants[]              = $this->mybb->user['uid'];
		$participants                = $this->addParticipantsToConversation($conversationId, $participants);
		$insertArray['participants'] = $participants;

		$firstMessage            = $this->addMessageToConversation($conversationId, $firstMessage);
		$insertArray['messages'] = array($firstMessage);

		$this->updateLastMessageId($conversationId, $firstMessage['id']);
		$insertArray['last_message_id'] = $firstMessage['id'];

		return $insertArray;
	}

	/**
	 * Add an array of participants to a conversation.
	 *
	 * @param int   $conversationId The ID of the conversation to add the participants to.
	 * @param array $participants   An array of participant usernames or user IDs to add.
	 *
	 * @return array The details of the new participants added.
	 */
	public function addParticipantsToConversation($conversationId = 0, array $participants)
	{
		$conversationId = (int) $conversationId;

		$newParticipants = $this->getNewParticipants($conversationId, $participants);

		$participantsToAdd = array();

		$now = new DateTime();

		foreach ($newParticipants as $participant) {
			$participantsToAdd[] = array(
				'conversation_id'  => $conversationId,
				'user_id'          => (int) $participant,
				'inviting_user_id' => (int) $this->mybb->user['uid'],
				'created_at'       => $this->db->escape_string($now->format('Y-m-d H:i:s')),
			);
		}

		$this->db->insert_query_multiple('conversation_participants', $participantsToAdd);

		return $participantsToAdd;
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
	 * Get the details for participants in a conversation.
	 *
	 * @param int $conversationId The ID of the conversation.
	 *
	 * @return array Participant details.
	 */
	public function getParticipantsForConversation($conversationId)
	{
		$conversationId = (int) $conversationId;
		$participants   = array();

		$query = "SELECT * FROM %sconversation_participants cp LEFT JOIN %susers u ON (cp.user_id = u.uid) WHERE cp.conversation_id = '{$conversationId}'";

		$query = sprintf($query, TABLE_PREFIX, TABLE_PREFIX);

		$query = $this->db->write_query($query);

		if ($this->db->num_rows($query) > 0) {
			while ($user = $this->db->fetch_array($query)) {
				$participants[] = array(
					'uid'          => (int) $user['user_id'],
					'username'     => $user['username'],
					'avatar'       => $user['avatar'],
					'usergroup'    => $user['usergroup'],
					'displaygroup' => $user['displaygroup'],
				);
			}
		}

		return $participants;
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

	/**
	 * Get an array of new participants to add to a conversation.
	 *
	 * @param int   $conversationId  The ID of the conversation to get the new participants from.
	 * @param array $participantList An array of participants to get the non-existent participants from.
	 *
	 * @return array An array of new participants to be added to the conversation.
	 */
	private function getNewParticipants($conversationId, array $participantList)
	{
		$currentParticipants = $this->getParticipantsForConversation($conversationId);

		$newParticipants        = array();
		$newParticipantsStrings = array();

		foreach ($participantList as $potentialParticipant) {
			$found = false;

			foreach ($currentParticipants as $participant) {
				if (is_numeric($potentialParticipant)) {
					if ($participant['uid'] == $potentialParticipant) {
						$found = true;
						break;
					}
				} else {
					if ($participant['username'] == $potentialParticipant) {
						$found = true;
						break;
					}
				}

			}

			if (!$found) {
				if (is_numeric($potentialParticipant)) {
					$newParticipants[] = (int) $potentialParticipant;
				} else {
					$newParticipantsStrings[] = $potentialParticipant;
				}
			}
		}

		$newParticipants = array_merge($newParticipants, $this->getUidsFromUserNames($newParticipantsStrings));
		$newParticipants = array_unique($newParticipants);

		return $newParticipants;
	}

	/**
	 * Get an array of user IDs based on an array of user names.
	 *
	 * @param array $userNames The user names to fetch the user IDs for.
	 *
	 * @return array The corresponding user IDs.
	 */
	private function getUidsFromUserNames(array $userNames)
	{
		$userNames = array_map('trim', $userNames);
		$userNames = array_map(array($this->db, 'escape_string'), $userNames);
		$userNames = "'" . implode("','", array_filter($userNames)) . "'";

		$userIds = array();

		$query = $this->db->simple_select('users', 'uid', "username IN ({$userNames})");

		while ($user = $this->db->fetch_array($query)) {
			$userIds[] = (int) $user['uid'];
		}

		return $userIds;
	}

}
