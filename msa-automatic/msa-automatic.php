<?php
/**
 * Plugin Name: MSA Automatic
 * Plugin URI: https://example.com/msa-automatic
 * Description: Automatically fetch and post content from various social media platforms
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * Text Domain: msa-automatic
 * Domain Path: /languages
 * Requires at least: 5.6
 * Requires PHP: 7.2
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('MSA_AUTOMATIC_VERSION', '1.0.0');
define('MSA_AUTOMATIC_FILE', __FILE__);
define('MSA_AUTOMATIC_PATH', plugin_dir_path(__FILE__));
define('MSA_AUTOMATIC_URL', plugin_dir_url(__FILE__));

/**
 * Main plugin class
 */
class MSA_Automatic {
    /**
     * Instance of this class
     * @var MSA_Automatic
     */
    private static $instance = null;

    /**
     * Get instance of this class
     *
     * @return MSA_Automatic
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
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Load plugin files
        add_action('plugins_loaded', array($this, 'load_files'));

        // Initialize plugin
        add_action('init', array($this, 'init'));

        // Register activation/deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));

        // Add action links
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_action_links'));

        // Register scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'register_admin_assets'));

        // Add AJAX handlers
        add_action('wp_ajax_msa_run_campaign', array($this, 'ajax_run_campaign'));
        add_action('wp_ajax_msa_test_connection', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_msa_clear_logs', array($this, 'ajax_clear_logs'));
    }

    /**
     * Load required files
     */
    public function load_files() {
        // Load translations
        load_plugin_textdomain('msa-automatic', false, dirname(plugin_basename(__FILE__)) . '/languages');

        // Core files
        require_once MSA_AUTOMATIC_PATH . 'core.php';

        // Include classes
        require_once MSA_AUTOMATIC_PATH . 'inc/class-msa-utils.php';
        require_once MSA_AUTOMATIC_PATH . 'inc/class-msa-logger.php';
        require_once MSA_AUTOMATIC_PATH . 'inc/class-msa-settings-manager.php';
        require_once MSA_AUTOMATIC_PATH . 'inc/class-msa-campaign-processor.php';

        // Fetcher classes
        require_once MSA_AUTOMATIC_PATH . 'inc/fetchers/class-msa-base-fetcher.php';
        require_once MSA_AUTOMATIC_PATH . 'inc/fetchers/class-msa-facebook-fetcher.php';
        require_once MSA_AUTOMATIC_PATH . 'inc/fetchers/class-msa-twitter-fetcher.php';
        require_once MSA_AUTOMATIC_PATH . 'inc/fetchers/class-msa-youtube-fetcher.php';
        require_once MSA_AUTOMATIC_PATH . 'inc/fetchers/class-msa-tiktok-fetcher.php';
        require_once MSA_AUTOMATIC_PATH . 'inc/class-msa-fetcher-loader.php';

        // Translator classes
        require_once MSA_AUTOMATIC_PATH . 'inc/translators/class-msa-base-translator.php';
        require_once MSA_AUTOMATIC_PATH . 'inc/translators/class-msa-yandex-translator.php';
        require_once MSA_AUTOMATIC_PATH . 'inc/class-msa-translator-loader.php';
    }

