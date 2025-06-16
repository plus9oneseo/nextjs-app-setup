<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add custom cron schedules
 *
 * @param array $schedules Existing schedules
 * @return array Modified schedules
 */
function msa_add_cron_schedules($schedules) {
    $schedules['every_5_minutes'] = array(
        'interval' => 300,
        'display' => __('Every 5 minutes', 'msa-automatic')
    );

    $schedules['every_15_minutes'] = array(
        'interval' => 900,
        'display' => __('Every 15 minutes', 'msa-automatic')
    );

    $schedules['every_30_minutes'] = array(
        'interval' => 1800,
        'display' => __('Every 30 minutes', 'msa-automatic')
    );

    return $schedules;
}
add_filter('cron_schedules', 'msa_add_cron_schedules');

/**
 * Add meta boxes to campaign edit screen
 */
function msa_add_meta_boxes() {
    add_meta_box(
        'msa_campaign_settings',
        __('Campaign Settings', 'msa-automatic'),
        'msa_render_campaign_settings',
        'msa_campaign',
        'normal',
        'high'
    );

    add_meta_box(
        'msa_campaign_status',
        __('Campaign Status', 'msa-automatic'),
        'msa_render_campaign_status',
        'msa_campaign',
        'side',
        'high'
    );
}
add_action('add_meta_boxes_msa_campaign', 'msa_add_meta_boxes');

/**
 * Save campaign meta data
 *
 * @param int $post_id Post ID
 * @param WP_Post $post Post object
 */
