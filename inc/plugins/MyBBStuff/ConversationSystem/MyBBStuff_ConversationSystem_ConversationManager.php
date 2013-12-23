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

		$query = <<<SQL
	SELECT * FROM %sconversations c LEFT JOIN %sconversation_participants cp ON (c.id = cp.conversation_id) WHERE cp.user_id = '{$userId}';
SQL;

		$query = sprintf($query, TABLE_PREFIX, TABLE_PREFIX);
	}

}
