<?php

namespace CleantalkAP\Common;

use CleantalkAP\Variables\Get;

abstract class RemoteCalls
{

	const COOLDOWN = 10;

    /**
     * Checking if current request is a remote call request.
     *
     * @return bool
     */
	public static function check() {
		return Get::is_set('spbc_remote_call_token', 'spbc_remote_call_action', 'plugin_name') && in_array(Get::get('plugin_name'), array('antispam','anti-spam', 'apbct'))
			? true
			: false;
	}

	static function perform() {
	    error_log( 'The method has to be declared by the extended class.' );
    }

}