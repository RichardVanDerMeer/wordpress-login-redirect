<?php
/**
 * Development-only checks. Run with wp eval-file; never included in release ZIPs.
 */
if (!defined('WP_CLI') || !WP_CLI) {
	exit;
}

if (!class_exists(\LoginToDashboard\Plugin::class)) {
	WP_CLI::error('Activate Login to Dashboard before running these checks.');
}

$pluginFile = 'wordpress-login-redirect/wordpress-login-redirect.php';
$cacheKey = 'login_to_dashboard_github_release';
$previousCache = get_site_transient($cacheKey);
$previousCacheExpiry = (int) get_site_option('_site_transient_timeout_' . $cacheKey, 0);
$previousUpdates = get_site_transient('update_plugins');
$pluginData = get_plugin_data(WP_PLUGIN_DIR . '/' . $pluginFile, false, false);
$versionParts = explode('.', $pluginData['Version']);
$versionParts[count($versionParts) - 1] = (string) ((int) end($versionParts) + 1);
$nextVersion = implode('.', $versionParts);
$failed = false;
$checks = 0;
$check = function ($condition, string $label) use (&$failed, &$checks) {
	$checks++;
	$failed = $failed || !$condition;
	WP_CLI::line(($condition ? 'PASS ' : 'FAIL ') . $label);
};

$matcher = new ReflectionMethod(\LoginToDashboard\Plugin::class, 'matchesLoginRequest');
$matcher->setAccessible(true);
$customUrl = home_url('/login-test/');
$customPath = wp_parse_url($customUrl, PHP_URL_PATH);
$homePath = wp_parse_url(home_url('/'), PHP_URL_PATH);
foreach (array(
	array($customUrl, $customPath, true, 'Custom login path'),
	array($customUrl, untrailingslashit($customPath), true, 'Trailing slash optional'),
	array($customUrl, $customPath . 'extra/', false, 'Prefix matches rejected'),
	array($customUrl, '/unrelated/', false, 'Unrelated page unchanged'),
	array(home_url('/'), $homePath, false, 'Homepage cannot become login page without a query'),
	array(home_url('/wp-login.php'), wp_parse_url(home_url('/wp-login.php'), PHP_URL_PATH), false, 'Standard login route unchanged'),
	array(admin_url(), wp_parse_url(admin_url(), PHP_URL_PATH), false, 'Dashboard cannot redirect to itself'),
	array('https://external.invalid' . $customPath, $customPath, false, 'External login host rejected'),
	array(home_url('/?private-login'), $homePath . '?private-login', true, 'Query-based login route'),
	array(home_url('/?private-login'), $homePath, false, 'Required login query must be present'),
	array(home_url('/?private-login=secret'), $homePath . '?private-login=other', false, 'Required login query value must match'),
) as $case) {
	$check($matcher->invoke(null, $case[0], $case[1]) === $case[2], $case[3]);
}
$subdirectoryHome = function () {
	return 'https://site.invalid/subdirectory/';
};
add_filter('home_url', $subdirectoryHome);
try {
	$check(!$matcher->invoke(null, 'https://site.invalid/subdirectory/', '/subdirectory/'), 'Subdirectory homepage protected');
	$check($matcher->invoke(null, 'https://site.invalid/subdirectory/login/', '/subdirectory/login/'), 'Subdirectory login supported');
} finally {
	remove_filter('home_url', $subdirectoryHome);
}

