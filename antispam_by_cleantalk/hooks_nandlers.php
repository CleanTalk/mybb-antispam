<?php

use CleantalkAP\Common\API;
use CleantalkAP\Mybb\Cron;
use CleantalkAP\Mybb\RemoteCalls;
use CleantalkAP\Variables\Server;

/**
 * Hooked at 'admin_config_settings_change_commit'
 */
function savesettings_trigger()
{
    global $mybb,$db;
    $query = $db->simple_select('settinggroups', '*', "name='antispam_by_cleantalk'");
    $app = $db->fetch_array($query);

    if (isset($_POST['gid']) && $_POST['gid'] === $app['gid'])
    {
        require_once MYBB_ROOT . '/inc/adminfunctions_templates.php';

        $access_key = trim($mybb->settings['antispam_by_cleantalk_accesskey']);

        if ($access_key != '')
        {
            API::method__send_empty_feedback($access_key, ENGINE);

            if ($mybb->settings['antispam_by_cleantalk_sfw'] === '1')
            {
                $result = antispam_by_cleantalk_sfw_update( $access_key );
                if( ! empty( $result['error'] ) ) {
                    //@todo We have to implement errors handling
                    //error_log( 'CleanTalk sfw_update error: ' . $result['error'] );
                } else {
                    Cron::updateTask( 'sfw_update', 'antispam_by_cleantalk_sfw_update', 86400, time() + 86400 );
                }

                $result = antispam_by_cleantalk_sfw_send_logs( $access_key );
                if( ! empty( $result['error'] ) ) {
                    //@todo We have to implement errors handling
                    //error_log( 'CleanTalk sfw_send_logs error: ' . $result['error'] );
                } else {
                    Cron::updateTask( 'send_sfw_logs', 'antispam_by_cleantalk_sfw_send_logs', 3600, time() + 3600 );
                }


            }
        }
        $db->delete_query("templates", "title='footer' AND sid='1'");

        if ($mybb->settings['antispam_by_cleantalk_footerlink'] === '1')
            find_replace_templatesets("footer", '#'.preg_quote('{$auto_dst_detection}').'#', '<div id=\'cleantalk_footer_link\' style=\'width:100%;text-align:center;\'>MyBB spam blocked <a href=https://cleantalk.org/antispam-mybb>by CleanTalk.</a></div>
		        {$auto_dst_detection}',1);
    }

}


/**
 * General global hook
 * Hooked at 'global_start'
 */
function antispam_by_cleantalk_set_global()
{
    global $mybb;

    // Set cookies
    antispam_by_cleantalk_setcookies();

    $access_key = trim($mybb->settings['antispam_by_cleantalk_accesskey']);

    // SpamFireWall checking
    if ( $access_key != '' && $mybb->settings['antispam_by_cleantalk_sfw'] === '1' ) {
        // Run SFW except the remote calls and excluded URLs
        if( Server::get('REQUEST_METHOD') == 'GET' && ( ! RemoteCalls::check() || ! antispam_by_cleantalk_check_exclusions_url() ) ) {
            antispam_by_cleantalk_sfw_check();
        }
    }

    // Checking remote calls
    if ( RemoteCalls::check() ) {
        RemoteCalls::perform();
    }

    // Self cron
    if( ! defined('CT_CRON') || ( defined('CT_CRON' ) && CT_CRON !== true ) ){

        $ct_cron = new \CleantalkAP\Mybb\Cron();
        $ct_cron->checkTasks();

        if( ! empty( $ct_cron->tasks_to_run ) ){

            define('CT_CRON', true); // Letting know functions that they are running under CT_CRON
            $result = $ct_cron->runTasks();
            if( ! $result ) {
                //@todo We have to implement errors handling
            }
            unset($ct_cron);

        }
    }
}

/**
 * Adding JS to the page content
 * @param $contents
 * @return mixed
 */
function antispam_by_cleantalk_add_js($contents )
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
				if(ctMouseEventTimerFlag === true){
					var mouseDate = new Date();
					ctMouseData += "[" + Math.round(event.pageY) + "," + Math.round(event.pageX) + "," + Math.round(mouseDate.getTime() - ctTimeMs) + "],";
					ctMouseDataCounter++;
					ctMouseEventTimerFlag = false;
					if(ctMouseDataCounter >= 100)
						ctMouseStopData();
				}
			};
			
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
			};

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

/**
 * Checking message by API
 * @return void | bool
 */
function antispam_by_cleantalk_trigger()
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

/**
 * Checking registration by API
 * @return void | bool
 */
function antispam_by_cleantalk_regtrigger()
{
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

/**
 * Checking message by API
 * @return void | bool
 */
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
