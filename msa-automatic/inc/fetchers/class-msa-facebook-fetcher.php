<?php
if (!defined('ABSPATH')) {
    exit;
}

class MSA_Facebook_Fetcher extends MSA_Base_Fetcher {
    /**
     * Fetcher type identifier
     * @var string
     */
    protected $type = 'facebook';

    /**
     * Facebook Graph API version
     * @var string
     */
    protected $api_version = 'v17.0';

    /**
     * Get required settings fields
     *
     * @return array
     */
    public function get_required_settings() {
        return array(
            'app_id' => __('App ID', 'msa-automatic'),
            'app_secret' => __('App Secret', 'msa-automatic'),
            'page_id' => __('Page ID', 'msa-automatic'),
            'access_token' => __('Access Token', 'msa-automatic')
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

        $url = sprintf(
            'https://graph.facebook.com/%s/%s',
            $this->api_version,
            $this->settings['page_id']
        );

        $response = $this->request($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->settings['access_token']
            )
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        return true;
    }

    /**
     * Fetch content from Facebook
     *
     * @param int $campaign_id Campaign ID
     * @return array|WP_Error Array of fetched items or WP_Error on failure
     */
    public function fetch($campaign_id) {
        $validation = $this->validate_settings($this->settings);
        if (is_wp_error($validation)) {
            return $validation;
        }

        $url = sprintf(
            'https://graph.facebook.com/%s/%s/posts',
            $this->api_version,
            $this->settings['page_id']
        );

        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->settings['access_token']
            ),
            'body' => array(
                'fields' => 'id,message,created_time,permalink_url,full_picture,attachments{type,url,title,description},from'
            )
        );

        $response = $this->request($url, $args);
        if (is_wp_error($response)) {
            return $response;
        }

        if (empty($response['data'])) {
            return new WP_Error(
                'no_data',
                __('No posts found', 'msa-automatic')
            );
        }

        $items = $this->format_items($response['data']);
        $filters = get_post_meta($campaign_id, '_msa_filters', true) ?: array();

        return $this->apply_filters($items, $filters);
    }

    /**
     * Get item title
     *
     * @param array $item Raw item data
     * @return string
     */
    protected function get_item_title($item) {
        if (!empty($item['attachments']['data'][0]['title'])) {
            return $item['attachments']['data'][0]['title'];
        }

        // Extract first line or first 100 characters of message as title
        if (!empty($item['message'])) {
            $lines = explode("\n", $item['message']);
            $first_line = trim($lines[0]);
            if (!empty($first_line)) {
                return wp_trim_words($first_line, 10);
            }
            return wp_trim_words($item['message'], 10);
        }

        return __('Facebook Post', 'msa-automatic');
    }

    /**
     * Get item content
     *
     * @param array $item Raw item data
     * @return string
     */
    protected function get_item_content($item) {
        $content = '';

        // Add message
        if (!empty($item['message'])) {
            $content .= wp_kses_post($item['message']) . "\n\n";
        }

        // Add attachment details
        if (!empty($item['attachments']['data'][0])) {
            $attachment = $item['attachments']['data'][0];
            
            if (!empty($attachment['description'])) {
                $content .= wp_kses_post($attachment['description']) . "\n\n";
            }

            if ($attachment['type'] === 'video_inline' && !empty($attachment['url'])) {
                $content .= sprintf(
                    '<p><a href="%s" target="_blank">%s</a></p>',
                    esc_url($attachment['url']),
                    __('Watch Video', 'msa-automatic')
                );
            }
        }

        return $content;
    }

    /**
     * Get item author
     *
     * @param array $item Raw item data
     * @return string
     */
    protected function get_item_author($item) {
        return !empty($item['from']['name']) ? $item['from']['name'] : '';
    }

    /**
     * Get item date
     *
     * @param array $item Raw item data
     * @return string
     */
    protected function get_item_date($item) {
        return !empty($item['created_time']) ? $item['created_time'] : '';
    }

    /**
     * Get item URL
     *
     * @param array $item Raw item data
     * @return string
     */
    protected function get_item_url($item) {
        return !empty($item['permalink_url']) ? $item['permalink_url'] : '';
    }

    /**
     * Get item image URL
     *
     * @param array $item Raw item data
     * @return string
     */
    protected function get_item_image($item) {
        return !empty($item['full_picture']) ? $item['full_picture'] : '';
    }

    /**
     * Get item additional metadata
     *
     * @param array $item Raw item data
     * @return array
     */
    protected function get_item_meta($item) {
        $meta = array(
            'facebook_id' => $item['id'],
            'type' => !empty($item['attachments']['data'][0]['type']) 
                ? $item['attachments']['data'][0]['type'] 
                : 'status'
        );

        // Add engagement metrics if available
        if (!empty($item['likes'])) {
            $meta['likes'] = count($item['likes']['data']);
        }
        if (!empty($item['comments'])) {
            $meta['comments'] = count($item['comments']['data']);
        }
        if (!empty($item['shares'])) {
            $meta['shares'] = $item['shares']['count'];
        }

        return $meta;
    }

    /**
     * Get long-lived access token
     *
     * @param string $short_lived_token Short-lived access token
     * @return string|WP_Error Long-lived access token or WP_Error on failure
     */
    public function get_long_lived_token($short_lived_token) {
        $url = sprintf(
            'https://graph.facebook.com/%s/oauth/access_token',
            $this->api_version
        );

        $args = array(
            'body' => array(
                'grant_type' => 'fb_exchange_token',
                'client_id' => $this->settings['app_id'],
                'client_secret' => $this->settings['app_secret'],
                'fb_exchange_token' => $short_lived_token
            )
        );

        $response = $this->request($url, $args, false);
        if (is_wp_error($response)) {
            return $response;
        }

        if (empty($response['access_token'])) {
            return new WP_Error(
                'token_error',
                __('Failed to get long-lived access token', 'msa-automatic')
            );
        }

        return $response['access_token'];
    }

    /**
     * Get page access token
     *
     * @param string $user_token User access token
     * @return string|WP_Error Page access token or WP_Error on failure
     */
    public function get_page_token($user_token) {
        $url = sprintf(
            'https://graph.facebook.com/%s/%s',
            $this->api_version,
            $this->settings['page_id']
        );

        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $user_token
            ),
            'body' => array(
                'fields' => 'access_token'
            )
        );

        $response = $this->request($url, $args, false);
        if (is_wp_error($response)) {
            return $response;
        }

        if (empty($response['access_token'])) {
            return new WP_Error(
                'token_error',
                __('Failed to get page access token', 'msa-automatic')
            );
        }

        return $response['access_token'];
    }
}
