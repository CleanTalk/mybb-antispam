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

use CleantalkAP\Common\API;
use CleantalkAP\Common\Helper;
use CleantalkAP\Mybb\Cleantalk;
use CleantalkAP\Mybb\CleantalkRequest;
use CleantalkAP\Mybb\RemoteCalls;
use CleantalkAP\Mybb\SFW;
use CleantalkAP\Variables\Server;

if (!defined('IN_MYBB')){
	die('This file cannot be accessed directly.');
}

// CleanTalk version and agent
define( 'CLEANTALK_ANTISPAM_VERSION', '1.3' );
define( 'ENGINE', 'mybb-' . str_replace( '.', '', CLEANTALK_ANTISPAM_VERSION ) );

// The plugin's hooks
$plugins->add_hook('newthread_do_newthread_start', 'antispam_by_cleantalk_trigger');
$plugins->add_hook('newreply_do_newreply_start', 'antispam_by_cleantalk_trigger');
$plugins->add_hook('editpost_do_editpost_start', 'antispam_by_cleantalk_trigger');
$plugins->add_hook('member_do_register_start', 'antispam_by_cleantalk_regtrigger');
$plugins->add_hook('contact_do_start', 'antispam_by_cleantalk_contacttrigger');
$plugins->add_hook('pre_output_page', 'antispam_by_cleantalK_add_js');
$plugins->add_hook('global_start', 'antispam_by_cleantalk_set_global');
$plugins->add_hook('admin_config_settings_change_commit', 'savesettings_trigger');

