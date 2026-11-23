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
        if (is_array($file_urls)) {
            $file_urls = array_values(array_filter(array_map('trim', $file_urls), 'strlen'));
            foreach ($file_urls as $file_url) {
                if (!antispam_by_cleantalk_is_cleantalk_file_url($file_url)) {
                    return array('error' => 'SFW_UPDATE_UNTRUSTED_FILE_URL');
                }
            }
        }

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
                            'spbc_remote_call_token'  => antispam_by_cleantalk_remote_token(),
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
    $access_key = trim($mybb->settings['antispam_by_cleantalk_accesskey']);

    // A pass cookie belongs to the visitor IP. It must not hide an administrator's SFW test.
    if (!$sfw->test) {
        foreach($sfw->ip_array as $origin => $value)
        {
            if ($origin === 'sfw_test') {
                continue;
            }
            $expected = md5($value . $access_key);
            $cookie = isset($_COOKIE['ct_sfw_pass_key']) ? (string)$_COOKIE['ct_sfw_pass_key'] : '';
            if($cookie !== '' && strlen($cookie) === strlen($expected) && hash_equals($expected, $cookie))
            {
                $is_sfw_check=false;
                if(isset($_COOKIE['ct_sfw_passed']))
                {
                    @setcookie ('ct_sfw_passed'); //Deleting cookie
                    $sfw->sfw_update_logs($value, 'passed');
                }
            }
        } unset($origin, $value);
    }

    if($is_sfw_check)
    {
        $sfw->check_ip();

        if( $sfw->pass === false || $sfw->test )
        {
            if ($sfw->pass === false && !empty($sfw->blocked_ips)) {
                $blocked = reset($sfw->blocked_ips);
                if (!empty($blocked['ip'])) {
                    $sfw->sfw_update_logs($blocked['ip'], 'blocked');
                }
            }
            $sfw->sfw_die($access_key);
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
        if (!is_array($cookie_test) || !isset($cookie_test['cookies_names'], $cookie_test['check_value']) || !is_array($cookie_test['cookies_names'])) {
            return 0;
        }

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

function antispam_by_cleantalk_is_enabled()
{
    global $mybb;

    return isset($mybb->settings['antispam_by_cleantalk_enabled'])
        && (string)$mybb->settings['antispam_by_cleantalk_enabled'] === '1';
}

function antispam_by_cleantalk_is_admin()
{
    global $mybb;

    return !empty($mybb->usergroup['cancp']);
}

/**
 * Public browser value. It proves that JavaScript ran and is not the remote-call password.
 */
function antispam_by_cleantalk_js_value()
{
    global $mybb;

    $key = isset($mybb->settings['antispam_by_cleantalk_accesskey'])
        ? trim($mybb->settings['antispam_by_cleantalk_accesskey'])
        : '';

    return $key === '' ? '' : md5($key);
}

/**
 * Password for CleanTalk remote calls. Derived from the access key and board URL
 * so a value printed in page HTML cannot authorize a call.
 */
function antispam_by_cleantalk_remote_token()
{
    global $mybb;

    $key = isset($mybb->settings['antispam_by_cleantalk_accesskey'])
        ? trim($mybb->settings['antispam_by_cleantalk_accesskey'])
        : '';
    $url = isset($mybb->settings['bburl']) ? rtrim(trim($mybb->settings['bburl']), '/') : '';
    if ($key === '' || $url === '') {
        return '';
    }

    return md5($key . $url);
}

/**
 * User-supplied remote-call delay, capped the same way as the WordPress plugin.
 */
function antispam_by_cleantalk_remote_delay($value)
{
    if ($value === null || $value === '' || !is_numeric($value)) {
        return 0;
    }
    $delay = (int)$value;
    if ($delay < 0) {
        return 0;
    }
    if ($delay > 10) {
        return 10;
    }

    return $delay;
}

/**
 * SpamFireWall archives are accepted only from CleanTalk HTTPS hosts.
 */
function antispam_by_cleantalk_is_cleantalk_file_url($url)
{
    $parts = parse_url(trim((string)$url));
    if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
        return false;
    }
    if (strcasecmp($parts['scheme'], 'https') !== 0) {
        return false;
    }
    if (isset($parts['port']) && (int)$parts['port'] !== 443) {
        return false;
    }
    $host = strtolower($parts['host']);
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        return false;
    }
    if ($host !== 'cleantalk.org' && substr($host, -strlen('.cleantalk.org')) !== '.cleantalk.org') {
        return false;
    }

    return true;
}

