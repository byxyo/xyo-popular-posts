<?php

namespace Xyo\MostRead;

/**
 * Builds the data for the homepage sliders: the editor-picked "favorites" and
 * the automatic "most read" list. Reads rank data from the daily aggregate.
 *
 * Note: RedisFactory, NullRedis and FavoritesSlider are theme-level globals, so
 * they are referenced here fully qualified with a leading backslash.
 */
class Slider
{
    const _CACHE_TIME = 60;
    const FAVORITES_SLIDES = '_xyo_favorites_posts';

    public static function getFavorites()
    {
        $slider = new self();
        $redis = \RedisFactory::getInstance();

        if ($redis instanceof \NullRedis) {
            // No usable Redis: read the favorites ids straight from the WP option (CSV).
            $postIds = get_option('_xyo_favorites_posts');
        } else {
            // Redis available: use the cached ids (stored as a JSON array), falling
            // back to the option when that cache is empty.
            $cachedIds = json_decode($redis->get(\FavoritesSlider::FAVORITES_POST_IDS), true);
            $postIds = is_array($cachedIds) && $cachedIds
                ? implode(',', $cachedIds)
                : get_option('_xyo_favorites_posts');
        }

        // Normalise the (possibly option-sourced) id list to a clean set of ints
        // before it ever reaches SQL — never interpolate a raw string into IN().
        $ids = array_filter(array_map('absint', explode(',', (string) $postIds)));
        if (!$ids) {
            return [];
        }

        global $wpdb;
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $query = $wpdb->prepare(
            "SELECT p.ID, p.post_title
             FROM " . $wpdb->prefix . "posts AS p
             WHERE p.ID IN ($placeholders)
             ORDER BY p.ID DESC",
            $ids
        );

        $output = wp_cache_get('slider_favorites_' . md5($query), 'slider');
        if (!$output) {
            $output = $wpdb->get_results($query);
            $output = $slider->getInfosFromPosts($output, 'FavoritesSlideInfos');
            wp_cache_set('slider_favorites_' . md5($query), $output, 'slider');
            $redis->set(self::FAVORITES_SLIDES, json_encode($output));
        }

        return $output;
    }

    /**
     * @param bool $debug
     *
     * @return mixed
     */
    public static function getMostRead($debug = false)
    {
        $slider = new self();
        global $wpdb;

        $daily = Schema::dailyTable();
        $posts = $wpdb->prefix . 'posts';

        $query = $wpdb->prepare(
            "SELECT d.post_id, SUM(d.hits) AS nb, p.ID, p.post_title
             FROM $daily d
             INNER JOIN $posts AS p ON p.ID = d.post_id
             WHERE d.day >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
               AND p.post_status = 'publish'
               AND p.post_type = 'post'
             GROUP BY d.post_id
             ORDER BY nb DESC
             LIMIT %d",
            (int) get_option('_xyo_top_lastdays', 30),
            5
        );

        $output = wp_cache_get('mostread_' . md5($query), 'widget');
        if (!$output) {
            $output = $wpdb->get_results($query);
            $output = $slider->getInfosFromPosts($output, 'MostReadSlideInfos');
            wp_cache_set('mostread_' . md5($query), $output, 'widget', self::_CACHE_TIME);
        }

        if ($debug) {
            var_dump(__CLASS__ . '::' . __FUNCTION__ . ' @ ' . __LINE__);
            var_dump($output);
        }

        return $output;
    }

    /**
     * @param        $output
     *
     * @param string $cacheKey
     *
     * @return array|bool|mixed
     */
    public function getInfosFromPosts($output, $cacheKey = 'html_slide_infos')
    {
        $posts = wp_cache_get('mostread_' . md5($cacheKey), 'slider');

        if (!$posts) {
            $posts = [];
            foreach ($output AS $post) {
                $post->thumbnail = $this->getThumbnail($post->ID, $post);
                $post->permalink = get_permalink($post->ID);
                array_push($posts, $post);
            }

            wp_cache_set('mostread_' . md5($cacheKey), $posts, 'slider', self::_CACHE_TIME);
        }

        return $posts;
    }

    /**
     * @param int $post_id
     * @param     $post
     *
     * @return null|string
     */
    public function getThumbnail($post_id, $post)
    {
        $thumbnail_id = get_post_thumbnail_id($post_id);
        $thumbnail = wp_get_attachment_image_src($thumbnail_id, 'full');
        if (isset($thumbnail[0])) {
            return $thumbnail[0];
        }

        $thumbnail = get_thumbnail(null, null, null, null, null, true, null, $post);

        if (isset($thumbnail['fullpath'])) {
            return $thumbnail['fullpath'];
        }

        return null;
    }
}
