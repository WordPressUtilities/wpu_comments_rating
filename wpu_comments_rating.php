<?php
/*
Plugin Name: WPU Comments Rating
Plugin URI: https://github.com/WordPressUtilities/wpu_comments_rating
Update URI: https://github.com/WordPressUtilities/wpu_comments_rating
Description: Allow users to rate in comments.
Version: 0.6.2
Author: Darklg
Author URI: https://darklg.me/
Text Domain: wpu_comments_rating
Domain Path: /lang
Requires at least: 6.2
Requires PHP: 8.0
Network: Optional
License: MIT License
License URI: https://opensource.org/licenses/MIT
Thanks to: https://www.cssigniter.com/add-rating-wordpress-comment-system
*/

if (!defined('ABSPATH')) {
    exit();
}

class WPUCommentsRating {
    private $plugin_version = '0.6.2';
    private $plugin_description;
    private $post_types;
    private $rating_required = false;
    private $form_displayed = false;
    private $max_rating = 5;
    private $star_icon_vote = '';
    private $star_icon_empty = '';
    private $star_icon_full = '';
    private $star_icon_full_admin = '';
    private $star_icon_empty_admin = '';

    public function __construct() {
        add_action('plugins_loaded', array(&$this, 'plugins_loaded'));
        add_action('init', array(&$this, 'load_translation'));
        # Assets
        add_action('admin_enqueue_scripts', array(&$this, 'admin_enqueue_scripts'));
        add_action('wp_enqueue_scripts', array(&$this, 'wp_enqueue_scripts'));

        # Display rating form
        add_action('comment_form_logged_in_after', array(&$this, 'display_rating_form'));
        add_action('comment_form_after_fields', array(&$this, 'display_rating_form'));

        # Save & update rating
        add_action('comment_post', array($this, 'save_rating'), 10, 2);
        add_action('save_post', function ($post_id) {
            if (in_array(get_post_type($post_id), $this->post_types)) {
                $this->update_post_rating($post_id);
            }
        });
        add_action('edit_comment', function ($comment_ID, $data) {
            if ($data['comment_approved'] == '1') {
                $this->update_post_rating($data['comment_post_ID']);
            }
        }, 99, 2);
        add_action('delete_comment', function ($comment_ID, $comment) {
            $this->update_post_rating($this->get_comment_parent($comment_ID));
        }, 99, 2);
        add_action('wp_set_comment_status', function ($comment_ID, $status) {
            $this->update_post_rating($this->get_comment_parent($comment_ID));
        }, 99, 2);
        add_filter('preprocess_comment', function ($commentdata) {
            if (!$this->rating_required) {
                return $commentdata;
            }
            $post_type = get_post_type($commentdata['comment_post_ID']);
            if (!in_array($post_type, $this->post_types)) {
                return $commentdata;
            }
            if (!isset($_POST['rating']) || !ctype_digit($_POST['rating']) || $_POST['rating'] < 1 || $_POST['rating'] > $this->max_rating) {
                wp_die(__('Error: You must provide a valid rating.', 'wpu_comments_rating'));
            }
            return $commentdata;
        });

        # Add metabox to post types
        add_action('add_meta_boxes', array(&$this, 'add_metaboxes'));

        # Display rating in comments
        add_filter('comment_text', array($this, 'comment_text'), 10, 3);

        /* Edit rating in comment page */
        add_action('add_meta_boxes_comment', array(&$this, 'add_meta_boxes_comment'));
        add_action('edit_comment', function ($comment_id) {
            if (!isset($_POST['rating']) || !ctype_digit($_POST['rating']) || $_POST['rating'] < 1 || $_POST['rating'] > $this->max_rating) {
                return;
            }
            update_comment_meta($comment_id, 'wpu_comment_rating', intval($_POST['rating']));
        }, 10, 2);

        /* Disable comment form novalidate if rating is enabled */
        add_action('wp_footer', function () {
            if (!$this->form_displayed || !$this->rating_required) {
                return;
            }
            echo "<script>";
            echo "document.addEventListener('DOMContentLoaded',function(){'use strict';document.querySelectorAll('.comment-form[novalidate]').forEach(function(_f){_f.removeAttribute('novalidate')})});";
            echo "</script>";
        }, 999);
    }

