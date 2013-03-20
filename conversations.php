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

$templatelist = 'mybbconversations_list,mybbconversations_row_empty,mybbconversations_row';

require __DIR__.'/global.php';

if (!isset($lang->mybbconversations)) {
	$lang->load('mybbconversations');
}

add_breadcrumb($lang->mybbconversations_nav, 'conversations.php');

if ($mybb->user['uid'] == 0 OR !$mybb->settings['mybbconversations_enabled']) {
	error_no_permission();
}

if (isset($mybb->input['action']) AND $mybb->input['action'] == 'create_conversation') {
	add_breadcrumb($lang->mybbconversations_nav_create, 'conversations.php?action=create_conversation');

	if (strtolower($mybb->request_method) == 'post') {

	} else {
		$codebuttons = build_mycode_inserter();
		eval("\$page = \"".$templates->get('mybbconversations_create_conversation')."\";");
		output_page($page);
	}
} else {
	$conversations = '';
	$altbg = '';
	$uid = (int) $mybb->user['uid'];

	eval("\$createButton = \"".$templates->get('mybbconversations_create_button')."\";");

	$queryString = "SELECT COUNT(*) AS count FROM %sconversations c LEFT JOIN %sconversation_participants cp ON (c.id = cp.conversation_id) WHERE cp.user_id = '{$uid}'";
	$query = $db->write_query(sprintf($queryString, TABLE_PREFIX, TABLE_PREFIX));
	$count = (int) $db->fetch_field($query, 'count');
	unset($queryString);
	unset($query);

	$perpage = (int) $mybb->settings['mybbconversations_conversations_per_page'];
	if ($perpage == 0) {
		$perpage = 20;
	}

	$page  = (int) $mybb->input['page'];
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
		$page = 1;
	}
	$multipage = multipage($count, $perpage, $page, 'conversations.php');

	$queryString = "SELECT * FROM %sconversations c LEFT JOIN %sconversation_participants cp ON (c.id = cp.conversation_id) LEFT JOIN %sconversation_messages cm ON (c.id = cm.conversation_id) WHERE cp.user_id = '{$uid}' GROUP BY c.id LIMIT {$start}, {$perpage}";
	$query = $db->write_query(sprintf($queryString, TABLE_PREFIX, TABLE_PREFIX, TABLE_PREFIX));

	if ($db->num_rows($query) > 0) {
		while ($conversation = $db->fetch_array($query)) {
			$altbg = alt_trow();
			eval("\$conversations .= \"".$templates->get('mybbconversations_row')."\";");
		}
		unset($query);
	} else {
		$altbg = 'trow1';
		eval("\$conversations = \"".$templates->get('mybbconversations_row_empty')."\";");
	}

	eval("\$page = \"".$templates->get('mybbconversations_list')."\";");
	output_page($page);
}
