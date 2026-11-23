<?php
/** Run with PHP 8.1, 8.2, 8.3 and 8.4: php tests/run.php */
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
    if (error_reporting() & $severity) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    }
    return false;
});
require dirname(__DIR__) . '/antispam_by_cleantalk/autoloader.php';
require dirname(__DIR__) . '/antispam_by_cleantalk/apbct_functions.php';
require dirname(__DIR__) . '/antispam_by_cleantalk/hooks_nandlers.php';

class RenderedMessage extends RuntimeException {}
function error($message) { throw new RenderedMessage($message); }
function htmlspecialchars_uni($value) { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
class TestMybb {
    const INPUT_INT = 1;
    public $settings = array();
    public $user = array();
    public $usergroup = array();
    public function get_input($name, $type = null) { return $type === self::INPUT_INT ? 0 : ''; }
}
class_alias('TestMybb', 'MyBB');
$mybb = new TestMybb();
$mybb->settings = array(
    'antispam_by_cleantalk_accesskey' => 'local-test-key',
    'antispam_by_cleantalk_enabled' => '1',
    'antispam_by_cleantalk_regcheck' => '1',
    'antispam_by_cleantalk_comcheck' => '1',
    'antispam_by_cleantalk_exclusions_url' => '',
    'antispam_by_cleantalk_exclusions_groups' => '',
);
$mybb->usergroup = array('all_usergroups' => '2,6');
function check($condition, $message) { if (!$condition) throw new RuntimeException($message); }
$tests = array(
    'optional text fields retain null' => function () {
        $ct = new CleantalkAP\Mybb\Cleantalk();
        check($ct->stringToUTF8(null) === null, 'Nullable request field changed');
        check($ct->stringFromUTF8(null) === null, 'Nullable response field changed');
        $method = new ReflectionMethod($ct, 'createMsg');
        $method->setAccessible(true);
        $request = new CleantalkAP\Mybb\CleantalkRequest(array('sender_email' => 'test@example.com'));
        $result = $method->invoke($ct, 'check_message', $request);
        check($result->example === null && $result->message === null, 'Optional fields must remain omitted');
        check($result->sender_email === 'test@example.com', 'Sender email changed');
    },
    'UTF-8 moderation text is preserved' => function () {
        $text = 'Проверка спама — 日本語';
        $result = new CleantalkAP\Mybb\CleantalkResponse(null, (object)array('errno' => 0, 'errstr' => null, 'allow' => 0, 'comment' => $text));
        check($result->comment === $text && $result->allow === 0 && $result->errstr === null, 'Response must preserve UTF-8');
    },
    'additional response fields cause no dynamic property warnings' => function () {
        $result = new CleantalkAP\Mybb\CleantalkResponse(array('allow' => 1, 'future_api_field' => 'value'));
        check($result->allow === 1 && $result->extra['future_api_field'] === 'value', 'Additional API field was lost');
    },
    'empty response is handled without warnings' => function () {
        $result = new CleantalkAP\Mybb\CleantalkResponse();
        check($result->errno === 1, 'Missing response must be reported as an error');
    },
    'missing DNS host returns an empty list' => function () {
        check((new CleantalkAP\Mybb\Cleantalk())->get_servers_ip(null) === array(), 'Expected an empty server list');
    },
    'optional HTTP presets accept null' => function () {
        $result = CleantalkAP\Common\Helper::http__request('http://127.0.0.1:1', array(), null, array(CURLOPT_CONNECTTIMEOUT_MS => 100, CURLOPT_TIMEOUT_MS => 100));
        check(is_array($result) && isset($result['error']), 'Expected a transport error without PHP warnings');
    },
    'blocked page contains only the CleanTalk message' => function () {
        try { antispam_by_cleantalk_show_message('*** Forbidden. Sender blacklisted. ***'); }
        catch (RenderedMessage $e) {
            check(strpos($e->getMessage(), 'Sender blacklisted') !== false, 'Message is missing');
            check(strpos($e->getMessage(), 'cleantalk-message') !== false, 'Plugin template is missing');
            check(strpos($e->getMessage(), 'permission') === false && strpos($e->getMessage(), 'banned') === false, 'Unexpected MyBB permission message');
            return;
        }
        throw new RuntimeException('Blocked page was not rendered');
    },
    'blocked message is rendered as text' => function () {
        try { antispam_by_cleantalk_show_message('Проверка <b>message</b>'); }
        catch (RenderedMessage $e) {
            check(strpos($e->getMessage(), 'Проверка &lt;b&gt;message&lt;/b&gt;') !== false, 'Message must be escaped and preserve UTF-8');
            return;
        }
        throw new RuntimeException('Blocked page was not rendered');
    },
    'malformed cookie is ignored' => function () {
        $_COOKIE['ct_cookies_test'] = '{invalid';
        check(antispam_by_cleantalk_testcookies() === 0, 'Invalid cookie was accepted');
        $_COOKIE['ct_cookies_test'] = '[]';
        check(antispam_by_cleantalk_testcookies() === 0, 'Incomplete cookie was accepted');
        unset($_COOKIE['ct_cookies_test']);
    },
    'URL exclusions match the configured path' => function () {
        global $mybb;
        $_SERVER['REQUEST_URI'] = '/contact.php';
        $mybb->settings['antispam_by_cleantalk_exclusions_url'] = '/contact.php';
        check(antispam_by_cleantalk_check_exclusions_url() === true, 'Excluded URL was checked');
        $mybb->settings['antispam_by_cleantalk_exclusions_url'] = '/member.php';
        check(antispam_by_cleantalk_check_exclusions_url() === false, 'Unrelated URL was excluded');
        $mybb->settings['antispam_by_cleantalk_exclusions_url'] = 'contact.php, http://forum.example/contact.php, forum.example/contact.php';
        check(antispam_by_cleantalk_check_exclusions_url() === true, 'URL without a domain was not excluded');
        \CleantalkAP\Variables\Server::getInstance()->variables = array();
        $_SERVER['REQUEST_URI'] = '/contact.php?action=do_contact';
        $mybb->settings['antispam_by_cleantalk_exclusions_url'] = 'http://mybb.loc/contact.php';
        check(antispam_by_cleantalk_check_exclusions_url() === true, 'URL with a domain was not excluded');
        \CleantalkAP\Variables\Server::getInstance()->variables = array();
        $_SERVER['REQUEST_URI'] = '/member.php';
        check(antispam_by_cleantalk_check_exclusions_url() === false, 'Another page was excluded by a domain URL');
        \CleantalkAP\Variables\Server::getInstance()->variables = array();
        $_SERVER['REQUEST_URI'] = '/member.php?action=do_register';
        $mybb->settings['antispam_by_cleantalk_exclusions_url'] = '/member.php?action=login, http://forum.example/member.php?action=login';
        check(antispam_by_cleantalk_check_exclusions_url() === false, 'Login exclusion skipped registration');
        \CleantalkAP\Variables\Server::getInstance()->variables = array();
        $_SERVER['REQUEST_URI'] = '/member.php?action=login&foo=1';
        check(antispam_by_cleantalk_check_exclusions_url() === true, 'Login exclusion missed the login action');
        \CleantalkAP\Variables\Server::getInstance()->variables = array();
        $_SERVER['REQUEST_URI'] = '/contact.php?action=do_contact';
        $mybb->settings['antispam_by_cleantalk_exclusions_url'] = 'contact.php';
        check(antispam_by_cleantalk_check_exclusions_url() === true, 'Path exclusion missed a request with a query');
        $mybb->settings['antispam_by_cleantalk_exclusions_url'] = '';
        $_SERVER['REQUEST_URI'] = '/';
    },
    'remote call password is not the public javascript value' => function () {
        global $mybb;
        $mybb->settings['antispam_by_cleantalk_accesskey'] = 'local-test-key';
        $mybb->settings['bburl'] = 'http://forum.example/';
        $public = antispam_by_cleantalk_js_value();
        $remote = antispam_by_cleantalk_remote_token();
        $board = 'http://forum.example';
        check($public === md5('local-test-key'), 'Public JS value changed');
        check($remote === hash_hmac('sha256', 'mybb-remote-call:' . $board, 'local-test-key'), 'Remote token derivation changed');
        check($remote !== md5('local-test-key' . $board), 'Remote token matches the referer cookie');
        check($public !== $remote, 'Remote token matches the printed value');
        $mybb->settings['bburl'] = '';
        check(antispam_by_cleantalk_remote_token() === '', 'Empty board URL still produced a token');
        $mybb->settings['bburl'] = 'http://forum.example';
    },
    'remote delay is capped at ten seconds' => function () {
        check(antispam_by_cleantalk_remote_delay(null) === 0, 'Missing delay was accepted');
        check(antispam_by_cleantalk_remote_delay('nope') === 0, 'Text delay was accepted');
        check(antispam_by_cleantalk_remote_delay(-5) === 0, 'Negative delay was accepted');
        check(antispam_by_cleantalk_remote_delay(3) === 3, 'Small delay was changed');
        check(antispam_by_cleantalk_remote_delay(3600) === 10, 'Large delay was not capped');
    },
    'firewall files are accepted only from CleanTalk' => function () {
        check(antispam_by_cleantalk_is_cleantalk_file_url('https://apix21.cleantalk.org/store/list.csv.gz') === true, 'CleanTalk archive was rejected');
        check(antispam_by_cleantalk_is_cleantalk_file_url('http://apix21.cleantalk.org/store/list.csv.gz') === false, 'Plain HTTP archive was accepted');
        check(antispam_by_cleantalk_is_cleantalk_file_url('https://evil.example/list.csv.gz') === false, 'Foreign archive was accepted');
        check(antispam_by_cleantalk_is_cleantalk_file_url('https://user:pass@apix21.cleantalk.org/list.csv.gz') === false, 'URL with credentials was accepted');
        check(antispam_by_cleantalk_is_cleantalk_file_url('https://127.0.0.1/list.csv.gz') === false, 'IP archive was accepted');
    },
    'block page values are escaped and the test parameter is removed' => function () {
        check(antispam_by_cleantalk_html('"><ctxss>') === '&quot;&gt;&lt;ctxss&gt;', 'HTML was not escaped');
        $path = antispam_by_cleantalk_safe_request_path('/forum?sfw_test_ip=10.10.10.10&debug=1&x=%3Cctxss%3E');
        check(strpos($path, 'sfw_test_ip') === false && strpos($path, 'debug') === false, 'Test parameters remained in the link');
        check(strpos($path, '<ctxss>') === false, 'Raw markup remained in the link');
    },
    'disabled plugin does not print the javascript value' => function () {
        global $mybb;
        $mybb->settings['antispam_by_cleantalk_enabled'] = '0';
        $mybb->settings['antispam_by_cleantalk_accesskey'] = 'local-test-key';
        $mybb->settings['bburl'] = 'http://forum.example';
        $page = antispam_by_cleantalk_add_js('<html><body>ok</body></html>');
        check(strpos($page, 'ct_checkjs_val') === false, 'JS value was printed while disabled');
        $mybb->settings['antispam_by_cleantalk_enabled'] = '1';
        $page = antispam_by_cleantalk_add_js('<html><body>ok</body></html>');
        check(strpos($page, antispam_by_cleantalk_remote_token()) === false, 'Remote token was printed');
        check(strpos($page, antispam_by_cleantalk_js_value()) !== false, 'Public JS value is missing');
    },
    'contact submissions are marked as contact forms' => function () {
        global $mybb;
        $mybb->settings['bburl'] = 'http://forum.example';
        $_SERVER['REQUEST_URI'] = '/contact.php';
        $info = json_decode(antispam_by_cleantalk_post_info('contact_form'), true);
        check(is_array($info) && $info['comment_type'] === 'contact_form', 'Contact type was not set');
        check($info['post_url'] === 'http://forum.example/contact.php', 'Contact URL was not set');
        $mybb->settings['bburl'] = 'https://forum.example/mybb';
        $_SERVER['REQUEST_URI'] = '/mybb/contact.php';
        $nested = json_decode(antispam_by_cleantalk_post_info('contact_form'), true);
        check(is_array($nested) && $nested['post_url'] === 'https://forum.example/mybb/contact.php', 'Installation directory was repeated');
        $mybb->settings['bburl'] = 'https://forum.example:8443/mybb';
        $_SERVER['REQUEST_URI'] = '/contact.php';
        $relative = json_decode(antispam_by_cleantalk_post_info('contact_form'), true);
        check(is_array($relative) && $relative['post_url'] === 'https://forum.example:8443/mybb/contact.php', 'Board-relative URL lost its directory');
        $comment = json_decode(antispam_by_cleantalk_post_info('comment'), true);
        check(is_array($comment) && $comment['comment_type'] === 'comment', 'Post type was not set');
    },
    'group exclusions bypass protection' => function () {
        global $mybb;
        $mybb->settings['antispam_by_cleantalk_exclusions_groups'] = '6';
        check(antispam_by_cleantalk_check_exclusions_roles() === true, 'Excluded group was checked');
        check(antispam_by_cleantalk_regtrigger() === false, 'Excluded registration was checked');
        check(antispam_by_cleantalk_contacttrigger() === false, 'Excluded contact was checked');
        $mybb->settings['antispam_by_cleantalk_exclusions_groups'] = '';
    },
    'disabled checks bypass cloud requests' => function () {
        global $mybb;
        $mybb->settings['antispam_by_cleantalk_enabled'] = '0';
        check(antispam_by_cleantalk_trigger() === false, 'Disabled post check was called');
        check(antispam_by_cleantalk_regtrigger() === false, 'Disabled registration check was called');
        check(antispam_by_cleantalk_contacttrigger() === false, 'Disabled contact check was called');
        $mybb->settings['antispam_by_cleantalk_enabled'] = '1';
    },
);
$failed = 0;
$output = array();
foreach ($tests as $name => $test) {
    try { $test(); $output[] = 'PASS ' . $name; }
    catch (Throwable $e) { ++$failed; $output[] = 'FAIL ' . $name . ': ' . $e->getMessage(); }
}
echo implode(PHP_EOL, $output), PHP_EOL, 'PHP ', PHP_VERSION, ': ', count($tests) - $failed, '/', count($tests), ' passed', PHP_EOL;
exit($failed ? 1 : 0);