    public function plugins_loaded() {
        $this->post_types = apply_filters('wpu_comments_rating__post_types', array('post'));
        $this->star_icon_vote = apply_filters('wpu_comments_rating__star_icon_vote', '&#11088;');
        $this->star_icon_empty = apply_filters('wpu_comments_rating__star_icon_empty', '&#9734;');
        $this->star_icon_full = apply_filters('wpu_comments_rating__star_icon_full', '&#9733;');
        $this->star_icon_full_admin = apply_filters('wpu_comments_rating__star_icon_full_admin', '<span class="dashicons dashicons-star-filled"></span>');
        $this->star_icon_empty_admin = apply_filters('wpu_comments_rating__star_icon_empty_admin', '<span class="dashicons dashicons-star-empty"></span>');
        $this->max_rating = apply_filters('wpu_comments_rating__max_rating', 5);
        $this->rating_required = apply_filters('wpu_comments_rating__rating_required', false);
    }

    /* ----------------------------------------------------------
      Plugin base
    ---------------------------------------------------------- */

    public function load_translation() {
        $lang_dir = dirname(plugin_basename(__FILE__)) . '/lang/';
        if (strpos(__DIR__, 'mu-plugins') !== false) {
            load_muplugin_textdomain('wpu_comments_rating', $lang_dir);
        } else {
            load_plugin_textdomain('wpu_comments_rating', false, $lang_dir);
        }
        $this->plugin_description = __('Allow users to rate in comments.', 'wpu_comments_rating');
    }

    public function admin_enqueue_scripts() {
        /* Back Style */
        wp_register_style('wpu_comments_rating_back_style', plugins_url('assets/note.css', __FILE__), array(), $this->plugin_version);
        wp_enqueue_style('wpu_comments_rating_back_style');
    }

    public function wp_enqueue_scripts() {
        /* Front Style */
        wp_register_style('wpu_comments_rating_front_style', plugins_url('assets/note.css', __FILE__), array(), $this->plugin_version);
        wp_enqueue_style('wpu_comments_rating_front_style');
        /* Front Script */
        wp_register_script('wpu_comments_rating_front_script', plugins_url('assets/note.js', __FILE__), array('jquery'), $this->plugin_version);
        wp_enqueue_script('wpu_comments_rating_front_script');
    }

    /* ----------------------------------------------------------
      Form
    ---------------------------------------------------------- */

    public function display_rating_form() {
        if (!in_array(get_post_type(), $this->post_types)) {
            return;
        }
        $this->form_displayed = true;
        echo '<fieldset class="comments-rating">';

        /* Label */
        echo '<label for="rating">';
        echo __('Global note', 'wpu_comments_rating');
        if ($this->rating_required) {
            echo ' <span class="required">*</span>';
        }
        echo '</label>';

        /* Rating */
        echo '<span class="rating-container">';
        for ($i = 1; $i <= $this->max_rating; $i++):
            echo '<span class="rating-item rating-item-' . $i . '">';
            echo '<span><input ' . ($this->rating_required ? 'required' : '') . ' type="radio" id="rating-' . esc_attr($i) . '" name="rating" value="' . esc_attr($i) . '" /></span>';
            echo '<label for="rating-' . esc_attr($i) . '">' . $this->star_icon_vote . '</label>';
            echo '</span>';
        endfor;
        echo '</span>';

        echo '</fieldset>';
    }

    /* ----------------------------------------------------------
      Rating actions
    ---------------------------------------------------------- */

    public function save_rating($comment_id, $comment_approved) {

        if (!isset($_POST['rating'])) {
            return;
        }
        if (!ctype_digit($_POST['rating']) || $_POST['rating'] < 1 || $_POST['rating'] > $this->max_rating) {
            return;
        }

        /* Get parent post */
        $post = get_post($this->get_comment_parent($comment_id));

        if (!$post || !$post->ID) {
            return;
        }

        $post_type = get_post_type($post->ID);
        if (!in_array($post_type, $this->post_types)) {
            return;
        }

        add_comment_meta($comment_id, 'wpu_comment_rating', intval($_POST['rating']));
        $this->update_post_rating($post->ID);
    }