$validRelease = array(
	'tag_name' => 'v' . $nextVersion,
	'draft' => false,
	'prerelease' => false,
	'assets' => array(array(
		'name' => 'wordpress-login-redirect.zip',
		'state' => 'uploaded',
		'browser_download_url' => 'https://github.com/RichardVanDerMeer/wordpress-login-redirect/releases/download/v' . $nextVersion . '/wordpress-login-redirect.zip',
	)),
);
$release = $validRelease;
$status = 200;
$apiError = false;
$requests = 0;
$httpMock = function ($preempt, $arguments, $url) use (&$release, &$status, &$apiError, &$requests) {
	if ($url === 'https://api.github.com/repos/RichardVanDerMeer/wordpress-login-redirect/releases/latest') {
		$requests++;
		if ($apiError) {
			return new WP_Error('test_network_failure', 'Simulated network failure');
		}
		return array('headers' => array(), 'body' => wp_json_encode($release), 'response' => array('code' => $status, 'message' => 'Fixture'), 'cookies' => array());
	}
	if (strpos($url, 'api.wordpress.org/plugins/update-check/') !== false) {
		return array('headers' => array(), 'body' => wp_json_encode(array('plugins' => array(), 'no_update' => array(), 'translations' => array())), 'response' => array('code' => 200, 'message' => 'Fixture'), 'cookies' => array());
	}
	return $preempt;
};
add_filter('pre_http_request', $httpMock, 10, 3);
$sessions = null;
$token = null;
try {
	$check(is_plugin_active($pluginFile), 'Installed plugin active');
	delete_site_transient($cacheKey);
	delete_site_transient('update_plugins');
	wp_update_plugins();
	$updates = get_site_transient('update_plugins');
	$update = $updates->response[$pluginFile] ?? null;
	$check($update && $update->new_version === $nextVersion && $update->package === $validRelease['assets'][0]['browser_download_url'], 'New release integrated with WordPress updates');
	$check($update && !empty($update->{'update-supported'}) && !empty($update->icons['svg']), 'Automatic-update support and icon metadata');
	apply_filters('update_plugins_github.com', false, $pluginData, $pluginFile, array());
	$check($requests === 1, 'GitHub API result cached');
	$information = apply_filters('plugins_api', false, 'plugin_information', (object) array('slug' => 'wordpress-login-redirect'));
	$check(is_object($information) && $information->version === $nextVersion, 'Custom plugin information');
	$check(apply_filters('update_plugins_github.com', 'unchanged', $pluginData, 'other-plugin/other.php', array()) === 'unchanged', 'Other GitHub plugins unaffected');

	$cases = array();
	$sameVersion = $validRelease;
	$sameVersion['tag_name'] = 'v' . $pluginData['Version'];
	$sameVersion['assets'][0]['browser_download_url'] = str_replace('/v' . $nextVersion . '/', '/v' . $pluginData['Version'] . '/', $sameVersion['assets'][0]['browser_download_url']);
	$cases['Same version'] = $sameVersion;
	$olderVersion = $sameVersion;
	$olderVersion['tag_name'] = 'v0.0';
	$olderVersion['assets'][0]['browser_download_url'] = str_replace('/v' . $pluginData['Version'] . '/', '/v0.0/', $sameVersion['assets'][0]['browser_download_url']);
	$cases['Older version'] = $olderVersion;
	$cases['No release'] = $validRelease;
	$cases['Network failure'] = $validRelease;
	$cases['Invalid response'] = 'not a release object';
	$cases['Malformed assets'] = array_merge($validRelease, array('assets' => 'not an array'));
	$cases['Malformed asset entries'] = array_merge($validRelease, array('assets' => array('invalid', null)));
	$cases['Missing ZIP'] = array_merge($validRelease, array('assets' => array()));
	$cases['Draft'] = array_merge($validRelease, array('draft' => true));
	$cases['Prerelease'] = array_merge($validRelease, array('prerelease' => true));
	$cases['Invalid version tag'] = array_merge($validRelease, array('tag_name' => 'vv0.2'));
	$foreignPackage = $validRelease;
	$foreignPackage['assets'][0]['browser_download_url'] = 'https://external.invalid/wordpress-login-redirect.zip';
	$cases['Foreign package host'] = $foreignPackage;
	$wrongRelease = $validRelease;
	$wrongRelease['assets'][0]['browser_download_url'] = str_replace('/v' . $nextVersion . '/', '/v9999.0/', $wrongRelease['assets'][0]['browser_download_url']);
	$cases['Package from a different release'] = $wrongRelease;
	foreach ($cases as $label => $fixture) {
		delete_site_transient($cacheKey);
		delete_site_transient('update_plugins');
		$release = $fixture;
		$status = $label === 'No release' ? 404 : 200;
		$apiError = $label === 'Network failure';
		wp_update_plugins();
		$updates = get_site_transient('update_plugins');
		$check(!isset($updates->response[$pluginFile]) && isset($updates->no_update[$pluginFile]), $label . ' offers no update');
	}

	remove_filter('pre_http_request', $httpMock, 10);
	$users = get_users(array('role' => 'administrator', 'number' => 1));
	if (!$users) {
		throw new RuntimeException('No administrator available for authenticated HTTP checks.');
	}
	$sessions = WP_Session_Tokens::get_instance($users[0]->ID);
	$expiration = time() + 300;
	$token = $sessions->create($expiration);
	$cookie = wp_generate_auth_cookie($users[0]->ID, $expiration, 'logged_in', $token);
	$loginUrl = wp_login_url();
	$login = wp_parse_url($loginUrl);
	$host = $login['host'] . (isset($login['port']) ? ':' . $login['port'] : '');
	$origin = getenv('WORDPRESS_LOGIN_REDIRECT_TEST_ORIGIN');
	if (!$origin) {
		$origin = $login['scheme'] . '://' . $host;
	}
	$path = $login['path'] . (isset($login['query']) ? '?' . $login['query'] : '');
	foreach (array(false, true) as $authenticated) {
		$headers = array('Host' => $host);
		if ($authenticated) {
			$headers['Cookie'] = LOGGED_IN_COOKIE . '=' . $cookie;
		}
		$response = wp_remote_get(untrailingslashit($origin) . $path, array('headers' => $headers, 'redirection' => 0, 'timeout' => 15));
		if (is_wp_error($response)) {
			throw new RuntimeException($response->get_error_message());
		}
		if ($authenticated) {
			$check(wp_remote_retrieve_response_code($response) === 302 && wp_remote_retrieve_header($response, 'location') === admin_url(), 'Authenticated login redirects to dashboard');
		} else {
			$check(wp_remote_retrieve_response_code($response) === 200 && strpos(wp_remote_retrieve_body($response), 'id="loginform"') !== false, 'Anonymous login form remains visible');
		}
	}
} finally {
	remove_filter('pre_http_request', $httpMock, 10);
	if ($sessions && $token) {
		$sessions->destroy($token);
	}
	delete_site_transient($cacheKey);
	if ($previousCache !== false && (!$previousCacheExpiry || $previousCacheExpiry > time())) {
		set_site_transient($cacheKey, $previousCache, $previousCacheExpiry ? $previousCacheExpiry - time() : 0);
	}
	delete_site_transient('update_plugins');
	if ($previousUpdates !== false) {
		set_site_transient('update_plugins', $previousUpdates);
	}
}
if ($failed) {
	WP_CLI::error('Plugin verification failed.');
}
WP_CLI::success("{$checks} checks passed; temporary sessions and update fixtures removed.");
