<?php
/**
 * MyBB Conversation System frontend
 *
 * A replacement for MyBB's core Private Message functionality based on IPB's Conversation feature.
 *
 * @category Front-End
 * @package  MyBB-Conversation-System
 * @author   Euan T. <euan@euantor.com>
 * @license  http://opensource.org/licenses/mit-license.php The MIT License
 * @version  0.1.0
 * @link     http://euantor.com/mybb-conversation-system
 */

define('IN_MYBB', 1);
define('THIS_SCRIPT', 'conversations.php');

$templatelist = 'mybbconversations_list,mybbconversations_row_empty,mybbconversations_row,mybbconversations_create_button,multipage_breadcrumb,multipage,multipage_end,multipage_nextpage,multipage_page,multipage_page_current,multipage_page_link_current,multipage_prevpage,multipage_start';

require dirname(__FILE__).'/global.php';

define('URL_VIEW_CONVERSATION', 'conversations.php?action=view&id=%s');

if (!isset($lang->mybbconversations)) {
	$lang->load('mybbconversations');
}

add_breadcrumb($lang->mybbconversations_nav, 'conversations.php');

if ($mybb->user['uid'] == 0 OR !$mybb->settings['mybbconversations_enabled']) {
	error_no_permission();
}

if ($mybb->input['action'] == 'create_conversation' AND strtolower($mybb->request_method) == 'post') {
	verify_post_check($mybb->input['my_post_key']);
	$errors = array();

	$mybb->input['subejct'] = trim($mybb->input['subject']);
	$mybb->input['message'] = trim($mybb->input['message']);

	if (!isset($mybb->input['subject']) OR empty($mybb->input['subject'])) {
		$errors[] = $lang->mybbconversations_error_title_required;
	}

	if (!isset($mybb->input['message']) OR empty($mybb->input['message'])) {
		$errors[] = $lang->mybbconversations_error_message_required;
	}

	if (!empty($errors)) {
		$inline_errors         = inline_error($errors);
		$mybb->input['action'] = 'create_conversation';
	}

	$now = new DateTime();

	$insertArray = array(
		'subject' => $db->escape_string($mybb->input['subject']),
		'user_id' => (int) $mybb->user['uid'],
		'created_at' => $now->format('Y-m-d H:i:s'),
	);

	$conversationId = (int) $db->insert_query('conversations', $insertArray);

	$insertArray = array(
		'user_id' => (int) $mybb->user['uid'],
		'conversation_id' => $conversationId,
		'message' => $db->escape_string($mybb->input['message']),
		'includesig' => 1,
		'created_at' => $now->format('Y-m-d H:i:s'),
		'updated_at' => $now->format('Y-m-d H:i:s'),
	);

	$firstMessageId = $db->insert_query('conversation_messages', $insertArray);

	$participants = array();
	$participantUids = array();

	if (strstr($mybb->input['participants'], ',')  === false) {
		$mybb->input['participants'] = array(
			$mybb->input['participants'],
		);
	} else {
		$mybb->input['participants'] = explode(',', $mybb->input['participants']);
	}

	$mybb->input['participants']  = array_map('trim', $mybb->input['participants']);
	$mybb->input['participants']  = array_map(array($db, 'escape_string'), $mybb->input['participants']);
	$usernames  = "'".implode("','", array_keys(array_filter($mybb->input['participants'])))."'";
	$users = $db->simple_select('users', 'uid, username', "username IN ({$usernames})");

	foreach ($users as $user) {
		$participantUids[$user['username']] = $user['uid'];
	}

	foreach ($mybb->input['participants'] as $participant) {
		$participants[] = array(
			'conversation_id' => $conversationId,
			'user_id' => (int) $participantUids[$participant],
			'created_at' => $now->format('Y-m-d H:i:s'),
		);
	}

	redirect(sprintf(URL_VIEW_CONVERSATION, $conversationId), 'New conversation created. Taking you to it now...', 'Conversation Created');
}

if ($mybb->input['action'] == 'create_conversation') {
	add_breadcrumb($lang->mybbconversations_nav_create, 'conversations.php?action=create_conversation');

	$codebuttons = build_mycode_inserter();
	eval("\$page = \"".$templates->get('mybbconversations_create_conversation')."\";");
	output_page($page);
}

