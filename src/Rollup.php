<?php

namespace Xyo\MostRead;

/**
 * The maintenance engine behind the aggregate model. Three jobs:
 *
 *   run()      — hourly: fold the raw hits of the last couple of days into the
 *                daily aggregate, deduplicating by IP (COUNT(DISTINCT ip)) so a
 *                day counts one visit per IP per post.
 *   backfill() — one-off, chunked: replay ~15 months of legacy raw hits into the
 *                aggregate before the raw rows get pruned away.
 *   prune()    — daily: drop raw rows older than the roll-up buffer and daily
 *                aggregates older than the retention window, in bounded batches
 *                so a huge legacy table never gets locked by a single DELETE.
 *
 * All queries are built with $wpdb->prepare.
 */
class Rollup
{
    /** Re-aggregate this many trailing days each run to catch late-arriving rows. */
    const ROLLUP_WINDOW_DAYS = 2;

    /** Days of history folded into the aggregate per backfill tick. */
    const BACKFILL_CHUNK_DAYS = 30;

    /** Max rows deleted per prune batch. */
    const PRUNE_BATCH = 5000;

    /**
     * Hourly roll-up of recent raw hits into the deduplicated daily aggregate.
     */
    public static function run()
    {
        global $wpdb;

        $raw   = Schema::rawTable();
        $daily = Schema::dailyTable();
        $from  = gmdate('Y-m-d', time() - (self::ROLLUP_WINDOW_DAYS * DAY_IN_SECONDS));

        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO $daily (post_id, day, hits)
                 SELECT post_id, visitdate, COUNT(DISTINCT ip)
                 FROM $raw
                 WHERE visitdate >= %s AND post_id > 0
                 GROUP BY post_id, visitdate
                 ON DUPLICATE KEY UPDATE hits = VALUES(hits)",
                $from
            )
        );

        // Also runs the backfill a chunk at a time until the history is replayed.
        self::backfill();
    }

    /**
     * Replay legacy raw hits into the aggregate, one date range per tick, so the
     * pruner can later discard the raw rows without losing read data. The cursor
     * is the exclusive upper bound of the next chunk and walks backward from
     * today down to the retention edge; cleared when caught up.
     */
    public static function backfill()
    {
        global $wpdb;

        if (get_option('wmrp_backfill_done')) {
            return;
        }

        $cursor = get_option('wmrp_backfill_cursor');
        if (!$cursor) {
            return; // Not armed (handled in Schema::install()).
        }

        $raw   = Schema::rawTable();
        $daily = Schema::dailyTable();

        $floor = gmdate('Y-m-d', time() - (Schema::RETENTION_DAYS * DAY_IN_SECONDS));
        $to    = $cursor;
        $from  = gmdate('Y-m-d', strtotime($cursor) - (self::BACKFILL_CHUNK_DAYS * DAY_IN_SECONDS));
        if ($from < $floor) {
            $from = $floor;
        }

        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO $daily (post_id, day, hits)
                 SELECT post_id, visitdate, COUNT(DISTINCT ip)
                 FROM $raw
                 WHERE visitdate >= %s AND visitdate < %s AND post_id > 0
                 GROUP BY post_id, visitdate
                 ON DUPLICATE KEY UPDATE hits = VALUES(hits)",
                $from,
                $to
            )
        );

        if ($from <= $floor) {
            delete_option('wmrp_backfill_cursor');
            update_option('wmrp_backfill_done', 1, false);

            return;
        }

        update_option('wmrp_backfill_cursor', $from, false);
    }

    /**
     * Daily retention. Deletes are batched (LIMIT) and looped so a multi-million
     * row legacy table is trimmed without a long-held lock.
     */
    public static function prune()
    {
        global $wpdb;

        // Don't trim raw history until it has been folded into the aggregate.
        if (!get_option('wmrp_backfill_done')) {
            return;
        }

        $raw   = Schema::rawTable();
        $daily = Schema::dailyTable();

        $rawCutoff   = gmdate('Y-m-d', time() - (Schema::RAW_BUFFER_DAYS * DAY_IN_SECONDS));
        $dailyCutoff = gmdate('Y-m-d', time() - (Schema::RETENTION_DAYS * DAY_IN_SECONDS));

        self::batchedDelete(
            $wpdb->prepare("DELETE FROM $raw WHERE visitdate < %s LIMIT %d", $rawCutoff, self::PRUNE_BATCH)
        );
        self::batchedDelete(
            $wpdb->prepare("DELETE FROM $daily WHERE day < %s LIMIT %d", $dailyCutoff, self::PRUNE_BATCH)
        );
    }

    /**
     * Run a LIMITed DELETE repeatedly until it stops removing rows. Capped at a
     * sane number of iterations so a single cron tick can't run away.
     */
    private static function batchedDelete($preparedQuery)
    {
        global $wpdb;

        $iterations = 0;
        do {
            $deleted = $wpdb->query($preparedQuery);
            $iterations++;
        } while ($deleted >= self::PRUNE_BATCH && $iterations < 200);
    }
}