function msa_save_campaign_meta($post_id, $post) {
    if (!isset($_POST['msa_campaign_nonce']) || 
        !wp_verify_nonce($_POST['msa_campaign_nonce'], 'msa_save_campaign')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if ($post->post_type !== 'msa_campaign') {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Save fetcher settings
    if (isset($_POST['msa_fetcher_type'])) {
        update_post_meta($post_id, '_msa_fetcher_type', sanitize_text_field($_POST['msa_fetcher_type']));
    }

    if (isset($_POST['msa_fetcher_settings'])) {
        update_post_meta($post_id, '_msa_fetcher_settings', msa_sanitize_array($_POST['msa_fetcher_settings']));
    }

    // Save translator settings
    if (isset($_POST['msa_enable_translation'])) {
        update_post_meta($post_id, '_msa_enable_translation', true);
    } else {
        delete_post_meta($post_id, '_msa_enable_translation');
    }

    if (isset($_POST['msa_translator_type'])) {
        update_post_meta($post_id, '_msa_translator_type', sanitize_text_field($_POST['msa_translator_type']));
    }

    if (isset($_POST['msa_translator_settings'])) {
        update_post_meta($post_id, '_msa_translator_settings', msa_sanitize_array($_POST['msa_translator_settings']));
    }

    if (isset($_POST['msa_target_language'])) {
        update_post_meta($post_id, '_msa_target_language', sanitize_text_field($_POST['msa_target_language']));
    }

    // Save schedule settings
    if (isset($_POST['msa_schedule_type'])) {
        update_post_meta($post_id, '_msa_schedule_type', sanitize_text_field($_POST['msa_schedule_type']));
    }

    if (isset($_POST['msa_schedule_time'])) {
        update_post_meta($post_id, '_msa_schedule_time', sanitize_text_field($_POST['msa_schedule_time']));
    }

    if (isset($_POST['msa_recurring_interval'])) {
        update_post_meta($post_id, '_msa_recurring_interval', sanitize_text_field($_POST['msa_recurring_interval']));
    }

    // Save post settings
    if (isset($_POST['msa_post_status'])) {
        update_post_meta($post_id, '_msa_post_status', sanitize_text_field($_POST['msa_post_status']));
    }

    if (isset($_POST['msa_post_category'])) {
        update_post_meta($post_id, '_msa_post_category', absint($_POST['msa_post_category']));
    }

    if (isset($_POST['msa_post_author'])) {
        update_post_meta($post_id, '_msa_post_author', absint($_POST['msa_post_author']));
    }

    // Save template
    if (isset($_POST['msa_template'])) {
        update_post_meta($post_id, '_msa_template', wp_kses_post($_POST['msa_template']));
    }

    // Save filters
    if (isset($_POST['msa_filters'])) {
        update_post_meta($post_id, '_msa_filters', msa_sanitize_array($_POST['msa_filters']));
    }

    // Save status
    if (isset($_POST['msa_status'])) {
        update_post_meta($post_id, '_msa_status', sanitize_text_field($_POST['msa_status']));
    }
}
add_action('save_post', 'msa_save_campaign_meta', 10, 2);

/**
 * Handle AJAX campaign run request
 */
function msa_ajax_run_campaign() {
    check_ajax_referer('msa_automatic_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('Permission denied', 'msa-automatic'));
    }

    $campaign_id = isset($_POST['campaign_id']) ? absint($_POST['campaign_id']) : 0;
    if (!$campaign_id) {
        wp_send_json_error(__('Invalid campaign ID', 'msa-automatic'));
    }

    $result = msa_campaign_processor()->process_campaign($campaign_id);
    if (is_wp_error($result)) {
        wp_send_json_error($result->get_error_message());
    }

    wp_send_json_success(__('Campaign processed successfully', 'msa-automatic'));
}
add_action('wp_ajax_msa_run_campaign', 'msa_ajax_run_campaign');

/**
 * Handle AJAX connection test request
 */
function msa_ajax_test_connection() {
    check_ajax_referer('msa_automatic_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('Permission denied', 'msa-automatic'));
    }

    $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';
    $settings = isset($_POST['settings']) ? msa_sanitize_array($_POST['settings']) : array();

    if (empty($type)) {
        wp_send_json_error(__('Invalid type', 'msa-automatic'));
    }

    if (isset($_POST['service']) && $_POST['service'] === 'translator') {
        $result = msa_translator_loader()->test_connection($type, $settings);
    } else {
        $result = msa_fetcher_loader()->test_connection($type, $settings);
    }

    if (is_wp_error($result)) {
        wp_send_json_error($result->get_error_message());
    }

    wp_send_json_success(__('Connection test successful', 'msa-automatic'));
}
add_action('wp_ajax_msa_test_connection', 'msa_ajax_test_connection');

/**
 * Handle AJAX clear logs request
 */
function msa_ajax_clear_logs() {
    check_ajax_referer('msa_automatic_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('Permission denied', 'msa-automatic'));
    }

    $level = isset($_POST['level']) ? sanitize_text_field($_POST['level']) : null;
    $count = msa_logger()->clear_logs($level);

    wp_send_json_success(sprintf(
        __('%d logs cleared', 'msa-automatic'),
        $count
    ));
}
add_action('wp_ajax_msa_clear_logs', 'msa_ajax_clear_logs');

/**
 * Sanitize array recursively
 *
 * @param array $array Array to sanitize
 * @return array Sanitized array
 */
function msa_sanitize_array($array) {
    if (!is_array($array)) {
        return sanitize_text_field($array);
    }

    foreach ($array as $key => $value) {
        if (is_array($value)) {
            $array[$key] = msa_sanitize_array($value);
        } else {
            $array[$key] = sanitize_text_field($value);
        }
    }

    return $array;
}

/**
 * Get campaign status options
 *
 * @return array Status options
 */
function msa_get_campaign_statuses() {
    return array(
        'active' => __('Active', 'msa-automatic'),
        'paused' => __('Paused', 'msa-automatic'),
        'completed' => __('Completed', 'msa-automatic'),
        'failed' => __('Failed', 'msa-automatic'),
        'draft' => __('Draft', 'msa-automatic')
    );
}

/**
 * Get campaign schedule types
 *
 * @return array Schedule types
 */
function msa_get_schedule_types() {
    return array(
        'manual' => __('Manual', 'msa-automatic'),
        'scheduled' => __('Scheduled', 'msa-automatic'),
        'recurring' => __('Recurring', 'msa-automatic')
    );
}

/**
 * Get campaign recurring intervals
 *
 * @return array Recurring intervals
 */
function msa_get_recurring_intervals() {
    return array(
        'hourly' => __('Hourly', 'msa-automatic'),
        'daily' => __('Daily', 'msa-automatic'),
        'weekly' => __('Weekly', 'msa-automatic')
    );
}

/**
 * Get campaign filter types
 *
 * @return array Filter types
 */
function msa_get_filter_types() {
    return array(
        'keyword' => __('Keyword', 'msa-automatic'),
        'length' => __('Content Length', 'msa-automatic'),
        'date' => __('Date', 'msa-automatic')
    );
}

/**
 * Format campaign last run time
 *
 * @param int $campaign_id Campaign ID
 * @return string Formatted time
 */
function msa_get_last_run($campaign_id) {
    $last_run = get_post_meta($campaign_id, '_msa_last_run', true);
    if (!$last_run) {
        return __('Never', 'msa-automatic');
    }
    return msa_utils()->time_ago($last_run);
}

/**
 * Get campaign next run time
 *
 * @param int $campaign_id Campaign ID
 * @return string|false Formatted time or false if not scheduled
 */
function msa_get_next_run($campaign_id) {
    $schedule_type = get_post_meta($campaign_id, '_msa_schedule_type', true);
    
    if ($schedule_type === 'scheduled') {
        $time = get_post_meta($campaign_id, '_msa_schedule_time', true);
        return $time ? msa_utils()->format_date($time) : false;
    }

    if ($schedule_type === 'recurring') {
        $last_run = get_post_meta($campaign_id, '_msa_last_run', true);
        $interval = get_post_meta($campaign_id, '_msa_recurring_interval', true);

        if ($last_run && $interval) {
            $next = strtotime($last_run) + msa_get_interval_seconds($interval);
            return msa_utils()->format_date(date('Y-m-d H:i:s', $next));
        }
    }

    return false;
}

/**
 * Get interval seconds
 *
 * @param string $interval Interval type
 * @return int Seconds
 */
function msa_get_interval_seconds($interval) {
    switch ($interval) {
        case 'hourly':
            return HOUR_IN_SECONDS;
        case 'daily':
            return DAY_IN_SECONDS;
        case 'weekly':
            return WEEK_IN_SECONDS;
        default:
            return DAY_IN_SECONDS;
    }
}
