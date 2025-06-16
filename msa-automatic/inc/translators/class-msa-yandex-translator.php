<?php
if (!defined('ABSPATH')) {
    exit;
}

class MSA_Yandex_Translator extends MSA_Base_Translator {
    /**
     * Translator type identifier
     * @var string
     */
    protected $type = 'yandex';

    /**
     * Yandex Translate API base URL
     * @var string
     */
    protected $api_base = 'https://translate.api.cloud.yandex.net/translate/v2';

    /**
     * Get required settings fields
     *
     * @return array
     */
    public function get_required_settings() {
        return array(
            'api_key' => __('API Key', 'msa-automatic'),
            'folder_id' => __('Folder ID', 'msa-automatic')
        );
    }

    /**
     * Test API connection
     *
     * @return bool|WP_Error True on success or WP_Error on failure
     */
    public function test_connection() {
        $validation = $this->validate_settings($this->settings);
        if (is_wp_error($validation)) {
            return $validation;
        }

        // Try to translate a simple text
        $result = $this->translate('Hello', 'es');
        if (is_wp_error($result)) {
            return $result;
        }

        return true;
    }

    /**
     * Translate text
     *
     * @param string $text Text to translate
     * @param string $target_lang Target language code
     * @param string $source_lang Source language code (optional)
     * @return string|WP_Error Translated text or WP_Error on failure
     */
    public function translate($text, $target_lang, $source_lang = '') {
        if (empty($text)) {
            return '';
        }

        $validation = $this->validate_settings($this->settings);
        if (is_wp_error($validation)) {
            return $validation;
        }

        if (!$this->is_language_supported($target_lang)) {
            return new WP_Error(
                'unsupported_language',
                sprintf(
                    __('Language not supported: %s', 'msa-automatic'),
                    $target_lang
                )
            );
        }

        // Clean and prepare text
        $text = $this->clean_text($text);
        $chunks = $this->split_text($text);
        $translated_chunks = array();

        foreach ($chunks as $chunk) {
            $url = $this->api_base . '/translate';
            
            $body = array(
                'targetLanguageCode' => $this->format_language_code($target_lang),
                'texts' => array($chunk),
                'folderId' => $this->settings['folder_id']
            );

            if (!empty($source_lang)) {
                $body['sourceLanguageCode'] = $this->format_language_code($source_lang);
            }

            $response = $this->request($url, array(
                'method' => 'POST',
                'headers' => array(
                    'Authorization' => 'Api-Key ' . $this->settings['api_key'],
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode($body)
            ));

            if (is_wp_error($response)) {
                return $response;
            }

            if (empty($response['translations'][0]['text'])) {
                return new WP_Error(
                    'translation_failed',
                    __('Translation failed: Empty response from API', 'msa-automatic')
                );
            }

            $translated_chunks[] = $response['translations'][0]['text'];
        }

        return implode(' ', $translated_chunks);
    }

    /**
     * Detect language of text
     *
     * @param string $text Text to analyze
     * @return string|WP_Error Language code or WP_Error on failure
     */
    public function detect_language($text) {
        if (empty($text)) {
            return new WP_Error(
                'empty_text',
                __('No text provided for language detection', 'msa-automatic')
            );
        }

        $validation = $this->validate_settings($this->settings);
        if (is_wp_error($validation)) {
            return $validation;
        }

        $url = $this->api_base . '/detect';
        
        $response = $this->request($url, array(
            'method' => 'POST',
            'headers' => array(
                'Authorization' => 'Api-Key ' . $this->settings['api_key'],
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'text' => $this->clean_text($text),
                'folderId' => $this->settings['folder_id']
            ))
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        if (empty($response['languageCode'])) {
            return new WP_Error(
                'detection_failed',
                __('Language detection failed', 'msa-automatic')
            );
        }

        return $response['languageCode'];
    }

    /**
     * Get supported languages
     *
     * @return array Array of language codes and names
     */
    public function get_supported_languages() {
        return array(
            'af' => __('Afrikaans', 'msa-automatic'),
            'am' => __('Amharic', 'msa-automatic'),
            'ar' => __('Arabic', 'msa-automatic'),
            'az' => __('Azerbaijani', 'msa-automatic'),
            'ba' => __('Bashkir', 'msa-automatic'),
            'be' => __('Belarusian', 'msa-automatic'),
            'bg' => __('Bulgarian', 'msa-automatic'),
            'bn' => __('Bengali', 'msa-automatic'),
            'bs' => __('Bosnian', 'msa-automatic'),
            'ca' => __('Catalan', 'msa-automatic'),
            'ceb' => __('Cebuano', 'msa-automatic'),
            'cs' => __('Czech', 'msa-automatic'),
            'cy' => __('Welsh', 'msa-automatic'),
            'da' => __('Danish', 'msa-automatic'),
            'de' => __('German', 'msa-automatic'),
            'el' => __('Greek', 'msa-automatic'),
            'en' => __('English', 'msa-automatic'),
            'eo' => __('Esperanto', 'msa-automatic'),
            'es' => __('Spanish', 'msa-automatic'),
            'et' => __('Estonian', 'msa-automatic'),
            'eu' => __('Basque', 'msa-automatic'),
            'fa' => __('Persian', 'msa-automatic'),
            'fi' => __('Finnish', 'msa-automatic'),
            'fr' => __('French', 'msa-automatic'),
            'ga' => __('Irish', 'msa-automatic'),
            'gd' => __('Scottish Gaelic', 'msa-automatic'),
            'gl' => __('Galician', 'msa-automatic'),
            'gu' => __('Gujarati', 'msa-automatic'),
            'he' => __('Hebrew', 'msa-automatic'),
            'hi' => __('Hindi', 'msa-automatic'),
            'hr' => __('Croatian', 'msa-automatic'),
            'ht' => __('Haitian', 'msa-automatic'),
            'hu' => __('Hungarian', 'msa-automatic'),
            'hy' => __('Armenian', 'msa-automatic'),
            'id' => __('Indonesian', 'msa-automatic'),
            'is' => __('Icelandic', 'msa-automatic'),
            'it' => __('Italian', 'msa-automatic'),
            'ja' => __('Japanese', 'msa-automatic'),
            'jv' => __('Javanese', 'msa-automatic'),
            'ka' => __('Georgian', 'msa-automatic'),
            'kk' => __('Kazakh', 'msa-automatic'),
            'km' => __('Khmer', 'msa-automatic'),
            'kn' => __('Kannada', 'msa-automatic'),
            'ko' => __('Korean', 'msa-automatic'),
            'ky' => __('Kyrgyz', 'msa-automatic'),
            'la' => __('Latin', 'msa-automatic'),
            'lb' => __('Luxembourgish', 'msa-automatic'),
            'lo' => __('Lao', 'msa-automatic'),
            'lt' => __('Lithuanian', 'msa-automatic'),
            'lv' => __('Latvian', 'msa-automatic'),
            'mg' => __('Malagasy', 'msa-automatic'),
            'mi' => __('Maori', 'msa-automatic'),
            'mk' => __('Macedonian', 'msa-automatic'),
            'ml' => __('Malayalam', 'msa-automatic'),
            'mn' => __('Mongolian', 'msa-automatic'),
            'mr' => __('Marathi', 'msa-automatic'),
            'ms' => __('Malay', 'msa-automatic'),
            'mt' => __('Maltese', 'msa-automatic'),
            'my' => __('Burmese', 'msa-automatic'),
            'ne' => __('Nepali', 'msa-automatic'),
            'nl' => __('Dutch', 'msa-automatic'),
            'no' => __('Norwegian', 'msa-automatic'),
            'pa' => __('Punjabi', 'msa-automatic'),
            'pl' => __('Polish', 'msa-automatic'),
            'pt' => __('Portuguese', 'msa-automatic'),
            'ro' => __('Romanian', 'msa-automatic'),
            'ru' => __('Russian', 'msa-automatic'),
            'si' => __('Sinhala', 'msa-automatic'),
            'sk' => __('Slovak', 'msa-automatic'),
            'sl' => __('Slovenian', 'msa-automatic'),
            'sq' => __('Albanian', 'msa-automatic'),
            'sr' => __('Serbian', 'msa-automatic'),
            'su' => __('Sundanese', 'msa-automatic'),
            'sv' => __('Swedish', 'msa-automatic'),
            'sw' => __('Swahili', 'msa-automatic'),
            'ta' => __('Tamil', 'msa-automatic'),
            'te' => __('Telugu', 'msa-automatic'),
            'tg' => __('Tajik', 'msa-automatic'),
            'th' => __('Thai', 'msa-automatic'),
            'tl' => __('Filipino', 'msa-automatic'),
            'tr' => __('Turkish', 'msa-automatic'),
            'tt' => __('Tatar', 'msa-automatic'),
            'uk' => __('Ukrainian', 'msa-automatic'),
            'ur' => __('Urdu', 'msa-automatic'),
            'uz' => __('Uzbek', 'msa-automatic'),
            'vi' => __('Vietnamese', 'msa-automatic'),
            'yi' => __('Yiddish', 'msa-automatic'),
            'zh' => __('Chinese', 'msa-automatic')
        );
    }

    /**
     * Format language code
     *
     * @param string $lang_code Language code to format
     * @return string Formatted language code
     */
    protected function format_language_code($lang_code) {
        // Yandex uses lowercase two-letter codes
        return strtolower(substr($lang_code, 0, 2));
    }
}
