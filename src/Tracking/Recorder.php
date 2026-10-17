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
     * Resolve the real visitor IP as a stable 32-bit key for same-day dedup.
     *
     * The site sits behind Cloudflare, so REMOTE_ADDR is the edge IP — we must
     * read CF-Connecting-IP (then X-Forwarded-For) first. ip2long() only handles
     * IPv4; for IPv6 we fall back to a crc32 fingerprint so distinct visitors
     * still count as distinct in COUNT(DISTINCT ip) without widening the column.
     *
     * @return int
     */
    private function _clientIpKey()
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

        $long = ip2long($ip);

        return $long !== false ? $long : (int) sprintf('%u', crc32($ip));
    }

    /**
     * @return false|int
     */
    private function countHitByPost()
    {
        global $wpdb;

        $ip = $this->_clientIpKey();

        return $wpdb->insert(
            $wpdb->prefix . 'xmr_hits',
            array(
                'post_id' => $this->postID,
                'ip' => $ip,
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
