<?php
/****************************************************************************
	This program is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.
	
	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.
	
	You should have received a copy of the GNU General Public License
	along with this program.  If not, see <http://www.gnu.org/licenses/>.
****************************************************************************/

if (!defined('IN_MYBB')){
	die('This file cannot be accessed directly.');
}

// CleanTalk version and agent
define( 'CLEANTALK_ANTISPAM_VERSION', '1.4' );
define( 'ENGINE', 'mybb-' . str_replace( '.', '', CLEANTALK_ANTISPAM_VERSION ) );

require_once MYBB_ROOT . 'inc/plugins/antispam_by_cleantalk/autoloader.php';
require_once MYBB_ROOT . 'inc/plugins/antispam_by_cleantalk/hooks_nandlers.php';
require_once MYBB_ROOT . 'inc/plugins/antispam_by_cleantalk/apbct_functions.php';

// The plugin's hooks
$plugins->add_hook('newthread_do_newthread_start',        'antispam_by_cleantalk_trigger');
$plugins->add_hook('newreply_do_newreply_start',          'antispam_by_cleantalk_trigger');
$plugins->add_hook('editpost_do_editpost_start',          'antispam_by_cleantalk_trigger');
$plugins->add_hook('member_do_register_start',            'antispam_by_cleantalk_regtrigger');
$plugins->add_hook('contact_do_start',                    'antispam_by_cleantalk_contacttrigger');
$plugins->add_hook('pre_output_page',                     'antispam_by_cleantalk_add_js');
$plugins->add_hook('global_start',                        'antispam_by_cleantalk_set_global');
$plugins->add_hook('admin_config_settings_change_commit', 'savesettings_trigger');

function antispam_by_cleantalk_info()
{
	return array(
		"name" => "Antispam by CleanTalk (SPAM protection)",
		"description" => "Max power, all-in-one, no Captcha, premium anti-spam plugin. No comment spam, no registration spam. Formerly Anti-Spam by CleanTalk",
		"website" => "https://cleantalk.org/",
		"author" => "CleanTalk",
		"authorsite" => "https://cleantalk.org/",
		"version" => "v" . CLEANTALK_ANTISPAM_VERSION,
		"guid" => "",
		"compatibility" => "18*"
	);
}

