<?php

/**
 * Most Read Posts widget view. Included from Xyo\MostRead\Widget::viewWidget()
 * so $this is the widget instance. This file stays in the global namespace, so
 * the WordPress/template functions below resolve directly.
 */

$thumbBase = site_url() . '/media/cache/timthumb.php';
$thumbW    = (int) $this->instance['thumb_width'];
$thumbH    = (int) $this->instance['thumb_height'];

echo '<ul class="nav mostread clearfix"><li class="btn" id="mostread" data-wmrp="mostread">Populaires <i class="fa fa-angle-down"></i></li><li class="btn" id="lastposts" data-wmrp="lastposts">R&eacute;cents <i class="fa fa-angle-down"></i></li></ul>';

if ($this->output) {
    echo '<ul class="posts-list" id="list-mostread" data-wmrp="mostread">';

    foreach ($this->output as $row) {
        $permalink = get_permalink($row->post_id);
        $title     = $row->post_title;

        echo '<li class="post-item">';
        echo '<a title="' . esc_attr($title) . '" href="' . esc_url($permalink) . '">';
        echo '<span class="hover-img"></span>';

        if ($this->instance['show_thumbnails']) {
            $image   = $this->getPostImage($row->post_id);
            $thumb   = add_query_arg(
                ['src' => $image, 'zc' => 1, 'w' => $thumbW, 'h' => $thumbH],
                $thumbBase
            );
            echo '<img src="' . esc_url($thumb) . '" alt="" />';
        }

        $print_views = $this->instance['show_hits'] ? ' (' . (int) $row->nb . ')' : '';

        echo '<span class="title-img"><span class="text">' . esc_html($title) . esc_html($print_views) . '</span></span></a>';
        echo '</li>';
    }
    echo '</ul>';
} else {
    echo '<ul class="posts-list" id="list-mostread"><li>' . esc_html__('No results available', 'most_read') . '</li></ul>';
}

if ($this->lastPosts) {
    echo '<ul class="posts-list" id="list-lastposts" data-wmrp="lastposts">';

    foreach ($this->lastPosts as $row) {
        $thumb = add_query_arg(
            ['src' => $row['thumbnail']['url'], 'zc' => 1, 'w' => $thumbW, 'h' => $thumbH],
            $thumbBase
        );

        echo '<li class="post-item"><a href="' . esc_url($row['link']) . '">';
        echo '<img src="' . esc_url($thumb) . '" alt="" />';
        echo '<span class="title-img"><span class="text">' . esc_html($row['title']) . '</span></span></a></li>';
    }

    echo '</ul>';
}
