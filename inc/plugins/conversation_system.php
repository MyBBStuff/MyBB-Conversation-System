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
	define('PLUGINLIBRARY', MYBB_ROOT . 'inc/plugins/pluginlibrary.php');
}

define('MYBBSTUFF_CONVERSATION_SYSTEM_VERSION', '1.0.0');

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
		'website'       => 'http://www.mybsstuff.com',
		'author'        => 'Euan T',
		'authorsite'    => 'http://www.euantor.com',
		'version'       => MYBBSTUFF_CONVERSATION_SYSTEM_VERSION,
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

	conversation_system_run_db_scripts('install');
}

/**
 * Run database scripts for the current version.
 */
function conversation_system_run_db_scripts($action = 'install')
{
	$path = dirname(__FILE__) . '/MyBBStuff/ConversationSystem/database/' . MYBBSTUFF_CONVERSATION_SYSTEM_VERSION;

	try {
		$dir       = new DirectoryIterator($path);
		$dbScripts = array();
		foreach ($dir as $file) {
			/** @var DirectoryIterator $file */
			if (!$file->isDot() AND !$file->isDir() AND pathinfo($file->getFilename(), PATHINFO_EXTENSION) == 'sql') {
				if (substr($file->getBasename('.sql'), 0, strlen($action)) == $action) {
					$sqlScript                             = str_replace(
						'PREFIX_',
						TABLE_PREFIX,
						file_get_contents($file->getPathName())
					);
					$dbScripts[$file->getBasename('.sql')] = $sqlScript;
				}
			}
		}

		if (!empty($dbScripts)) {
			global $db;

			foreach ($dbScripts as $script) {
				$db->write_query($script);
			}
		}
	} catch (Exception $e) {
		flash_message('Error running database scripts.');
		admin_redirect('index.php?module=config-plugins');
	}
}

/**
 * Update the euantor_plugins cache with details fo the conversation system.
 */
function conversation_system_update_plugin_cache()
{
	global $cache;

	$euantor_plugins = $cache->read('euantor_plugins');
	if (!is_array($euantor_plugins)) {
		$euantor_plugins = array();
	}
	$euantor_plugins['conversation_system'] = array(
		'title'   => 'Conversation System',
		'version' => MYBBSTUFF_CONVERSATION_SYSTEM_VERSION,
	);
	$cache->update('euantor_plugins', $euantor_plugins);
}

/**
 * Function to check if the plugin in installed.
 *
 * @return boolean Whether the system is installed.
 */
function conversation_system_is_installed()
{
	global $db;

	return $db->table_exists('conversations') AND $db->table_exists('conversation_messages') AND $db->table_exists(
		'conversation_participants'
	);
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
 *
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

	conversation_system_update_plugin_cache();

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

	try {
		$dir       = new DirectoryIterator(dirname(__FILE__) . '/MyBBStuff/ConversationSystem/templates');
		$templates = array();
		foreach ($dir as $file) {
			/** @var DirectoryIterator $file */
			if (!$file->isDot() AND !$file->isDir() AND pathinfo($file->getFilename(), PATHINFO_EXTENSION) == 'html') {
				$templates[$file->getBasename('.html')] = file_get_contents($file->getPathName());
			}
		}

		if (!empty($templates)) {
			$PL->templates(
				'mybbconversations',
				$lang->mybbconversations_title,
				$templates
			);
		}
	} catch (Exception $e) {
		flash_message('Error inserting templates');
		admin_redirect('index.php?module=config-plugins');
	}

}