function antispam_by_cleantalk_html($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/**
 * Local path and query for the block page, without the parameters that force the page.
 */
function antispam_by_cleantalk_safe_request_path($uri)
{
    $parts = parse_url((string)$uri);
    $path = (is_array($parts) && !empty($parts['path']) && is_string($parts['path'])) ? $parts['path'] : '/';
    if ($path === '' || $path[0] !== '/' || strpos($path, '\\') !== false || strpos($path, '//') === 0) {
        $path = '/';
    }
    $query = array();
    if (is_array($parts) && !empty($parts['query'])) {
        parse_str($parts['query'], $query);
        unset($query['sfw_test_ip'], $query['debug']);
    }
    $built = $path;
    if ($query) {
        $built .= '?' . http_build_query($query);
    }

    return antispam_by_cleantalk_html($built);
}

function antispam_by_cleantalk_post_info($comment_type)
{
    global $mybb;

    $post_url = '';
    if (!empty($mybb->settings['bburl'])) {
        $post_url = rtrim($mybb->settings['bburl'], '/');
        if (!empty($_SERVER['REQUEST_URI'])) {
            $post_url .= $_SERVER['REQUEST_URI'];
        }
    }

    return json_encode(array(
        'comment_type' => $comment_type,
        'post_url' => $post_url,
    ));
}

function antispam_by_cleantalk_js_test()
{
    $expected = antispam_by_cleantalk_js_value();
    $cookie = isset($_COOKIE['ct_checkjs']) ? (string)$_COOKIE['ct_checkjs'] : '';

    return ($expected !== '' && strlen($cookie) === strlen($expected) && hash_equals($expected, $cookie)) ? 1 : 0;
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
function antispam_by_cleantalk_exclusion_path($value)
{
    $value = trim(html_entity_decode((string)$value, ENT_QUOTES, 'UTF-8'));
    if ($value === '') {
        return '';
    }

    if (preg_match('#\A[a-z][a-z0-9+.-]*://#i', $value)) {
        $parts = parse_url($value);
        $path = (is_array($parts) && !empty($parts['path'])) ? $parts['path'] : '/';
    } elseif ($value[0] !== '/' && strpos($value, '/') !== false) {
        $host = strstr($value, '/', true);
        if (strpos($host, '.') !== false || strpos($host, ':') !== false) {
            $path = substr($value, strlen($host));
        } else {
            $path = $value;
        }
    } else {
        $path = $value;
    }

    $path = preg_replace('/[?#].*$/', '', $path);
    if ($path === null || $path === '') {
        return '/';
    }

    return $path;
}

function antispam_by_cleantalk_check_exclusions_url()
{
    global $mybb;
    if ( empty( $mybb->settings['antispam_by_cleantalk_exclusions_url'] ) ) {
        return false;
    }

    $request = antispam_by_cleantalk_exclusion_path(Server::get('REQUEST_URI'));
    $request_path = '/' . ltrim($request, '/');
    $exclusions = explode( ',', $mybb->settings['antispam_by_cleantalk_exclusions_url'] );
    foreach ( $exclusions as $exclusion ) {
        $path = antispam_by_cleantalk_exclusion_path($exclusion);
        if ($path === '' || $path === '/') {
            continue;
        }
        $exclusion_path = '/' . ltrim($path, '/');
        if ( stripos( $request_path, $exclusion_path ) !== false ){
            return true;
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
    $ct->server_url = 'https://moderate.cleantalk.org';
    $ct->stay_on_server = true;

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
        'REFFERRER' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '',
        'USER_AGENT' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
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

    global $mybb, $lang;

    if ($mybb->get_input('ajax', MyBB::INPUT_INT)){
        header("Content-type: application/json; charset={$lang->settings['charset']}");
        echo json_encode(array("errors" => array($comment)));
        exit;
    }
    $cleantalk_message = nl2br(htmlspecialchars_uni((string)$comment));
    $template = file_get_contents(__DIR__ . '/blocked_message.html');
    $errorpage = str_replace('{CLEANTALK_MESSAGE}', $cleantalk_message, $template);
    error($errorpage);

}
