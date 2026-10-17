<?php

namespace Xyo\MostRead\Tracking;

/**
 * Records a single raw hit into {prefix}xmr_hits. The roll-up later folds these
 * rows into the deduplicated daily aggregate, so this stays deliberately small.
 */
class Recorder
{
    const _DATE_FULL_FORMAT = 'Y-m-d H:i:s';
    const _DATE_FORMAT = 'Y-m-d';
    const _TIME_FORMAT = 'H:i:s';

    /** @var int */
    private $postID;

    /**
     * @param int $post_ID
     */
    public function __construct($post_ID)
    {
        $this->postID = (int) $post_ID;

        $this->countHitByPost();
    }

    /**
     * @param string $type
     * @return false|string
     */
    private function _getNow($type = 'full')
    {
        if ($type === 'date') {
            return date(self::_DATE_FORMAT);
        } elseif ($type === 'time') {
            return date(self::_TIME_FORMAT);
        } else {
            return date(self::_DATE_FULL_FORMAT);
        }
    }

    /**
     * Pseudonymous same-day dedup key — never the raw IP.
     *
     * We resolve the real visitor IP (the site sits behind Cloudflare, so read
     * CF-Connecting-IP then X-Forwarded-For before REMOTE_ADDR), then return the
     * top 32 bits of HMAC-SHA256(ip|user-agent) keyed by a secret salt that
     * rotates every day. Keeping 32 bits matches the old ip2long key width, so
     * COUNT(DISTINCT ip) dedup precision is unchanged — but the value is not
     * reversible: the salt is secret and overwritten at midnight, so yesterday's
     * keys can no longer be recomputed from an IP. Works the same for IPv4/IPv6.
     *
     * @return int
     */
    private function _visitorKey()
    {
        $ip = '';
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $header) {
            if (!empty($_SERVER[$header])) {
                // X-Forwarded-For may be a comma-separated chain; take the first.
                $candidate = trim(explode(',', wp_unslash($_SERVER[$header]))[0]);
                if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                    $ip = $candidate;
                    break;
                }
            }
        }

        if ($ip === '') {
            return 0;
        }

        $ua = isset($_SERVER['HTTP_USER_AGENT'])
            ? wp_unslash($_SERVER['HTTP_USER_AGENT'])
            : '';

        $hash = hash_hmac('sha256', $ip . '|' . $ua, $this->_daySalt());

        return (int) hexdec(substr($hash, 0, 8));
    }

    /**
     * Secret HMAC salt that changes at midnight (site timezone). Stored in a
     * single, non-autoloaded option that is overwritten on rotation, so the
     * previous day's salt is irrecoverable and old visitor keys cannot be
     * recomputed.
     *
     * @return string
     */
    private function _daySalt()
    {
        $today = wp_date('Y-m-d');
        $data  = get_option('wmrp_day_salt');

        if (!is_array($data) || ($data['day'] ?? '') !== $today) {
            $data = array(
                'day'  => $today,
                'salt' => bin2hex(random_bytes(32)),
            );
            update_option('wmrp_day_salt', $data, false);
        }

        return $data['salt'];
    }

    /**
     * @return false|int
     */
    private function countHitByPost()
    {
        global $wpdb;

        // The 'ip' column now holds a daily-rotating pseudonymous token (see
        // _visitorKey()), not an IP address. Kept as the column name to avoid an
        // ALTER on the legacy table; COUNT(DISTINCT ip) dedup is unchanged.
        $visitor = $this->_visitorKey();

        return $wpdb->insert(
            $wpdb->prefix . 'xmr_hits',
            array(
                'post_id' => $this->postID,
                'ip' => $visitor,
                'visitdate' => $this->_getNow('date'),
                'visittime' => $this->_getNow('time'),
                'visitstamp' => $this->_getNow()
            ),
            array(
                '%d',
                '%d',
                '%s',
                '%s',
                '%s'
            )
        );
    }
}