if (!isset($mybb->input['action']) OR $mybb->input['action'] == 'list') {
	$conversations = '';
	$altbg         = '';
	$uid           = (int)$mybb->user['uid'];

	eval("\$createButton = \"".$templates->get('mybbconversations_create_button')."\";");

	$queryString = "SELECT COUNT(*) AS count FROM %sconversations c LEFT JOIN %sconversation_participants cp ON (c.id = cp.conversation_id) WHERE cp.user_id = '{$uid}'";
	$query       = $db->write_query(sprintf($queryString, TABLE_PREFIX, TABLE_PREFIX));
	$count       = (int)$db->fetch_field($query, 'count');
	unset($queryString);
	unset($query);

	$perpage = (int)$mybb->settings['mybbconversations_conversations_per_page'];
	if ($perpage == 0) {
		$perpage = 20;
	}

	$page  = (int)$mybb->input['page'];
	$pages = $count / $perpage;
	$pages = ceil($pages);
	if ($mybb->input['page'] == "last") {
		$page = $pages;
	}

	if ($page > $pages OR $page <= 0) {
		$page = 1;
	}

	if ($page AND $page > 0) {
		$start = ($page - 1) * $perpage;
	} else {
		$start = 0;
		$page  = 1;
	}
	$multipage = multipage($count, $perpage, $page, 'conversations.php');

	$queryString = "SELECT c.*, cp.*, u.username, u.avatar, u.usergroup, u.displaygroup FROM %sconversations c LEFT JOIN %sconversation_participants cp ON (c.id = cp.conversation_id) LEFT JOIN %susers u ON (c.user_id = u.uid) WHERE cp.user_id = '{$uid}' GROUP BY c.id LIMIT {$start}, {$perpage};";
	$query       = $db->write_query(sprintf($queryString, TABLE_PREFIX, TABLE_PREFIX, TABLE_PREFIX));

	if ($db->num_rows($query) > 0) {
		$conversations = array();
		while ($conversation = $db->fetch_array($query)) {
			$conversations[(int)$conversation['id']] = $conversation;
		}
		unset($queryString);
		unset($query);

		$inString = "'".implode("','", array_keys(array_filter($conversations)))."'";
		$queryString = "SELECT cm.*, u.username, u.avatar, u.usergroup, u.displaygroup FROM %sconversation_messages cm LEFT JOIN %susers u ON (cm.user_id = u.uid) WHERE cm.conversation_id IN({$inString}) ORDER BY ABS(cm.created_at) ASC;";
		$query  = $db->write_query(sprintf($queryString, TABLE_PREFIX, TABLE_PREFIX));
		while ($conversation = $db->fetch_array($query)) {
			$conversations[(int)$conversation['conversation_id']]['lastmessage'] = $conversation;
		}

		$conversationsList = '';
		foreach ($conversations as $conversation) {
			$altbg = alt_trow();
			$conversation['link'] = htmlspecialchars_uni(sprintf(URL_VIEW_CONVERSATION, (int) $conversation['id']));
			$conversation['subject'] = htmlspecialchars_uni($conversation['subject']);
			$conversation['created_at'] = my_date($mybb->settings['dateformat'], strtotime($conversation['created_at'])).' '.my_date($mybb->settings['timeformat'], strtotime($conversation['created_at']));
			$conversation['lastmessage']['created_at']  = my_date($mybb->settings['dateformat'], strtotime($conversation['lastmessage']['created_at'])).' '.my_date($mybb->settings['timeformat'], strtotime($conversation['lastmessage']['created_at']));
			$conversation['lastmessage']['profilelink'] = build_profile_link(format_name(htmlspecialchars_uni($conversation['lastmessage']['username']), $conversation['lastmessage']['usergroup'], $conversation['lastmessage']['displaygroup']), $conversation['lastmessage']['user_id']);
			eval("\$conversationsList .= \"".$templates->get('mybbconversations_row')."\";");
		}
	} else {
		$altbg = 'trow1';
		eval("\$conversationsList = \"".$templates->get('mybbconversations_row_empty')."\";");
	}

	eval("\$page = \"".$templates->get('mybbconversations_list')."\";");
	output_page($page);
}