    public function update_post_rating($post_id) {
        global $wpdb;

        /* Average rating */
        $q = $wpdb->prepare("SELECT AVG(meta_value) FROM $wpdb->commentmeta WHERE meta_key = 'wpu_comment_rating'  AND comment_id  IN(SELECT comment_ID FROM $wpdb->comments WHERE  comment_approved='1' && comment_post_ID=%d)", $post_id);
        $median = $wpdb->get_var($q);
        if (!is_numeric($median)) {
            $median = 0;
        }
        update_post_meta($post_id, 'wpu_post_rating', round($median, 2));

        /* Number of ratings */
        $q = $wpdb->prepare("SELECT COUNT(*) FROM $wpdb->commentmeta WHERE meta_key = 'wpu_comment_rating'  AND comment_id  IN(SELECT comment_ID FROM $wpdb->comments WHERE  comment_approved='1' && comment_post_ID=%d)", $post_id);
        $count = $wpdb->get_var($q);
        if (is_numeric($count)) {
            update_post_meta($post_id, 'wpu_post_rating_count', $count);
        }
    }

    public function get_comment_parent($comment_id) {
        $comment = get_comment($comment_id);
        if (!$comment) {
            return false;
        }
        return $comment->comment_post_ID;
    }

    /* Display rating */

    public function comment_text($comment_text, $comment, $args) {
        $rating = get_comment_meta($comment->comment_ID, 'wpu_comment_rating', true);
        $rating_html = '';

        if ($rating) {
            $rating_html = '<div class="comment-rating">' . $this->comments_get_rating_html($rating) . '</div>';
        }

        $rating_position = apply_filters('wpu_comments_rating__rating_position', 'before');

        if ($rating_position == 'before') {
            return $rating_html . $comment_text;
        }
        if ($rating_position == 'after') {
            return $comment_text . $rating_html;
        }

        return $comment_text;
    }

    /* ----------------------------------------------------------
      Metabox
    ---------------------------------------------------------- */

    public function add_metaboxes() {
        foreach ($this->post_types as $post_type) {
            add_meta_box(
                'wpu_comments_rating',
                __('Rating', 'wpu_comments_rating'),
                array(&$this, 'display_metabox'),
                $post_type,
                'side',
                'default'
            );
        }
    }

    public function display_metabox($post) {
        /* Display post rating */
        echo $this->comments_get_post_rating_html($post->ID);
        $rating = get_post_meta($post->ID, 'wpu_post_rating', true);
        echo wpautop(sprintf(__('Rating : %s', 'wpu_comments_rating'), $rating));

        /* Display number of ratings */
        $rating_count = $this->get_post_rating_count($post->ID);
        echo wpautop(sprintf(__('Number of ratings : %d', 'wpu_comments_rating'), $rating_count));

    }

    public function get_post_rating_count($post_id) {
        $rating_count = get_post_meta($post_id, 'wpu_post_rating_count', true);
        if (!$rating_count) {
            $comments = get_comments(array(
                'post_id' => $post_id,
                'status' => 'approve',
                'meta_key' => 'wpu_comment_rating'
            ));
            $rating_count = count($comments);
            update_post_meta($post_id, 'wpu_post_rating_count', $rating_count);
        }
        if (!$rating_count) {
            $rating_count = 0;
        }
        return intval($rating_count, 10);
    }

    public function comments_get_post_rating_html($post_id) {
        $note = get_post_meta($post_id, 'wpu_post_rating', 1);
        if (!$note) {
            return '';
        }
        return $this->comments_get_rating_html($note, 'post');
    }

