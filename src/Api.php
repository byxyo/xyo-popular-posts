<?php

namespace Xyo\MostRead;

/**
 * Read-only REST surface consumed by the front end. The primary route base is
 * `xyo/1.0.0`; `bee/1.0.0` is kept registered as a deprecated backward-compat
 * alias so any external client still calling the old routes keeps working.
 * Remove the alias once nothing hits `bee/` anymore.
 */
class Api
{
    const VERSION = '1.0.0';

    /** Primary vendor namespace first, deprecated alias second. */
    const ROUTE_VENDORS = ['xyo', 'bee'];

    /**
     * Api constructor.
     */
    public function __construct()
    {
        foreach (self::ROUTE_VENDORS as $vendor) {
            $this->registerRoutes($vendor . '/' . self::VERSION);
        }
    }

    /**
     * Register the read endpoints under a given base. All three are read-only
     * public data, so the permission callback is explicitly open — this also
     * silences WP 5.5+'s "missing permission_callback" notice.
     *
     * @param string $base
     */
    private function registerRoutes($base)
    {
        register_rest_route($base, 'last/(?P<lastdays>\d+)/nb/(?P<nb>\d+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getTopPostsIds'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route($base, 'favorites', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getFavoritesPosts'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route($base, 'favorites_with_count', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getFavoritesPostsWithCount'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * @param $request
     *
     * @return array|\WP_Error
     */
    public function getTopPostsIds(\WP_REST_Request $request)
    {
        if ($request['nb'] > 20) {
            return new \WP_Error(
                'rest_too_much',
                esc_html__('You ask too much.'),
                ['status' => 'Too much number']
            );
        }

        $topPostIds = Core::getTopPostIds($request['lastdays'], $request['nb']);
        $array = [];
        foreach ($topPostIds as $topPostId) {
            $url = get_permalink($topPostId->post_id);
            $socialCount = new \socialCount($topPostId->post_id, $url);
            $item = ['id' => (int)$topPostId->post_id, 'url' => $url];
            $item['fb_count'] = $socialCount->getCountFromFacebook();
            $array[] = $item;
        }

        return $array;
    }

    /**
     * @return array
     */
    public function getFavoritesPosts()
    {
        $array = [];
        $array['owl'] = [];
        $items = Slider::getFavorites();
        foreach ($items as $item) {
            $array['owl'][] = ['item' => $this->parseItem($item)];
        }

        return $array;
    }

    /**
     * @return array
     */
    public function getFavoritesPostsWithCount()
    {
        $items = Slider::getFavorites();
        foreach ($items as &$item) {
            $socialCount = new \socialCount($item->ID, $item->permalink);
            $item->fb_count = $socialCount->getCountFromFacebook();
        }

        return $items;
    }

    /**
     * @param $item
     *
     * @return string
     */
    public function parseItem($item)
    {
        return '<div class="item" style="background-image:url(' . $item->thumbnail . ')">' .
            '<a href="' . $item->permalink . '"><h3><span>' . $item->post_title . '</span></h3></a>' .
            '</div>';
    }
}
