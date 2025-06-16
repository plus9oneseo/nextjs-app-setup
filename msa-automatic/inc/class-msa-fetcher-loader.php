<?php
if (!defined('ABSPATH')) {
    exit;
}

class MSA_Fetcher_Loader {
    /**
     * Instance of this class
     * @var MSA_Fetcher_Loader
     */
    private static $instance = null;

    /**
     * Available fetchers
     * @var array
     */
    private $fetchers = array();

    /**
     * Get instance of this class
     *
     * @return MSA_Fetcher_Loader
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->register_default_fetchers();
    }

    /**
     * Register default fetchers
     */
    private function register_default_fetchers() {
        $this->register_fetcher('facebook', array(
            'name' => __('Facebook', 'msa-automatic'),
            'description' => __('Fetch posts from Facebook pages', 'msa-automatic'),
            'class' => 'MSA_Facebook_Fetcher',
            'settings' => array(
                'app_id' => __('App ID', 'msa-automatic'),
                'app_secret' => __('App Secret', 'msa-automatic'),
                'page_id' => __('Page ID', 'msa-automatic'),
                'access_token' => __('Access Token', 'msa-automatic')
            )
        ));

        $this->register_fetcher('twitter', array(
            'name' => __('Twitter', 'msa-automatic'),
            'description' => __('Fetch tweets from Twitter accounts', 'msa-automatic'),
            'class' => 'MSA_Twitter_Fetcher',
            'settings' => array(
                'api_key' => __('API Key', 'msa-automatic'),
                'api_secret' => __('API Secret', 'msa-automatic'),
                'bearer_token' => __('Bearer Token', 'msa-automatic'),
                'username' => __('Username', 'msa-automatic')
            )
        ));

        $this->register_fetcher('youtube', array(
            'name' => __('YouTube', 'msa-automatic'),
            'description' => __('Fetch videos from YouTube channels', 'msa-automatic'),
            'class' => 'MSA_YouTube_Fetcher',
            'settings' => array(
                'api_key' => __('API Key', 'msa-automatic'),
                'channel_id' => __('Channel ID', 'msa-automatic'),
                'max_results' => __('Max Results', 'msa-automatic')
            )
        ));

        $this->register_fetcher('tiktok', array(
            'name' => __('TikTok', 'msa-automatic'),
            'description' => __('Fetch videos from TikTok accounts', 'msa-automatic'),
            'class' => 'MSA_TikTok_Fetcher',
            'settings' => array(
                'client_key' => __('Client Key', 'msa-automatic'),
                'client_secret' => __('Client Secret', 'msa-automatic'),
                'access_token' => __('Access Token', 'msa-automatic'),
                'username' => __('Username', 'msa-automatic')
            )
        ));
    }

    /**
     * Register a new fetcher
     *
     * @param string $type Fetcher type identifier
     * @param array $args Fetcher arguments
     * @return bool True on success, false on failure
     */
    public function register_fetcher($type, $args) {
        if (empty($type) || empty($args['class']) || !class_exists($args['class'])) {
            return false;
        }

        $this->fetchers[$type] = wp_parse_args($args, array(
            'name' => '',
            'description' => '',
            'class' => '',
            'settings' => array()
        ));

        return true;
    }

    /**
     * Unregister a fetcher
     *
     * @param string $type Fetcher type identifier
     * @return bool True on success, false on failure
     */
    public function unregister_fetcher($type) {
        if (isset($this->fetchers[$type])) {
            unset($this->fetchers[$type]);
            return true;
        }
        return false;
    }

    /**
     * Get available fetchers
     *
     * @return array Array of registered fetchers
     */
    public function get_available_fetchers() {
        return $this->fetchers;
    }

    /**
     * Get fetcher information
     *
     * @param string $type Fetcher type identifier
     * @return array|false Fetcher information or false if not found
     */
    public function get_fetcher_info($type) {
        return isset($this->fetchers[$type]) ? $this->fetchers[$type] : false;
    }

    /**
     * Get fetcher instance
     *
     * @param string $type Fetcher type identifier
     * @param array $settings Fetcher settings
     * @return MSA_Base_Fetcher|WP_Error Fetcher instance or WP_Error on failure
     */
    public function get_fetcher($type, $settings = array()) {
        if (!isset($this->fetchers[$type])) {
            return new WP_Error(
                'invalid_fetcher',
                sprintf(__('Invalid fetcher type: %s', 'msa-automatic'), $type)
            );
        }

        $class = $this->fetchers[$type]['class'];
        if (!class_exists($class)) {
            return new WP_Error(
                'missing_class',
                sprintf(__('Fetcher class not found: %s', 'msa-automatic'), $class)
            );
        }

        try {
            $fetcher = new $class($settings);
            
            if (!($fetcher instanceof MSA_Base_Fetcher)) {
                throw new Exception(__('Invalid fetcher class', 'msa-automatic'));
            }

            return $fetcher;
        } catch (Exception $e) {
            return new WP_Error(
                'fetcher_error',
                $e->getMessage()
            );
        }
    }

    /**
     * Get fetcher for campaign
     *
     * @param int $campaign_id Campaign ID
     * @return MSA_Base_Fetcher|WP_Error Fetcher instance or WP_Error on failure
     */
    public function get_campaign_fetcher($campaign_id) {
        $type = get_post_meta($campaign_id, '_msa_fetcher_type', true);
        if (empty($type)) {
            return new WP_Error(
                'no_fetcher',
                __('No fetcher type specified for campaign', 'msa-automatic')
            );
        }

        $settings = get_post_meta($campaign_id, '_msa_fetcher_settings', true);
        if (!is_array($settings)) {
            $settings = array();
        }

        return $this->get_fetcher($type, $settings);
    }

    /**
     * Validate fetcher settings
     *
     * @param string $type Fetcher type identifier
     * @param array $settings Settings to validate
     * @return bool|WP_Error True if valid or WP_Error on failure
     */
    public function validate_settings($type, $settings) {
        $fetcher = $this->get_fetcher($type);
        if (is_wp_error($fetcher)) {
            return $fetcher;
        }

        return $fetcher->validate_settings($settings);
    }

    /**
     * Test fetcher connection
     *
     * @param string $type Fetcher type identifier
     * @param array $settings Settings to test
     * @return bool|WP_Error True on success or WP_Error on failure
     */
    public function test_connection($type, $settings) {
        $fetcher = $this->get_fetcher($type, $settings);
        if (is_wp_error($fetcher)) {
            return $fetcher;
        }

        return $fetcher->test_connection();
    }

    /**
     * Get required settings for fetcher
     *
     * @param string $type Fetcher type identifier
     * @return array Array of required settings
     */
    public function get_required_settings($type) {
        $fetcher = $this->get_fetcher($type);
        if (is_wp_error($fetcher)) {
            return array();
        }

        return $fetcher->get_required_settings();
    }
}

/**
 * Get instance of fetcher loader
 *
 * @return MSA_Fetcher_Loader
 */
function msa_fetcher_loader() {
    return MSA_Fetcher_Loader::get_instance();
}
