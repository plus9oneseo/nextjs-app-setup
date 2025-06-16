<?php
if (!defined('ABSPATH')) {
    exit;
}

class MSA_Utils {
    /**
     * Instance of this class
     * @var MSA_Utils
     */
    private static $instance = null;

    /**
     * Get instance of this class
     *
     * @return MSA_Utils
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Format date
     *
     * @param string $date Date string
     * @param string $format Date format (optional)
     * @return string Formatted date
     */
    public function format_date($date, $format = '') {
        if (empty($format)) {
            $format = get_option('date_format') . ' ' . get_option('time_format');
        }

        $timestamp = strtotime($date);
        return date_i18n($format, $timestamp);
    }

    /**
     * Format time ago
     *
     * @param string $date Date string
     * @return string Time ago string
     */
    public function time_ago($date) {
        $timestamp = strtotime($date);
        $current_time = current_time('timestamp');
        $diff = $current_time - $timestamp;

        if ($diff < 60) {
            return __('just now', 'msa-automatic');
        }

        $intervals = array(
            31536000 => __('year', 'msa-automatic'),
            2592000 => __('month', 'msa-automatic'),
            604800 => __('week', 'msa-automatic'),
            86400 => __('day', 'msa-automatic'),
            3600 => __('hour', 'msa-automatic'),
            60 => __('minute', 'msa-automatic')
        );

        foreach ($intervals as $seconds => $label) {
            $count = floor($diff / $seconds);
            if ($count > 0) {
                if ($count == 1) {
                    return sprintf(__('%s ago', 'msa-automatic'), $label);
                } else {
                    return sprintf(__('%s %ss ago', 'msa-automatic'), $count, $label);
                }
            }
        }
    }

    /**
     * Format file size
     *
     * @param int $bytes Size in bytes
     * @return string Formatted size
     */
    public function format_size($bytes) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Format duration
     *
     * @param int $seconds Duration in seconds
     * @return string Formatted duration
     */
    public function format_duration($seconds) {
        if ($seconds < 60) {
            return sprintf(_n('%d second', '%d seconds', $seconds, 'msa-automatic'), $seconds);
        }

        $minutes = floor($seconds / 60);
        if ($minutes < 60) {
            return sprintf(_n('%d minute', '%d minutes', $minutes, 'msa-automatic'), $minutes);
        }

        $hours = floor($minutes / 60);
        $minutes = $minutes % 60;

        if ($hours < 24) {
            return sprintf(
                __('%dh %dm', 'msa-automatic'),
                $hours,
                $minutes
            );
        }

        $days = floor($hours / 24);
        $hours = $hours % 24;

        return sprintf(
            __('%dd %dh %dm', 'msa-automatic'),
            $days,
            $hours,
            $minutes
        );
    }

    /**
     * Generate random string
     *
     * @param int $length String length
     * @return string Random string
     */
    public function random_string($length = 10) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $string = '';

        for ($i = 0; $i < $length; $i++) {
            $string .= $characters[rand(0, strlen($characters) - 1)];
        }

