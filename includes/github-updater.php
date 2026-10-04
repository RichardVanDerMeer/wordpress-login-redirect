<?php

namespace LoginToDashboard;

defined('ABSPATH') || exit;

/** Supply stable public GitHub releases to WordPress's own update system. */
final class GitHubUpdater {
	private const REPOSITORY = 'https://github.com/RichardVanDerMeer/wordpress-login-redirect';
	private const API = 'https://api.github.com/repos/RichardVanDerMeer/wordpress-login-redirect/releases/latest';
	private const CACHE_KEY = 'login_to_dashboard_github_release';
	private static ?string $pluginFile = null;

	public static function register(string $file): void {
		self::$pluginFile = plugin_basename($file);
		add_filter('update_plugins_github.com', array(self::class, 'checkUpdate'), 10, 3);
		add_filter('plugins_api', array(self::class, 'pluginInformation'), 10, 3);
	}

	public static function checkUpdate($update, array $pluginData, string $pluginFile) {
		if ($pluginFile !== self::$pluginFile) {
			return $update;
		}

		$release = self::latestRelease();
		return array(
			'id' => self::REPOSITORY,
			'slug' => 'wordpress-login-redirect',
			'version' => $release ? $release['version'] : $pluginData['Version'],
			'url' => self::REPOSITORY,
			'package' => $release ? $release['package'] : '',
			'update-supported' => true,
			'icons' => array('svg' => plugins_url('assets/icon.svg', dirname(__DIR__) . '/wordpress-login-redirect.php')),
		);
	}

	/** Return only a validated release, or fail closed without offering an update. */
	private static function latestRelease(): ?array {
		$cached = get_site_transient(self::CACHE_KEY);
		if ($cached !== false) {
			return is_array($cached) ? $cached : null;
		}

		$response = wp_remote_get(self::API, array(
			'timeout' => 10,
			'limit_response_size' => 512 * 1024,
			'headers' => array('Accept' => 'application/vnd.github+json', 'X-GitHub-Api-Version' => '2022-11-28'),
		));
		if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
			set_site_transient(self::CACHE_KEY, 'unavailable', 5 * MINUTE_IN_SECONDS);
			return null;
		}

		$data = json_decode(wp_remote_retrieve_body($response), true);
		$tag = is_array($data) && is_string($data['tag_name'] ?? null) ? $data['tag_name'] : '';
		$matches = array();
		if (!is_array($data) || !empty($data['draft']) || !empty($data['prerelease']) || !preg_match('/^v?(\d+(?:\.\d+)+)$/D', $tag, $matches) || !is_array($data['assets'] ?? null)) {
			set_site_transient(self::CACHE_KEY, 'unavailable', 5 * MINUTE_IN_SECONDS);
			return null;
		}

		$version = $matches[1];
		$expectedPackage = self::REPOSITORY . '/releases/download/' . rawurlencode($tag) . '/wordpress-login-redirect.zip';
		foreach ($data['assets'] as $asset) {
			if (!is_array($asset)) {
				continue;
			}
			$package = $asset['browser_download_url'] ?? '';
			if (($asset['name'] ?? '') === 'wordpress-login-redirect.zip' && ($asset['state'] ?? '') === 'uploaded' && $package === $expectedPackage) {
				$release = array('version' => $version, 'package' => $package);
				set_site_transient(self::CACHE_KEY, $release, HOUR_IN_SECONDS);
				return $release;
			}
		}

		set_site_transient(self::CACHE_KEY, 'unavailable', 5 * MINUTE_IN_SECONDS);
		return null;
	}

	public static function pluginInformation($information, string $action, $arguments) {
		if ($action !== 'plugin_information' || ($arguments->slug ?? '') !== 'wordpress-login-redirect') {
			return $information;
		}

		$release = self::latestRelease();
		$headers = get_file_data(dirname(__DIR__) . '/wordpress-login-redirect.php', array('version' => 'Version'));
		return (object) array(
			'name' => 'Login to Dashboard',
			'slug' => 'wordpress-login-redirect',
			'version' => $release ? $release['version'] : $headers['version'],
			'author' => '<a href="https://richardvandermeer.nl">Richard van der Meer</a>',
			'homepage' => self::REPOSITORY,
			'requires' => '6.0',
			'requires_php' => '7.4',
			'download_link' => $release ? $release['package'] : '',
			'sections' => array(
				'description' => '<p>Redirects logged-in visitors from a custom login URL to the WordPress dashboard. Compatible with WP Captcha (including Pro); WP Captcha is not required.</p>',
				'changelog' => '<p><a href="' . esc_url(self::REPOSITORY . '/releases') . '">View release notes on GitHub</a></p>',
			),
		);
	}
}