    /**
     * Initialize plugin
     */
    public function init() {
        // Register post types
        $this->register_post_types();

        // Schedule cron events
        $this->schedule_events();
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables
        msa_logger()->create_tables();

        // Schedule cron events
        $this->schedule_events();

        // Set default options
        $this->set_default_options();

        // Clear rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Unschedule cron events
        wp_clear_scheduled_hook('msa_process_scheduled_campaigns');
        wp_clear_scheduled_hook('msa_cleanup_logs');

        // Clear rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Register post types
     */
    private function register_post_types() {
        register_post_type('msa_campaign', array(
            'labels' => array(
                'name' => __('Campaigns', 'msa-automatic'),
                'singular_name' => __('Campaign', 'msa-automatic'),
                'add_new' => __('Add New', 'msa-automatic'),
                'add_new_item' => __('Add New Campaign', 'msa-automatic'),
                'edit_item' => __('Edit Campaign', 'msa-automatic'),
                'new_item' => __('New Campaign', 'msa-automatic'),
                'view_item' => __('View Campaign', 'msa-automatic'),
                'search_items' => __('Search Campaigns', 'msa-automatic'),
                'not_found' => __('No campaigns found', 'msa-automatic'),
                'not_found_in_trash' => __('No campaigns found in Trash', 'msa-automatic'),
                'menu_name' => __('Campaigns', 'msa-automatic')
            ),
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'capability_type' => 'post',
            'hierarchical' => false,
            'supports' => array('title'),
            'has_archive' => false,
            'rewrite' => false
        ));
    }

    /**
     * Schedule cron events
     */
    private function schedule_events() {
        if (!wp_next_scheduled('msa_process_scheduled_campaigns')) {
            wp_schedule_event(time(), 'every_5_minutes', 'msa_process_scheduled_campaigns');
        }

        if (!wp_next_scheduled('msa_cleanup_logs')) {
            wp_schedule_event(time(), 'daily', 'msa_cleanup_logs');
        }
    }

    /**
     * Set default options
     */
    private function set_default_options() {
        $settings = msa_settings_manager();
        foreach ($settings->get_defaults() as $section => $options) {
            if (!get_option('msa_' . $section . '_settings')) {
                update_option('msa_' . $section . '_settings', $options);
            }
        }
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('MSA Automatic', 'msa-automatic'),
            __('MSA Automatic', 'msa-automatic'),
            'manage_options',
            'msa-automatic',
            array($this, 'render_admin_page'),
            'dashicons-share',
            30
        );

        add_submenu_page(
            'msa-automatic',
            __('Campaigns', 'msa-automatic'),
            __('Campaigns', 'msa-automatic'),
            'manage_options',
            'msa-automatic'
        );

        add_submenu_page(
            'msa-automatic',
            __('Add New Campaign', 'msa-automatic'),
            __('Add New', 'msa-automatic'),
            'manage_options',
            'post-new.php?post_type=msa_campaign'
        );

        add_submenu_page(
            'msa-automatic',
            __('Settings', 'msa-automatic'),
            __('Settings', 'msa-automatic'),
            'manage_options',
            'msa-automatic-settings',
            array($this, 'render_settings_page')
        );

        add_submenu_page(
            'msa-automatic',
            __('Logs', 'msa-automatic'),
            __('Logs', 'msa-automatic'),
            'manage_options',
            'msa-automatic-logs',
            array($this, 'render_logs_page')
        );

        add_submenu_page(
            'msa-automatic',
            __('Tools', 'msa-automatic'),
            __('Tools', 'msa-automatic'),
            'manage_options',
            'msa-automatic-tools',
            array($this, 'render_tools_page')
        );
    }

    /**
     * Add action links
     *
     * @param array $links Existing links
     * @return array Modified links
     */
    public function add_action_links($links) {
        $plugin_links = array(
            '<a href="' . admin_url('admin.php?page=msa-automatic') . '">' . __('Campaigns', 'msa-automatic') . '</a>',
            '<a href="' . admin_url('admin.php?page=msa-automatic-settings') . '">' . __('Settings', 'msa-automatic') . '</a>'
        );
        return array_merge($plugin_links, $links);
    }

    /**
     * Register admin assets
     */
    public function register_admin_assets() {
        wp_register_style(
            'msa-automatic-admin',
            MSA_AUTOMATIC_URL . 'css/msa-automatic-admin.css',
            array(),
            MSA_AUTOMATIC_VERSION
        );

        wp_register_script(
            'msa-automatic-admin',
            MSA_AUTOMATIC_URL . 'js/msa-automatic-admin.js',
            array('jquery'),
            MSA_AUTOMATIC_VERSION,
            true
        );

        wp_register_script(
            'msa-automatic-admin-edit',
            MSA_AUTOMATIC_URL . 'js/msa-automatic-admin-edit.js',
            array('jquery'),
            MSA_AUTOMATIC_VERSION,
            true
        );

        wp_localize_script('msa-automatic-admin', 'msaAutomaticAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('msa_automatic_nonce'),
            'i18n' => array(
                'confirm_delete' => __('Are you sure you want to delete this campaign?', 'msa-automatic'),
                'confirm_clear_logs' => __('Are you sure you want to clear all logs?', 'msa-automatic'),
                'running' => __('Running...', 'msa-automatic'),
                'success' => __('Success!', 'msa-automatic'),
                'error' => __('Error:', 'msa-automatic')
            )
        ));
    }

    /**
     * Render admin pages
     */
    public function render_admin_page() {
        wp_enqueue_style('msa-automatic-admin');
        wp_enqueue_script('msa-automatic-admin');
        require_once MSA_AUTOMATIC_PATH . 'views/admin-page.php';
    }

    public function render_settings_page() {
        wp_enqueue_style('msa-automatic-admin');
        wp_enqueue_script('msa-automatic-admin');
        require_once MSA_AUTOMATIC_PATH . 'views/tabs/settings.php';
    }

    public function render_logs_page() {
        wp_enqueue_style('msa-automatic-admin');
        wp_enqueue_script('msa-automatic-admin');
        require_once MSA_AUTOMATIC_PATH . 'views/tabs/logs.php';
    }

    public function render_tools_page() {
        wp_enqueue_style('msa-automatic-admin');
        wp_enqueue_script('msa-automatic-admin');
        require_once MSA_AUTOMATIC_PATH . 'views/tabs/tools.php';
    }
}

/**
 * Initialize plugin
 */
function msa_automatic() {
    return MSA_Automatic::get_instance();
}

// Start the plugin
msa_automatic();
