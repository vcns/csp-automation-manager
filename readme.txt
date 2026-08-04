=== CSP Automation Manager ===
Contributors: vcns
Tags: security, csp, content security policy, headers, wordpress security
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.0.26
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automates strict Content Security Policy rollout, violation reporting, source discovery, and policy-change review for WordPress.

== Description ==

CSP Automation Manager helps site owners roll out strict Content Security Policy headers safely and incrementally.

The plugin provides per-surface CSP profiles, nonce injection, source discovery, violation reporting, policy-change review, reason-required append-only audit records, policy history, readiness checks, conflict detection for existing CSP emitters, and administrator-controlled rollout tools.

== External services ==

This WordPress.org build does not contact third-party services for plugin updates, licensing, checkout, telemetry, or remote product configuration.

GitHub release builds are published separately for administrators who install from GitHub rather than WordPress.org. The GitHub-channel ZIP checks https://vcns.github.io/wp-updates/csp-automation-manager/update.json from administrator update contexts only, validates the advertised package host and SHA-256 checksum, and then lets WordPress perform the update. Define WP_CSP_DISABLE_AUTO_UPDATE as true in wp-config.php to prevent background auto-updates for the GitHub-channel package.

By default, the plugin emits CSP reporting headers that point browsers back to this WordPress site's own REST endpoint:

* `/wp-json/csp-manager/v1/report`

Administrators may override the reporting server URL when the public HTTPS endpoint differs from the WordPress-detected site URL, such as behind a proxy, CDN, or load balancer. If the override points to another host, browsers will send CSP reports to that configured endpoint; local report learning only works when the URL routes back to this plugin's report endpoint.

Purpose:
* receive browser-generated CSP violation reports for this site;
* store reports locally so administrators can review and refine policy safely.

Data handled:
* browser CSP violation report fields such as blocked URL, document URL, violated directive, referrer, user agent, line/column where provided, and an optional script sample where the active policy requests `report-sample`.

Reports received by this plugin are validated and stored in this site's WordPress database. They are not sent to any external provider by default.

For Cloudflare, CDN, and reverse-proxy deployments, administrators can configure an origin-only policy header name such as X-Origin-CSP-Policy. The proxy can then copy that origin header into the browser-facing Content-Security-Policy-Report-Only or Content-Security-Policy header.

== Changelog ==

= 1.0.26 =

* Prevents checked Auto Approval from being silently disabled by a stored maximum-per-run value of 0.
* Normalises enabled non-manual automation surfaces with a zero cap to the default cap of 50.
* Clarifies the Settings automation cap copy so disabling auto-approval is done with the Auto Approval checkbox.

= 1.0.25 =

* Records inline script and style hashes for CSP3 element directives (`script-src-elem` and `style-src-elem`) as well as the broader fallback directives.
* Learns `font-src data:` browser reports as high-risk pending proposals for administrator review.
* Keeps style-attribute violations evidence-only until exact attribute hash approval with `unsafe-hashes` is implemented safely.

= 1.0.24 =

* Learns same-origin browser file violations as reviewable `'self'` proposals instead of discarding them before review.
* Applies the existing same-origin-as-low automation setting to deterministic risk scoring.
* Clarifies that browser violations are evidence and only become Recent Decisions after approval, rejection, reversal, undo, or automation.

= 1.0.23 =

* Moves CSP surface mode controls from Dashboard > Profiles into Settings > Configuration > Deterministic Automation.
* Moves Policy Audit and Readiness into tabs under Settings and removes the redundant Dashboard > Profiles tab.
* Separates inline CSP violation evidence by source location and sample so different inline failures no longer collapse into one broad row.
* Adds Violations tab totals and an Evidence column to explain grouped browser report processing.

= 1.0.22 =

* Moves CSP surface mode controls from Dashboard > Profiles into Settings > Configuration > Deterministic Automation.
* Moves Policy Audit and Readiness into tabs under Settings and removes the redundant Dashboard > Profiles tab.

= 1.0.21 =

* Renames the Settings deterministic automation table columns to "Automation" and "Auto Approval" for clearer operator intent.

= 1.0.20 =

* Groups the Violations dashboard by canonical policy source so already-stored Google Fonts and similar file-level reports display as one host-level row with a summed occurrence count.

= 1.0.19 =

* Rolls up remote host-source violation reports by policy source, reducing Google Fonts and similar asset noise from many file-level rows into a single host-level occurrence count.

= 1.0.18 =

* Clarifies the wp-admin enforce-mode warning and presents the Trac reference as a single clean "Learn more" link.

= 1.0.17 =

