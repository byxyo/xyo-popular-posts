<?php

namespace Xyo\MostRead;

/**
 * Sidebar "Most Read Posts" widget. The widget id_base stays `wmrp_widget` so
 * existing stored widget instances/settings keep working after the class was
 * renamed and namespaced.
 */
class Widget extends \WP_Widget
{
    const _CACHE_TIME = 60;

    /** @var array */
    public $instance = [];
    /** @var array */
    public $output;
    /** @var array */
    public $lastPosts;
    /** @var array */
    public $lastTV;

    public function __construct()
    {
        parent::__construct('wmrp_widget', 'Most Read Posts (Xyo)', array('description' => 'Display most viewed posts'));
    }

    /**
     * @param null $ID
     * @return array|false|string
     */
    public function getPostImage($ID = null)
    {
        if ($ID === null) {
            global $post;
            $ID = $post->ID;
        }

        if (has_post_thumbnail($ID)) {
            $thumbnail_id = get_post_thumbnail_id($ID);
            $post_image = wp_get_attachment_url($thumbnail_id);

        } else {
            $post_image = post_custom('Thumbnail');
        }

        return $post_image;
    }

    /**
     * @param array $args
     * @param array $instance
     */
    public function widget($args, $instance)
    {
        extract($args, EXTR_SKIP);
        $this->instance = $this->default_options($instance);

        $this->getMostRead();
        $this->lastPosts = $this->getLastPosts();
        $this->lastTV = $this->getLastPosts('tv');

        echo $before_widget;
        echo $before_title . $instance['title'] . $after_title;

        $this->viewWidget();
        echo $after_widget;
    }

    /**
     * @param array $instance
     */
    public function form($instance)
    {

        $defaults = $this->default_options($instance);
        ?>
        <div>
            <p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?></label>
                <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>"
                       name="<?php echo $this->get_field_name('title'); ?>"
                       type="text"
                       value="<?php echo esc_attr($defaults['title']); ?>"/>
            </p>

            <p>
                <label for="<?php echo $this->get_field_id('last_days'); ?>"><?php _e('Show most read posts in', 'xmr'); ?></label>
                <input class="" id="<?php echo $this->get_field_id('last_days'); ?>"
                       size="3"
                       name="<?php echo $this->get_field_name('last_days'); ?>"
                       type="text"
                       value="<?php echo esc_attr($defaults['last_days']); ?>"/> <?php _e('days', 'xmr'); ?>

                <label for="<?php echo $this->get_field_id('limit'); ?>"><?php _e('Show', 'xmr'); ?></label>
                <input class="" id="<?php echo $this->get_field_id('limit'); ?>"
                       size="3"
                       name="<?php echo $this->get_field_name('limit'); ?>"
                       type="text"
                       value="<?php echo esc_attr($defaults['limit']); ?>"/> <?php _e('posts in the sidebar', 'xmr'); ?>
            </p>

            <p>
                <input class="checkbox"
                       id="<?php echo $this->get_field_id('show_hits'); ?>"
                       size="3"
                       name="<?php echo $this->get_field_name('show_hits'); ?>"
                       type="checkbox"
                       value="yes" <?php
                if ($defaults['show_hits']) {
                    echo 'checked';
                }
                ?> />
                <label for="<?php echo $this->get_field_id('show_hits'); ?>"><?php _e('Show hits near title', 'xmr'); ?></label>
                <br/>(<?php _e('check this if you want to show hits near the post title in your sidebar', 'xmr'); ?>)
            </p>

            <p>
                <input class="checkbox"
                       id="<?php echo $this->get_field_id('show_thumbnails'); ?>"
                       size="3"
                       name="<?php echo $this->get_field_name('show_thumbnails'); ?>"
                       type="checkbox"
                       value="yes" <?php
                if ($defaults['show_thumbnails']) {
                    echo 'checked';
                }
                ?> />
                <label for="<?php echo $this->get_field_id('show_thumbnails'); ?>"><?php _e('Show thumbnails', 'xmr'); ?></label>
            </p>

            <p>
                <label for="<?php echo $this->get_field_id('thumb_width'); ?>"><?php _e('Indicate thumb\'s width', 'xmr'); ?></label>
                <input class="" id="<?php echo $this->get_field_id('thumb_width'); ?>"
                       size="3"
                       name="<?php echo $this->get_field_name('thumb_width'); ?>"
                       type="text"
                       value="<?php echo $defaults['thumb_width']; ?>"/> <?php _e('px', 'xmr'); ?>


                <label for="<?php echo $this->get_field_id('thumb_height'); ?>"><?php _e('Indicate thumb\'s height', 'xmr'); ?></label>
                <input class="" id="<?php echo $this->get_field_id('thumb_height'); ?>"
                       size="3"
                       name="<?php echo $this->get_field_name('thumb_height'); ?>"
                       type="text"
                       value="<?php echo $defaults['thumb_height']; ?>"/> <?php _e('px', 'xmr'); ?>


                <label for="<?php echo $this->get_field_id('nbchar'); ?>"><?php _e('Indicate thumb\'s characters', 'xmr'); ?></label>
                <input class="" id="<?php echo $this->get_field_id('nbchar'); ?>"
                       size="3"
                       name="<?php echo $this->get_field_name('nbchar'); ?>"
                       type="text"
                       value="<?php echo $defaults['nbchar']; ?>"/> <?php _e('caract&egrave;res', 'xmr'); ?>
            </p>

        </div>
    <?php
    }

