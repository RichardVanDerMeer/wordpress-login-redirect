=== Login to Dashboard ===
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 1.0
License: GPL-2.0-or-later

Redirect logged-in visitors from a custom WordPress login URL to the dashboard.

== Description ==

By Richard van der Meer: https://richardvandermeer.nl

Automatically follows the URL returned by WordPress wp_login_url(). Works with
WP Captcha and other custom-login plugins that filter that URL. No site URL or
login slug is hardcoded, so changing the login URL needs no extra configuration.
Supports subdirectory installations and query-based login URLs.

== Compatibility and dependencies ==

WP Captcha Pro is not required. The plugin uses WordPress APIs and does not call
WP Captcha classes or read its settings. WP Captcha (including Pro) is a compatible
login provider; other providers must expose their custom URL through wp_login_url()
or the integration filter documented below. The provider remains responsible for
the login form. Without a custom login URL, this plugin does nothing.

There is deliberately no Requires Plugins header: no particular plugin is required.

Logged-out visitors continue to see the existing login form. Only ordinary GET
visits by logged-in users are redirected to admin_url(). POST submissions and
requests with action, reauth, interim-login, loggedout or checkemail are untouched.
The plugin does not change permissions or grant dashboard access.

== Installation ==

1. Upload wordpress-login-redirect.zip through Plugins > Add New > Upload Plugin.
2. Activate Login to Dashboard.
3. Keep the plugin that provides your custom login URL active.

No redirect settings or database tables are required. Deactivate this plugin to disable
the redirect.

== Automatic updates ==

Updates are delivered through stable public releases at:
https://github.com/RichardVanDerMeer/wordpress-login-redirect/releases

Enable auto-updates for Login to Dashboard on the WordPress Plugins page.
The latest release must include the wordpress-login-redirect.zip asset.
No GitHub token or additional updater plugin is required.
Automatic installation is opt-in and follows WordPress's normal update policies.
The updater accepts only a validated stable release ZIP from this repository.
See README.md for the publishing workflow and local development limitations.

== Developer integration ==

For a custom login mechanism that does not filter wp_login_url(), return its
absolute login URL from the login_to_dashboard_login_url filter.
The login mechanism itself remains responsible for serving the login form.

== Changelog ==

= 1.0 =
Initial public version with custom-login redirect, icon and GitHub release updates.
