<?php
if (!defined('ABSPATH')) {
    exit;
}

class MSA_Settings_Manager {
    /**
     * Instance of this class
     * @var MSA_Settings_Manager
     */
    private static $instance = null;

    /**
     * Plugin settings
     * @var array
     */
    private $settings = array();

    /**
     * Default settings
     * @var array
     */
    private $defaults = array(
        'general' => array(
            'debug_mode' => false,
            'log_retention' => 30,
            'batch_size' => 10,
            'timeout' => 30
        ),
        'fetching' => array(
            'cache_duration' => 3600,
            'retry_attempts' => 3,
            'retry_delay' => 300,
            'user_agent' => ''
        ),
        'translation' => array(
            'cache_duration' => 86400,
            'chunk_size' => 1000,
            'preserve_formatting' => true
        ),
        'posting' => array(
            'default_status' => 'publish',
            'default_category' => '',
            'default_author' => '',
            'allow_duplicates' => false,
            'featured_image' => true,
            'keep_original_date' => true
        ),
        'scheduling' => array(
            'cron_interval' => 'hourly',
            'max_execution_time' => 300,
            'concurrent_campaigns' => 1
        )
    );

    /**
     * Get instance of this class
     *
     * @return MSA_Settings_Manager
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
        $this->load_settings();
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('admin_init', array($this, 'register_settings'));
    }

    /**
     * Load settings from database
     */
    private function load_settings() {
        foreach (array_keys($this->defaults) as $section) {
            $this->settings[$section] = get_option(
                'msa_' . $section . '_settings',
                $this->defaults[$section]
            );
        }
    }

    /**
     * Register settings
     */
    public function register_settings() {
        // General Settings
        register_setting(
            'msa_general_settings',
            'msa_general_settings',
            array($this, 'sanitize_general_settings')
        );

        // Fetching Settings
        register_setting(
            'msa_fetching_settings',
            'msa_fetching_settings',
            array($this, 'sanitize_fetching_settings')
        );

        // Translation Settings
        register_setting(
            'msa_translation_settings',
            'msa_translation_settings',
            array($this, 'sanitize_translation_settings')
        );

        // Posting Settings
        register_setting(
            'msa_posting_settings',
            'msa_posting_settings',
            array($this, 'sanitize_posting_settings')
        );

        // Scheduling Settings
        register_setting(
            'msa_scheduling_settings',
            'msa_scheduling_settings',
            array($this, 'sanitize_scheduling_settings')
        );
    }

    /**
     * Get setting value
     *
     * @param string $section Setting section
     * @param string $key Setting key
     * @param mixed $default Default value
     * @return mixed Setting value
     */
    public function get_setting($section, $key, $default = null) {
        if (!isset($this->settings[$section])) {
            return $default;
        }

        return isset($this->settings[$section][$key])
            ? $this->settings[$section][$key]
            : $default;
    }

    /**
     * Update setting value
     *
     * @param string $section Setting section
     * @param string $key Setting key
     * @param mixed $value Setting value
     * @return bool Whether the setting was updated
     */
    public function update_setting($section, $key, $value) {
        if (!isset($this->settings[$section])) {
            return false;
        }

        $this->settings[$section][$key] = $value;
        return update_option('msa_' . $section . '_settings', $this->settings[$section]);
    }

    /**
     * Get all settings
     *
     * @return array All settings
     */
    public function get_all_settings() {
        return $this->settings;
    }

    /**
     * Get section settings
     *
     * @param string $section Setting section
     * @return array Section settings
     */
    public function get_section_settings($section) {
        return isset($this->settings[$section])
            ? $this->settings[$section]
            : array();
    }

    /**
     * Get default settings
     *
     * @param string $section Setting section (optional)
     * @return array Default settings
     */
    public function get_defaults($section = null) {
        if ($section) {
            return isset($this->defaults[$section])
                ? $this->defaults[$section]
                : array();
        }
        return $this->defaults;
    }

    /**
     * Reset settings to defaults
     *
     * @param string $section Setting section (optional)
     * @return bool Whether settings were reset
     */
    public function reset_settings($section = null) {
        if ($section) {
            if (!isset($this->defaults[$section])) {
                return false;
            }
            $this->settings[$section] = $this->defaults[$section];
            return update_option('msa_' . $section . '_settings', $this->defaults[$section]);
        }

        foreach ($this->defaults as $section => $settings) {
            $this->settings[$section] = $settings;
            update_option('msa_' . $section . '_settings', $settings);
        }

        return true;
    }

