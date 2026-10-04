<?php
/**
 * Plugin Name: Login to Dashboard
 * Description: Redirects logged-in visitors from a custom login URL to the dashboard. Compatible with WP Captcha (including Pro) and other plugins that filter wp_login_url(); no WP Captcha dependency.
 * Version: 1.0
 * Plugin URI: https://github.com/RichardVanDerMeer/wordpress-login-redirect
 * Update URI: https://github.com/RichardVanDerMeer/wordpress-login-redirect
 * Author: Richard van der Meer
 * Author URI: https://richardvandermeer.nl
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 * Text Domain: wordpress-login-redirect
 */

namespace LoginToDashboard;

defined('ABSPATH') || exit;

/** Redirect ordinary authenticated visits before the login provider serves its form. */
final class Plugin {

	public static function redirect(): void {
		if ((defined('WP_CLI') && WP_CLI) || wp_doing_ajax() || wp_doing_cron() || is_admin()) {
			return;
		}

		// Do not interrupt authentication submissions or explicit login actions.
		if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET' || !is_user_logged_in()) {
			return;
		}

		foreach (array('action', 'reauth', 'interim-login', 'loggedout', 'checkemail') as $parameter) {
			if (isset($_GET[$parameter])) {
				return;
			}
		}

		/** Allow integrations that do not filter wp_login_url() to supply their URL. */
		$loginUrl = apply_filters('login_to_dashboard_login_url', wp_login_url());
		if (!is_string($loginUrl) || $loginUrl === '' || !self::matchesLoginRequest($loginUrl, wp_unslash($_SERVER['REQUEST_URI'] ?? ''))) {
			return;
		}

		nocache_headers();
		if (wp_safe_redirect(admin_url(), 302, 'Login to Dashboard')) {
			exit;
		}
	}

	/** Match only a local custom login route, including query-based login URLs. */
	private static function matchesLoginRequest(string $loginUrl, string $requestUri): bool {
		$loginParts = wp_parse_url($loginUrl);
		$homeParts = wp_parse_url(home_url('/'));
		$requestParts = wp_parse_url($requestUri);
		if (!is_array($loginParts) || !is_array($homeParts) || !is_array($requestParts)) {
			return false;
		}

		// An external authentication URL must not turn a local page into a login route.
		if (strtolower($loginParts['host'] ?? '') !== strtolower($homeParts['host'] ?? '') || !in_array($loginParts['scheme'] ?? '', array('http', 'https'), true)) {
			return false;
		}

		$loginPath = untrailingslashit($loginParts['path'] ?? '/');
		$requestPath = untrailingslashit($requestParts['path'] ?? '/');
		$adminPath = untrailingslashit(wp_parse_url(admin_url(), PHP_URL_PATH) ?? '/wp-admin');

		// Only handle custom login routes, and never redirect the dashboard to itself.
		if (basename($loginPath) === 'wp-login.php' || $loginPath === $adminPath || $requestPath !== $loginPath) {
			return false;
		}

		// Plain permalinks can identify the login route using a query parameter.
		$loginQuery = array();
		$requestQuery = array();
		parse_str($loginParts['query'] ?? '', $loginQuery);
		parse_str($requestParts['query'] ?? '', $requestQuery);
		$homePath = untrailingslashit($homeParts['path'] ?? '/');
		if ($loginPath === $homePath && !$loginQuery) {
			return false;
		}
		foreach ($loginQuery as $key => $value) {
			if (!array_key_exists($key, $requestQuery) || $requestQuery[$key] !== $value) {
				return false;
			}
		}

		return true;
	}
}

// WP Captcha serves the custom login form during wp_loaded at priority 10.
add_action('wp_loaded', array(Plugin::class, 'redirect'), -100);

require_once __DIR__ . '/includes/github-updater.php';
GitHubUpdater::register(__FILE__);