function antispam_by_cleantalk_install()
{
	global $db;
	$ap_group = Array(
		'name'			=> "antispam_by_cleantalk",
		'title'			=> "Antispam by CleanTalk (SPAM protection)",
		'description'	=> "",
		'disporder'		=> 1,
		'isdefault'		=> 1
	);
	
	$gid = $db->insert_query("settinggroups", $ap_group);
	
	$antispam_by_cleantalk_settings = Array();
	$antispam_by_cleantalk_settings[] = Array(
		'name'			=> "antispam_by_cleantalk_enabled",
		'title'			=> "Enabled?",
		'description'	=> "Enable/disable Antispam by CleanTalk",
		'optionscode'	=> "yesno",
		'value'			=> "1",
		'disporder'		=> 1,
		'gid'			=> $gid
	);

	$antispam_by_cleantalk_settings[] = Array(
		'name'			=> "antispam_by_cleantalk_regcheck",
		'title'			=> "Check registrations",
		'description'	=> "Spam-bots will be rejected with a statement of reasons",
		'optionscode'	=> "yesno",
		'value'			=> "1",
		'disporder'		=> 2,
		'gid'			=> $gid
	);
	
	$antispam_by_cleantalk_settings[] = Array(
		'name'			=> "antispam_by_cleantalk_comcheck",
		'title'			=> "Check posts",
		'description'	=> "Posts and topics from users with numposts <=3 will be checked for spam",
		'optionscode'	=> "yesno",
		'value'			=> "1",
		'disporder'		=> 3,
		'gid'			=> $gid
	);
	$antispam_by_cleantalk_settings[] = Array(
		'name'			=> "antispam_by_cleantalk_exclusions_url",
		'title'			=> "URL exclusions",
		'description'	=> "You could type here URL you want to exclude. Use comma as separator.",
		'optionscode'	=> "text",
		'value'			=> "",
		'disporder'		=> 4,
		'gid'			=> $gid
	);
    $antispam_by_cleantalk_settings[] = Array(
        'name'			=> "antispam_by_cleantalk_exclusions_groups",
        'title'			=> "User\'s groups exclusions",
        'description'	=> "Roles which bypass spam test. Hold CTRL to select multiple roles.",
        'optionscode'	=> "groupselect",
        'value'			=> "",
        'disporder'		=> 5,
        'gid'			=> $gid
    );
	$antispam_by_cleantalk_settings[] = Array(
		'name'			=> "antispam_by_cleantalk_sfw",
		'title'			=> "Enable SFW",
		'description'	=> "This option allows to filter spam bots before they access website. Also reduces CPU usage on hosting server and accelerates pages load time",
		'optionscode'	=> "yesno",
		'value'			=> "1",
		'disporder'		=> 6,
		'gid'			=> $gid
	);	

    $antispam_by_cleantalk_settings[] = Array(
        'name'          => "antispam_by_cleantalk_footerlink",
        'title'         => "Tell others about CleanTalk?",
        'description'   => "Enabling this option places a small link in the footer of your site that lets others know what anti-spam tool protects your site.",
        'optionscode'   => "yesno",
        'value'         => "0",
        'disporder'     => 7,
        'gid'           => $gid
    );

	$antispam_by_cleantalk_settings[] = Array(
		'name'			=> "antispam_by_cleantalk_accesskey",
		'title'			=> "Access key",
		'description'	=> "To get an access key, please register <a target=_blank href=https://cleantalk.org/register>here</a>",
		'optionscode'	=> "text",
		'value'			=> "",
		'disporder'		=> 8,
		'gid'			=> $gid
	);
	
	foreach ($antispam_by_cleantalk_settings as $setting){
		$db->insert_query("settings", $setting);
	}
	rebuild_settings();
	$template = '{$lang->error_nopermission_user_1}
<ol>
	<li>{$lang->error_nopermission_user_2}</li>
	<li>{$lang->error_nopermission_user_3}</li>
	<li>{$lang->error_nopermission_user_4} (<a href="member.php?action=resendactivation">{$lang->error_nopermission_user_resendactivation}</a>)</li>
	<li>{$lang->error_nopermission_user_5}</li>
</ol>
<br />
{$lang->error_nopermission_user_username}';

	$insert_array = array(
		'title' => 'antispam_by_cleantalk_error_page',
		'template' => $db->escape_string($template),
		'sid' => '-1',
		'version' => '',
		'dateline' => time()
	);

	$db->insert_query('templates', $insert_array);

	$db->query("CREATE TABLE IF NOT EXISTS ".TABLE_PREFIX."cleantalk_sfw (
	    `network` int(11) unsigned NOT NULL,
		`mask` int(11) unsigned NOT NULL,
		`status` TINYINT(1) NOT NULL DEFAULT 0,
		INDEX (  `network` ,  `mask` ));
	");

	$db->query("CREATE TABLE IF NOT EXISTS ".TABLE_PREFIX."cleantalk_sfw_logs (
	    `ip` VARCHAR(15) NOT NULL,
		`all_entries` INT NOT NULL,
		`blocked_entries` INT NOT NULL,
		`entries_timestamp` INT NOT NULL,
		PRIMARY KEY (`ip`));
	");	
}

function antispam_by_cleantalk_uninstall()
{
	global $db;

	$db->delete_query("settings", "name IN ('antispam_by_cleantalk_enabled','antispam_by_cleantalk_regcheck','antispam_by_cleantalk_comcheck', 'antispam_by_cleantalk_sfw', 'antispam_by_cleantalk_footerlink','antispam_by_cleantalk_accesskey')");
	$db->delete_query("settinggroups", "name='antispam_by_cleantalk'");
	$db->delete_query("templates", "title = 'antispam_by_cleantalk_error_page'");
	$db->drop_table("cleantalk_sfw");
	$db->drop_table("cleantalk_sfw_logs");
	$db->delete_query("templates", "title='footer' AND sid='1'");
	
	rebuild_settings();
}

function antispam_by_cleantalk_is_installed()
{
	global $db;
	$query = $db->simple_select('settinggroups', '*', "name='antispam_by_cleantalk'");
	if ($db->num_rows($query)){
		return true;
	}
	return false;
}