    public function comments_get_rating_html($note, $type = 'comment') {
        if (!$note) {
            return '';
        }
        $width = $note / $this->max_rating * 100;
        $html = '';
        $stars_filled = '';
        $stars_empty = '';

        for ($i = 0; $i < $this->max_rating; $i++) {
            $stars_filled .= is_admin() ? $this->star_icon_full_admin : $this->star_icon_full;
            $stars_empty .= is_admin() ? $this->star_icon_empty_admin : $this->star_icon_empty;
        }

        $ratings_schema = 'itemscope itemtype="https://schema.org/AggregateRating"';
        if ($type == 'comment') {
            $ratings_schema = 'itemprop="reviewRating" itemscope itemtype="https://schema.org/Rating"';
        }

        $html .= '<div ' . $ratings_schema . ' class="wpu-comment-note__wrapper">';
        $html .= '<meta itemprop="worstRating" content="1">';
        $html .= '<meta itemprop="bestRating" content="' . $this->max_rating . '">';
        $html .= '<div class="wpu-comment-note__metas"><span itemprop="ratingValue">' . $note . '</span>/' . $this->max_rating . '</div>';
        $html .= '<div class="wpu-comment-note" title="' . $note . '/' . $this->max_rating . '">';
        $html .= '<div class="wpu-comment-note__bg">' . $stars_empty . '</div>';
        $html .= '<div class="wpu-comment-note__val" style="width: ' . $width . '%">' . $stars_filled . '</div>';
        $html .= '</div>';
        $html .= '</div>';
        return $html;
    }

    public function add_meta_boxes_comment($comment) {
        add_meta_box('wpu_comment_rating', __('Rating', 'wpu_comments_rating'), array(&$this, 'wpu_comment_rating'), null, 'normal', 'high');
    }

    public function wpu_comment_rating($comment) {
        $rating = get_comment_meta($comment->comment_ID, 'wpu_comment_rating', true);
        echo '<fieldset class="comments-rating">';
        echo '<span class="rating-container">';
        for ($i = 1; $i <= $this->max_rating; $i++):
            echo '<span style="margin-right:0.75em;line-height:2;" class="rating-item-' . $i . '">';
            echo '<span><input type="radio" id="rating-' . esc_attr($i) . '" name="rating" value="' . esc_attr($i) . '" ' . checked($rating, $i, false) . ' /></span>';
            echo '<label style="vertical-align: middle;" for="rating-' . esc_attr($i) . '">' . $this->star_icon_full_admin . '</label>';
            echo '</span>';
        endfor;
        echo '</span>';
        echo '</fieldset>';
    }

}

$WPUCommentsRating = new WPUCommentsRating();

/* ----------------------------------------------------------
  Front helper
---------------------------------------------------------- */

/**
 * Get rating for a post
 * @param boolean $post_id
 * @return float
 */
function wpu_comments_rating__get_rating($post_id = false) {
    if (!$post_id) {
        $post_id = get_the_ID();
    }
    return get_post_meta($post_id, 'wpu_post_rating', true);
}

/**
 * Get HTML rating for a post
 *
 * @param boolean $post_id
 * @return string HTML
 */
function wpu_comments_rating__get_rating_html($post_id = false) {
    if (!$post_id) {
        $post_id = get_the_ID();
    }
    global $WPUCommentsRating;
    return $WPUCommentsRating->comments_get_post_rating_html($post_id);
}

/**
 * Get number of ratings for a post
 * @param boolean $post_id
 * @return int
 */
function wpu_comments_rating__get_rating_count($post_id = false) {
    if (!$post_id) {
        $post_id = get_the_ID();
    }
    global $WPUCommentsRating;
    return $WPUCommentsRating->get_post_rating_count($post_id);
}

/**
 * Get HTML rating for a comment
 *
 * @param object $comment
 * @return string HTML
 */
function wpu_comments_rating__get_comment_rating_html($comment) {
    global $WPUCommentsRating;
    return $WPUCommentsRating->comments_get_rating_html(wpu_comments_rating__get_comment_rating($comment), 'comment');
}

/**
 * Get rating for a comment
 * @param object $comment
 * @return float
 */
function wpu_comments_rating__get_comment_rating($comment) {
    return get_comment_meta($comment->comment_ID, 'wpu_comment_rating', true);
}
