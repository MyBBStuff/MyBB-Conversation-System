<?php
/**
 * MyBB Conversation System
 *
 * A replacement for MyBB's core Private Message functionality based on IPB's Conversation feature.
 *
 * @author   Euan T. <euan@euantor.com>
 * @license  http://opensource.org/licenses/mit-license.php The MIT License
 * @version  1.0
 * @link     http://euantor.com/mybb-conversation-system
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
function mybbconversations_info()
{
	global $lang;

	if (!isset($lang->mybbconversations_title)) {
		$lang->load('mybbconversations');
	}

	return array(
		'name'          => $lang->mybbconversations_title,
		'description'   => $lang->mybbconversations_description,
		'website'       => 'http://euantor.com/mybb-conversation-system',
		'author'        => 'Euan T.',
		'authorsite'    => 'http://euantor.com',
		'version'       => '1.0',
		'guid'          => '',
		'compatibility' => '16*',
	);
}

/**
 * Installation function.
 *
 * @return null
 */
function mybbconversations_install()
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

	$collation = $db->build_create_table_collation();

	if (!$db->table_exists('conversations')) {
		$db->write_query(
			"CREATE TABLE ".TABLE_PREFIX."conversations(
			id INT(10) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
			user_id INT(10) unsigned NOT NULL,
			subject VARCHAR(120) NOT NULL
			) ENGINE=MyISAM{$collation};"
		);
	}

	if (!$db->table_exists('conversation_messages')) {
		$db->write_query(
			"CREATE TABLE ".TABLE_PREFIX."conversation_messages(
			id INT(10) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
			user_id INT(10) unsigned NOT NULL,
			conversation_id INT(10) unsigned NOT NULL,
			message TEXT NOT NULL,
			includesig INT(1) NOT NULL DEFAULT '1',
			unread INT(1) NOT NULL DEFAULT '1',
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL
			) ENGINE=MyISAM{$collation};"
		);
	}

	if (!$db->table_exists('conversation_participants')) {
		$db->write_query(
			"CREATE TABLE ".TABLE_PREFIX."conversation_participants(
			id INT(10) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
			conversation_id INT(10) unsigned NOT NULL,
			user_id INT(10) unsigned NOT NULL,
			created_at DATETIME NOT NULL
			) ENGINE=MyISAM{$collation};"
		);
	}

	unset($collation);
}

/**
 * Function to check if the plugin in installed.
 *
 * @return boolean Whether the system is installed.
 */
function mybbconversations_is_installed()
{
	global $db;

	return $db->table_exists('conversations') AND $db->table_exists('conversation_messages') AND $db->table_exists('conversation_participants');
}

/**
 * Uninstallation function.
 *
 * @return null
 */
function mybbconversations_uninstall()
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
function mybbconversations_activate()
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

	$plugin_info     = myalerts_info();
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

	$dir       = new DirectoryIterator(dirname(__FILE__).'/ConversationSystem/templates'); // Change this to the path for your plugin
	$templates = array();
	foreach ($dir as $file) {
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
