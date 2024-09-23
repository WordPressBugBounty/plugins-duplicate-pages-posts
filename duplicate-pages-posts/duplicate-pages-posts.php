<?php

namespace DuplicatePagesPosts;

/*
* Plugin Name:       Duplicate Pages, Posts & CPT
* Plugin URI:        https://wp-ninjas.de/plugins/duplicate-pages-posts/
* Description:       Duplicate pages, posts and custom post types with all their settings and contents with a single click.
* Version:           1.3
* Requires at least: 4.0
* Requires PHP:      7.0
* Author:            WP Ninjas - Jonas Tietgen, Ferry Abt
* Author URI:        https://wp-ninjas.de/
* License:           GPL v3
* License URI:       https://www.gnu.org/licenses/gpl-3.0.html
* Text Domain:       duplicate-pages-posts
*/

use WP_Admin_Bar;
use WP_Post;

/**
 * @return void
 */
function init()
{
    add_filter('post_row_actions', 'DuplicatePagesPosts\post_list_link', 10, 2);
    add_filter('page_row_actions', 'DuplicatePagesPosts\post_list_link', 10, 2);
    add_action('admin_action_duplicate_pages_posts_wp_ninjas', 'DuplicatePagesPosts\duplicate');
    add_action('admin_bar_menu', 'DuplicatePagesPosts\admin_bar_link', 100);
    add_action('plugins_loaded', 'DuplicatePagesPosts\load_plugin_textdomain');
}

/**
 * @return void
 */
function load_plugin_textdomain()
{
    \load_plugin_textdomain('duplicate-pages-posts', false, basename(__DIR__) . '/languages/');
}

/**
 * @param array   $actions
 * @param WP_Post $post
 *
 * @return string[]
 */
function post_list_link(array $actions, WP_Post $post): array
{
    if (current_user_can('edit_post', $post->ID)) {
        $query_args           = [
            'action' => 'duplicate_pages_posts_wp_ninjas',
            'post'   => $post->ID,
            'nonce'  => wp_create_nonce('duplicate_pages_posts_wp_ninjas'),
        ];
        $url                  = esc_url(add_query_arg($query_args, admin_url()));
        $link_text            = _x('Duplicate', 'list link', 'duplicate-pages-posts');
        $action               = "<a href='$url'>$link_text</a>";
        $actions['ninja_dup'] = $action;
    }

    return $actions;
}

/**
 * @param WP_Admin_Bar $admin_bar
 *
 * @return void
 */
function admin_bar_link(WP_Admin_Bar $admin_bar)
{
    global $current_screen;
    if (!empty($current_screen) && strpos($current_screen->id, 'edit') !== false) {
        return;
    }

    global $wp_query;
    $not_duplicable = [
        "is_404",
        "is_admin",
        "is_archive",
        "is_attachment",
        "is_author",
        "is_category",
        "is_comment_feed",
        "is_date",
        "is_day",
        "is_embed",
        "is_favicon",
        "is_feed",
        "is_home",
        "is_month",
        "is_paged",
        "is_post_type_archive",
        "is_privacy_policy",
        "is_robots",
        "is_search",
        "is_tag",
        "is_tax",
        "is_time",
        "is_trackback",
        "is_year",
    ];
    foreach ($not_duplicable as $key) {
        if ($wp_query->$key) {
            return;
        }
    }

    $post = get_post();
    if (empty($post) || !current_user_can('edit_post', $post->ID)) {
        return;
    }
    $query_args = [
        'action' => 'duplicate_pages_posts_wp_ninjas',
        'post'   => $post->ID,
        'nonce'  => wp_create_nonce('duplicate_pages_posts_wp_ninjas'),
    ];
    $url        = esc_url(add_query_arg($query_args, admin_url()));
    $icon       = plugins_url('admin/icon.png', __FILE__);
    $text       = _x('Duplicate', 'admin bar', 'duplicate-pages-posts');
    $title      = "<span style='display:inline-block;padding-right:10px;top:6px;position:relative;'>";
    $title      .= "<img src='$icon' style='width:21px; height:21px;'/>";
    $title      .= "</span>";
    $title      .= "<span>$text</span>";
    $admin_bar->add_menu(['id' => 'ninja-cloner', 'title' => $title, 'href' => $url,]);
}

/**
 * @return void
 */
function duplicate()
{
    if (!isset($_GET['action']) || 'duplicate_pages_posts_wp_ninjas' !== $_GET['action']) {
        return;
    }
    if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'duplicate_pages_posts_wp_ninjas')) {
        return;
    }
    if (!isset($_GET['post'])) {
        return;
    }
    $post_id = intval($_GET['post']);
    $post    = get_post($post_id);
    if (empty($post) || !current_user_can('edit_post', $post->ID)) {
        return;
    }
    $duplicate_post_title = $post->post_title . ' - ' . _x('Duplicate', 'post title', 'duplicate-pages-posts');
    $args                 = [
        'post_content'   => $post->post_content,
        'post_title'     => $duplicate_post_title,
        'post_excerpt'   => $post->post_excerpt,
        'post_status'    => 'draft',
        'post_type'      => $post->post_type,
        'comment_status' => $post->comment_status,
        'ping_status'    => $post->ping_status,
        'post_password'  => $post->post_password,
        'post_name'      => $post->post_name,
        'to_ping'        => $post->to_ping,
        'post_parent'    => $post->post_parent,
        'menu_order'     => $post->menu_order,
        'post_mime_type' => $post->post_mime_type,
    ];
    $new_post_id          = wp_insert_post($args);
    foreach (get_post_meta($post_id) as $key => $value) {
        if (is_array($value)) {
            foreach ($value as $value2) {
                $data = @unserialize($value2);
                if ($data !== false) {
                    add_post_meta($new_post_id, $key, $data);
                } else {
                    add_post_meta($new_post_id, $key, wp_slash($value2));
                }
            }
        } else {
            add_post_meta($new_post_id, $key, wp_slash($value));
        }
    }
    $taxonomies = get_object_taxonomies($post->post_type);
    if (!empty($taxonomies) && is_array($taxonomies)) {
        foreach ($taxonomies as $taxonomy) {
            $post_terms = wp_get_object_terms($post_id, $taxonomy, ['fields' => 'slugs']);
            wp_set_object_terms($new_post_id, $post_terms, $taxonomy);
        }
    }
    wp_redirect(admin_url('edit.php?post_type=' . $post->post_type));
}

init();