        return $string;
    }

    /**
     * Clean URL
     *
     * @param string $url URL to clean
     * @return string Cleaned URL
     */
    public function clean_url($url) {
        // Remove query string
        $url = strtok($url, '?');

        // Remove trailing slash
        $url = rtrim($url, '/');

        // Ensure https
        $url = str_replace('http://', 'https://', $url);

        return $url;
    }

    /**
     * Extract domain from URL
     *
     * @param string $url URL
     * @return string Domain
     */
    public function get_domain($url) {
        return parse_url($url, PHP_URL_HOST);
    }

    /**
     * Clean HTML content
     *
     * @param string $content Content to clean
     * @param array $allowed_tags Allowed HTML tags
     * @return string Cleaned content
     */
    public function clean_html($content, $allowed_tags = array()) {
        if (empty($allowed_tags)) {
            $allowed_tags = array(
                'p', 'br', 'hr', 'a', 'img',
                'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
                'ul', 'ol', 'li',
                'blockquote', 'pre', 'code',
                'em', 'strong', 'b', 'i'
            );
        }

        // Convert to array if string
        if (is_string($allowed_tags)) {
            $allowed_tags = explode(',', str_replace(' ', '', $allowed_tags));
        }

        // Build allowed tags array
        $allowed = '<' . implode('><', $allowed_tags) . '>';

        // Strip tags
        $content = strip_tags($content, $allowed);

        // Remove empty paragraphs
        $content = preg_replace('/<p>\s*<\/p>/i', '', $content);

        // Clean up whitespace
        $content = preg_replace('/\s+/', ' ', $content);
        $content = trim($content);

        return $content;
    }

    /**
     * Extract excerpt from content
     *
     * @param string $content Content
     * @param int $length Excerpt length
     * @param string $more More text
     * @return string Excerpt
     */
    public function get_excerpt($content, $length = 55, $more = '...') {
        // Strip shortcodes and HTML
        $excerpt = strip_shortcodes($content);
        $excerpt = wp_strip_all_tags($excerpt);

        // Trim to length
        $words = explode(' ', $excerpt, $length + 1);
        if (count($words) > $length) {
            array_pop($words);
            $excerpt = implode(' ', $words) . $more;
        }

        return $excerpt;
    }

    /**
     * Get image dimensions from URL
     *
     * @param string $url Image URL
     * @return array|false Array with width and height or false on failure
     */
    public function get_image_dimensions($url) {
        // Try to get from cache first
        $cache_key = 'msa_img_' . md5($url);
        $dimensions = get_transient($cache_key);

        if (false !== $dimensions) {
            return $dimensions;
        }

        // Download image temporarily
        $tmp = download_url($url);
        if (is_wp_error($tmp)) {
            return false;
        }

        // Get dimensions
        $size = @getimagesize($tmp);
        unlink($tmp);

        if (!$size) {
            return false;
        }

        $dimensions = array(
            'width' => $size[0],
            'height' => $size[1]
        );

        // Cache for 1 day
        set_transient($cache_key, $dimensions, DAY_IN_SECONDS);

        return $dimensions;
    }

    /**
     * Check if URL is valid image
     *
     * @param string $url URL to check
     * @return bool Whether URL is valid image
     */
    public function is_valid_image($url) {
        $extension = strtolower(pathinfo($url, PATHINFO_EXTENSION));
        $valid_extensions = array('jpg', 'jpeg', 'png', 'gif', 'webp');

        return in_array($extension, $valid_extensions);
    }

    /**
     * Get YouTube video ID from URL
     *
     * @param string $url YouTube URL
     * @return string|false Video ID or false if not found
     */
    public function get_youtube_id($url) {
        $pattern = '/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/i';
        
        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }

        return false;
    }

    /**
     * Get campaign status label
     *
     * @param string $status Status key
     * @return string Status label
     */
    public function get_status_label($status) {
        $statuses = array(
            'active' => __('Active', 'msa-automatic'),
            'paused' => __('Paused', 'msa-automatic'),
            'completed' => __('Completed', 'msa-automatic'),
            'failed' => __('Failed', 'msa-automatic'),
            'draft' => __('Draft', 'msa-automatic')
        );

        return isset($statuses[$status]) ? $statuses[$status] : $status;
    }

    /**
     * Get campaign status color
     *
     * @param string $status Status key
     * @return string CSS color class
     */
    public function get_status_color($status) {
        $colors = array(
            'active' => 'green',
            'paused' => 'orange',
            'completed' => 'blue',
            'failed' => 'red',
            'draft' => 'gray'
        );

        return isset($colors[$status]) ? $colors[$status] : 'gray';
    }

    /**
     * Get next cron run time
     *
     * @param string $hook Cron hook
     * @return string|false Next run time or false if not scheduled
     */
    public function get_next_cron($hook) {
        $next = wp_next_scheduled($hook);
        return $next ? $this->format_date($next) : false;
    }

    /**
     * Check if process is running
     *
     * @param string $process Process name
     * @return bool Whether process is running
     */
    public function is_process_running($process) {
        $lock_file = WP_CONTENT_DIR . "/msa-{$process}.lock";

        if (!file_exists($lock_file)) {
            return false;
        }

        $pid = file_get_contents($lock_file);
        if (!$pid) {
            return false;
        }

        // Check if process is still running
        if (function_exists('posix_kill')) {
            return posix_kill($pid, 0);
        }

        return true;
    }

    /**
     * Set process as running
     *
     * @param string $process Process name
     * @return bool Whether lock was created
     */
    public function lock_process($process) {
        $lock_file = WP_CONTENT_DIR . "/msa-{$process}.lock";
        return file_put_contents($lock_file, getmypid());
    }

    /**
     * Release process lock
     *
     * @param string $process Process name
     */
    public function unlock_process($process) {
        $lock_file = WP_CONTENT_DIR . "/msa-{$process}.lock";
        if (file_exists($lock_file)) {
            unlink($lock_file);
        }
    }
}

/**
 * Get instance of utils
 *
 * @return MSA_Utils
 */
function msa_utils() {
    return MSA_Utils::get_instance();
}
