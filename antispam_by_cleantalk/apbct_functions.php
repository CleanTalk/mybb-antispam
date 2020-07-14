<?php

use CleantalkAP\Common\Helper;
use CleantalkAP\Mybb\Cleantalk;
use CleantalkAP\Mybb\CleantalkRequest;
use CleantalkAP\Mybb\SFW;
use CleantalkAP\Variables\Server;

function antispam_by_cleantalk_sfw_update($api_key = '', $immediate = false )
{
    global $mybb;

    $api_key = trim($mybb->settings['antispam_by_cleantalk_accesskey']) != '' ? trim($mybb->settings['antispam_by_cleantalk_accesskey']) : $api_key;

    if( ! empty( $api_key ) ) {

        $sfw = new SFW();

        $file_urls = isset($_GET['file_urls']) ? urldecode( $_GET['file_urls'] ) : null;
        $file_urls = isset($file_urls) ? explode(',', $file_urls) : null;

        if( ! $file_urls ){

            $result = $sfw->sfw_update( $api_key, null, $immediate );

            return ! empty( $result['error'] )
                ? $result
                : true;

        }elseif( is_array( $file_urls ) && count( $file_urls ) ){

            $result = $sfw->sfw_update($api_key, $file_urls[0], $immediate);

            if( empty( $result['error'] ) ){

                array_shift( $file_urls );

                if ( count( $file_urls ) ) {
                    Helper::http__request(
                        $mybb->settings['bburl'],
                        array(
                            'spbc_remote_call_token'  => md5($api_key),
                            'spbc_remote_call_action' => 'sfw_update',
                            'plugin_name'             => 'apbct',
                            'file_urls'               => implode(',', $file_urls),
                        ),
                        array('get', 'async')
                    );
                } else {
                    return $result;
                }
            }else
                return $result;
        }else
            return array('error' => 'SFW_UPDATE WRONG_FILE_URLS');
    }

    return array('error' => 'API_KEY_IS_EMPTY');
}

function antispam_by_cleantalk_sfw_send_logs( $api_key = '' )
{
    global $mybb;

    $api_key = trim($mybb->settings['antispam_by_cleantalk_accesskey']) != '' ? trim($mybb->settings['antispam_by_cleantalk_accesskey']) : $api_key;

    if( ! empty( $api_key ) ) {

        $sfw = new SFW();
        $sfw->send_logs( $api_key );

    }

    return array('error' => 'API_KEY_IS_EMPTY');
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

        if( $sfw->pass === false )
        {
            $sfw->sfw_update_logs($sfw->blocked_ip, 'blocked');
            $sfw->sfw_die(trim($mybb->settings['antispam_by_cleantalk_accesskey']));
        }
    }

}


function antispam_by_cleantalk_setcookies()
{
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

function antispam_by_cleantalk_testcookies()
{
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
        'cookies_enabled' => antispam_by_cleantalk_testcookies(),
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