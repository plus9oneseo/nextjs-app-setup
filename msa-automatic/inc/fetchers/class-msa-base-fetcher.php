<?php
if (!defined('ABSPATH')) {
    exit;
}

abstract class MSA_Base_Fetcher {
    /**
     * Fetcher type identifier
     * @var string
     */
    protected $type;

    /**
     * Fetcher settings
     * @var array
     */
    protected $settings;

    /**
     * Logger instance
     * @var MSA_Logger
     */
    protected $logger;

    /**
     * Constructor
     *
     * @param array $settings Fetcher settings
     */
    public function __construct($settings = array()) {
        $this->settings = $settings;
        $this->logger = msa_logger();
    }

    /**
     * Get fetcher type
     *
     * @return string
     */
    public function get_type() {
        return $this->type;
    }

    /**
     * Get fetcher settings
     *
     * @return array
     */
    public function get_settings() {
        return $this->settings;
    }

    /**
     * Update fetcher settings
     *
     * @param array $settings New settings
     * @return void
     */
    public function update_settings($settings) {
        $this->settings = wp_parse_args($settings, $this->settings);
    }

    /**
     * Fetch content
     *
     * @param int $campaign_id Campaign ID
     * @return array|WP_Error Array of fetched items or WP_Error on failure
     */
    abstract public function fetch($campaign_id);

    /**
     * Test API connection
     *
     * @return bool|WP_Error True on success or WP_Error on failure
     */
    abstract public function test_connection();

    /**
     * Get required settings fields
     *
     * @return array
     */
    abstract public function get_required_settings();

    /**
     * Validate settings
     *
     * @param array $settings Settings to validate
     * @return bool|WP_Error True if valid or WP_Error on failure
     */
    public function validate_settings($settings) {
        $required = $this->get_required_settings();
        $missing = array();

        foreach ($required as $key => $label) {
            if (empty($settings[$key])) {
                $missing[] = $label;
            }
        }

        if (!empty($missing)) {
            return new WP_Error(
                'missing_settings',
                sprintf(
                    __('Missing required settings: %s', 'msa-automatic'),
                    implode(', ', $missing)
                )
            );
        }

        return true;
    }

    /**
     * Make API request
     *
     * @param string $url Request URL
     * @param array $args Request arguments
     * @param bool $cache Whether to cache the response
     * @return array|WP_Error Response data or WP_Error on failure
     */
    protected function request($url, $args = array(), $cache = true) {
        $cache_key = 'msa_api_' . md5($url . serialize($args));

        if ($cache) {
            $cached = get_transient($cache_key);
            if (false !== $cached) {
                return $cached;
            }
        }

        $args = wp_parse_args($args, array(
            'timeout' => 30,
            'sslverify' => true,
            'user-agent' => 'MSA Automatic/' . MSA_AUTOMATIC_VERSION
        ));

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            $this->logger->error(sprintf(
                'API request failed: %s',
                $response->get_error_message()
            ), array(
                'url' => $url,
                'args' => $args
            ));
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            $error = new WP_Error(
                'api_error',
                sprintf(
                    __('API request failed with status code: %d', 'msa-automatic'),
                    $code
                )
            );
            $this->logger->error($error->get_error_message(), array(
                'url' => $url,
                'args' => $args,
                'response' => wp_remote_retrieve_body($response)
            ));
            return $error;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $error = new WP_Error(
                'json_error',
                __('Failed to parse API response', 'msa-automatic')
            );
            $this->logger->error($error->get_error_message(), array(
                'url' => $url,
                'args' => $args,
                'response' => $body
            ));
            return $error;
        }

        if ($cache) {
            set_transient($cache_key, $data, HOUR_IN_SECONDS);
        }