function savesettings_trigger()
{
    global $mybb,$db;

	$query = $db->simple_select('settinggroups', '*', "name='antispam_by_cleantalk'");
    $app = $db->fetch_array($query);

    if (isset($_POST['gid']) && $_POST['gid'] === $app['gid'])
    {
   		require_once MYBB_ROOT."/inc/adminfunctions_templates.php";
        // Including an autoloader
        require_once(dirname(__FILE__) . '/antispam_by_cleantalk/autoloader.php');
    	$access_key = trim($mybb->settings['antispam_by_cleantalk_accesskey']);    	

	    if ($access_key != '')
	    {
	    	API::method__send_empty_feedback($access_key, ENGINE);

		    if ($mybb->settings['antispam_by_cleantalk_sfw'] === '1')
		    {
		    	$sfw = new SFW();
		    	$sfw->sfw_update($access_key);
		    	$sfw->send_logs($access_key);
		    }    	
	    }
	    $db->delete_query("templates", "title='footer' AND sid='1'");
	    
	    if ($mybb->settings['antispam_by_cleantalk_footerlink'] === '1')	    	
		    find_replace_templatesets("footer", '#'.preg_quote('{$auto_dst_detection}').'#', '<div id=\'cleantalk_footer_link\' style=\'width:100%;text-align:center;\'>MyBB spam blocked <a href=https://cleantalk.org/antispam-mybb>by CleanTalk.</a></div>
		        {$auto_dst_detection}',1);    	
    }


    
}
function antispam_by_cleantalk_info()
{
	return Array(
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
	  `network` int(10) unsigned NOT NULL,
	  `mask` int(10) unsigned NOT NULL,
	  PRIMARY KEY (`network`)
	) DEFAULT CHARSET=latin1;
	");

	$db->query("CREATE TABLE IF NOT EXISTS ".TABLE_PREFIX."cleantalk_sfw_logs (
	  `ip` varchar(15) NOT NULL,
	  `all_entries` int(11) NOT NULL,
	  `blocked_entries` int(11) NOT NULL,
	  `entries_timestamp` int(11) NOT NULL,	  	  
	  PRIMARY KEY (`ip`)
	) DEFAULT CHARSET=latin1;
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

function antispam_by_cleantalk_sfw_check()
{
	global $mybb;

    $is_sfw_check = true;
    $sfw = new SFW();
    $sfw->ip_array = (array)$sfw->ip_get(array('real'), true);

    foreach($sfw->ip_array as $key => $value)
    {
        if(isset($_COOKIE['ct_sfw_pass_key']) && $_COOKIE['ct_sfw_pass_key'] == md5($value . trim($mybb->settings['antispam_by_cleantalk_accesskey'])))
        {
          $is_sfw_check=false;
          if(isset($_COOKIE['ct_sfw_passed']))
          {
            @setcookie ('ct_sfw_passed'); //Deleting cookie
            $sfw->sfw_update_logs($value, 'passed');
          }
        }
    } unset($key, $value);

    if($is_sfw_check)
    {
        $sfw->check_ip();

        if($sfw->test){
            $sfw->sfw_die(trim($mybb->settings['antispam_by_cleantalk_accesskey']), '', '', 'test');
        }

        if($sfw->result)
        {
            $sfw->sfw_update_logs($sfw->blocked_ip, 'blocked');
            $sfw->sfw_die(trim($mybb->settings['antispam_by_cleantalk_accesskey']));
        }
    }

}

// General global hook
function antispam_by_cleantalk_set_global()
{
    global $mybb;

    // Including an autoloader
    require_once(dirname(__FILE__) . '/antispam_by_cleantalk/autoloader.php');

    // Set cookies
	antispam_by_cleantalk_setcookies();

	// SpamFireWall checking
    if ( $mybb->settings['antispam_by_cleantalk_sfw'] === '1' ) {
        // Run SFW except the remote calls and excluded URLs
        if( ! RemoteCalls::check() || ! antispam_by_cleantalk_check_exclusions_url() ) {
            antispam_by_cleantalk_sfw_check();
        }
    }

	// Checking remote calls
	if ( RemoteCalls::check() ) {
        RemoteCalls::perform();
    }
}

function antispam_by_cleantalK_add_js( $contents )
{
	global $mybb;

	if (trim($mybb->settings['antispam_by_cleantalk_accesskey']) != '')
	{
		$ct_checkjs_val = md5(trim($mybb->settings['antispam_by_cleantalk_accesskey']));

        $cleantalk_js_code =
            '<script><!--
		    var ct_checkjs_val = \''.$ct_checkjs_val.'\', d = new Date(), 
				ctTimeMs = new Date().getTime(),
				ctMouseEventTimerFlag = true, //Reading interval flag
				ctMouseData = "[",
				ctMouseDataCounter = 0;
			
			function ctSetCookie(c_name, value) {
				document.cookie = c_name + "=" + escape(value) + "; path=/;";
			}

			ctSetCookie("ct_checkjs", ct_checkjs_val);
			ctSetCookie("ct_ps_timestamp", Math.floor(new Date().getTime()/1000));
			ctSetCookie("ct_fkp_timestamp", "0");
			ctSetCookie("ct_pointer_data", "0");
			ctSetCookie("ct_timezone", "0");
			setTimeout(function(){
				ctSetCookie("ct_timezone", d.getTimezoneOffset()/60*(-1));
			},1000);
			
			//Reading interval
			var ctMouseReadInterval = setInterval(function(){
					ctMouseEventTimerFlag = true;
				}, 150);
				
			//Writting interval
			var ctMouseWriteDataInterval = setInterval(function(){
					var ctMouseDataToSend = ctMouseData.slice(0,-1).concat("]");
					ctSetCookie("ct_pointer_data", ctMouseDataToSend);
				}, 1200);
			
			//Stop observing function
			function ctMouseStopData(){
				if(typeof window.addEventListener == "function")
					window.removeEventListener("mousemove", ctFunctionMouseMove);
				else
					window.detachEvent("onmousemove", ctFunctionMouseMove);
				clearInterval(ctMouseReadInterval);
				clearInterval(ctMouseWriteDataInterval);				
			}
			
			//Logging mouse position each 300 ms
			var ctFunctionMouseMove = function output(event){
				if(ctMouseEventTimerFlag == true){
					var mouseDate = new Date();
					ctMouseData += "[" + Math.round(event.pageY) + "," + Math.round(event.pageX) + "," + Math.round(mouseDate.getTime() - ctTimeMs) + "],";
					ctMouseDataCounter++;
					ctMouseEventTimerFlag = false;
					if(ctMouseDataCounter >= 100)
						ctMouseStopData();
				}
			}
			
			//Stop key listening function
			function ctKeyStopStopListening(){
				if(typeof window.addEventListener == "function"){
					window.removeEventListener("mousedown", ctFunctionFirstKey);
					window.removeEventListener("keydown", ctFunctionFirstKey);
				}else{
					window.detachEvent("mousedown", ctFunctionFirstKey);
					window.detachEvent("keydown", ctFunctionFirstKey);
				}
			}
			
			//Writing first key press timestamp
			var ctFunctionFirstKey = function output(event){
				var KeyTimestamp = Math.floor(new Date().getTime()/1000);
				ctSetCookie("ct_fkp_timestamp", KeyTimestamp);
				ctKeyStopStopListening();
			}

			if(typeof window.addEventListener == "function"){
				window.addEventListener("mousemove", ctFunctionMouseMove);
				window.addEventListener("mousedown", ctFunctionFirstKey);
				window.addEventListener("keydown", ctFunctionFirstKey);
			}else{
				window.attachEvent("onmousemove", ctFunctionMouseMove);
				window.attachEvent("mousedown", ctFunctionFirstKey);
				window.attachEvent("keydown", ctFunctionFirstKey);
			}
			// -->
			</script>';

        $contents = str_replace("</body>", $cleantalk_js_code . "</body>", $contents);

	}

    return $contents;

}
function antispam_by_cleantalk_setcookies(){
    global $mybb;

    // Cookie names to validate
    $cookie_test_value = array(
        'cookies_names' => array(),
        'check_value' => trim($mybb->settings['antispam_by_cleantalk_accesskey']),
    );

    // Pervious referer
    if(!empty($_SERVER['HTTP_REFERER'])){
        setcookie('ct_prev_referer', $_SERVER['HTTP_REFERER'], 0, '/');
        $cookie_test_value['cookies_names'][] = 'ct_prev_referer';
        $cookie_test_value['check_value'] .= $_SERVER['HTTP_REFERER'];
    }
    
    // Cookies test
    $cookie_test_value['check_value'] = md5($cookie_test_value['check_value']);
    setcookie('ct_cookies_test', json_encode($cookie_test_value), 0, '/');    
}
function antispam_by_cleantalk_testcokkies(){
    global $mybb;

    if(isset($_COOKIE['ct_cookies_test'])){
        
        $cookie_test = json_decode(stripslashes($_COOKIE['ct_cookies_test']), true);
        
        $check_srting = trim($mybb->settings['antispam_by_cleantalk_accesskey']);
        foreach($cookie_test['cookies_names'] as $cookie_name){
            $check_srting .= isset($_COOKIE[$cookie_name]) ? $_COOKIE[$cookie_name] : '';
        } unset($cokie_name);
        
        if($cookie_test['check_value'] == md5($check_srting)){
            return 1;
        }else{
            return 0;
        }
    }else{
        return null;
    }    
}
function antispam_by_cleantalk_trigger(){
	global $mybb;

	if (
	    $mybb->settings['antispam_by_cleantalk_enabled'] === '0' ||
        $mybb->settings['antispam_by_cleantalk_comcheck'] === '0' ||
        trim($mybb->settings['antispam_by_cleantalk_accesskey']) === '' ||
        antispam_by_cleantalk_check_exclusions_url() ||
        antispam_by_cleantalk_check_exclusions_roles()
    ){
		return false;
	}
	if ($mybb->user['postnum'] <= '3' && !$mybb->get_input("savedraft"))
    {
        $ct_result = antispam_by_cleantalk_spam_check(
            'check_message', array(
                'message' => $mybb->get_input('message') . " \n\n" . $mybb->get_input('subject'),
                'sender_email' => isset($mybb->user['email']) ? $mybb->user['email'] : '',
                'sender_nickname' => isset($mybb->user['username']) ? $mybb->user['username'] : '',
            )
        );

        if ($ct_result->allow == 0)
        	antispam_by_cleantalk_show_message($ct_result->comment);
	}
	
}
function antispam_by_cleantalk_regtrigger(){
	global $mybb;
	if (
	    $mybb->settings['antispam_by_cleantalk_enabled'] === '0' ||
        $mybb->settings['antispam_by_cleantalk_regcheck'] === '0' ||
        trim($mybb->settings['antispam_by_cleantalk_accesskey']) === '' ||
        antispam_by_cleantalk_check_exclusions_url() ||
        antispam_by_cleantalk_check_exclusions_roles()
    ){
		return false;
	}
        $ct_result = antispam_by_cleantalk_spam_check(
            'check_newuser', array(
                'sender_email' => isset($mybb->input['email']) ? $mybb->input['email'] : '',
                'sender_nickname' => isset($mybb->input['username']) ? $mybb->input['username'] : '',                
            )
        );

        if ($ct_result->allow == 0)
        	antispam_by_cleantalk_show_message($ct_result->comment);
	
}
function antispam_by_cleantalk_contacttrigger()
{
    global $mybb;

    if (
        $mybb->settings['antispam_by_cleantalk_enabled'] === '0' ||
        $mybb->settings['antispam_by_cleantalk_comcheck'] === '0' ||
        trim($mybb->settings['antispam_by_cleantalk_accesskey']) === '' ||
        antispam_by_cleantalk_check_exclusions_url() ||
        antispam_by_cleantalk_check_exclusions_roles()
    ){
        return false;
    }
        $ct_result = antispam_by_cleantalk_spam_check(
            'check_message', array(
                'message' => $mybb->get_input('message') . " \n\n" . $mybb->get_input('subject'),
                'sender_email' => $mybb->get_input('email'),
                'sender_nickname' => isset($mybb->user['username']) ? $mybb->user['username'] : '',
            )
        );
        if ($ct_result->allow == 0)
            antispam_by_cleantalk_show_message($ct_result->comment);
    
}
function antispam_by_cleantalk_js_test()
{
	global $mybb;

	return (isset($_COOKIE['ct_checkjs']) &&  md5(trim($mybb->settings['antispam_by_cleantalk_accesskey'])) == $_COOKIE['ct_checkjs']) ? 1 : 0;
}

/**
 * Get options data for sender_info
 *
 * @return array
 */
function antispam_by_cleantalk_get_options()
{
    global $mybb;
    return array(
        'antispam_by_cleantalk_exclusions_groups' => $mybb->settings['antispam_by_cleantalk_exclusions_groups'],
        'antispam_by_cleantalk_exclusions_url'    => $mybb->settings['antispam_by_cleantalk_exclusions_url'],
        'antispam_by_cleantalk_comcheck'          => $mybb->settings['antispam_by_cleantalk_comcheck'],
        'antispam_by_cleantalk_enabled'           => $mybb->settings['antispam_by_cleantalk_enabled'],
        'antispam_by_cleantalk_regcheck'          => $mybb->settings['antispam_by_cleantalk_regcheck'],
        'antispam_by_cleantalk_sfw'               => $mybb->settings['antispam_by_cleantalk_sfw'],
        'antispam_by_cleantalk_footerlink'        => $mybb->settings['antispam_by_cleantalk_footerlink'],
        'antispam_by_cleantalk_accesskey'         => trim($mybb->settings['antispam_by_cleantalk_accesskey']),
    );
}

/**
 * Check exclusions - URL
 * @return bool
 */
function antispam_by_cleantalk_check_exclusions_url()
{
    global $mybb;
    if ( ! empty( $mybb->settings['antispam_by_cleantalk_exclusions_url'] ) ) {
        $exclusions = explode( ',', $mybb->settings['antispam_by_cleantalk_exclusions_url'] );
        $haystack = Server::get('REQUEST_URI');
        foreach ( $exclusions as $exclusion ) {
            if ( stripos( $haystack, $exclusion ) !== false ){
                return true;
            }
        }
    }
    return false;
}

/**
 * Check exclusions - User roles
 * @return bool
 */
function antispam_by_cleantalk_check_exclusions_roles()
{
    global $mybb;
    if ( ! empty( $mybb->settings['antispam_by_cleantalk_exclusions_groups'] ) ) {
        $exclusions = explode( ',', $mybb->settings['antispam_by_cleantalk_exclusions_groups'] );
        $haystack = explode( ',', $mybb->usergroup['all_usergroups'] );
        foreach ( $exclusions as $exclusion ) {
            if ( in_array( $exclusion, $haystack ) ){
                return true;
            }
        }
    }
    return false;
}

function antispam_by_cleantalk_spam_check($method, $params)
{
    global $mybb;

    $ct = new Cleantalk();
    $ct->server_url = 'http://moderate4.cleantalk.org';

    $ct_request = new CleantalkRequest();

    foreach ($params as $k => $v) 
        $ct_request->$k = $v;

    $page_set_timestamp = (isset($_COOKIE['ct_ps_timestamp']) ? $_COOKIE['ct_ps_timestamp'] : 0);
    $js_timezone = (isset($_COOKIE['ct_timezone']) ? $_COOKIE['ct_timezone'] : '');
    $first_key_timestamp = (isset($_COOKIE['ct_fkp_timestamp']) ? $_COOKIE['ct_fkp_timestamp'] : '');
    $pointer_data = (isset($_COOKIE['ct_pointer_data']) ? json_decode($_COOKIE['ct_pointer_data']) : '');

    $ct_request->auth_key = trim($mybb->settings['antispam_by_cleantalk_accesskey']);
    $ct_request->sender_ip = Helper::ip__get(array('real'), false);
    $ct_request->x_forwarded_for = Helper::ip__get(array('x_forwarded_for'), false);
    $ct_request->x_real_ip       = Helper::ip__get(array('x_real_ip'), false);
    $ct_request->agent = ENGINE;
    $ct_request->js_on = antispam_by_cleantalk_js_test();
    $ct_request->submit_time = time() - intval($page_set_timestamp);
    $ct_request->sender_info = json_encode(array(
        'page_url' => htmlspecialchars(@$_SERVER['SERVER_NAME'].@$_SERVER['REQUEST_URI']),
        'REFFERRER' => $_SERVER['HTTP_REFERER'],
        'USER_AGENT' => $_SERVER['HTTP_USER_AGENT'],
        'REFFERRER_PREVIOUS' => isset($_COOKIE['ct_prev_referer'])?$_COOKIE['ct_prev_referer']:0,     
        'cookies_enabled' => antispam_by_cleantalk_testcokkies(), 
        'js_timezone' => $js_timezone,
        'mouse_cursor_positions' => $pointer_data,
        'key_press_timestamp' => $first_key_timestamp,
        'page_set_timestamp' => $page_set_timestamp,
        'ct_options'             => json_encode(antispam_by_cleantalk_get_options()),
    ));    

    switch ($method) 
    {
        case 'check_message':
            $result = $ct->isAllowMessage($ct_request);
            break;
        case 'send_feedback':
            $result = $ct->sendFeedback($ct_request);
            break;
        case 'check_newuser':
            $result = $ct->isAllowUser($ct_request);
            break;
        default:
            return NULL;
    } 

    return $result;                                

}
function antispam_by_cleantalk_show_message($comment){
	
	global $mybb, $templates, $lang;
    
	if ($mybb->get_input('ajax', MyBB::INPUT_INT)){
		header("Content-type: application/json; charset={$lang->settings['charset']}");
		echo json_encode(array("errors" => array($comment)));
		exit;
	}
	$lang->error_nopermission_user_username = $lang->sprintf("<h1 style='font-size: 15px; color: red;'>".$comment."</h1>");
	eval("\$errorpage = \"".$templates->get("antispam_by_cleantalk_error_page")."\";");
	error($errorpage);
	
}