* Reorders the Dashboard tabs to Profiles, Violations, For Review, and Policy Changes.
* Moves the recent Scan Log table into Settings with the scan schedule controls.

= 1.0.16 =

* Adds a separate GitHub-channel release ZIP with checksum-verified native WordPress update integration.
* Keeps the plain release ZIP WordPress.org-safe by excluding the GitHub updater and Update URI header.
* Publishes GitHub-channel update metadata for vcns/wp-updates when the release pipeline has a WP_UPDATES_TOKEN.
* Adds selectable automation postures on Profiles and Settings: Manual, Automatic with medium+high approvals, Automatic with high approvals only, and Fully Automatic within deterministic hard safety exclusions.
* Replaces the dashboard Strict-Dynamic display column with a per-surface Automation dropdown.

= 1.0.15 =

* Renames the dashboard source review queue to "For Review".
* Expands Policy Changes into a policy activity timeline showing source proposals, decisions, actors, suppression state, and policy snapshots.
* Adds per-surface deterministic automation settings and records eligible automatic approvals as `automation_engine` decisions.

= 1.0.14 =

* Changes the default CSP reporting transport to direct `report-uri` so browser violations reach the local endpoint promptly during learning.
* Adds a Reporting transport setting for opting into Reporting API headers when required.

= 1.0.13 =

* Fixes policy version table creation on MariaDB by avoiding a reserved index name.
* Prevents activation from querying policy version snapshots when the snapshot table could not be created.
* Avoids calling WordPress REST routing too early while activation-time policy snapshots are being built.

= 1.0.12 =

* Normalizes CSP directives so `'none'` is removed when approved sources are merged into the same directive.
* Accepts CSP reports from configured public reporting endpoint and forwarded hostnames so proxied deployments do not silently discard valid browser reports.
* Clarifies that browser violation reports can be collected from either report-only or enforce CSP headers.

= 1.0.11 =

* Makes CSP surface detection path-aware so wp-admin URLs that produce redirects or 404 responses still use the admin surface configuration.

= 1.0.10 =

* Makes the destructive CSP data reset return all surfaces to disabled mode so the plugin stops emitting CSP headers until rollout is deliberately restarted.
* Emits CSP headers before WordPress redirects, making unauthenticated wp-admin redirects easier to diagnose.

= 1.0.9 =

* Adds a configurable origin policy header name for Cloudflare, CDN, and reverse-proxy deployments that copy an origin header back to the browser-facing CSP header.
* Adds schema self-healing so missing CSP tables are recreated even when the stored database version already matches the current schema version.

= 1.0.8 =

* Clarifies the Violations empty state so administrators know manual scans do not create browser violation reports and can see the expected reporting endpoint.

= 1.0.7 =

* Adds a CSP Manager Readiness page for plugin-specific schema, database, reporting endpoint, scan schedule, and automation posture checks.
* Adds a Reset action in the Installed Plugins row that links to a destructive reset panel requiring current administrator password re-authentication and typed confirmation.

= 1.0.6 =

* Adds CSP conflict detection for existing `.htaccess`, server, and security-header-plugin CSP emitters.
* Requires administrator reasons for source approval, rejection, reversion, and undo decisions.
* Adds undo support for approved and rejected source decisions without rewriting history.
* Adds dashboard tab guidance, configurable reporting server URL support, and an Installed Plugins Settings link.

= 1.0.5 =

* Renames the package slug, text domain, main plugin file, release ZIP, and WordPress.org deployment slug to `csp-automation-manager`.
* Updates WordPress.org scanner metadata and includes the declared `languages` directory.

= 1.0.4 =

* Removes the custom runtime update checker and all third-party update manifest polling from the WordPress.org plugin package.
* Removes legacy external-service admin surfaces from the WordPress.org plugin package.
* Makes all shipped CSP capabilities available locally without payment, remote entitlement checks, or trialware-style feature locking.
* Updates package copy and disclosures for WordPress.org guideline alignment.

= 1.0.3 =

* Renames the public plugin display name to `CSP Automation Manager` to comply with WordPress.org plugin naming requirements.

= 1.0.2 =

* Tightens the release package so development-only files, internal policy notes, and local cache files are excluded from distributed ZIP builds.
* Adds release workflow checks that fail if submission-only or development-only files are present in the packaged ZIP.

= 1.0.1 =
* Adds Reporting API headers, forbidden-directive filtering, violation sample persistence, audit logging, policy-change proposals, decision suppression, revert behaviour, violation rollups, policy history, and review APIs.

= 0.2.0 =
* Initial public plugin implementation.