        return $data;
    }

    /**
     * Format fetched items into a standard structure
     *
     * @param array $items Raw items from API
     * @return array Formatted items
     */
    protected function format_items($items) {
        $formatted = array();

        foreach ($items as $item) {
            $formatted[] = array(
                'title' => $this->get_item_title($item),
                'content' => $this->get_item_content($item),
                'author' => $this->get_item_author($item),
                'date' => $this->get_item_date($item),
                'url' => $this->get_item_url($item),
                'image' => $this->get_item_image($item),
                'meta' => $this->get_item_meta($item)
            );
        }

        return $formatted;
    }

    /**
     * Get item title
     *
     * @param array $item Raw item data
     * @return string
     */
    protected function get_item_title($item) {
        return '';
    }

    /**
     * Get item content
     *
     * @param array $item Raw item data
     * @return string
     */
    protected function get_item_content($item) {
        return '';
    }

    /**
     * Get item author
     *
     * @param array $item Raw item data
     * @return string
     */
    protected function get_item_author($item) {
        return '';
    }

    /**
     * Get item date
     *
     * @param array $item Raw item data
     * @return string
     */
    protected function get_item_date($item) {
        return '';
    }

    /**
     * Get item URL
     *
     * @param array $item Raw item data
     * @return string
     */
    protected function get_item_url($item) {
        return '';
    }

    /**
     * Get item image URL
     *
     * @param array $item Raw item data
     * @return string
     */
    protected function get_item_image($item) {
        return '';
    }

    /**
     * Get item additional metadata
     *
     * @param array $item Raw item data
     * @return array
     */
    protected function get_item_meta($item) {
        return array();
    }

    /**
     * Apply filters to fetched items
     *
     * @param array $items Items to filter
     * @param array $filters Filters to apply
     * @return array Filtered items
     */
    protected function apply_filters($items, $filters) {
        if (empty($filters)) {
            return $items;
        }

        return array_filter($items, function($item) use ($filters) {
            foreach ($filters as $filter) {
                if (!$this->matches_filter($item, $filter)) {
                    return false;
                }
            }
            return true;
        });
    }

    /**
     * Check if item matches a filter
     *
     * @param array $item Item to check
     * @param array $filter Filter to apply
     * @return bool Whether item matches filter
     */
    protected function matches_filter($item, $filter) {
        switch ($filter['type']) {
            case 'keyword':
                return $this->matches_keyword($item, $filter['value']);

            case 'length':
                return $this->matches_length($item, $filter['value']);

            case 'date':
                return $this->matches_date($item, $filter['value']);

            default:
                return true;
        }
    }

    /**
     * Check if item matches keyword filter
     *
     * @param array $item Item to check
     * @param string $keyword Keyword to match
     * @return bool
     */
    protected function matches_keyword($item, $keyword) {
        $keyword = strtolower($keyword);
        $title = strtolower($item['title']);
        $content = strtolower($item['content']);

        return strpos($title, $keyword) !== false || 
               strpos($content, $keyword) !== false;
    }

    /**
     * Check if item matches length filter
     *
     * @param array $item Item to check
     * @param string $length Length requirement (e.g., '>500', '<1000')
     * @return bool
     */
    protected function matches_length($item, $length) {
        preg_match('/([<>])\s*(\d+)/', $length, $matches);
        if (empty($matches)) {
            return true;
        }

        $operator = $matches[1];
        $value = intval($matches[2]);
        $content_length = strlen(strip_tags($item['content']));

        return $operator === '>' ? $content_length > $value : $content_length < $value;
    }

    /**
     * Check if item matches date filter
     *
     * @param array $item Item to check
     * @param string $date Date requirement (e.g., '>2023-01-01', '<2023-12-31')
     * @return bool
     */
    protected function matches_date($item, $date) {
        preg_match('/([<>])\s*(.+)/', $date, $matches);
        if (empty($matches)) {
            return true;
        }

        $operator = $matches[1];
        $value = strtotime($matches[2]);
        $item_date = strtotime($item['date']);

        return $operator === '>' ? $item_date > $value : $item_date < $value;
    }
}
