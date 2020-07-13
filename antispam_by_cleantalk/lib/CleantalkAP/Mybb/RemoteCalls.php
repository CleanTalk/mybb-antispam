<?php

namespace CleantalkAP\Mybb;

use CleantalkAP\Common\Err;
use CleantalkAP\Variables\Get;

class RemoteCalls extends \CleantalkAP\Common\RemoteCalls
{
	public static function perform(){

	    global $mybb;

        $remote_calls = array( 'close_renew_banner' => 0, 'update_plugin' => 0, 'sfw_update' => 0, 'sfw_send_logs' => 0 );

		$action = strtolower(Get::get('spbc_remote_call_action'));
		$token  = strtolower(Get::get('spbc_remote_call_token'));

		if ( array_key_exists( $action, $remote_calls ) ) {

            // Check API key
            if( $token == md5(trim($mybb->settings['antispam_by_cleantalk_accesskey'])) ){

                $action = 'action__'.$action;

                // Common actions
                if( method_exists( '\CleantalkAP\Mybb\RemoteCalls', $action ) ){

                    sleep( (int) Get::get('delay') ); // Delay before perform action;
                    $out = self::$action();

                }else
                    Err::add('UNKNOWN_ACTION_METHOD');
            }else
                Err::add('WRONG_TOKEN');
		}else
			Err::add('UNKNOWN_ACTION');

		die( Err::check()
			? Err::check_and_output( 'as_json' )
			: json_encode($out)
		);
	}

	static function action__sfw_update() {

        global $mybb;

        $result = antispam_by_cleantalk_sfw_update( trim( $mybb->settings['antispam_by_cleantalk_accesskey'] ), true );
        die( empty( $result['error'] ) ? 'OK' : 'FAIL ' . json_encode( array( 'error' => $result['error_string'] ) ) );

	}

    static function action__sfw_send_logs() {

        global $mybb;

        $result = antispam_by_cleantalk_sfw_send_logs( trim( $mybb->settings['antispam_by_cleantalk_accesskey'] ) );
        die( empty( $result['error'] ) ? 'OK' : 'FAIL ' . json_encode( array( 'error' => $result['error_string'] ) ) );

    }

    static function action__close_renew_banner() {

        die('Not implemented.');

    }

    static function action__update_plugin() {

        die('Not implemented.');

    }

}