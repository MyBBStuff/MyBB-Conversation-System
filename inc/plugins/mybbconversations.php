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
    die('Diect access to this file is not allowed. Please ensure IN_MYBB is defined.');
}

if (!defined('PLUGINLIBRARY')) {
    define('PLUGINLIBRARY', MYBB_ROOT.'inc/plugins/pluginlibrary.php');
}

/**
 * Information function.
 *
 * @return Array The plugin details.
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
            id INT(10) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            conversation_id INT(10) NOT NULL,
            user_id INT(10) NOT NULL,
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

    $PL->settings(
        'mybbconversations',
        $lang->setting_group_mybbconversations,
        $lang->setting_group_mybbconversations_desc,
        array(
            'enabled'          =>  array(
                'title'         =>  $lang->setting_mybbconversations_enabled,
                'description'   =>  $lang->setting_mybbconversations_enabled_desc,
                'value'         =>  '1',
            ),
        )
    );

    $PL->templates(
        'mybbconversations',
        $lang->mybbconversations_title,
        array(
            'list'                => '<html>
    <head>
        <title>Conversations - {$mybb->settings[\'bbname\']}</title>
        {$headerinclude}
    </head>
    <body>
        {$header}
            <table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
                <thead>
                    <tr>
                        <th class="thead" colspan="2">
                            <strong>{$lang->mybbconversations_nav}</strong>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="tcat">
                            <strong>{$lang->mybbconversations_conversation_title}</strong>
                        </td>
                        <td class="tcat">
                            <strong>{$lang->mybbconversations_conversation_created_at}</strong>
                        </td>
                    </tr>
                    {$conversations}
                </tbody>
            </table>
        {$footer}
    </body>
</html>',

            'row'                 => '<tr>
    <td class="{$altbg}">
        {$conversation[\'subject\']}
    </td>
    <td class="{$altbg}">
        {$conversation[\'created_at\']}
    </td>
</tr>',

            'row_empty'           => '<tr>
    <td class="{$altbg}" colspan="2" style="text-align: center;">
        {$lang->mybbconversations_no_conversations}
    </td>
</tr>',

            'create_conversation' => '<html>
    <head>
        <title>Start a conversation - {$mybb->settings[\'bbname\']}</title>
        {$headerinclude}
        <script type="text/javascript" src="jscripts/usercp.js"></script>
    </head>
    <body>
        {$header}
            <table border=0 cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" width="100%">
                <tr>
                    <td width="20%" valign="top">
                        <table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
                            <thead>
                                <tr>
                                    <td class="thead" colspan="2">
                                        <strong>{$lang->mybbconversations_participants}</strong>
                                    </td>
                                </tr>
                            </thead>
                            <tbody>
                                {$conversationParticipants}
                                <tr>
                                    <td class="trow1">
                                        <strong>
                                            <label for="to">
                                                {$lang->mybbconversations_input_add_participant}
                                            </label>
                                        </strong>
                                    </td>
                                    <td class="trow1">
                                        <textarea name="to" id="to" rows="2" cols="38" tabindex="1">{$to}</textarea>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </td>
                    <td valign="top">
                        <form action="conversations.php?action=create_conversation" method="post">
                            <input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
                            <table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
                                <thead>
                                    <tr>
                                        <th class="thead" colspan="2">
                                            <strong>{$lang->mybbconversations_nav_create}</strong>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="trow1" width="20%">
                                            <label for="input_title">
                                                <strong>{$lang->mybbconversations_input_title}</strong>
                                            </label>
                                        </td>
                                        <td class="trow1">
                                            <input type="text" id="input_title" name="title" value="{$title}" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="trow2" width="20%">
                                            <label for="message">
                                                <strong>{$lang->mybbconversations_input_message}</strong>
                                            </label>
                                        </td>
                                        <td class="trow1">
                                            <textarea name="message" id="message" rows="20" cols="70" tabindex="2">{$message}</textarea>
                                            {$codebuttons}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </form>
                    </td>
                </tr>
            </table>
        {$footer}
    </body>
</html>',
        )
    );
}
