<?php
/**
 * MyBB Conversation System
 *
 * A replacement for MyBB's core Private Message functionality based on IPB's Conversation feature.
 *
 * @category Feature_Enhancements
 * @package  MyBB-Conversation-System
 * @author   Euan T. <euan@euantor.com>
 * @license  http://opensource.org/licenses/mit-license.php The MIT License
 * @version  0.1.0
 * @link     http://euantor.com/mybb-conversation-system
 */

if (!defined('IN_MYBB')) {
    define('IN_MYBB', 1);
}

if (!defined('PLUGINLIBRARY')) {
    define('PLUGINLIBRARY', MYBB_ROOT.'inc/plugins/pluginlibrary.php');
}

/**
 * Information function.
 *
 * @return Array The plugin details.
 */
function mybconversations_info()
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
        'version'       => '0.1.0',
        'guid'          => '',
        'compatibility' => '16*',
    );
}

/**
 * Installation function.
 *
 * @return null
 */
function mybconversations_install()
{
    global $db, $cache;

    if (!file_exists(PLUGINLIBRARY)) {
        flash_message('The selected plugin could not be uninstalled because <a href=\"http://mods.mybb.com/view/pluginlibrary\">PluginLibrary</a> is missing.', 'error');
        admin_redirect('index.php?module=config-plugins');
    }

    $PL or include_once PLUGINLIBRARY;

    if ((int) $PL->version < 9) {
        flash_message('This plugin requires PluginLibrary 9 or newer', 'error');
        admin_redirect('index.php?module=config-plugins');
    }

    $plugin_info = myalerts_info();
    $euantor_plugins = $cache->read('euantor_plugins');
    if (empty($euantor_plugins) OR !is_array($euantor_plugins)) {
        $euantor_plugins = array();
    }
    $euantor_plugins['mybbconversations'] = array(
        'title'   =>  'MyBB Conversation System',
        'version' =>  $plugin_info['version'],
    );
    $cache->update('euantor_plugins', $euantor_plugins);

    $collation = $db->build_create_table_collation();

    if (!$db->table_exists('conversations')) {
        $db->write_query(
            "CREATE TABLE ".TABLE_PREFIX."conversations(
            id INT(10) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id INT(10) NOT NULL,
            subject VARCHAR(120) NOT NULL
            ) ENGINE=MyISAM{$collation};"
        );
    }

    if (!$db->table_exists('conversation_messages')) {
        $db->write_query(
            "CREATE TABLE ".TABLE_PREFIX."conversation_messages(
            id INT(10) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id INT(10) NOT NULL,
            conversation_id INT(10) NOT NULL,
            subject VARCHAR(120) NOT NULL,
            dateline DATETIME NOT NULL,
            includesig INT(1) NOT NULL DEFAULT '1',
            unread INT(1) NOT NULL DEFAULT '1'
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

    return $db->table_exists('conversations') AND $db->table_exists('conversation_messages');
}

/**
 * Uninstallation function.
 *
 * @return null
 */
function mybconversations_uninstall()
{
    global $db;

    if (!file_exists(PLUGINLIBRARY)) {
        flash_message('The selected plugin could not be uninstalled because <a href=\"http://mods.mybb.com/view/pluginlibrary\">PluginLibrary</a> is missing.', 'error');
        admin_redirect('index.php?module=config-plugins');
    }

    $PL or include_once PLUGINLIBRARY;

    if ((int) $PL->version < 9) {
        flash_message('This plugin requires PluginLibrary 9 or newer', 'error');
        admin_redirect('index.php?module=config-plugins');
    }

    if ($db->table_exists('conversations')) {
        $db->drop_table('conversations');
    }

    if ($db->table_exists('conversation_messages')) {
        $db->drop_table('conversation_messages');
    }
}