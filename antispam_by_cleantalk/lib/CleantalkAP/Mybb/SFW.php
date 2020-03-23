<?php

namespace CleantalkAP\Mybb;

use CleantalkAP\Common\API;
use CleantalkAP\Common\Helper;
use CleantalkAP\Variables\Get;
use CleantalkAP\Variables\Server;

class SFW
{

    public $ip = 0;
    public $ip_str = '';
    public $ip_array = Array();
    public $ip_str_array = Array();
    public $blocked_ip = '';
    public $passed_ip = '';
    public $result = false;
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
        $this->db = $db;
        $this->debug = Get::get('debug') === '1' ? true : false;
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

        $result = (array)Helper::ip__get($ips_input, $v4_only);

        $result = !empty($result) ? $result : array();

        if( Get::get('sfw_test_ip') ){
            if( Helper::ip__validate(Get::get('sfw_test_ip')) !== false ) {
                $result['sfw_test'] = Get::get('sfw_test_ip');
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

            $query = "SELECT 
				COUNT(network) AS cnt, network, mask
				FROM ".$this->table_prefix."cleantalk_sfw
				WHERE network = ".sprintf("%u", ip2long($current_ip))." & mask";
            $this->unversal_query($query,true);
            $this->unversal_fetch();

            if($this->db_result_data['cnt']){
                $this->pass = false;
                $this->blocked_ips[$origin] = array(
                    'ip'      => $current_ip,
                    'network' => long2ip($this->db->result_data['network']),
                    'mask'    => Helper::ip__mask__long_to_number($this->db->result_data['mask']),
                );
                $this->all_ips[$origin] = array(
                    'ip'      => $current_ip,
                    'network' => long2ip($this->db->result_data['network']),
                    'mask'    => Helper::ip__mask__long_to_number($this->db->result_data['mask']),
                    'status'  => -1,
                );
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
    public function sfw_update($ct_key){

        $result = API::method__get_2s_blacklists_db($ct_key);

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
            $request_uri  = Server::get( 'REQUEST_URI' );
            $sfw_die_page = str_replace('{SFW_DIE_NOTICE_IP}',              'SpamFireWall is activated for your IP ', $sfw_die_page);
            $sfw_die_page = str_replace('{SFW_DIE_MAKE_SURE_JS_ENABLED}',   'To continue working with web site, please make sure that you have enabled JavaScript.', $sfw_die_page);
            $sfw_die_page = str_replace('{SFW_DIE_CLICK_TO_PASS}',          'Please click below to pass protection,', $sfw_die_page);
            $sfw_die_page = str_replace('{SFW_DIE_YOU_WILL_BE_REDIRECTED}', sprintf('Or you will be automatically redirected to the requested page after %d seconds.', 1), $sfw_die_page);
            $sfw_die_page = str_replace('{CLEANTALK_TITLE}',                'Antispam by CleanTalk', $sfw_die_page);
            $sfw_die_page = str_replace('{TEST_TITLE}',                     ($this->test ? 'This is the testing page for SpamFireWall' : ''), $sfw_die_page);

            if($this->test){
                $sfw_die_page = str_replace('{REAL_IP__HEADER}', 'Real IP:', $sfw_die_page);
                $sfw_die_page = str_replace('{TEST_IP__HEADER}', 'Test IP:', $sfw_die_page);
                $sfw_die_page = str_replace('{TEST_IP}', $this->all_ips['sfw_test']['ip'], $sfw_die_page);
                $sfw_die_page = str_replace('{REAL_IP}', $this->all_ips['real']['ip'],     $sfw_die_page);
                $sfw_die_page = str_replace('{TEST_IP_BLOCKED}', $this->all_ips['sfw_test']['status'] == 1 ? 'Passed' : 'Blocked', $sfw_die_page);
                $sfw_die_page = str_replace('{REAL_IP_BLOCKED}', $this->all_ips['real']['status'] == 1 ? 'Passed' : 'Blocked',     $sfw_die_page);
            }else{
                $sfw_die_page = str_replace('{REAL_IP__HEADER}', '', $sfw_die_page);
                $sfw_die_page = str_replace('{TEST_IP__HEADER}', '', $sfw_die_page);
                $sfw_die_page = str_replace('{TEST_IP}', '', $sfw_die_page);
                $sfw_die_page = str_replace('{REAL_IP}', '', $sfw_die_page);
                $sfw_die_page = str_replace('{TEST_IP_BLOCKED}', '', $sfw_die_page);
                $sfw_die_page = str_replace('{REAL_IP_BLOCKED}', '', $sfw_die_page);
            }

            $sfw_die_page = str_replace('{REMOTE_ADDRESS}', $this->blocked_ips ? $this->blocked_ips[key($this->blocked_ips)]['ip'] : '', $sfw_die_page);

            // Service info
            $sfw_die_page = str_replace('{REQUEST_URI}',    $request_uri,                    $sfw_die_page);
            $sfw_die_page = str_replace('{COOKIE_PREFIX}',  $cookie_prefix,                  $sfw_die_page);
            $sfw_die_page = str_replace('{COOKIE_DOMAIN}',  $cookie_domain,                  $sfw_die_page);
            $sfw_die_page = str_replace('{SERVICE_ID}',     '',                      $sfw_die_page);
            $sfw_die_page = str_replace('{HOST}',           Server::get( 'HTTP_HOST' ),      $sfw_die_page);

            $sfw_die_page = str_replace(
                '{SFW_COOKIE}',
                $this->test
                    ? md5( $this->all_ips['sfw_test']['ip'] . $api_key )
                    : md5( current(end($this->blocked_ips)) . $api_key ),
                $sfw_die_page
            );

            if($this->debug){
                $debug = '<h1>IP and Networks</h1>'
                    . var_export($this->all_ips, true)
                    .'<h1>Blocked IPs</h1>'
                    . var_export($this->passed_ips, true)
                    .'<h1>Passed IPs</h1>'
                    . var_export($this->blocked_ips, true)
                    . '<h1>Headers</h1>'
                    . var_export(apache_request_headers(), true)
                    . '<h1>REMOTE_ADDR</h1>'
                    . var_export(Server::get( 'REMOTE_ADDR' ), true)
                    . '<h1>SERVER_ADDR</h1>'
                    . var_export(Server::get( 'REMOTE_ADDR' ), true)
                    . '<h1>IP_ARRAY</h1>'
                    . var_export($this->ip_array, true)
                    . '<h1>ADDITIONAL</h1>'
                    . var_export($this->debug_data, true);
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

}