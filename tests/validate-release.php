<?php
/**
 * Validate local release metadata. Run: RELEASE_TAG=v1.0 php tests/validate-release.php
 */
if (PHP_SAPI !== 'cli') {
	exit;
}

$root = dirname(__DIR__);
$plugin = file_get_contents($root . '/wordpress-login-redirect.php');
$readme = file_get_contents($root . '/readme.txt');

preg_match('/^\s*\*\s*Version:\s*(\S+)/m', $plugin, $pluginVersion);
preg_match('/^Stable tag:\s*(\S+)/m', $readme, $stableVersion);
$version = $pluginVersion[1] ?? '';
$tag = getenv('RELEASE_TAG');

if (!preg_match('/^\d+(?:\.\d+)+$/D', $version) || ($stableVersion[1] ?? '') !== $version || $tag !== 'v' . $version) {
	fwrite(STDERR, "Plugin version, readme stable tag and release tag must match.\n");
	exit(1);
}

echo "PASS Release metadata matches {$tag}\n";
