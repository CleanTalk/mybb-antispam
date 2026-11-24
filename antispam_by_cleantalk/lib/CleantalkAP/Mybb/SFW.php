<?php

namespace CleantalkAP\Mybb;

use CleantalkAP\Common\API;
use CleantalkAP\Common\Helper;
use CleantalkAP\Variables\Get;
use CleantalkAP\Variables\Server;

class SFW
{
    /*
     * Select limit for logs.
     */
    const APBCT_WRITE_LIMIT = 5000;

    public $ip = 0;
    public $ip_str = '';
    public $ip_array = Array();
    public $ip_str_array = Array();
    public $blocked_ip = '';
    public $passed_ip = '';
    public $pass = true;
    public $test = false;

    /**
     * @var array of arrays array(origin => array(
    'ip'      => '192.168.0.1',
    'network' => '192.168.0.0',
    'mask'    => '24',
    'status'  => -1 (blocked) | 1 (passed)
    )
     */
    public $all_ips = array();

    /**
     * @var array of arrays array(origin => array(
    'ip'      => '192.168.0.1',
    )
     */
    public $passed_ips = array();

    /**
     * @var array of arrays array(origin => array(
    'ip'      => '192.168.0.1',
    'network' => '192.168.0.0',
    'mask'    => '24',
    )
     */
    public $blocked_ips = array();

    //Database variables
    private $table_prefix;
    private $data_table;
    private $db;
    private $query;
    private $db_result;
    private $db_result_data = array();

    //Debug
    public $debug;
    public $debug_data = '';

    public function __construct()
    {
        global $db;
        $this->table_prefix = TABLE_PREFIX;
        $this->data_table = $this->table_prefix . 'cleantalk_sfw';
        $this->db = $db;
        $this->debug = Get::get('debug') === '1'
            && function_exists('antispam_by_cleantalk_is_admin')
            && antispam_by_cleantalk_is_admin();
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
        $this->db_result_data = $this->db->fetch_array($this->db_result);
    }

    public function unversal_fetch_all()
    {
        while ($row = $this->db->fetch_array($this->db_result)){
            $this->db_result_data[] = $row;
        }
    }


    /*
    *	Getting arrays of IP (REMOTE_ADDR, X-Forwarded-For, X-Real-Ip, Cf_Connecting_Ip)
    *	reutrns array('remote_addr' => 'val', ['x_forwarded_for' => 'val', ['x_real_ip' => 'val', ['cloud_flare' => 'val']]])
    */
    public function ip_get($ips_input = array('real', 'remote_addr', 'x_forwarded_for', 'x_real_ip', 'cloud_flare'), $v4_only = true){

        $result = Helper::ip__get($ips_input, $v4_only);

        $result = !empty($result) ? array('real' => $result) : array();

        if( Get::get('sfw_test_ip') ){
            $test_ip = (string) Get::get('sfw_test_ip');
            if( Helper::ip__validate($test_ip) !== false ) {
                $result['sfw_test'] = $test_ip;
                $this->test = true;
            }
        }
        return $result;

    }

