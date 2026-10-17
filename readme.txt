=== Popular Posts by Xyo ===
Contributors: mrxyo
Tags: popular posts, most read, most viewed, statistics, widget
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Most-read posts ranking built from daily aggregated view counters, deduplicated per visitor and per day.

== Description ==

Popular Posts by Xyo ranks your most-read content from a compact daily counter
instead of scanning an ever-growing log of every page view.

* A lightweight tracking beacon records a view on singular posts, using
  `navigator.sendBeacon` with a `fetch` fallback (no jQuery, fire-and-forget,
  nonce-checked). It does not block page rendering.
* Raw hits are rolled up hourly into a daily aggregate table, deduplicated so a
  given visitor counts once per post per day.
* Rankings (widget + REST API) read only from that aggregate, so they stay fast
  no matter how much history accumulates.

Features:

* "Most Read Posts" sidebar widget.
* Read-only REST API under `xyo/1.0.0` for custom front-ends.
* Self-healing schema: the required tables and scheduled jobs are created and
  kept up to date on upgrade, guarded by a stored version flag.

== Privacy ==

The plugin stores view data on your own server only. Nothing is sent to any
third party.

* What: a per-post, per-day view counter. To deduplicate repeated views, the
  visitor IP address is recorded on the short-lived raw hits table and used to
  compute a distinct-visitor count per day.
* Retention: raw hits (including the IP) are pruned a few days after roll-up;
  the daily aggregate kept afterwards contains only counts, no IP.
* No personal data is shared with third parties.

Suggested privacy-policy text is added to Settings > Privacy via
`wp_add_privacy_policy_content()`.

== Installation ==

1. Upload the plugin folder to `wp-content/plugins/` and activate it.
2. Add the "Most Read Posts" widget to a sidebar, or query the REST API.

Tables and scheduled jobs are set up automatically on activation and reconciled
on every upgrade.

== Frequently Asked Questions ==

= Where are the rankings stored? =

In two custom tables: a short-lived raw hits table and a daily aggregate that is
the source of truth for all "most read" queries.

= Does it slow down my pages? =

No. Tracking is a fire-and-forget beacon sent after the page loads, and reads
hit the small aggregate table.

== Changelog ==

= 2.0.0 =
* Rewritten on a daily aggregated model with hourly roll-up and bounded pruning.
* New read-only REST API (`xyo/1.0.0`).
* Rewritten tracking beacon (sendBeacon, no jQuery).
* Self-healing schema with versioned upgrades.

== Upgrade Notice ==

= 2.0.0 =
Database schema changes. Take a backup before upgrading.
