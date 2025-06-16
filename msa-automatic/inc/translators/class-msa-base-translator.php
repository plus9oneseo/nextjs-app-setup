<?php
if (!defined('ABSPATH')) {
    exit;
}

abstract class MSA_Base_Translator {
    /**
     * Translator type identifier
     * @var string
     */
    protected $type;

    /**
     * Translator settings
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
     * @param array $settings Translator settings
     */
    public function __construct($settings = array()) {
        $this->settings = $settings;
        $this->logger = msa_logger();
    }

    /**
     * Get translator type
     *
     * @return string
     */
    public function get_type() {
        return $this->type;
    }

    /**
     * Get translator settings
     *
     * @return array
     */
    public function get_settings() {
        return $this->settings;
    }

    /**
     * Update translator settings
     *
     * @param array $settings New settings
     * @return void
     */
    public function update_settings($settings) {
        $this->settings = wp_parse_args($settings, $this->settings);
    }

    /**
     * Translate text
     *
     * @param string $text Text to translate
     * @param string $target_lang Target language code
     * @param string $source_lang Source language code (optional)
     * @return string|WP_Error Translated text or WP_Error on failure
     */
    abstract public function translate($text, $target_lang, $source_lang = '');

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
     * Get supported languages
     *
     * @return array Array of language codes and names
     */
    abstract public function get_supported_languages();

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
        $cache_key = 'msa_translation_' . md5($url . serialize($args));

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
                'Translation API request failed: %s',
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
                    __('Translation API request failed with status code: %d', 'msa-automatic'),
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
                __('Failed to parse translation API response', 'msa-automatic')
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
     * Clean text before translation
     *
     * @param string $text Text to clean
     * @return string Cleaned text
     */
    protected function clean_text($text) {
        // Remove HTML tags but preserve line breaks
        $text = wp_strip_all_tags($text, false);
        
        // Convert HTML entities
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        
        // Remove multiple spaces
        $text = preg_replace('/\s+/', ' ', $text);
        
        return trim($text);
    }

    /**
     * Split text into chunks for translation
     *
     * @param string $text Text to split
     * @param int $max_length Maximum chunk length
     * @return array Array of text chunks
     */
    protected function split_text($text, $max_length = 1000) {
        if (strlen($text) <= $max_length) {
            return array($text);
        }

        $chunks = array();
        $sentences = preg_split('/(?<=[.!?])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $current_chunk = '';

        foreach ($sentences as $sentence) {
            $new_chunk = trim($current_chunk . ' ' . $sentence);
            
            if (strlen($new_chunk) > $max_length && !empty($current_chunk)) {
                $chunks[] = $current_chunk;
                $current_chunk = $sentence;
            } else {
                $current_chunk = $new_chunk;
            }
        }

        if (!empty($current_chunk)) {
            $chunks[] = $current_chunk;
        }

        return $chunks;
    }

    /**
     * Detect language of text
     *
     * @param string $text Text to analyze
     * @return string|WP_Error Language code or WP_Error on failure
     */
    public function detect_language($text) {
        return new WP_Error(
            'not_implemented',
            __('Language detection not implemented for this translator', 'msa-automatic')
        );
    }

    /**
     * Format language code
     *
     * @param string $lang_code Language code to format
     * @return string Formatted language code
     */
    protected function format_language_code($lang_code) {
        // Default implementation - override in specific translators if needed
        return strtolower($lang_code);
    }

    /**
     * Check if language is supported
     *
     * @param string $lang_code Language code to check
     * @return bool Whether language is supported
     */
    public function is_language_supported($lang_code) {
        $supported = $this->get_supported_languages();
        return isset($supported[$this->format_language_code($lang_code)]);
    }

    /**
     * Get language name
     *
     * @param string $lang_code Language code
     * @return string Language name or code if not found
     */
    public function get_language_name($lang_code) {
        $supported = $this->get_supported_languages();
        $code = $this->format_language_code($lang_code);
        return isset($supported[$code]) ? $supported[$code] : $lang_code;
    }
}
