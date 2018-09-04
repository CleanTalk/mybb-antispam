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
const ENGINE = 'mybb-12';
$plugins->add_hook('newthread_do_newthread_start', 'antispam_by_cleantalk_trigger');
$plugins->add_hook('newreply_do_newreply_start', 'antispam_by_cleantalk_trigger');
$plugins->add_hook('editpost_do_editpost_start', 'antispam_by_cleantalk_trigger');
$plugins->add_hook('member_do_register_start', 'antispam_by_cleantalk_regtrigger');
$plugins->add_hook('contact_do_start', 'antispam_by_cleantalk_contacttrigger');
$plugins->add_hook('global_start', 'antispam_by_cleantalk_set_global');
$plugins->add_hook('admin_config_settings_change_commit', 'savesettings_trigger');

function savesettings_trigger()
{
    global $mybb,$db;

    require_once MYBB_ROOT."/inc/adminfunctions_templates.php";

    $access_key = trim($mybb->settings['antispam_by_cleantalk_accesskey']);

    if ($access_key != '')
    {
    	CleantalkHelper::api_method_send_empty_feedback($access_key, ENGINE);

	    if ($mybb->settings['antispam_by_cleantalk_sfw'] === '1')
	    {
	    	$sfw = new CleantalkSFW();
	    	$sfw->update_sfw($access_key);
	    	$sfw->send_logs($access_key);
	    }    	
    }

    if ($mybb->settings['antispam_by_cleantalk_footerlink'] === '1')
    {
	    find_replace_templatesets("footer", '#'.preg_quote('<div id=\'cleantalk_footer_link\' style=\'width:100%;text-align:center;\'>MyBB spam blocked <a href=https://cleantalk.org/vbulletin-anti-spam-hack>by CleanTalk.</a></div>').'#', '',1); 
	    find_replace_templatesets("footer", '#'.preg_quote('{$auto_dst_detection}').'#', '<div id=\'cleantalk_footer_link\' style=\'width:100%;text-align:center;\'>MyBB spam blocked <a href=https://cleantalk.org/vbulletin-anti-spam-hack>by CleanTalk.</a></div>
	        {$auto_dst_detection}',1); 
	}
    else 
    	find_replace_templatesets("footer", '#'.preg_quote('<div id=\'cleantalk_footer_link\' style=\'width:100%;text-align:center;\'>MyBB spam blocked <a href=https://cleantalk.org/vbulletin-anti-spam-hack>by CleanTalk.</a></div>').'#', '',1); 

    
}
function antispam_by_cleantalk_info()
{
	return Array(
		"name" => "Antispam by CleanTalk (SPAM protection)",
		"description" => "Max power, all-in-one, no Captcha, premium anti-spam plugin. No comment spam, no registration spam. Formerly Anti-Spam by CleanTalk",
		"website" => "https://cleantalk.org/",
		"author" => "CleanTalk",
		"authorsite" => "https://cleantalk.org/",
		"version" => "v1.2",
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
		'name'			=> "antispam_by_cleantalk_sfw",
		'title'			=> "Enable SFW",
		'description'	=> "This option allows to filter spam bots before they access website. Also reduces CPU usage on hosting server and accelerates pages load time",
		'optionscode'	=> "yesno",
		'value'			=> "1",
		'disporder'		=> 4,
		'gid'			=> $gid
	);	

    $antispam_by_cleantalk_settings[] = Array(
        'name'          => "antispam_by_cleantalk_footerlink",
        'title'         => "Tell others about CleanTalk?",
        'description'   => "Enabling this option places a small link in the footer of your site that lets others know what anti-spam tool protects your site.",
        'optionscode'   => "yesno",
        'value'         => "0",
        'disporder'     => 5,
        'gid'           => $gid
    );

	$antispam_by_cleantalk_settings[] = Array(
		'name'			=> "antispam_by_cleantalk_accesskey",
		'title'			=> "Access key",
		'description'	=> "To get an access key, please register <a target=_blank href=https://cleantalk.org/register>here</a>",
		'optionscode'	=> "text",
		'value'			=> "",
		'disporder'		=> 6,
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
	) ENGINE=MyISAM DEFAULT CHARSET=latin1;
	");

	$db->query("CREATE TABLE IF NOT EXISTS ".TABLE_PREFIX."cleantalk_sfw_logs (
	  `ip` varchar(15) NOT NULL,
	  `all_entries` int(11) NOT NULL,
	  `blocked_entries` int(11) NOT NULL,
	  `entries_timestamp` int(11) NOT NULL,	  	  
	  PRIMARY KEY (`ip`)
	) ENGINE=MyISAM DEFAULT CHARSET=latin1;
	");	
}

function antispam_by_cleantalk_uninstall()
{
	global $db;

	$db->delete_query("settings", "name IN ('antispam_by_cleantalk_enabled','antispam_by_cleantalk_regcheck','antispam_by_cleantalk_comcheck','antispam_by_cleantalk_footerlink','antispam_by_cleantalk_accesskey')");
	$db->delete_query("settinggroups", "name='antispam_by_cleantalk'");
	$db->delete_query("templates", "title = 'antispam_by_cleantalk_error_page'");
	$db->drop_table("cleantalk_sfw");
	$db->drop_table("cleantalk_sfw_logs");

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

	if ($mybb->settings['antispam_by_cleantalk_sfw'] === '1')
	{
	   	$is_sfw_check = true;
		$sfw = new CleantalkSFW();
		$sfw->ip_array = (array)CleantalkSFW::ip_get(array('real'), true);	
			
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
			if($sfw->result)
			{
				$sfw->sfw_update_logs($sfw->blocked_ip, 'blocked');
				$sfw->sfw_die(trim($mybb->settings['antispam_by_cleantalk_accesskey']));
			}
		}	      				
	}	
}
function antispam_by_cleantalk_set_global()
{
	antispam_by_cleantalk_setcookies();

	antispam_by_cleantalk_sfw_check();

	antispam_by_cleantalK_add_js();

}
function antispam_by_cleantalK_add_js()
{
	global $mybb;

	if (trim($mybb->settings['antispam_by_cleantalk_accesskey']) != '')
	{
		$ct_checkjs_val = md5(trim($mybb->settings['antispam_by_cleantalk_accesskey']));
		echo '<script>var ct_check_js_val = '.$ct_checkjs_val.', d = new Date(), 
				ctTimeMs = new Date().getTime(),
				ctMouseEventTimerFlag = true, //Reading interval flag
				ctMouseData = "[",
				ctMouseDataCounter = 0;
			
			function ctSetCookie(c_name, value) {
				document.cookie = c_name + "=" + encodeURIComponent(value) + "; path=/";
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
			}</script>';		
	}

}
function antispam_by_cleantalk_setcookies(){
    global $mybb;

    // Cookie names to validate
    $cookie_test_value = array(
        'cookies_names' => array(),
        'check_value' => trim($mybb->settings['antispam_by_cleantalk_accesskey']),
    );
        
    // Submit time
    $apbct_timestamp = time();
    setcookie('ct_timestamp', $apbct_timestamp, 0, '/');
    $cookie_test_value['cookies_names'][] = 'ct_timestamp';
    $cookie_test_value['check_value'] .= $apbct_timestamp;

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

	if ($mybb->settings['antispam_by_cleantalk_enabled'] === '0' || $mybb->settings['antispam_by_cleantalk_comcheck'] === '0' || trim($mybb->settings['antispam_by_cleantalk_accesskey']) === ''){
		return false;
	}
	
	if ($mybb->user['postnum'] <= '3')
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
	if ($mybb->settings['antispam_by_cleantalk_enabled'] === '0' || $mybb->settings['antispam_by_cleantalk_regcheck'] === '0' || trim($mybb->settings['antispam_by_cleantalk_accesskey']) === ''){
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

    if ($mybb->settings['antispam_by_cleantalk_enabled'] === '0' || $mybb->settings['antispam_by_cleantalk_comcheck'] === '0' || trim($mybb->settings['antispam_by_cleantalk_accesskey']) === ''){
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
function antispam_by_cleantalk_spam_check($method, $params)
{
    global $mybb;

    $ct = new Cleantalk();
    $ct->server_url = 'http://moderate.cleantalk.ru';

    $ct_request = new CleantalkRequest();

    foreach ($params as $k => $v) 
        $ct_request->$k = $v;

    $page_set_timestamp = (isset($_COOKIE['ct_ps_timestamp']) ? $_COOKIE['ct_ps_timestamp'] : 0);
    $js_timezone = (isset($_COOKIE['ct_timezone']) ? $_COOKIE['ct_timezone'] : '');
    $first_key_timestamp = (isset($_COOKIE['ct_fkp_timestamp']) ? $_COOKIE['ct_fkp_timestamp'] : '');
    $pointer_data = (isset($_COOKIE['ct_pointer_data']) ? json_decode($_COOKIE['ct_pointer_data']) : '');

    $ct_request->auth_key = trim($mybb->settings['antispam_by_cleantalk_accesskey']);
    $ct_request->sender_ip = CleantalkHelper::ip_get(array('real'), false);
    $ct_request->x_forwarded_for = CleantalkHelper::ip_get(array('x_forwarded_for'), false);
    $ct_request->x_real_ip       = CleantalkHelper::ip_get(array('x_real_ip'), false);
    $ct_request->agent = ENGINE;
    $ct_request->js_on = 1;
    $ct_request->submit_time = isset($_COOKIE['ct_timestamp']) ? time() - intval($_COOKIE['ct_timestamp']) : 0; 
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

/**
 * Cleantalk base class
 *
 * @version 2.1.3
 * @package Cleantalk
 * @subpackage Base
 * @author Cleantalk team (welcome@cleantalk.org)
 * @copyright (C) 2014 CleanTalk team (http://cleantalk.org)
 * @license GNU/GPL: http://www.gnu.org/copyleft/gpl.html
 * @see https://github.com/CleanTalk/php-antispam 
 *
 */
 
/**
 * Response class
 */
class CleantalkResponse
{

    /**
     * Received feedback nubmer
     * @var int
     */
    public $received = null;
    
    /**
     *  Is stop words
     * @var int
     */
    public $stop_words = null;
    
    /**
     * Cleantalk comment
     * @var string
     */
    public $comment = null;

    /**
     * Is blacklisted
     * @var int
     */
    public $blacklisted = null;

    /**
     * Is allow, 1|0
     * @var int
     */
    public $allow = null;

    /**
     * Request ID
     * @var int
     */
    public $id = null;

    /**
     * Request errno
     * @var int
     */
    public $errno = null;

    /**
     * Error string
     * @var string
     */
    public $errstr = null;

    /**
     * Error string
     * @var string
     */
    public $curl_err = null;
    
    /**
     * Is fast submit, 1|0
     * @var string
     */
    public $fast_submit = null;

    /**
     * Is spam comment
     * @var string
     */
    public $spam = null;

    /**
     * Is JS
     * @var type 
     */
    public $js_disabled = null;

    /**
     * Sms check
     * @var type 
     */
    public $sms_allow = null;

    /**
     * Sms code result
     * @var type 
     */
    public $sms = null;
    
    /**
     * Sms error code
     * @var type 
     */
    public $sms_error_code = null;
    
    /**
     * Sms error code
     * @var type 
     */
    public $sms_error_text = null;
    
    /**
     * Stop queue message, 1|0
     * @var int  
     */
    public $stop_queue = null;
    
    /**
     * Account shuld by deactivated after registration, 1|0
     * @var int  
     */
    public $inactive = null;

    /**
     * Account status 
     * @var int  
     */
    public $account_status = -1;

    /**
     * Create server response
     *
     * @param type $response
     * @param type $obj
     */
    function __construct($response = null, $obj = null) {
        if ($response && is_array($response) && count($response) > 0) {
            foreach ($response as $param => $value) {
                $this->{$param} = $value;
            }
        } else {
            $this->errno = $obj->errno;
            $this->errstr = $obj->errstr;
            $this->curl_err = !empty($obj->curl_err) ? $obj->curl_err : false;

            $this->errstr = preg_replace("/.+(\*\*\*.+\*\*\*).+/", "$1", $this->errstr);

            $this->stop_words = isset($obj->stop_words) ? utf8_decode($obj->stop_words) : null;
            $this->comment = isset($obj->comment) ? utf8_decode($obj->comment) : null;
            $this->blacklisted = (isset($obj->blacklisted)) ? $obj->blacklisted : null;
            $this->allow = (isset($obj->allow)) ? $obj->allow : 0;
            $this->id = (isset($obj->id)) ? $obj->id : null;
            $this->fast_submit = (isset($obj->fast_submit)) ? $obj->fast_submit : 0;
            $this->spam = (isset($obj->spam)) ? $obj->spam : 0;
            $this->js_disabled = (isset($obj->js_disabled)) ? $obj->js_disabled : 0;
            $this->sms_allow = (isset($obj->sms_allow)) ? $obj->sms_allow : null;
            $this->sms = (isset($obj->sms)) ? $obj->sms : null;
            $this->sms_error_code = (isset($obj->sms_error_code)) ? $obj->sms_error_code : null;
            $this->sms_error_text = (isset($obj->sms_error_text)) ? $obj->sms_error_text : null;
            $this->stop_queue = (isset($obj->stop_queue)) ? $obj->stop_queue : 0;
            $this->inactive = (isset($obj->inactive)) ? $obj->inactive : 0;
            $this->account_status = (isset($obj->account_status)) ? $obj->account_status : -1;
            $this->received = (isset($obj->received)) ? $obj->received : -1;

            if ($this->errno !== 0 && $this->errstr !== null && $this->comment === null)
                $this->comment = '*** ' . $this->errstr . ' Antispam service cleantalk.org ***'; 
        }
    }
}
/**
 * Request class
 */
class CleantalkRequest {

     /**
     *  All http request headers
     * @var string
     */
     public $all_headers = null;
     
     /**
     *  IP address of connection
     * @var string
     */
     //public $remote_addr = null;
     
     /**
     *  Last error number
     * @var integer
     */
     public $last_error_no = null;
     
     /**
     *  Last error time
     * @var integer
     */
     public $last_error_time = null;
     
     /**
     *  Last error text
     * @var string
     */
     public $last_error_text = null;

    /**
     * User message
     * @var string
     */
    public $message = null;

    /**
     * Post example with last comments
     * @var string
     */
    public $example = null;

    /**
     * Auth key
     * @var string
     */
    public $auth_key = null;

    /**
     * Engine
     * @var string
     */
    public $agent = null;

    /**
     * Is check for stoplist,
     * valid are 0|1
     * @var int
     */
    public $stoplist_check = null;

    /**
     * Language server response,
     * valid are 'en' or 'ru'
     * @var string
     */
    public $response_lang = null;

    /**
     * User IP
     * @var strings
     */
    public $sender_ip = null;

    /**
     * User email
     * @var strings
     */
    public $sender_email = null;

    /**
     * User nickname
     * @var string
     */
    public $sender_nickname = null;

    /**
     * Sender info JSON string
     * @var string
     */
    public $sender_info = null;

    /**
     * Post info JSON string
     * @var string
     */
    public $post_info = null;

    /**
     * Is allow links, email and icq,
     * valid are 1|0
     * @var int
     */
    public $allow_links = null;

    /**
     * Time form filling
     * @var int
     */
    public $submit_time = null;
    
    public $x_forwarded_for = '';
    public $x_real_ip = '';

    /**
     * Is enable Java Script,
     * valid are 0|1|2
     * Status:
     *  null - JS html code not inserted into phpBB templates
     *  0 - JS disabled at the client browser
     *  1 - JS enabled at the client broswer
     * @var int
     */
    public $js_on = null;

    /**
     * user time zone
     * @var string
     */
    public $tz = null;

    /**
     * Feedback string,
     * valid are 'requset_id:(1|0)'
     * @var string
     */
    public $feedback = null;

    /**
     * Phone number
     * @var type 
     */
    public $phone = null;
    
    /**
    * Method name
    * @var string
    */
    public $method_name = 'check_message'; 

    /**
     * Fill params with constructor
     * @param type $params
     */
    public function __construct($params = null) {
        if (is_array($params) && count($params) > 0) {
            foreach ($params as $param => $value) {
                $this->{$param} = $value;
            }
        }
    }

}
/**
 * Cleantalk class create request
 */
class Cleantalk
{

    /**
     * Debug level
     * @var int
     */
    public $debug = 0;
    
    /**
    * Maximum data size in bytes
    * @var int
    */
    private $dataMaxSise = 32768;
    
    /**
    * Data compression rate 
    * @var int
    */
    private $compressRate = 6;
    
    /**
    * Server connection timeout in seconds 
    * @var int
    */
    private $server_timeout = 15;

    /**
     * Cleantalk server url
     * @var string
     */
    public $server_url = null;

    /**
     * Last work url
     * @var string
     */
    public $work_url = null;

    /**
     * WOrk url ttl
     * @var int
     */
    public $server_ttl = null;

    /**
     * Time wotk_url changer
     * @var int
     */
    public $server_changed = null;

    /**
     * Flag is change server url
     * @var bool
     */
    public $server_change = false;

    /**
     * Use TRUE when need stay on server. Example: send feedback
     * @var bool
     */
    public $stay_on_server = false;
    
    /**
     * Codepage of the data 
     * @var bool
     */
    public $data_codepage = null;
    
    /**
     * API version to use 
     * @var string
     */
    public $api_version = '/api2.0';
    
    /**
     * Use https connection to servers 
     * @var bool 
     */
    public $ssl_on = false;
    
    /**
     * Path to SSL certificate 
     * @var string
     */
    public $ssl_path = '';

    /**
     * Minimal server response in miliseconds to catch the server
     *
     */
    public $min_server_timeout = 50;

    /**
     * Function checks whether it is possible to publish the message
     * @param CleantalkRequest $request
     * @return type
     */
    public function isAllowMessage(CleantalkRequest $request) {
        $request = $this->filterRequest($request);
        $msg = $this->createMsg('check_message', $request);
        return $this->httpRequest($msg);
    }

    /**
     * Function checks whether it is possible to publish the message
     * @param CleantalkRequest $request
     * @return type
     */
    public function isAllowUser(CleantalkRequest $request) {
        $request = $this->filterRequest($request);
        $msg = $this->createMsg('check_newuser', $request);
        return $this->httpRequest($msg);
    }

    /**
     * Function sends the results of manual moderation
     *
     * @param CleantalkRequest $request
     * @return type
     */
    public function sendFeedback(CleantalkRequest $request) {
        $request = $this->filterRequest($request);
        $msg = $this->createMsg('send_feedback', $request);
        return $this->httpRequest($msg);
    }

    /**
     *  Filter request params
     * @param CleantalkRequest $request
     * @return type
     */
    private function filterRequest(CleantalkRequest $request) {
        // general and optional
        foreach ($request as $param => $value) {
            if (in_array($param, array('message', 'example', 'agent',
                        'sender_info', 'sender_nickname', 'post_info', 'phone')) && !empty($value)) {
                if (!is_string($value) && !is_integer($value)) {
                    $request->$param = NULL;
                }
            }

            if (in_array($param, array('stoplist_check', 'allow_links')) && !empty($value)) {
                if (!in_array($value, array(1, 2))) {
                    $request->$param = NULL;
                }
            }
            
            if (in_array($param, array('js_on')) && !empty($value)) {
                if (!is_integer($value)) {
                    $request->$param = NULL;
                }
            }

            if ($param == 'sender_ip' && !empty($value)) {
                if (!is_string($value)) {
                    $request->$param = NULL;
                }
            }

            if ($param == 'sender_email' && !empty($value)) {
                if (!is_string($value)) {
                    $request->$param = NULL;
                }
            }

            if ($param == 'submit_time' && !empty($value)) {
                if (!is_int($value)) {
                    $request->$param = NULL;
                }
            }
        }
        return $request;
    }
    
    /**
     * Compress data and encode to base64 
     * @param type string
     * @return string 
     */
    private function compressData($data = null){
        
        if (strlen($data) > $this->dataMaxSise && function_exists('gzencode') && function_exists('base64_encode')){

            $localData = gzencode($data, $this->compressRate, FORCE_GZIP);

            if ($localData === false)
                return $data;
            
            $localData = base64_encode($localData);
            
            if ($localData === false)
                return $data;
            
            return $localData;
        }

        return $data;
    } 

    /**
     * Create msg for cleantalk server
     * @param type $method
     * @param CleantalkRequest $request
     * @return \xmlrpcmsg
     */
    private function createMsg($method, CleantalkRequest $request) {
        switch ($method) {
            case 'check_message':
                // Convert strings to UTF8
                $request->message = $this->stringToUTF8($request->message, $this->data_codepage);
                $request->example = $this->stringToUTF8($request->example, $this->data_codepage);
                $request->sender_email = $this->stringToUTF8($request->sender_email, $this->data_codepage);
                $request->sender_nickname = $this->stringToUTF8($request->sender_nickname, $this->data_codepage);

                $request->message = $this->compressData($request->message);
                $request->example = $this->compressData($request->example);
                break;

            case 'check_newuser':
                // Convert strings to UTF8
                $request->sender_email = $this->stringToUTF8($request->sender_email, $this->data_codepage);
                $request->sender_nickname = $this->stringToUTF8($request->sender_nickname, $this->data_codepage);
                break;

            case 'send_feedback':
                if (is_array($request->feedback)) {
                    $request->feedback = implode(';', $request->feedback);
                }
                break;
        }
        
        $request->method_name = $method;
        
        //
        // Removing non UTF8 characters from request, because non UTF8 or malformed characters break json_encode().
        //
        foreach ($request as $param => $value) {
            if (!preg_match('//u', $value))
                $request->{$param} = 'Nulled. Not UTF8 encoded or malformed.'; 
        }
        
        return $request;
    }
    
    /**
     * Send JSON request to servers 
     * @param $msg
     * @return boolean|\CleantalkResponse
     */
    private function sendRequest($data = null, $url, $server_timeout = 3) {
        // Convert to array
        $data = (array)json_decode(json_encode($data), true);
        
        $original_url = $url;
        $original_data = $data;
        
        //Cleaning from 'null' values
        $tmp_data = array();
        foreach($data as $key => $value){
            if($value !== null){
                $tmp_data[$key] = $value;
            }
        }
        $data = $tmp_data;
        unset($key, $value, $tmp_data);
        
        // Convert to JSON
        $data = json_encode($data);
        
        if (isset($this->api_version)) {
            $url = $url . $this->api_version;
        }
        
        // Switching to secure connection
        if ($this->ssl_on && !preg_match("/^https:/", $url)) {
            $url = preg_replace("/^(http)/i", "$1s", $url);
        }
        
        $result = false;
        $curl_error = null;
        if(function_exists('curl_init')){
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_TIMEOUT, $server_timeout);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            // receive server response ...
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            // resolve 'Expect: 100-continue' issue
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:'));
            // see http://stackoverflow.com/a/23322368
            curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
            
            // Disabling CA cert verivication
            // Disabling common name verification
            if ($this->ssl_on && $this->ssl_path=='') {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            }
            else if ($this->ssl_on && $this->ssl_path!='') {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
                curl_setopt($ch, CURLOPT_CAINFO, $this->ssl_path);
            }

            $result = curl_exec($ch);
            if (!$result) {
                $curl_error = curl_error($ch);
                // Use SSL next time, if error occurs.
                if(!$this->ssl_on){
                    $this->ssl_on = true;
                    return $this->sendRequest($original_data, $original_url, $server_timeout);
                }
            }
            
            curl_close($ch); 
        }

        if (!$result) {
            $allow_url_fopen = ini_get('allow_url_fopen');
            if (function_exists('file_get_contents') && isset($allow_url_fopen) && $allow_url_fopen == '1') {
                $opts = array('http' =>
                  array(
                    'method'  => 'POST',
                    'header'  => "Content-Type: text/html\r\n",
                    'content' => $data,
                    'timeout' => $server_timeout
                  )
                );

                $context  = stream_context_create($opts);
                $result = @file_get_contents($url, false, $context);
            }
        }
        
        if (!$result || !self::cleantalk_is_JSON($result)) {
            $response = null;
            $response['errno'] = 1;
            $response['errstr'] = true;
            $response['curl_err'] = isset($curl_error) ? $curl_error : false;
            $response = json_decode(json_encode($response));
            
            return $response;
        }
        
        $errstr = null;
        $response = json_decode($result);
        if ($result !== false && is_object($response)) {
            $response->errno = 0;
            $response->errstr = $errstr;
        } else {
            $errstr = 'Unknown response from ' . $url . '.' . ' ' . $result;
            
            $response = null;
            $response['errno'] = 1;
            $response['errstr'] = $errstr;
            $response = json_decode(json_encode($response));
        } 
        
        
        return $response;
    }

    /**
     * httpRequest 
     * @param $msg
     * @return boolean|\CleantalkResponse
     */
    private function httpRequest($msg) {
        
        $result = false;
        
        if($msg->method_name != 'send_feedback'){
            $tmp = function_exists('apache_request_headers')
                ? apache_request_headers()
                : self::apache_request_headers();
            
            if(isset($tmp['Cookie'])){
                $cookie_name = 'Cookie';
            }elseif(isset($tmp['cookie'])){
                $cookie_name = 'cookie';
            }else{
                $cookie_name = 'COOKIE';
            }
            
            if(isset($tmp[$cookie_name])){
                $tmp[$cookie_name] = preg_replace(array(
                    '/\s{0,1}ct_checkjs=[a-z0-9]*[;|$]{0,1}/',
                    '/\s{0,1}ct_timezone=.{0,1}\d{1,2}[;|$]/', 
                    '/\s{0,1}ct_pointer_data=.*5D[;|$]{0,1}/', 
                    '/;{0,1}\s{0,3}$/'
                ), '', $tmp[$cookie_name]);
            }
            
            $msg->all_headers=json_encode($tmp);
        }
        
        $si=(array)json_decode($msg->sender_info,true);

        $si['remote_addr'] = $_SERVER['REMOTE_ADDR'];
        if(isset($_SERVER['X_FORWARDED_FOR'])) $msg->x_forwarded_for = $_SERVER['X_FORWARDED_FOR'];
        if(isset($_SERVER['X_REAL_IP']))       $msg->x_real_ip       = $_SERVER['X_REAL_IP'];
        
        $msg->sender_info=json_encode($si);
        if (((isset($this->work_url) && $this->work_url !== '') && ($this->server_changed + $this->server_ttl > time()))
                || $this->stay_on_server == true) {
            
            $url = (!empty($this->work_url)) ? $this->work_url : $this->server_url;
                    
            $result = $this->sendRequest($msg, $url, $this->server_timeout);
        }

        if (($result === false || $result->errno != 0) && $this->stay_on_server == false) {
            // Split server url to parts
            preg_match("@^(https?://)([^/:]+)(.*)@i", $this->server_url, $matches);
            $url_prefix = '';
            if (isset($matches[1]))
                $url_prefix = $matches[1];

            $pool = null;
            if (isset($matches[2]))
                $pool = $matches[2];
            
            $url_suffix = '';
            if (isset($matches[3]))
                $url_suffix = $matches[3];
            
            if ($url_prefix === '')
                $url_prefix = 'http://';

            if (empty($pool)) {
                return false;
            } else {
                // Loop until find work server
                foreach ($this->get_servers_ip($pool) as $server) {
                    if ($server['host'] === 'localhost' || $server['ip'] === null) {
                        $work_url = $server['host'];
                    } else {
                        $server_host = $server['ip'];
                        $work_url = $server_host;
                    }
                    $work_url = $url_prefix . $work_url; 
                    if (isset($url_suffix)) 
                        $work_url = $work_url . $url_suffix;
                    
                    $this->work_url = $work_url;
                    $this->server_ttl = $server['ttl'];
                    
                    $result = $this->sendRequest($msg, $this->work_url, $this->server_timeout);

                    if ($result !== false && $result->errno === 0) {
                        $this->server_change = true;
                        break;
                    }
                }
            }
        }
        
        $response = new CleantalkResponse(null, $result);
        
        if (!empty($this->data_codepage) && $this->data_codepage !== 'UTF-8') 
        {
            if (!empty($response->comment))
            $response->comment = $this->stringFromUTF8($response->comment, $this->data_codepage);
            if (!empty($response->errstr))
            $response->errstr = $this->stringFromUTF8($response->errstr, $this->data_codepage);
            if (!empty($response->sms_error_text))
            $response->sms_error_text = $this->stringFromUTF8($response->sms_error_text, $this->data_codepage);
        }
        
        return $response;
    }
    
    /**
     * Function DNS request
     * @param $host
     * @return array
     */
    public function get_servers_ip($host)
    {
        $response = null;
        if (!isset($host))
            return $response;

        if (function_exists('dns_get_record')) {
            $records = @dns_get_record($host, DNS_A);

            if ($records !== FALSE) {
                foreach ($records as $server) {
                    $response[] = $server;
                }
            }
        }

        if (count($response) == 0 && function_exists('gethostbynamel')) {
            $records = gethostbynamel($host);

            if ($records !== FALSE) {
                foreach ($records as $server) {
                    $response[] = array(
                        "ip" => $server,
                        "host" => $host,
                        "ttl" => $this->server_ttl
                    );
                }
            }
        }

        if (count($response) == 0) {
            $response[] = array("ip" => null,
                "host" => $host,
                "ttl" => $this->server_ttl
            );
        } else {
            // $i - to resolve collisions with localhost
            $i = 0;
            $r_temp = null;
            $fast_server_found = false;
            foreach ($response as $server) {
                
                // Do not test servers because fast work server found
                if ($fast_server_found) {
                    $ping = $this->min_server_timeout; 
                } else {
                    $ping = $this->httpPing($server['ip']);
                    $ping = $ping * 1000;
                }
                
                // -1 server is down, skips not reachable server
                if ($ping != -1) {
                    $r_temp[$ping + $i] = $server;
                }
                $i++;
                
                if ($ping < $this->min_server_timeout) {
                    $fast_server_found = true;
                }
            }
            if (count($r_temp)){
                ksort($r_temp);
                $response = $r_temp;
            }
        }

        return $response;
    }

    /**
     * Function to get the message hash from Cleantalk.ru comment
     * @param $message
     * @return null
     */
    public function getCleantalkCommentHash($message) {
        $matches = array();
        if (preg_match('/\n\n\*\*\*.+([a-z0-9]{32}).+\*\*\*$/', $message, $matches))
            return $matches[1];
        else if (preg_match('/\<br.*\>[\n]{0,1}\<br.*\>[\n]{0,1}\*\*\*.+([a-z0-9]{32}).+\*\*\*$/', $message, $matches))
            return $matches[1];

        return NULL;
    }

    /**
     * Function adds to the post comment Cleantalk.ru
     * @param $message
     * @param $comment
     * @return string
     */
    public function addCleantalkComment($message, $comment) {
        $comment = preg_match('/\*\*\*(.+)\*\*\*/', $comment, $matches) ? $comment : '*** ' . $comment . ' ***';
        return $message . "\n\n" . $comment;
    }

    /**
     * Function deletes the comment Cleantalk.ru
     * @param $message
     * @return mixed
     */
    public function delCleantalkComment($message) {
        $message = preg_replace('/\n\n\*\*\*.+\*\*\*$/', '', $message);

        // DLE sign cut
        $message = preg_replace('/<br\s?\/><br\s?\/>\*\*\*.+\*\*\*$/', '', $message);

        $message = preg_replace('/\<br.*\>[\n]{0,1}\<br.*\>[\n]{0,1}\*\*\*.+\*\*\*$/', '', $message);
        
        return $message;
    }

    /**
    *   Get user IP behind proxy server
    */
    public function ct_session_ip( $data_ip ) {
        if (!$data_ip || !preg_match("/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/", $data_ip))
            return $data_ip;
        
        return self::cleantalk_get_real_ip();
    }

    /**
    * From http://php.net/manual/en/function.ip2long.php#82397
    */
    public function net_match($CIDR,$IP) { 
        list ($net, $mask) = explode ('/', $CIDR); 
        return ( ip2long ($IP) & ~((1 << (32 - $mask)) - 1) ) == ip2long ($net); 
    } 
    
    /**
    * Function to check response time
    * param string
    * @return int
    */
    public function httpPing($host){

        // Skip localhost ping cause it raise error at fsockopen.
        // And return minimun value 
        if ($host == 'localhost')
            return 0.001;

        $starttime = microtime(true);
        $file      = @fsockopen ($host, 80, $errno, $errstr, $this->server_timeout);
        $stoptime  = microtime(true);
        $status    = 0;
        if (!$file) {
            $status = -1;  // Site is down
        } else {
            fclose($file);
            $status = ($stoptime - $starttime);
            $status = round($status, 4);
        }
        
        return $status;
    }
    
    /**
    * Function convert string to UTF8 and removes non UTF8 characters 
    * param string
    * param string
    * @return string
    */
    public function stringToUTF8($str, $data_codepage = null){
        if (!preg_match('//u', $str) && function_exists('mb_detect_encoding') && function_exists('mb_convert_encoding'))
        {
            
            if ($data_codepage !== null)
                return mb_convert_encoding($str, 'UTF-8', $data_codepage);

            $encoding = mb_detect_encoding($str);
            if ($encoding)
                return mb_convert_encoding($str, 'UTF-8', $encoding);
        }
        
        return $str;
    }
    
    /**
    * Function convert string from UTF8 
    * param string
    * param string
    * @return string
    */
    public function stringFromUTF8($str, $data_codepage = null){
        if (preg_match('//u', $str) && function_exists('mb_convert_encoding') && $data_codepage !== null)
        {
            return mb_convert_encoding($str, $data_codepage, 'UTF-8');
        }
        
        return $str;
    }
    
    static public function cleantalk_get_real_ip(){
        
        $headers = function_exists('apache_request_headers')
            ? apache_request_headers()
            : self::apache_request_headers();
        
        // Getting IP for validating
        if (array_key_exists( 'X-Forwarded-For', $headers )){
            $ip = explode(",", trim($headers['X-Forwarded-For']));
            $ip = trim($ip[0]);
        }elseif(array_key_exists( 'HTTP_X_FORWARDED_FOR', $headers)){
            $ip = explode(",", trim($headers['HTTP_X_FORWARDED_FOR']));
            $ip = trim($ip[0]);
        }else{
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        // Validating IP
        // IPv4
        if(filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)){
            $the_ip = $ip;
            // IPv6
        }elseif(filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)){
            $the_ip = $ip;
            // Unknown
        }else{
            $the_ip = null;
        }

        return $the_ip;
    }
    
    static public function cleantalk_is_JSON($string){
        return ((is_string($string) && (is_object(json_decode($string)) || is_array(json_decode($string))))) ? true : false;
    }
    
    /* 
     * If Apache web server is missing then making
     * Patch for apache_request_headers() 
     */
    static function apache_request_headers(){
        
        $headers = array(); 
        foreach($_SERVER as $key => $val){
            if(preg_match('/\AHTTP_/', $key)){
                $server_key = preg_replace('/\AHTTP_/', '', $key);
                $key_parts = explode('_', $server_key);
                if(count($key_parts) > 0 and strlen($server_key) > 2){
                    foreach($key_parts as $part_index => $part){
                        $key_parts[$part_index] = mb_strtolower($part);
                        $key_parts[$part_index][0] = strtoupper($key_parts[$part_index][0]);                    
                    }
                    $server_key = implode('-', $key_parts);
                }
                $headers[$server_key] = $val;
            }
        }
        return $headers;
    }
}
class CleantalkHelper
{
    const URL = 'https://api.cleantalk.org';

    public static $cdn_pool = array(
        'cloud_flare' => array(
            'ipv4' => array(
                '103.21.244.0/22',
                '103.22.200.0/22',
                '103.31.4.0/22',
                '104.16.0.0/12',
                '108.162.192.0/18',
                '131.0.72.0/22',
                '141.101.64.0/18',
                '162.158.0.0/15',
                '172.64.0.0/13',
                '173.245.48.0/20',
                '185.93.231.18/20', // User fix
                '185.220.101.46/20', // User fix
                '188.114.96.0/20',
                '190.93.240.0/20',
                '197.234.240.0/22',
                '198.41.128.0/17',
            ),
            'ipv6' => array(
                '2400:cb00::/32',
                '2405:8100::/32',
                '2405:b500::/32',
                '2606:4700::/32',
                '2803:f800::/32',
                '2c0f:f248::/32',
                '2a06:98c0::/29',
            ),
        ),
    );
    
    public static $private_networks = array(
        '10.0.0.0/8',
        '100.64.0.0/10',
        '172.16.0.0/12',
        '192.168.0.0/16',
        '127.0.0.1/32',
    );
    
    /*
    *   Getting arrays of IP (REMOTE_ADDR, X-Forwarded-For, X-Real-Ip, Cf_Connecting_Ip)
    *   reutrns array('remote_addr' => 'val', ['x_forwarded_for' => 'val', ['x_real_ip' => 'val', ['cloud_flare' => 'val']]])
    */
    static public function ip_get($ips_input = array('real', 'remote_addr', 'x_forwarded_for', 'x_real_ip', 'cloud_flare'), $v4_only = true)
    {
        $ips = array();
        foreach($ips_input as $ip_type){
            $ips[$ip_type] = '';
        } unset($ip_type);
                
        $headers = function_exists('apache_request_headers') ? apache_request_headers() : self::apache_request_headers();
        
        // REMOTE_ADDR
        if(isset($ips['remote_addr'])){
            $ips['remote_addr'] = $_SERVER['REMOTE_ADDR'];
        }
        
        // X-Forwarded-For
        if(isset($ips['x_forwarded_for'])){
            if(isset($headers['X-Forwarded-For'])){
                $tmp = explode(",", trim($headers['X-Forwarded-For']));
                $ips['x_forwarded_for']= trim($tmp[0]);
            }
        }
        
        // X-Real-Ip
        if(isset($ips['x_real_ip'])){
            if(isset($headers['X-Real-Ip'])){
                $tmp = explode(",", trim($headers['X-Real-Ip']));
                $ips['x_real_ip']= trim($tmp[0]);
            }
        }
        
        // Cloud Flare
        if(isset($ips['cloud_flare'])){
            if(isset($headers['Cf-Connecting-Ip'])){
                if(self::ip_mask_match($ips['remote_addr'], self::$cdn_pool['cloud_flare']['ipv4'])){
                    $ips['cloud_flare'] = $headers['Cf-Connecting-Ip'];
                }
            }
        }
        
        // Getting real IP from REMOTE_ADDR or Cf_Connecting_Ip if set or from (X-Forwarded-For, X-Real-Ip) if REMOTE_ADDR is local.
        if(isset($ips['real'])){
            
            $ips['real'] = $_SERVER['REMOTE_ADDR'];
            
            // Cloud Flare
            if(isset($headers['Cf-Connecting-Ip'])){
                if(self::ip_mask_match($ips['real'], self::$cdn_pool['cloud_flare']['ipv4'])){
                    $ips['real'] = $headers['Cf-Connecting-Ip'];
                }
            // Incapsula proxy
            }elseif(isset($headers['Incap-Client-Ip'])){
                $ips['real'] = $headers['Incap-Client-Ip'];
            // Private networks. Looking for X-Forwarded-For and X-Real-Ip
            }elseif(self::ip_mask_match($ips['real'], self::$private_networks)){
                if(isset($headers['X-Forwarded-For'])){
                    $tmp = explode(",", trim($headers['X-Forwarded-For']));
                    $ips['real']= trim($tmp[0]);
                }elseif(isset($headers['X-Real-Ip'])){
                    $tmp = explode(",", trim($headers['X-Real-Ip']));
                    $ips['real']= trim($tmp[0]);
                }
            }
        }
        
        // Validating IPs
        $result = array();
        foreach($ips as $key => $ip){
            if($v4_only){
                if(self::ip_validate($ip) == 'v4')
                    $result[$key] = $ip;
            }else{
                if(self::ip_validate($ip))
                    $result[$key] = $ip;
            }
        }
        
        $result = array_unique($result);
        
        return count($ips_input) > 1 
            ? $result 
            : (reset($result) !== false
                ? reset($result)
                : null);
    }
        
    /*
     * Check if the IP belong to mask. Recursivly if array given
     * @param ip string  
     * @param cird mixed (string|array of strings)
    */
    static public function ip_mask_match($ip, $cidr){
        if(is_array($cidr)){
            foreach($cidr as $curr_mask){
                if(self::ip_mask_match($ip, $curr_mask)){
                    return true;
                }
            } unset($curr_mask);
            return false;
        }
        $exploded = explode ('/', $cidr);
        $net = $exploded[0];
        $mask = 4294967295 << (32 - $exploded[1]);
        return (ip2long($ip) & $mask) == (ip2long($net) & $mask);
    }
    
    /*
    *   Validating IPv4, IPv6
    *   param (string) $ip
    *   returns (string) 'v4' || (string) 'v6' || (bool) false
    */
    static public function ip_validate($ip)
    {
        if(!$ip)                                                  return false; // NULL || FALSE || '' || so on...
        if(filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) return 'v4';  // IPv4
        if(filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) return 'v6';  // IPv6
                                                                  return false; // Unknown
    }
    
    /*
    * Wrapper for sfw_logs API method
    * 
    * returns mixed STRING || array('error' => true, 'error_string' => STRING)
    */
    static public function api_method__sfw_logs($api_key, $data, $do_check = true){
        
        $request = array(
            'auth_key' => $api_key,
            'method_name' => 'sfw_logs',
            'data' => json_encode($data),
            'rows' => count($data),
            'timestamp' => time()
        );
        $result = self::api_send_request($request);
        $result = $do_check ? self::api_check_response($result, 'sfw_logs') : $result;
        
        return $result;
    }
    
    /*
    * Wrapper for 2s_blacklists_db API method
    * 
    * returns mixed STRING || array('error' => true, 'error_string' => STRING)
    */
    static public function api_method__get_2s_blacklists_db($api_key, $do_check = true){
        
        $request = array(
            'method_name' => '2s_blacklists_db',
            'auth_key' => $api_key,         
        );
        
        $result = self::api_send_request($request);
        $result = $do_check ? self::api_check_response($result, '2s_blacklists_db') : $result;
        
        return $result;
    }
    
    /**
     * Function gets access key automatically
     *
     * @param string website admin email
     * @param string website host
     * @param string website platform
     * @return type
     */
    static public function api_method__get_api_key($email, $host, $platform, $agent = null, $timezone = null, $language = null, $ip = null, $do_check = true)
    {       
        $request = array(
            'method_name'          => 'get_api_key',
            'product_name'         => 'antispam',
            'email'                => $email,
            'website'              => $host,
            'platform'             => $platform,
            'agent'                => $agent,           
            'timezone'             => $timezone,
            'http_accept_language' => !empty($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : null,
            'user_ip'              => $ip ? $ip : self::ip_get(array('real'), false),
        );
        
        $result = self::api_send_request($request);
        // $result = $do_check ? self::api_check_response($result, 'get_api_key') : $result;
        
        return $result;
    }
    
    /**
     * Function gets information about renew notice
     *
     * @param string api_key
     * @return type
     */
    static public function api_method__notice_validate_key($api_key, $do_check = true)
    {
        $request = array(
            'method_name' => 'notice_validate_key',
            'auth_key' => $api_key,     
        );
        
        $result = self::api_send_request($request);
        $result = $do_check ? self::api_check_response($result, 'notice_validate_key') : $result;
        
        return $result;
    }
    
    /**
     * Function gets information about renew notice
     *
     * @param string api_key
     * @return type
     */
    static public function api_method__notice_paid_till($api_key, $do_check = true)
    {
        $request = array(
            'method_name' => 'notice_paid_till',
            'auth_key' => $api_key,
        );
        
        $result = self::api_send_request($request);
        $result = $do_check ? self::api_check_response($result, 'notice_paid_till') : $result;
        
        return $result;
    }

    /**
     * Function gets spam report
     *
     * @param string website host
     * @param integer report days
     * @return type
     */
    static public function api_method__get_antispam_report($host, $period = 1)
    {
        $request=Array(
            'method_name' => 'get_antispam_report',
            'hostname' => $host,
            'period' => $period,
        );
        
        $result = self::api_send_request($request);
        // $result = $do_check ? self::api_check_response($result, 'get_antispam_report') : $result;
        
        return $result;
    }

    /**
     * Function gets information about account
     *
     * @param string api_key
     * @param string perform check flag
     * @return mixed (STRING || array('error' => true, 'error_string' => STRING))
     */
    static public function api_method__get_account_status($api_key, $do_check = true)
    {
        $request = array(
            'method_name' => 'get_account_status',
            'auth_key' => $api_key
        );
        
        $result = self::api_send_request($request);
        $result = $do_check ? self::api_check_response($result, 'get_account_status') : $result;
        
        return $result;
    }

    /**
     * Function gets spam statistics
     *
     * @param string website host
     * @param integer report days
     * @return type
     */
    static public function api_method__get_antispam_report_breif($api_key, $do_check = true)
    {
        
        $request = array(
            'method_name' => 'get_antispam_report_breif',
            'auth_key' => $api_key,     
        );
        
        $result = self::api_send_request($request);
        $result = $do_check ? self::api_check_response($result, 'get_antispam_report_breif') : $result;
        
        $tmp = array();
        for( $i = 0; $i < 7; $i++ )
            $tmp[ date( 'Y-m-d', time() - 86400 * 7 + 86400 * $i ) ] = 0;
        
        $result['spam_stat']    = array_merge( $tmp, isset($result['spam_stat']) ? $result['spam_stat'] : array() );
        $result['top5_spam_ip'] = isset($result['top5_spam_ip']) ? $result['top5_spam_ip'] : array();
        
        return $result;     
    }
    
    /**
     * Function gets spam report
     *
     * @param string website host
     * @param integer report days
     * @return type
     */
    static public function api_method__spam_check_cms($api_key, $data, $date = null, $do_check = true)
    {
        $request=Array(
            'method_name' => 'spam_check_cms',
            'auth_key' => $api_key,
            'data' => is_array($data) ? implode(',',$data) : $data,         
        );
        
        if($date) $request['date'] = $date;
        
        $result = self::api_send_request($request, self::URL, false, 6);
        $result = $do_check ? self::api_check_response($result, 'spam_check_cms') : $result;
        
        return $result;
    }
    
    /**
     * Function sends empty feedback for version comparison in Dashboard
     *
     * @param string api_key
     * @param string agent-version
     * @param bool perform check flag
     * @return mixed (STRING || array('error' => true, 'error_string' => STRING))
     */
    static public function api_method_send_empty_feedback($api_key, $agent, $do_check = true){
        
        $request = array(
            'method_name' => 'send_feedback',
            'auth_key' => $api_key,
            'feedback' => 0 . ':' . $agent,
        );
        
        $result = self::api_send_request($request);
        $result = $do_check ? self::api_check_response($result, 'send_feedback') : $result;
        
        return $result;
    }

    /**
     * Function sends raw request to API server
     *
     * @param string url of API server
     * @param array data to send
     * @param boolean is data have to be JSON encoded or not
     * @param integer connect timeout
     * @return type
     */
    static public function api_send_request($data, $url = self::URL, $isJSON = false, $timeout=3, $ssl = false)
    {   
        
        $result = null;
        $curl_error = false;
        
        $original_data = $data;
        
        if(!$isJSON){
            $data = http_build_query($data);
            $data = str_replace("&amp;", "&", $data);
        }else{
            $data = json_encode($data);
        }
        
        if (function_exists('curl_init') && function_exists('json_decode')){
        
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:'));
            
            if ($ssl === true) {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            }else{
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            }
            
            $result = curl_exec($ch);
            
            if($result === false){
                if($ssl === false){
                    return self::api_send_request($original_data, $url, $isJSON, $timeout, true);
                }
                $curl_error = curl_error($ch);
            }
            
            curl_close($ch);
            
        }else{
            $curl_error = 'CURL_NOT_INSTALLED';
        }
        
        if($curl_error){
            
            $opts = array(
                'http'=>array(
                    'method'  => "POST",
                    'timeout' => $timeout,
                    'content' => $data,
                )
            );
            $context = stream_context_create($opts);
            $result = @file_get_contents($url, 0, $context);
        }
        
        if(!$result && $curl_error)
            return json_encode(array('error' => true, 'error_string' => $curl_error));
        
        return $result;
    }

    /**
     * Function checks server response
     *
     * @param string result
     * @param string request_method
     * @return mixed (array || array('error' => true))
     */
    static public function api_check_response($result, $method_name = null)
    {   
        
        // Errors handling
        
        // Bad connection
        if(empty($result)){
            return array(
                'error' => true,
                'error_string' => 'CONNECTION_ERROR'
            );
        }
        
        // JSON decode errors
        $result = json_decode($result, true);
        if(empty($result)){
            return array(
                'error' => true,
                'error_string' => 'JSON_DECODE_ERROR'
            );
        }
        
        // cURL error
        if(!empty($result['error'])){
            return array(
                'error' => true,
                'error_string' => 'CONNECTION_ERROR: ' . $result['error_string'],
            );
        }
        
        // Server errors
        if($result && (isset($result['error_no']) || isset($result['error_message']))){
            return array(
                'error' => true,
                'error_string' => "SERVER_ERROR NO: {$result['error_no']} MSG: {$result['error_message']}",
                'error_no' => $result['error_no'],
                'error_message' => $result['error_message']
            );
        }
        
        // Pathces for different methods
        
        // mehod_name = notice_validate_key
        if($method_name == 'notice_validate_key' && isset($result['valid'])){
            return $result;
        }
        
        // Other methods
        if(isset($result['data']) && is_array($result['data'])){
            return $result['data'];
        }
    }
    
    static public function is_json($string)
    {
        return is_string($string) && is_array(json_decode($string, true)) ? true : false;
    }

    /* 
     * If Apache web server is missing then making
     * Patch for apache_request_headers() 
     */
    static function apache_request_headers(){
        
        $headers = array(); 
        foreach($_SERVER as $key => $val){
            if(preg_match('/\AHTTP_/', $key)){
                $server_key = preg_replace('/\AHTTP_/', '', $key);
                $key_parts = explode('_', $server_key);
                if(count($key_parts) > 0 and strlen($server_key) > 2){
                    foreach($key_parts as $part_index => $part){
                        $key_parts[$part_index] = mb_strtolower($part);
                        $key_parts[$part_index][0] = strtoupper($key_parts[$part_index][0]);                    
                    }
                    $server_key = implode('-', $key_parts);
                }
                $headers[$server_key] = $val;
            }
        }
        return $headers;
    }   
}
class CleantalkSFW extends CleantalkHelper
{
	public $ip = 0;
	public $ip_str = '';
	public $ip_array = Array();
	public $ip_str_array = Array();
	public $blocked_ip = '';
	public $passed_ip = '';
	public $result = false;
	
	//Database variables
	private $table_prefix;
	private $db;
	private $query;
	private $db_result;
	private $db_result_data = array();
	
	public function __construct()
	{
		global $db;
		$this->table_prefix = TABLE_PREFIX;
		$this->db = $db;
	}
	
	public function unversal_query($query, $straight_query = false)
	{
		if($straight_query)
			$this->db_result = $this->db->query($query);
		else
			$this->query = $query;
	}
	
	public function unversal_fetch()
	{
		$this->db_result_data = $this->db->fetch_field($this->query);
	}
	
	public function unversal_fetch_all()
	{
		$this->db_result_data = $this->db->fetch_array($this->query);
	}
	
	
	/*
	*	Getting arrays of IP (REMOTE_ADDR, X-Forwarded-For, X-Real-Ip, Cf_Connecting_Ip)
	*	reutrns array('remote_addr' => 'val', ['x_forwarded_for' => 'val', ['x_real_ip' => 'val', ['cloud_flare' => 'val']]])
	*/
	static public function ip_get($ips_input = array('real', 'remote_addr', 'x_forwarded_for', 'x_real_ip', 'cloud_flare'), $v4_only = true){
		
		$result = (array)parent::ip_get($ips_input, $v4_only);
		
		$result = !empty($result) ? $result : array();
		
		if(isset($_GET['sfw_test_ip'])){
			if(self::ip_validate($_GET['sfw_test_ip']) !== false)
				$result['sfw_test'] = $_GET['sfw_test_ip'];
		}
		
		return $result;
		
	}
	
	/*
	*	Checks IP via Database
	*/
	public function check_ip(){
		
		foreach($this->ip_array as $current_ip){
		
			$query = "SELECT 
				COUNT(network) AS cnt
				FROM ".$this->table_prefix."cleantalk_sfw
				WHERE network = ".sprintf("%u", ip2long($current_ip))." & mask";
			$this->unversal_query($query);
			$this->unversal_fetch();
			
			if($this->db_result_data['cnt']){
				$this->result = true;
				$this->blocked_ip = $current_ip;
			}else{
				$this->passed_ip = $current_ip;
			}
		}
	}
		
	/*
	*	Add entry to SFW log
	*/
	public function sfw_update_logs($ip, $result){
		
		if($ip === NULL || $result === NULL){
			return;
		}
		
		$blocked = ($result == 'blocked' ? ' + 1' : '');
		$time = time();

		$query = "INSERT INTO ".$this->table_prefix."cleantalk_sfw_logs
		SET 
			ip = '$ip',
			all_entries = 1,
			blocked_entries = 1,
			entries_timestamp = '".intval($time)."'
		ON DUPLICATE KEY 
		UPDATE 
			all_entries = all_entries + 1,
			blocked_entries = blocked_entries".strval($blocked).",
			entries_timestamp = '".intval($time)."'";

		$this->unversal_query($query,true);
	}
	
	/*
	* Updates SFW local base
	* 
	* return mixed true || array('error' => true, 'error_string' => STRING)
	*/
	public function sfw_update($ct_key){
		
		$result = self::api_method__get_2s_blacklists_db($ct_key);
		
		if(empty($result['error'])){
			
			$this->unversal_query("TRUNCATE TABLE ".$this->table_prefix."cleantalk_sfw",true);
						
			// Cast result to int
			foreach($result as $value){
				$value[0] = intval($value[0]);
				$value[1] = intval($value[1]);
			} unset($value);
			
			$query="INSERT INTO ".$this->table_prefix."cleantalk_sfw VALUES ";
			for($i=0, $arr_count = count($result); $i < $arr_count; $i++){
				if($i == count($result)-1){
					$query.="(".$result[$i][0].",".$result[$i][1].")";
				}else{
					$query.="(".$result[$i][0].",".$result[$i][1]."), ";
				}
			}
			$this->unversal_query($query,true);
			
			return true;
			
		}else{
			return $result;
		}
	}
	
	/*
	* Sends and wipe SFW log
	* 
	* returns mixed true || array('error' => true, 'error_string' => STRING)
	*/
	public function send_logs($ct_key){
		
		//Getting logs
		$query = "SELECT * FROM ".$this->table_prefix."cleantalk_sfw_logs";
		$this->unversal_query($query);
		$this->unversal_fetch_all();
		
		if(count($this->db_result_data)){
			
			//Compile logs
			$data = array();
			foreach($this->db_result_data as $key => $value){
				$data[] = array(trim($value['ip']), $value['all_entries'], $value['all_entries']-$value['blocked_entries'], $value['entries_timestamp']);
			}
			unset($key, $value);
			
			//Sending the request
			$result = self::api_method__sfw_logs($ct_key, $data);
			
			//Checking answer and deleting all lines from the table
			if(empty($result['error'])){
				if($result['rows'] == count($data)){
					$this->unversal_query("TRUNCATE TABLE ".$this->table_prefix."cleantalk_sfw_logs",true);
					return true;
				}
			}else{
				return $result;
			}
				
		}else{
			return array('error' => true, 'error_string' => 'NO_LOGS_TO_SEND');
		}
	}
	
	/*
	* Shows DIE page
	* 
	* Stops script executing
	*/	
	public function sfw_die($api_key, $cookie_prefix = '', $cookie_domain = ''){
		
		$sfw_die_page = '<!doctype html>

		<html lang="en">
		<head>
		    <meta charset="utf-8">
		    <meta name="viewport" content="width=device-width, initial-scale=1">
		    <meta http-equiv="ñache-ñontrol" content="no-cache">
		    <meta http-equiv="ñache-ñontrol" content="private">
		    <meta http-equiv="ñache-ñontrol" content="max-age=0, must-revalidate">
		    <meta http-equiv="ñache-ñontrol" content="max-age=0, proxy-revalidate">
		    <meta http-equiv="expires" content="0" />
		    <meta http-equiv="expires" content="Tue, 01 Jan 1980 1:00:00 GMT" />
		    <meta http-equiv="pragma" content="no-cache" />

		    <title>Blacklisted</title> 

		  <!--[if lt IE 9]>
		  <script src="http://html5shiv.googlecode.com/svn/trunk/html5.js"></script>
		  <![endif]-->
		<style>
		    html{font-size: 10pt;font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;background: #f1f1f1;}
		    h1{text-align:center}
		    a:hover,
		    a:active {
		            color: #00a0d2;
		    }
		    h1.main{margin-top: 1em;margin-bottom: 3em;}
		    div.container {text-align:center;
		                background: #fff;
		            color: #444;
		            margin: 2em auto;
		            padding: 1em 2em;
		            max-width: 700px;
		            -webkit-box-shadow: 0 1px 3px rgba(0,0,0,0.13);
		            box-shadow: 0 1px 3px rgba(0,0,0,0.13);}
		    div.container p.js_notice{width: 100%%; display: inline-block;}
		    div.footer {color: #666; position: absolute; bottom: 1em; text-align: center; width: 99%;}
		    div.footer a {color: #666; vertical-align:bottom; text-align: center;}
		    div#js_passed {display:none;}
		    @media (max-width: 600px) {
		    }
		</style>
		<script>
		var reload_timeout = 3000;
		function set_spamFireWallCookie(cookie_name, cookie_value) {
		    document.cookie = cookie_name + "=" + escape(cookie_value) + "; path=/;";
		    return null;
		}
		function get_current_url() {
		    document.write(window.location.href);
		    return null;
		}
		</script>
		</head>

		<body>
		    <div class="container">
		        <h1 class="main">SpamFireWall is activated for your IP <a href="https://cleantalk.org/blacklists/{REMOTE_ADDRESS}" target="_blank">{REMOTE_ADDRESS}</a></h1>
		        
		        <div id="js_info"><br />To continue working with web site, please make sure that you have enabled JavaScript.</div>
		        
		        <div id="js_passed">
		        <h3>Please click bellow to pass protection</h3>
		        <a href="{REQUEST_URI}"><script>get_current_url();</script></a>
		        <br /><br /><br />
		        <p class="js_notice">Or you will be automatically redirected to the requested page after 3 seconds</p>
		        </div>
		    </div>
		    <div class="footer">
		    <a href="https://cleantalk.org" target="_blank">Anti-Spam by CleanTalk</a>
		    </div>
		    <script type="text/javascript">
		        document.getElementById("js_info").style.display = "none";
		        document.getElementById("js_passed").style.display = "block";
		        set_spamFireWallCookie("ct_sfw_pass_key","{SFW_COOKIE}");
		        set_spamFireWallCookie("ct_sfw_passed","1");
		        setTimeout(function(){
		            window.location.reload(1);
		        }, reload_timeout);
		    </script>
		</body>
		</html>';
		// Service info
		$sfw_die_page = str_replace('{REMOTE_ADDRESS}', $this->blocked_ip, $sfw_die_page);
		$sfw_die_page = str_replace('{REQUEST_URI}', $_SERVER['REQUEST_URI'], $sfw_die_page);
		$sfw_die_page = str_replace('{SFW_COOKIE}', md5($this->blocked_ip.$api_key), $sfw_die_page);
		
		// Headers
		if(headers_sent() === false){
			header('Expires: '.date(DATE_RFC822, mktime(0, 0, 0, 1, 1, 1971)));
			header('Cache-Control: no-store, no-cache, must-revalidate');
			header('Cache-Control: post-check=0, pre-check=0', FALSE);
			header('Pragma: no-cache');
			header("HTTP/1.0 403 Forbidden");
			$sfw_die_page = str_replace('{GENERATED}', "", $sfw_die_page);
		}else{
			$sfw_die_page = str_replace('{GENERATED}', "<h2 class='second'>The page was generated at&nbsp;".date("D, d M Y H:i:s")."</h2>",$sfw_die_page);
		}
		
		die($sfw_die_page);
		
	}
}