    /**
     * Sanitize general settings
     *
     * @param array $input Settings input
     * @return array Sanitized settings
     */
    public function sanitize_general_settings($input) {
        $sanitized = array();

        $sanitized['debug_mode'] = !empty($input['debug_mode']);
        $sanitized['log_retention'] = absint($input['log_retention']);
        $sanitized['batch_size'] = absint($input['batch_size']);
        $sanitized['timeout'] = absint($input['timeout']);

        return $sanitized;
    }

    /**
     * Sanitize fetching settings
     *
     * @param array $input Settings input
     * @return array Sanitized settings
     */
    public function sanitize_fetching_settings($input) {
        $sanitized = array();

        $sanitized['cache_duration'] = absint($input['cache_duration']);
        $sanitized['retry_attempts'] = absint($input['retry_attempts']);
        $sanitized['retry_delay'] = absint($input['retry_delay']);
        $sanitized['user_agent'] = sanitize_text_field($input['user_agent']);

        return $sanitized;
    }

    /**
     * Sanitize translation settings
     *
     * @param array $input Settings input
     * @return array Sanitized settings
     */
    public function sanitize_translation_settings($input) {
        $sanitized = array();

        $sanitized['cache_duration'] = absint($input['cache_duration']);
        $sanitized['chunk_size'] = absint($input['chunk_size']);
        $sanitized['preserve_formatting'] = !empty($input['preserve_formatting']);

        return $sanitized;
    }

    /**
     * Sanitize posting settings
     *
     * @param array $input Settings input
     * @return array Sanitized settings
     */
    public function sanitize_posting_settings($input) {
        $sanitized = array();

        $sanitized['default_status'] = sanitize_text_field($input['default_status']);
        $sanitized['default_category'] = absint($input['default_category']);
        $sanitized['default_author'] = absint($input['default_author']);
        $sanitized['allow_duplicates'] = !empty($input['allow_duplicates']);
        $sanitized['featured_image'] = !empty($input['featured_image']);
        $sanitized['keep_original_date'] = !empty($input['keep_original_date']);

        return $sanitized;
    }

    /**
     * Sanitize scheduling settings
     *
     * @param array $input Settings input
     * @return array Sanitized settings
     */
    public function sanitize_scheduling_settings($input) {
        $sanitized = array();

        $sanitized['cron_interval'] = sanitize_text_field($input['cron_interval']);
        $sanitized['max_execution_time'] = absint($input['max_execution_time']);
        $sanitized['concurrent_campaigns'] = absint($input['concurrent_campaigns']);

        return $sanitized;
    }

    /**
     * Get available post statuses
     *
     * @return array Post statuses
     */
    public function get_post_statuses() {
        return array(
            'publish' => __('Published', 'msa-automatic'),
            'draft' => __('Draft', 'msa-automatic'),
            'pending' => __('Pending Review', 'msa-automatic'),
            'private' => __('Private', 'msa-automatic')
        );
    }

    /**
     * Get available cron intervals
     *
     * @return array Cron intervals
     */
    public function get_cron_intervals() {
        return array(
            'every_5_minutes' => __('Every 5 minutes', 'msa-automatic'),
            'every_15_minutes' => __('Every 15 minutes', 'msa-automatic'),
            'every_30_minutes' => __('Every 30 minutes', 'msa-automatic'),
            'hourly' => __('Hourly', 'msa-automatic'),
            'twicedaily' => __('Twice Daily', 'msa-automatic'),
            'daily' => __('Daily', 'msa-automatic')
        );
    }

    /**
     * Get available authors
     *
     * @return array Authors
     */
    public function get_authors() {
        $authors = get_users(array(
            'who' => 'authors',
            'orderby' => 'display_name'
        ));

        $options = array();
        foreach ($authors as $author) {
            $options[$author->ID] = $author->display_name;
        }

        return $options;
    }

    /**
     * Get available categories
     *
     * @return array Categories
     */
    public function get_categories() {
        $categories = get_categories(array(
            'hide_empty' => false,
            'orderby' => 'name'
        ));

        $options = array();
        foreach ($categories as $category) {
            $options[$category->term_id] = $category->name;
        }

        return $options;
    }
}

/**
 * Get instance of settings manager
 *
 * @return MSA_Settings_Manager
 */
function msa_settings_manager() {
    return MSA_Settings_Manager::get_instance();
}
