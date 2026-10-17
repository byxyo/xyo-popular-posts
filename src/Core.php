<?php

namespace Xyo\MostRead;

use Xyo\MostRead\Tracking\Recorder;

/**
 * Front controller: widget registration, the tracking beacon + its recorder
 * endpoint, the admin bootstrap, and the public "top posts" read query.
 */
class Core
{
    const _CACHE_TIME = 1000;

    public static function widget()
    {
        register_widget(Widget::class);
    }

    /**
     * Emit the tracking beacon. Only fires on singular post/tv views (nothing to
     * track on archives), and uses navigator.sendBeacon with a vanilla fetch
     * fallback — no jQuery dependency, and fire-and-forget so it never blocks the
     * page. The request still carries a nonce for a minimal sanity check.
     */
    public static function javascript()
    {
        if (!is_singular(['post', 'tv'])) {
            return;
        }

        $postId = (int) get_queried_object_id();
        if ($postId <= 0) {
            return;
        }

        $token = wp_create_nonce('wmrp_token');
        $url   = esc_url(admin_url('admin-ajax.php'));
        ?>
        <!-- WordPress Most Read Posts -->
        <script>
        (function () {
            var d = new FormData();
            d.append('action', 'track_post');
            d.append('id', '<?php echo $postId; ?>');
            d.append('token', '<?php echo esc_js($token); ?>');
            var url = '<?php echo $url; ?>';
            if (navigator.sendBeacon) {
                navigator.sendBeacon(url, d);
            } else {
                fetch(url, { method: 'POST', body: d, credentials: 'same-origin', keepalive: true });
            }
        })();
        </script>
        <!-- /WordPress Most Read Posts -->
        <?php
    }

    /**
     * Record a hit. sendBeacon expects no meaningful response, so we keep it
     * terse and never trust missing input.
     */
    public static function track()
    {
        $token = isset($_POST['token']) ? sanitize_text_field(wp_unslash($_POST['token'])) : '';
        if (!wp_verify_nonce($token, 'wmrp_token')) {
            status_header(403);
            wp_send_json(['message' => 'Invalid Token'], 403);
        }

        $postId = isset($_POST['id']) ? absint($_POST['id']) : 0;
        if ($postId <= 0) {
            wp_send_json(['return' => false]);
        }

        new Recorder($postId);
        wp_send_json(['return' => true]);
    }

    public static function admin()
    {
        new Admin();
    }

    /**
     * @param $lastDays
     * @param $nb
     *
     * @return array|object|null
     */
    public static function getTopPostIds($lastDays, $nb)
    {
        global $wpdb;

        $daily = Schema::dailyTable();
        $posts = $wpdb->prefix . 'posts';

        $query = $wpdb->prepare(
            "SELECT d.post_id, SUM(d.hits) AS nb
             FROM $daily d
             INNER JOIN $posts AS p ON p.ID = d.post_id
             WHERE d.day >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
               AND p.post_status = 'publish'
               AND p.post_type IN ('post', 'tv')
             GROUP BY d.post_id
             ORDER BY nb DESC
             LIMIT %d",
            (int) $lastDays,
            (int) $nb
        );

        $result = wp_cache_get('mostread_' . md5($query), 'widget');
        if (!$result) {
            $result = $wpdb->get_results($query);
            wp_cache_set('mostread_' . md5($query), $result, 'widget', self::_CACHE_TIME);
        }

        return $result;
    }
}
