<?php
/**
 * MyBB Conversation System frontend
 *
 * A replacement for MyBB's core Private Message functionality based on IPB's Conversation feature.
 *
 * @author   Euan T <euan@euantor.com>
 * @version  1.0.0
 * @since    1.0.0
 */

define('IN_MYBB', 1);
define('THIS_SCRIPT', 'conversations.php');

$templatelist = 'mybbconversations_list,mybbconversations_row_empty,mybbconversations_row,mybbconversations_create_button,multipage_breadcrumb,multipage,multipage_end,multipage_nextpage,multipage_page,multipage_page_current,multipage_page_link_current,multipage_prevpage,multipage_start,mybbconversations_single_participant,mybbconversations_single_message_signature, mybbconversations_single_message,mybbconversations_view';

require dirname(__FILE__) . '/global.php';

define('URL_VIEW_CONVERSATION', 'conversations.php?action=view&id=%s');

require_once MYBB_ROOT . '/inc/plugins/MyBBStuff/ConversationSystem/ConversationManager.php';
require_once MYBB_ROOT . '/inc/plugins/MyBBStuff/ConversationSystem/NoPermissionException.php';
$conversationManager = new MyBBStuff_ConversationSystem_ConversationManager($mybb, $db);

if (!isset($lang->mybbconversations)) {
	$lang->load('mybbconversations');
}

add_breadcrumb($lang->mybbconversations_nav, 'conversations.php');

if ($mybb->user['uid'] == 0 OR !$mybb->settings['mybbconversations_enabled']) {
	error_no_permission();
}

if (isset($mybb->input['action']) AND $mybb->input['action'] == 'create_conversation' AND strtolower(
		$mybb->request_method
	) == 'post'
) {
	verify_post_check($mybb->input['my_post_key']);
	$errors = array();

	$mybb->input['subject'] = trim($mybb->input['subject']);
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

	if (strstr($mybb->input['participants'], ',') === false) {
		$mybb->input['participants'] = array(
			$mybb->input['participants'],
		);
	} else {
		$mybb->input['participants'] = explode(',', $mybb->input['participants']);
	}

	$conversation = $conversationManager->createConversation(
		$mybb->input['participants'],
		$mybb->input['subject'],
		$mybb->input['message']
	);

	redirect(
		sprintf(URL_VIEW_CONVERSATION, $conversation['id']),
		'New conversation created. Taking you to it now...',
		'Conversation Created'
	);
}

if (isset($mybb->input['action']) AND $mybb->input['action'] == 'create_conversation') {
	add_breadcrumb($lang->mybbconversations_nav_create, 'conversations.php?action=create_conversation');

	$codebuttons = build_mycode_inserter();
	eval("\$page = \"" . $templates->get('mybbconversations_create_conversation') . "\";");
	output_page($page);
}

if (isset($mybb->input['action']) AND $mybb->input['action'] == 'view') {
	$id = (int) $mybb->input['id'];

	if (empty($id)) {
		error('Conversation not found. Please try again.');
	}

	require_once MYBB_ROOT . 'inc/class_parser.php';
	$parser = new postParser;

	$parserOptions = array(
		'allow_html'    => false,
		'allow_mycode'  => true,
		'allow_smilies' => true,
		'allow_imgcode' => true,
		'allow_videocode' => true,
		'filter_badwords' => true,
	);

	$conversationManager->setParser($parser, $parserOptions);
	$conversation = array();

	try {
		$conversation = $conversationManager->getConversation($id);
	} catch (MyBBStuff_ConversationSystem_NoPermissionException $e) {
		error_no_permission();
	}

	if (empty($conversation)) {
		error('Invalid conversation.');
	}

	$participantList = '';
	foreach ($conversation['participants'] as $participant) {
		$altbg = alt_trow();
		eval("\$participantList .= \"" . $templates->get('mybbconversations_single_participant') . "\";");
	}

	$messageList = '';
	foreach ($conversation['messages'] as $singleMessage) {
		$altbg = alt_trow();
		$signature = '';

		if ($singleMessage['include_signature']) {
			eval("\$signature = \"" . $templates->get('mybbconversations_single_message_signature') . "\";");
		}

		eval("\$messageList .= \"" . $templates->get('mybbconversations_single_message') . "\";");
	}

	$codebuttons = build_mycode_inserter();
	eval("\$newReply = \"" . $templates->get('mybbconversations_new_reply') . "\";");

	add_breadcrumb($conversation['subject'], sprintf(URL_VIEW_CONVERSATION, $conversation['id']));

	eval("\$page = \"" . $templates->get('mybbconversations_view') . "\";");
	output_page($page);
}