    /*
    *	Checks IP via Database
    */
    public function check_ip(){

        foreach($this->ip_array as $origin => $current_ip){

            $current_ip_v4 = sprintf("%u", ip2long($current_ip));
            for ( $needles = array(), $m = 6; $m <= 32; $m ++ ) {
                $mask      = sprintf( "%u", ip2long( long2ip( - 1 << ( 32 - (int) $m ) ) ) );
                $needles[] = bindec( decbin( $mask ) & decbin( $current_ip_v4 ) );
            }
            $needles = array_unique( $needles );

            $query = "SELECT
				network, mask, status
				FROM " . $this->data_table . "
				WHERE network IN (". implode( ',', $needles ) .") 
				AND	network = " . $current_ip_v4 . " & mask
				ORDER BY status DESC LIMIT 1;";

            $this->unversal_query($query,true);
            $this->unversal_fetch();

            if( ! empty( $this->db_result_data ) ){

                if ( 1 == $this->db_result_data['status'] ) {
                    // It is the White Listed network - will be passed.
                    $this->passed_ips[$origin] = array(
                        'ip'     => $current_ip,
                    );
                    $this->all_ips[$origin] = array(
                        'ip'     => $current_ip,
                        'status' => 1,
                    );
                    continue;
                } else {
                    $this->pass = false;
                    $this->blocked_ips[$origin] = array(
                        'ip'      => $current_ip,
                        'network' => long2ip($this->db_result_data['network']),
                        'mask'    => Helper::ip__mask__long_to_number($this->db_result_data['mask']),
                    );
                    $this->all_ips[$origin] = array(
                        'ip'      => $current_ip,
                        'network' => long2ip($this->db_result_data['network']),
                        'mask'    => Helper::ip__mask__long_to_number($this->db_result_data['mask']),
                        'status'  => -1,
                    );
                }

            }else{
                $this->passed_ips[$origin] = array(
                    'ip'     => $current_ip,
                );
                $this->all_ips[$origin] = array(
                    'ip'     => $current_ip,
                    'status' => 1,
                );
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
    public function sfw_update($ct_key, $file_url = null, $immediate = false){

        global $mybb;

        // Getting remote file name
        if(!$file_url){

            sleep(6);

            $result = API::method__get_2s_blacklists_db($ct_key, 'multifiles', '2_0');

            if(empty($result['error'])){

                if( !empty($result['file_url']) ){

                    if(!$this->open_remote_gz_allowed($result['file_url'])){
                        return array('error' => 'SFW_UPDATE_UNTRUSTED_FILE_URL');
                    }

                    if(Helper::http__request($result['file_url'], array(), array('get_code', 'dont_follow_redirects')) === 200) {

                        if(ini_get('allow_url_fopen')) {

                            $pattenrs = array();
                            $pattenrs[] = 'get';

                            if(!$immediate) $pattenrs[] = 'async';

                            // Clear SFW table
                            $this->unversal_query("TRUNCATE TABLE {$this->data_table};", true);
                            $this->unversal_query("SELECT COUNT(network) as cnt FROM {$this->data_table};", true); // Check if it is clear
                            $this->unversal_fetch();
                            if($this->db_result_data['cnt'] != 0){
                                $this->unversal_query("DELETE FROM {$this->data_table};" , true); // Truncate table
                                $this->unversal_query("SELECT COUNT(network) as cnt FROM {$this->data_table};", true); // Check if it is clear
                                $this->unversal_fetch();
                                if($this->db_result_data['cnt'] != 0){
                                    return array('error' => 'COULD_NOT_CLEAR_SFW_TABLE'); // throw an error
                                }
                            }

                            $gf = $this->open_remote_gz($result['file_url']);

                            if ($gf) {

                                $file_urls = array();

                                while( ! \gzeof($gf) )
                                    $file_urls[] = trim( \gzgets($gf, 1024) );

                                \gzclose($gf);

                                return Helper::http__request(
                                    $mybb->settings['bburl'],
                                    array(
                                        'spbc_remote_call_token'  => antispam_by_cleantalk_remote_token(),
                                        'spbc_remote_call_action' => 'sfw_update',
                                        'plugin_name'             => 'apbct',
                                        'file_urls'               => implode(',', $file_urls),
                                    ),
                                    $pattenrs
                                );
                            }else
                                return array('error' => 'COULD_NOT_OPEN_REMOTE_FILE_SFW');
                        }else
                            return array('error' => 'ERROR_ALLOW_URL_FOPEN_DISABLED');
                    }else
                        return array('error' => 'NO_FILE_URL_PROVIDED');
                }else
                    return array('error' => 'BAD_RESPONSE');
            }else
                return $result;
        }else{

            if(!$this->open_remote_gz_allowed($file_url)){
                return array('error' => 'SFW_UPDATE_UNTRUSTED_FILE_URL');
            }

            if(Helper::http__request($file_url, array(), array('get_code', 'dont_follow_redirects')) === 200){ // Check if it's there

                $gf = $this->open_remote_gz($file_url);

                if($gf){

                    if( ! \gzeof($gf) ){

                        for( $count_result = 0; ! \gzeof($gf); ){

                            $query = "INSERT INTO ".$this->data_table." VALUES %s";

                            for($i=0, $values = array(); self::APBCT_WRITE_LIMIT !== $i && ! \gzeof($gf); $i++, $count_result++){

                                $entry = trim( \gzgets($gf, 1024) );

                                if(empty($entry)) continue;

                                $entry = explode(',', $entry);

                                // Cast result to int
                                $ip   = preg_replace('/[^\d]*/', '', $entry[0]);
                                $mask = preg_replace('/[^\d]*/', '', $entry[1]);
                                $private = isset($entry[2]) ? $entry[2] : 0;

                                if(!$ip || !$mask) continue;

                                $values[] = '('. $ip .','. $mask .','. $private .')';

                            }

                            if(!empty($values)){
                                $query = sprintf($query, implode(',', $values).';');
                                $this->unversal_query( $query, true );
                            }

                        }
                        \gzclose($gf);
                        return $count_result;

                    }else
                        return array('error' => 'ERROR_GZ_EMPTY');
                }else
                    return array('error' => 'ERROR_OPEN_GZ_FILE');
            }else
                return array('error' => 'NO_REMOTE_FILE_FOUND');
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
        $this->unversal_query($query,true);
        $this->unversal_fetch_all();

        if(count($this->db_result_data)){

            //Compile logs
            $data = array();
            foreach($this->db_result_data as $key => $value){
                $data[] = array(trim($value['ip']), $value['all_entries'], $value['all_entries']-$value['blocked_entries'], $value['entries_timestamp']);
            }
            unset($key, $value);

            //Sending the request
            $result = API::method__sfw_logs($ct_key, $data);

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
    public function sfw_die( $api_key, $cookie_prefix = '', $cookie_domain = '', $test = false ){

        if( file_exists( MYBB_ROOT . 'inc/plugins/antispam_by_cleantalk/die_page.html' ) ) {

            $sfw_die_page = file_get_contents(MYBB_ROOT . 'inc/plugins/antispam_by_cleantalk/die_page.html');

            if (headers_sent() === false) {
                header('Expires: ' . date(DATE_RFC822, mktime(0, 0, 0, 1, 1, 1971)));
                header('Cache-Control: no-store, no-cache, must-revalidate');
                header('Cache-Control: post-check=0, pre-check=0', FALSE);
                header('Pragma: no-cache');
                header("HTTP/1.0 403 Forbidden");
            }

            // Service info
            /*$sfw_die_page = str_replace('{REMOTE_ADDRESS}', $this->blocked_ip, $sfw_die_page);
            $sfw_die_page = str_replace('{REQUEST_URI}', $_SERVER['REQUEST_URI'], $sfw_die_page);
            $sfw_die_page = str_replace('{SFW_COOKIE}', md5($this->blocked_ip . $api_key), $sfw_die_page);*/

            // Translation
            $real_ip = isset($this->all_ips['real']['ip']) ? $this->all_ips['real']['ip'] : '';
            $test_ip = isset($this->all_ips['sfw_test']['ip']) ? $this->all_ips['sfw_test']['ip'] : '';
            $real_status = isset($this->all_ips['real']['status']) ? $this->all_ips['real']['status'] : 1;
            $test_status = isset($this->all_ips['sfw_test']['status']) ? $this->all_ips['sfw_test']['status'] : 1;
            $blocked_ip = '';
            if ($this->blocked_ips) {
                $blocked = reset($this->blocked_ips);
                $blocked_ip = isset($blocked['ip']) ? $blocked['ip'] : '';
            }
            $pass_cookie = (!$this->test && $blocked_ip !== '') ? md5($blocked_ip . $api_key) : '';
            if (!preg_match('/\A[a-f0-9]{32}\z/', $pass_cookie)) {
                $pass_cookie = '';
            }
            $cookie_prefix = preg_match('/\A[A-Za-z0-9_-]*\z/', (string)$cookie_prefix) ? (string)$cookie_prefix : '';
            $cookie_domain = preg_match('/\A[A-Za-z0-9.-]*\z/', (string)$cookie_domain) ? (string)$cookie_domain : '';

            $sfw_die_page = str_replace('{SFW_DIE_NOTICE_IP}',              'SpamFireWall is activated for your IP ', $sfw_die_page);
            $sfw_die_page = str_replace('{SFW_DIE_MAKE_SURE_JS_ENABLED}',   'To continue working with web site, please make sure that you have enabled JavaScript.', $sfw_die_page);
            $sfw_die_page = str_replace('{SFW_DIE_CLICK_TO_PASS}',          'Please click below to pass protection,', $sfw_die_page);
            $sfw_die_page = str_replace(
                '{SFW_DIE_YOU_WILL_BE_REDIRECTED}',
                $this->test ? '' : 'Or you will be automatically redirected to the requested page after 3 seconds.',
                $sfw_die_page
            );
            $sfw_die_page = str_replace('{CLEANTALK_TITLE}',                'Antispam by CleanTalk', $sfw_die_page);
            $sfw_die_page = str_replace('{TEST_TITLE}',                     ($this->test ? 'This is the testing page for SpamFireWall' : ''), $sfw_die_page);
            $sfw_die_page = str_replace('{REAL_IP__HEADER}', $this->test ? 'Real IP:' : '', $sfw_die_page);
            $sfw_die_page = str_replace('{TEST_IP__HEADER}', $this->test ? 'Test IP:' : '', $sfw_die_page);
            $sfw_die_page = str_replace('{TEST_IP}', antispam_by_cleantalk_html($this->test ? $test_ip : ''), $sfw_die_page);
            $sfw_die_page = str_replace('{REAL_IP}', antispam_by_cleantalk_html($this->test ? $real_ip : ''), $sfw_die_page);
            $sfw_die_page = str_replace('{TEST_IP_BLOCKED}', $this->test ? ($test_status == 1 ? 'Passed' : 'Blocked') : '', $sfw_die_page);
            $sfw_die_page = str_replace('{REAL_IP_BLOCKED}', $this->test ? ($real_status == 1 ? 'Passed' : 'Blocked') : '', $sfw_die_page);
            $sfw_die_page = str_replace('{REMOTE_ADDRESS}', antispam_by_cleantalk_html($blocked_ip), $sfw_die_page);
            $sfw_die_page = str_replace('{REQUEST_URI}', antispam_by_cleantalk_safe_request_path(Server::get('REQUEST_URI')), $sfw_die_page);
            $sfw_die_page = str_replace('{COOKIE_PREFIX}', $cookie_prefix, $sfw_die_page);
            $sfw_die_page = str_replace('{COOKIE_DOMAIN}', $cookie_domain, $sfw_die_page);
            $sfw_die_page = str_replace('{SERVICE_ID}', '', $sfw_die_page);
            $sfw_die_page = str_replace('{HOST}', antispam_by_cleantalk_html(Server::get('HTTP_HOST')), $sfw_die_page);
            $sfw_die_page = str_replace('{SFW_COOKIE}', $pass_cookie, $sfw_die_page);
            $sfw_die_page = str_replace('{RUN_REDIRECT}', $this->test ? 'false' : 'true', $sfw_die_page);
            $sfw_die_page = str_replace('{SET_PASS_COOKIE}', ($pass_cookie !== '' ? 'true' : 'false'), $sfw_die_page);

            if($this->debug){
                $debug = '<h1>IP and Networks</h1><pre>'
                    . antispam_by_cleantalk_html(var_export($this->all_ips, true))
                    . '</pre><h1>Blocked IPs</h1><pre>'
                    . antispam_by_cleantalk_html(var_export($this->blocked_ips, true))
                    . '</pre><h1>Passed IPs</h1><pre>'
                    . antispam_by_cleantalk_html(var_export($this->passed_ips, true))
                    . '</pre>';
            }else
                $debug = '';

            $sfw_die_page = str_replace( "{DEBUG}", $debug, $sfw_die_page );
            $sfw_die_page = str_replace('{GENERATED}', "<p>The page was generated at&nbsp;".date("D, d M Y H:i:s")."</p>",$sfw_die_page);

            die($sfw_die_page);
        } else {
            if (headers_sent() === false) {
                header("HTTP/1.0 403 Forbidden");
                die( "IP BLACKLISTED" );
            }
        }

    }

    private function open_remote_gz_allowed($url)
    {
        return function_exists('antispam_by_cleantalk_is_cleantalk_file_url')
            && antispam_by_cleantalk_is_cleantalk_file_url($url);
    }

    /**
     * Open a CleanTalk gzip archive without following redirects.
     *
     * @param string $url
     * @return resource|false
     */
    private function open_remote_gz($url)
    {
        if (!$this->open_remote_gz_allowed($url)) {
            return false;
        }

        $previous = stream_context_get_options(stream_context_get_default());
        stream_context_set_default(array(
            'http' => array(
                'follow_location' => 0,
                'timeout' => 20,
            ),
            'ssl' => array(
                'verify_peer' => true,
                'verify_peer_name' => true,
            ),
        ));
        $handle = gzopen($url, 'rb');
        $restore = $previous;
        if (!isset($restore['http']['follow_location'])) {
            $restore['http']['follow_location'] = 1;
        }
        if (!isset($restore['http']['timeout'])) {
            $restore['http']['timeout'] = (float) ini_get('default_socket_timeout');
        }
        stream_context_set_default($restore);

        return $handle;
    }

}
