<?php
if (!defined('ABSPATH')) {
    exit;
}

class MSA_Translator_Loader {
    /**
     * Instance of this class
     * @var MSA_Translator_Loader
     */
    private static $instance = null;

    /**
     * Available translators
     * @var array
     */
    private $translators = array();

    /**
     * Get instance of this class
     *
     * @return MSA_Translator_Loader
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
        $this->register_default_translators();
    }

    /**
     * Register default translators
     */
    private function register_default_translators() {
        $this->register_translator('yandex', array(
            'name' => __('Yandex Translate', 'msa-automatic'),
            'description' => __('Translate content using Yandex Translate API', 'msa-automatic'),
            'class' => 'MSA_Yandex_Translator',
            'settings' => array(
                'api_key' => __('API Key', 'msa-automatic'),
                'folder_id' => __('Folder ID', 'msa-automatic')
            )
        ));

        // Add more translators here as they are implemented
        // Example:
        /*
        $this->register_translator('google', array(
            'name' => __('Google Translate', 'msa-automatic'),
            'description' => __('Translate content using Google Cloud Translation API', 'msa-automatic'),
            'class' => 'MSA_Google_Translator',
            'settings' => array(
                'api_key' => __('API Key', 'msa-automatic'),
                'project_id' => __('Project ID', 'msa-automatic')
            )
        ));
        */
    }

    /**
     * Register a new translator
     *
     * @param string $type Translator type identifier
     * @param array $args Translator arguments
     * @return bool True on success, false on failure
     */
    public function register_translator($type, $args) {
        if (empty($type) || empty($args['class']) || !class_exists($args['class'])) {
            return false;
        }

        $this->translators[$type] = wp_parse_args($args, array(
            'name' => '',
            'description' => '',
            'class' => '',
            'settings' => array()
        ));

        return true;
    }

    /**
     * Unregister a translator
     *
     * @param string $type Translator type identifier
     * @return bool True on success, false on failure
     */
    public function unregister_translator($type) {
        if (isset($this->translators[$type])) {
            unset($this->translators[$type]);
            return true;
        }
        return false;
    }

    /**
     * Get available translators
     *
     * @return array Array of registered translators
     */
    public function get_available_translators() {
        return $this->translators;
    }

    /**
     * Get translator information
     *
     * @param string $type Translator type identifier
     * @return array|false Translator information or false if not found
     */
    public function get_translator_info($type) {
        return isset($this->translators[$type]) ? $this->translators[$type] : false;
    }

    /**
     * Get translator instance
     *
     * @param string $type Translator type identifier
     * @param array $settings Translator settings
     * @return MSA_Base_Translator|WP_Error Translator instance or WP_Error on failure
     */
    public function get_translator($type, $settings = array()) {
        if (!isset($this->translators[$type])) {
            return new WP_Error(
                'invalid_translator',
                sprintf(__('Invalid translator type: %s', 'msa-automatic'), $type)
            );
        }

        $class = $this->translators[$type]['class'];
        if (!class_exists($class)) {
            return new WP_Error(
                'missing_class',
                sprintf(__('Translator class not found: %s', 'msa-automatic'), $class)
            );
        }

        try {
            $translator = new $class($settings);
            
            if (!($translator instanceof MSA_Base_Translator)) {
                throw new Exception(__('Invalid translator class', 'msa-automatic'));
            }

            return $translator;
        } catch (Exception $e) {
            return new WP_Error(
                'translator_error',
                $e->getMessage()
            );
        }
    }

    /**
     * Get translator for campaign
     *
     * @param int $campaign_id Campaign ID
     * @return MSA_Base_Translator|WP_Error Translator instance or WP_Error on failure
     */
    public function get_campaign_translator($campaign_id) {
        $type = get_post_meta($campaign_id, '_msa_translator_type', true);
        if (empty($type)) {
            return new WP_Error(
                'no_translator',
                __('No translator type specified for campaign', 'msa-automatic')
            );
        }

        $settings = get_post_meta($campaign_id, '_msa_translator_settings', true);
        if (!is_array($settings)) {
            $settings = array();
        }

        return $this->get_translator($type, $settings);
    }

    /**
     * Validate translator settings
     *
     * @param string $type Translator type identifier
     * @param array $settings Settings to validate
     * @return bool|WP_Error True if valid or WP_Error on failure
     */
    public function validate_settings($type, $settings) {
        $translator = $this->get_translator($type);
        if (is_wp_error($translator)) {
            return $translator;
        }

        return $translator->validate_settings($settings);
    }

    /**
     * Test translator connection
     *
     * @param string $type Translator type identifier
     * @param array $settings Settings to test
     * @return bool|WP_Error True on success or WP_Error on failure
     */
    public function test_connection($type, $settings) {
        $translator = $this->get_translator($type, $settings);
        if (is_wp_error($translator)) {
            return $translator;
        }

        return $translator->test_connection();
    }

    /**
     * Get required settings for translator
     *
     * @param string $type Translator type identifier
     * @return array Array of required settings
     */
    public function get_required_settings($type) {
        $translator = $this->get_translator($type);
        if (is_wp_error($translator)) {
            return array();
        }

        return $translator->get_required_settings();
    }

    /**
     * Get supported languages for translator
     *
     * @param string $type Translator type identifier
     * @return array Array of supported languages
     */
    public function get_supported_languages($type) {
        $translator = $this->get_translator($type);
        if (is_wp_error($translator)) {
            return array();
        }

        return $translator->get_supported_languages();
    }
}

/**
 * Get instance of translator loader
 *
 * @return MSA_Translator_Loader
 */
function msa_translator_loader() {
    return MSA_Translator_Loader::get_instance();
}