if (isset($mybb->input['action']) AND $mybb->input['action'] == 'new_reply' AND strtolower(
		$mybb->request_method
	) == 'post'
) {
	verify_post_check($mybb->input['my_post_key']);

	// TODO: Check that conversation exists and user has access

	$conversationId = (int) $mybb->input['conversation_id'];
	$includeSig     = isset($mybb->input['include_signature']);
	$message        = $mybb->input['message'];

	if ($conversationManager->addMessageToConversation($conversationId, $message, $includeSig)) {
		redirect(
			"conversations.php?action=view&amp;id={$conversationId}",
			'Message added to conversation. Taking you back to it now.',
			'Message added'
		);
	} else {
		error('Failed to add message. Taking you back to the conversation now.', 'Message sending failed');
	}
}

if (!isset($mybb->input['action']) OR $mybb->input['action'] == 'list') {
	$conversations = '';
	$altbg         = '';
	$uid = (int) $mybb->user['uid'];

	eval("\$createButton = \"" . $templates->get('mybbconversations_create_button') . "\";");

	$numInvolvedConversations = $conversationManager->getNumInvolvedConversations();

	$perPage = (int) $mybb->settings['mybbconversations_conversations_per_page'];
	if ($perPage == 0) {
		$perPage = 20;
	}

	$page  = (int) $mybb->input['page'];
	$pages = $numInvolvedConversations / $perPage;
	$pages = ceil($pages);
	if ($mybb->input['page'] == "last") {
		$page = $pages;
	}

	if ($page > $pages OR $page <= 0) {
		$page = 1;
	}

	if ($page AND $page > 0) {
		$start = ($page - 1) * $perPage;
	} else {
		$start = 0;
		$page  = 1;
	}
	$multipage = multipage($numInvolvedConversations, $perPage, $page, 'conversations.php');

	$conversations = $conversationManager->getConversations($start, $perPage);

	if (!empty($conversations)) {
		$conversationsList = '';
		foreach ($conversations as $conversation) {
			$altbg                   = alt_trow();
			$conversation['link']                       = htmlspecialchars_uni(
				sprintf(URL_VIEW_CONVERSATION, (int) $conversation['id'])
			);
			$conversation['subject'] = htmlspecialchars_uni($conversation['subject']);
			$conversation['created_at']                 = my_date(
					$mybb->settings['dateformat'],
					strtotime($conversation['conversation_created_at'])
				) . ' ' . my_date($mybb->settings['timeformat'], strtotime($conversation['conversation_created_at']));
			$conversation['lastmessage']['created_at']  = my_date(
					$mybb->settings['dateformat'],
					strtotime($conversation['last_message_date'])
				) . ' ' . my_date($mybb->settings['timeformat'], strtotime($conversation['last_message_date']));
			$conversation['lastmessage']['profilelink'] = build_profile_link(
				format_name(
					htmlspecialchars_uni($conversation['last_message_username']),
					$conversation['last_message_usergroup'],
					$conversation['last_message_displaygroup']
				),
				$conversation['last_message_uid']
			);
			eval("\$conversationsList .= \"" . $templates->get('mybbconversations_row') . "\";");
		}
	} else {
		$altbg = 'trow1';
		eval("\$conversationsList = \"" . $templates->get('mybbconversations_row_empty') . "\";");
	}

	eval("\$page = \"" . $templates->get('mybbconversations_list') . "\";");
	output_page($page);
}