    /**
     * @param $instance
     * @return mixed
     */
    private function default_options($instance)
    {

        if (isset($instance['title'])) {
            $options['title'] = esc_attr($instance['title']);
        } else {
            $options['title'] = 'Most Read Posts';
        }

        if (isset($instance['last_days'])) {
            $options['last_days'] = (int) $instance['last_days'];
        } else {
            $options['last_days'] = 5;
        }

        if (isset($instance['limit'])) {
            $options['limit'] = (int) $instance['limit'];
        } else {
            $options['limit'] = 5;
        }

        if (isset($instance['show_hits'])) {
            $options['show_hits'] = (bool) $instance['show_hits'];
        } else {
            $options['show_hits'] = false;
        }

        if (isset($instance['show_thumbnails'])) {
            $options['show_thumbnails'] = (bool) $instance['show_thumbnails'];
        } else {
            $options['show_thumbnails'] = false;
        }

        if (isset($instance['thumb_width'])) {
            $options['thumb_width'] = (int) $instance['thumb_width'];
        } else {
            $options['thumb_width'] = 160;
        }

        if (isset($instance['thumb_height'])) {
            $options['thumb_height'] = (int) $instance['thumb_height'];
        } else {
            $options['thumb_height'] = 100;
        }

        if (isset($instance['nbchar'])) {
            $options['nbchar'] = (int) $instance['nbchar'];
        } else {
            $options['nbchar'] = 100;
        }

        return $options;
    }

    /**
     * @param array $new
     * @param array $old
     * @return array
     */
    public function update($new, $old)
    {
        $instance = wp_parse_args($new, $old);

        return $instance;
    }

    /**
     * Populate $this->output with the most-read ranking from the daily aggregate.
     */
    public function getMostRead()
    {
        global $wpdb;

        $daily = Schema::dailyTable();
        $posts = $wpdb->prefix . 'posts';

        $query = $wpdb->prepare(
            "SELECT d.post_id, SUM(d.hits) AS nb, p.ID, p.post_title
             FROM $daily d
             INNER JOIN $posts AS p ON p.ID = d.post_id
             WHERE d.day >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
               AND p.post_status = 'publish'
               AND p.post_type IN ('post', 'tv')
             GROUP BY d.post_id
             ORDER BY nb DESC
             LIMIT %d",
            (int) $this->instance['last_days'],
            (int) $this->instance['limit']
        );
        $this->output = wp_cache_get('mostread_' . md5($query), 'widget');
        if (!$this->output) {

            $this->output = $wpdb->get_results($query);

            wp_cache_set('mostread_' . md5($query), $this->output, 'widget', self::_CACHE_TIME);
        }
    }

    /**
     * Render the widget markup (template receives $this).
     */
    public function viewWidget()
    {
        require(XYO_MOSTREAD_PATH . 'templates/widget.php');
    }

    /**
     * @param bool $post_type
     * @return array
     */
    public function getLastPosts($post_type = false)
    {

        $args = array('numberposts' => $this->instance['limit']);
        if ($post_type != false) {
            $args['post_type'] = $post_type;
        }

        $lastposts = get_posts($args);

        $datas = array();

        foreach ($lastposts as $post) {
            setup_postdata($post);
            $datas[$post->ID]['title'] = $post->post_title;
            $datas[$post->ID]['link'] = get_permalink($post->ID);
            $thumbnail = wp_get_attachment_image_src(get_post_thumbnail_id($post->ID), 'full-size');
            $datas[$post->ID]['thumbnail']['url'] = $thumbnail[0];
            $datas[$post->ID]['thumbnail']['width'] = $thumbnail[1];
            $datas[$post->ID]['thumbnail']['height'] = $thumbnail[2];
            $datas[$post->ID]['thumbnail']['bool'] = $thumbnail[3];
        }

        return $datas;
    }
}
