<?php

namespace Xyo\MostRead;

/**
 * Owns the plugin's data model and its lifecycle. Historically the plugin had
 * no activation hook at all — the `xmr_hits` table was created by hand ~20
 * years ago and then grew one row per page view forever (no retention, no
 * aggregation). This class introduces:
 *
 *   - a compact daily aggregate table ({prefix}xmr_hits_daily) that is the
 *     source of truth for every "most read" read query;
 *   - indexes on the raw {prefix}xmr_hits table so roll-up / prune stay fast;
 *   - two scheduled jobs (roll-up + prune) wired through WP-Cron.
 *
 * Everything here is idempotent and self-healing: maybeUpgrade() runs on every
 * request (cheaply, guarded by a stored version option) so the plugin converges
 * to the right state on deploy even when the activation hook never fires.
 */
class Schema
{
    /** Bump when the schema or cron wiring changes to trigger maybeUpgrade(). */
    const DB_VERSION = '2';
    const OPTION_DB_VERSION = 'wmrp_db_version';

    /** How many days of deduplicated daily aggregates we keep (read horizon + margin). */
    const RETENTION_DAYS = 460;

    /** How many days of raw per-hit rows we keep before pruning (just a roll-up buffer). */
    const RAW_BUFFER_DAYS = 3;

    const CRON_ROLLUP = 'wmrp_rollup';
    const CRON_PRUNE  = 'wmrp_prune';

    /**
     * @return string {prefix}xmr_hits
     */
    public static function rawTable()
    {
        global $wpdb;

        return $wpdb->prefix . 'xmr_hits';
    }

    /**
     * @return string {prefix}xmr_hits_daily
     */
    public static function dailyTable()
    {
        global $wpdb;

        return $wpdb->prefix . 'xmr_hits_daily';
    }

    /**
     * Fired from register_activation_hook(). Builds everything up front.
     */
    public static function activate()
    {
        self::install();
    }

    /**
     * Cheap guard on every load: only does real work when the stored version is
     * stale (fresh install, upgrade) or the cron events went missing.
     */
    public static function maybeUpgrade()
    {
        if (get_option(self::OPTION_DB_VERSION) !== self::DB_VERSION) {
            self::install();

            return;
        }

        // Self-heal the schedules if something (another plugin, a wipe) dropped them.
        if (!wp_next_scheduled(self::CRON_ROLLUP) || !wp_next_scheduled(self::CRON_PRUNE)) {
            self::scheduleJobs();
        }
    }

    /**
     * Create the aggregate table, add the raw-table indexes, schedule the jobs
     * and arm the one-time historical backfill. Safe to run repeatedly.
     */
    public static function install()
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $daily   = self::dailyTable();
        $charset = $wpdb->get_charset_collate();

        // dbDelta handles "create or migrate" for the aggregate table.
        dbDelta(
            "CREATE TABLE $daily (
                post_id BIGINT(20) UNSIGNED NOT NULL,
                day DATE NOT NULL,
                hits INT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY  (post_id, day),
                KEY day (day)
            ) $charset;"
        );

        self::ensureRawIndexes();
        self::scheduleJobs();

        // Arm the historical backfill once so pruning the raw table never loses
        // read data. The cursor is the exclusive upper bound of the next chunk
        // and walks *backward* from today, so the most recent days (the ones the
        // widgets and homepage actually rank) populate first.
        if (get_option('wmrp_backfill_done') === false
            && get_option('wmrp_backfill_cursor') === false
        ) {
            update_option('wmrp_backfill_cursor', gmdate('Y-m-d'), false);
        }

        update_option(self::OPTION_DB_VERSION, self::DB_VERSION, false);
    }

    /**
     * The raw table pre-dates this code and may have no usable index for the
     * roll-up GROUP BY or the prune range scan. Add them if absent (ADD INDEX
     * IF NOT EXISTS isn't portable, so we probe SHOW INDEX first).
     *
     * On a large legacy table this is a one-off cost; prefer running it via
     * WP-CLI (`wp eval 'Xyo\MostRead\Schema::install();'`) rather than a web
     * request.
     */
    private static function ensureRawIndexes()
    {
        global $wpdb;

        $raw = self::rawTable();
        if ($wpdb->get_var("SHOW TABLES LIKE '$raw'") !== $raw) {
            return; // Nothing to index yet (brand-new site).
        }

        $existing = $wpdb->get_col("SHOW INDEX FROM $raw", 2); // column 2 = Key_name
        $existing = is_array($existing) ? array_unique($existing) : [];

        if (!in_array('visitdate_post', $existing, true)) {
            $wpdb->query("ALTER TABLE $raw ADD INDEX visitdate_post (visitdate, post_id)");
        }
    }

    /**
     * Register the recurring jobs. wp_schedule_event() is a no-op when the hook
     * is already scheduled, so this is safe to call again.
     */
    public static function scheduleJobs()
    {
        if (!wp_next_scheduled(self::CRON_ROLLUP)) {
            wp_schedule_event(time() + 300, 'hourly', self::CRON_ROLLUP);
        }
        if (!wp_next_scheduled(self::CRON_PRUNE)) {
            wp_schedule_event(time() + 600, 'daily', self::CRON_PRUNE);
        }
    }

    /**
     * Fired from register_deactivation_hook(): stop the jobs, keep the data.
     */
    public static function deactivate()
    {
        wp_clear_scheduled_hook(self::CRON_ROLLUP);
        wp_clear_scheduled_hook(self::CRON_PRUNE);
    }
}
