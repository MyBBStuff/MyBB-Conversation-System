<?php
/**
 * MyBB Conversation System
 *
 * A replacement for MyBB's core Private Message functionality based on IPB's Conversation feature.
 *
 * @author   Euan T <euan@euantor.com>
 * @version  1.0.0
 */

defined('IN_MYBB') or die('Diect access to this file is not allowed. Please ensure IN_MYBB is defined.');

if (!defined('PLUGINLIBRARY')) {
	define('PLUGINLIBRARY', MYBB_ROOT.'inc/plugins/pluginlibrary.php');
}

/**
 * Information function.
 *
 * @return array The plugin details.
 */
function conversation_system_info()
{
	return array(
		'name'          => 'Conversation System',
		'description'   => 'A drop in replacement for Private Messages. Let your users have conversations, not just exchange messages with each other.',
		'website'    => 'http://www.mybsstuff.com',
		'author'     => 'Euan T',
		'authorsite' => 'http://www.euantor.com',
		'version'    => '1.0.0',
		'guid'          => '',
		'compatibility' => '16*',
	);
}

/**
 * Installation function.
 *
 * @return null
 */
function conversation_system_install()
{
	global $db, $cache, $lang;

	if (!isset($lang->mybbconversations_title)) {
		$lang->load('mybbconversations');
	}

	conversation_system_run_db_scripts();
}

/**
 * Run database scripts for the current version.
 */
function conversation_system_run_db_scripts()
{
	$pluginInfo = conversation_system_info();
	$version    = $pluginInfo['version'];

	$path = __DIR__ . '/MyBBStuff/ConversationSystem/database/' . $version;
	var_dump($path);
	die();
}

/**
 * Function to check if the plugin in installed.
 *
 * @return boolean Whether the system is installed.
 */
function conversation_system_is_installed()
{
	global $db;

	return $db->table_exists('conversations') AND $db->table_exists('conversation_messages') AND $db->table_exists('conversation_participants');
}

/**
 * Uninstall function.
 *
 * @return null
 */
function conversation_system_uninstall()
{
	global $db, $cache, $lang;

	if (!isset($lang->mybbconversations_title)) {
		$lang->load('mybbconversations');
	}

	if (!file_exists(PLUGINLIBRARY)) {
		flash_message($lang->mybbconversations_pluginlibrary_missing, 'error');
		admin_redirect('index.php?module=config-plugins');
	}

	$PL or include_once PLUGINLIBRARY;

	$PL->settings_delete('mybbconversations', true);

	if ($db->table_exists('conversations')) {
		$db->drop_table('conversations');
	}

	if ($db->table_exists('conversation_messages')) {
		$db->drop_table('conversation_messages');
	}

	if ($db->table_exists('conversation_participants')) {
		$db->drop_table('conversation_participants');
	}

	$euantor_plugins = $cache->read('euantor_plugins');
	if (isset($euantor_plugins['mybbconversations']) AND is_array($euantor_plugins['mybbconversations'])) {
		unset($euantor_plugins['mybbconversations']);
	}
	$cache->update('euantor_plugins', $euantor_plugins);
}

/**
 * Activation function.
 * @return null
 */
function conversation_system_activate()
{
	global $lang, $PL, $cache;

	if (!$lang->mybbconversations) {
		$lang->load('mybbconversations');
	}

	if (!file_exists(PLUGINLIBRARY)) {
		flash_message($lang->mybbconversations_pluginlibrary_missing, 'error');
		admin_redirect('index.php?module=config-plugins');
	}

	$PL or require_once PLUGINLIBRARY;

	$plugin_info     = mybbconversations_info();
	$euantor_plugins = $cache->read('euantor_plugins');
	if (empty($euantor_plugins) OR !is_array($euantor_plugins)) {
		$euantor_plugins = array();
	}
	$euantor_plugins['mybbconversations'] = array(
		'title'   => 'MyBB Conversation System',
		'version' => $plugin_info['version'],
	);
	$cache->update('euantor_plugins', $euantor_plugins);

	$PL->settings(
		'mybbconversations',
		'Conversation System Settings',
		'Settings for the conversation system.',
		array(
			'enabled'                => array(
				'title'       => 'Enabled?',
				'description' => 'You can use this switch to globally disable the system if required.',
				'value'       => '1',
			),
			'conversations_per_page' => array(
				'title'       => 'Number of conversations to list per page?',
				'description' => 'How many conversations should be shown per page in the recent conversations list?',
				'optionscode' => 'text',
				'value'       => '20',
			)
		)
	);

	$dir = new DirectoryIterator(dirname(__FILE__) . '/ConversationSystem/templates');
	$templates = array();
	foreach ($dir as $file) {
		/** @var SPLFileInfo $file */
		if (!$file->isDot() AND !$file->isDir() AND pathinfo($file->getFilename(), PATHINFO_EXTENSION) == 'html') {
			$templates[$file->getBasename('.html')] = file_get_contents($file->getPathName());
		}
	}

	$PL->templates(
		'mybbconversations',
		$lang->mybbconversations_title,
		$templates
	);
}
