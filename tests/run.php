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
        $mybb->settings['antispam_by_cleantalk_exclusions_url'] = '';
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
