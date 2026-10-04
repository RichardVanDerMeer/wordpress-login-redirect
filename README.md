# Login to Dashboard

<img src="assets/icon.svg" width="96" height="96" alt="Login to Dashboard icon">

A WordPress plugin by [Richard van der Meer](https://richardvandermeer.nl).

A small WordPress plugin that sends already logged-in visitors from a custom
login page to the dashboard. This avoids the blank page that WP Captcha can show
when its custom login URL is opened without a `redirect_to` parameter.

For example, when your login URL is `/login-test/`:

| Visit | Result |
| --- | --- |
| Not logged in | Your existing login form |
| Already logged in | Temporary redirect to the site's dashboard |
| Password recovery, logout or reauthentication | Handled by the existing login provider |
| Login form submission | Handled by the existing login provider |

The plugin does not create or hide a login page. Your custom login provider does
that. It also does not change where users land after submitting the login form:
it handles ordinary visits made when the user is already logged in.

## Compatibility

WP Captcha Pro is **not required**. Login to Dashboard uses `wp_login_url()` to
discover the custom login URL. It works with WP Captcha (including Pro) and other
plugins that filter that URL. Without a custom login provider, it does nothing.

Requires WordPress **6.0+**, PHP **7.4+**, and a working custom login route exposed
by your login provider. There is no hard dependency on any named plugin.

The login URL must use the same hostname as the site. Path-based and query-based
URLs are supported, including WordPress installed in a subdirectory. External
authentication URLs are not handled.

There are no redirect settings, custom database tables or hardcoded site URLs.
The updater stores temporary release metadata in WordPress's normal cache.

## Installation

Download `wordpress-login-redirect.zip` from the
[GitHub releases](https://github.com/RichardVanDerMeer/wordpress-login-redirect/releases).
Upload it through **Plugins > Add New > Upload Plugin** and
activate **Login to Dashboard**. Keep your custom login provider active.

Use the attached plugin ZIP, not GitHub's automatically generated source archive:
the release ZIP keeps the plugin directory name stable for subsequent updates.

To check the installation, visit your login URL in an incognito window, then
open that same URL in a browser where you are logged in. The first should show
the form; the second should go to the dashboard. Deactivate this plugin to undo
the redirect.

When replacing an earlier installation in the `login-to-dashboard` or
`custom-login-dashboard-redirect` directory, deactivate that version before
activating this plugin. The new plugin directory is `wordpress-login-redirect`.

## Automatic updates

The built-in updater checks the latest stable public GitHub release and integrates
with WordPress's normal plugin updates. No access token or extra updater plugin is
required. Keep this plugin active. Enable **Auto-updates** for Login to Dashboard
on the WordPress Plugins page to allow automatic installation. Installing this
plugin does not enable automatic installation by itself. WordPress's normal
permissions, filesystem access, scheduled tasks and update policies still apply.

Only releases with an attached `wordpress-login-redirect.zip` are installable.
Pushing a commit alone does not publish an update. Drafts and prereleases are
excluded. Checks are cached for one hour, with a five-minute retry after a missing
release or a network error. No release available means no update is offered.

The local development site mounts this repository read-only to protect source
files. Install the ZIP into a normal writable plugins directory on other sites
to use automatic installation.

## Publishing a release

1. Set the same version in the plugin header and `readme.txt` stable tag.
2. Commit and push the files to the public repository.
3. Run the tests below, then create and push a matching tag.

For the first release:

```sh
git tag v1.0
git push origin v1.0
```

The included GitHub Actions workflow validates the version, builds the plugin ZIP
with a stable directory name, and publishes a GitHub release with that ZIP attached.
Later releases, such as `v1.1`, are detected as updates. Tags must match the version
in both the PHP header and the readme stable tag. GitHub Actions must be enabled
for the repository. This workflow publishes a public release as soon as a matching
tag is pushed; review the commit before pushing its tag.

## Security and scope

The destination is always WordPress's own `admin_url()`, using a safe temporary
redirect and no-cache headers. A URL passed in `redirect_to` is not used. The
plugin neither authenticates users nor grants permissions: WordPress still
controls dashboard access.

Only authenticated GET requests to the matching custom login route are handled.
Requests carrying `action`, `reauth`, `interim-login`, `loggedout` or `checkemail`,
and all POST submissions, are left to the login provider. The normal homepage,
standard `wp-login.php` route and dashboard are not redirected.

The updater contacts GitHub over HTTPS, validates the response and accepts only
the named ZIP at the exact release URL in this repository. Missing, malformed or
unavailable releases do not offer an update. Release code is trusted to this
repository and its maintainers; the ZIP has no separate cryptographic signature.
Protect write access to the repository and review releases before publishing.

## Troubleshooting

| Problem | Check |
| --- | --- |
| The custom login page still stays blank | Is this plugin active, and does your provider expose the URL through `wp_login_url()`? Use the integration filter below if needed. |
| A new version does not appear | Is it a stable public release with a higher version and the correctly named ZIP? Allow for both the GitHub cache and WordPress's own update schedule. |
| An update cannot be installed | Check filesystem permissions, WordPress update policies and scheduled tasks. A read-only development mount cannot be updated. |
| No releases exist yet | Build and upload the plugin ZIP manually for initial testing, or publish the first tagged release. |

The icon is included in the README and WordPress update metadata. WordPress's
standard installed-plugins list does not show a plugin icon by default.

## Development tests

Validate the version, readme and release tag without a WordPress installation:

```sh
RELEASE_TAG=v1.0 php tests/validate-release.php
```

Run the integration tests with WP-CLI on a **development site** where this plugin
is installed and active:

```sh
wp eval-file wp-content/plugins/wordpress-login-redirect/tests/integration.php
```

The tests check login-route matching, WordPress update integration, malformed
release responses and actual authenticated/anonymous HTTP requests. They mock
update APIs, temporarily create one administrator session, then remove that session
and restore update caches. They do not change passwords, install updates or publish
releases. An administrator account must already exist. Test files are excluded
from the release ZIP.

If the site's public URL cannot be reached from its container, provide a loopback
origin while keeping the site's original Host header:

```sh
WORDPRESS_LOGIN_REDIRECT_TEST_ORIGIN=http://localhost \
  wp eval-file wp-content/plugins/wordpress-login-redirect/tests/integration.php
```

## Custom integrations

Providers that do not filter `wp_login_url()` can supply their absolute login URL:

```php
add_filter('login_to_dashboard_login_url', function ($loginUrl) {
	return home_url('/custom-login/');
});
```

The provider must still serve the login form at that URL.

## Repository

[RichardVanDerMeer/wordpress-login-redirect](https://github.com/RichardVanDerMeer/wordpress-login-redirect)

Current version: `1.0`. Plugin display name: **Login to Dashboard**.

## License

GPL-2.0-or-later.
