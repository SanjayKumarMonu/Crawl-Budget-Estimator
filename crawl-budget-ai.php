<?php
/**
 * Plugin Name: Crawl Budget Estimator (AI Edition)
 * Description: Estimates AI crawl cost by comparing HTML bloat to visible text.
 * Version: 1.0.0
 * Author: Cursor AI
 * License: GPL-2.0-or-later
 * Text Domain: crawl-budget-ai
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Estimate tokens using 1 token ~= 4 characters.
 *
 * @param string $string Input string.
 * @return int Estimated token count.
 */
function crawl_budget_ai_estimate_tokens($string) {
    $length = function_exists('mb_strlen') ? mb_strlen($string, 'UTF-8') : strlen($string);
    return (int) ceil($length / 4);
}

/**
 * Calculate crawl budget metrics for a post.
 *
 * @param int $post_id Post ID.
 * @return array|null Metrics array or null on failure.
 */
function crawl_budget_ai_calculate_metrics($post_id) {
    $post = get_post($post_id);
    if (!$post) {
        return null;
    }

    $original_post = isset($GLOBALS['post']) ? $GLOBALS['post'] : null;
    $GLOBALS['post'] = $post;
    setup_postdata($post);

    $rendered_content = apply_filters('the_content', $post->post_content);

    wp_reset_postdata();
    if ($original_post) {
        $GLOBALS['post'] = $original_post;
    } else {
        unset($GLOBALS['post']);
    }

    $total_tokens = crawl_budget_ai_estimate_tokens($rendered_content);
    $content_text = strip_tags($rendered_content);
    $content_tokens = crawl_budget_ai_estimate_tokens($content_text);
    $code_bloat = max(0, $total_tokens - $content_tokens);
    $efficiency = $total_tokens > 0 ? ($content_tokens / $total_tokens) * 100 : 0;

    return array(
        'total_tokens'   => $total_tokens,
        'content_tokens' => $content_tokens,
        'code_bloat'     => $code_bloat,
        'efficiency'     => $efficiency,
        'updated_at'     => current_time('mysql'),
    );
}

/**
 * Save metrics on post save.
 *
 * @param int $post_id Post ID.
 */
function crawl_budget_ai_save_post_metrics($post_id) {
    if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
        return;
    }

    $post_type = get_post_type($post_id);
    if (!in_array($post_type, array('post', 'page'), true)) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $metrics = crawl_budget_ai_calculate_metrics($post_id);
    if (!$metrics) {
        return;
    }

    update_post_meta($post_id, '_crawl_budget_ai_metrics', $metrics);
}
add_action('save_post', 'crawl_budget_ai_save_post_metrics', 10, 1);

/**
 * Register the meta box.
 */
function crawl_budget_ai_add_meta_box() {
    add_meta_box(
        'crawl_budget_ai',
        __('AI Crawl Budget', 'crawl-budget-ai'),
        'crawl_budget_ai_render_meta_box',
        array('post', 'page'),
        'side',
        'default'
    );
}
add_action('add_meta_boxes', 'crawl_budget_ai_add_meta_box');

/**
 * Determine verdict based on efficiency.
 *
 * @param float $efficiency Efficiency percentage.
 * @return array Verdict data.
 */
function crawl_budget_ai_get_verdict($efficiency) {
    if ($efficiency > 50) {
        return array(
            'label' => __('Green', 'crawl-budget-ai'),
            'color' => '#2e7d32',
            'text'  => __('Healthy text density', 'crawl-budget-ai'),
        );
    }

    if ($efficiency >= 20) {
        return array(
            'label' => __('Orange', 'crawl-budget-ai'),
            'color' => '#ef6c00',
            'text'  => __('Moderate text density', 'crawl-budget-ai'),
        );
    }

    return array(
        'label' => __('Red', 'crawl-budget-ai'),
        'color' => '#c62828',
        'text'  => __('Too much HTML bloat', 'crawl-budget-ai'),
    );
}

/**
 * Render the meta box UI.
 *
 * @param WP_Post $post Current post object.
 */
function crawl_budget_ai_render_meta_box($post) {
    $metrics = get_post_meta($post->ID, '_crawl_budget_ai_metrics', true);
    if (!is_array($metrics)) {
        $metrics = crawl_budget_ai_calculate_metrics($post->ID);
    }

    if (!is_array($metrics)) {
        echo '<p>' . esc_html__('Save the post to generate AI crawl metrics.', 'crawl-budget-ai') . '</p>';
        return;
    }

    $total_tokens = (int) $metrics['total_tokens'];
    $content_tokens = (int) $metrics['content_tokens'];
    $efficiency = (float) $metrics['efficiency'];
    $efficiency_display = number_format_i18n($efficiency, 1);
    $progress = max(0, min(100, $efficiency));
    $verdict = crawl_budget_ai_get_verdict($efficiency);

    $progress_bar = sprintf(
        '<div style="background:#e0e0e0;border-radius:4px;height:8px;overflow:hidden;margin-top:6px;">
            <div style="background:%1$s;height:8px;width:%2$s%%;"></div>
        </div>',
        esc_attr($verdict['color']),
        esc_attr($progress)
    );

    echo '<div style="font-size:13px;line-height:1.5;">';
    echo '<p><strong>' . esc_html__('Total "Cost":', 'crawl-budget-ai') . '</strong> ' . esc_html(number_format_i18n($total_tokens)) . ' ' . esc_html__('Tokens', 'crawl-budget-ai') . '</p>';
    echo '<p><strong>' . esc_html__('Useful Content:', 'crawl-budget-ai') . '</strong> ' . esc_html(number_format_i18n($content_tokens)) . ' ' . esc_html__('Tokens', 'crawl-budget-ai') . '</p>';
    echo '<p><strong>' . esc_html__('Signal-to-Noise Ratio:', 'crawl-budget-ai') . '</strong> ' . esc_html($efficiency_display) . '%</p>';
    echo $progress_bar;
    echo '<p style="margin-top:8px;"><strong>' . esc_html__('Verdict:', 'crawl-budget-ai') . '</strong> ';
    echo '<span style="color:' . esc_attr($verdict['color']) . ';font-weight:600;">' . esc_html($verdict['label']) . '</span> ';
    echo '<span style="color:#666;">(' . esc_html($verdict['text']) . ')</span></p>';
    echo '</div>';
}
