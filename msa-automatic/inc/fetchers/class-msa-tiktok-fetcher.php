<?php
if (!defined('ABSPATH')) {
    exit;
}

class MSA_TikTok_Fetcher extends MSA_Base_Fetcher {
    /**
     * Fetcher type identifier
     * @var string
     */
    protected $type = 'tiktok';

    /**
     * TikTok API base URL
     * @var string
     */
    protected $api_base = 'https://open.tiktokapis.com/v2';

    /**
     * Get required settings fields
     *
     * @return array
     */
    public function get_required_settings() {
        return array(
            'client_key' => __('Client Key', 'msa-automatic'),
            'client_secret' => __('Client Secret', 'msa-automatic'),
            'access_token' => __('Access Token', 'msa-automatic'),
            'username' => __('Username', 'msa-automatic')
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

        // Try to get user information
        $url = $this->api_base . '/user/info/';
        
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
     * Fetch content from TikTok
     *
     * @param int $campaign_id Campaign ID
     * @return array|WP_Error Array of fetched items or WP_Error on failure
     */
    public function fetch($campaign_id) {
        $validation = $this->validate_settings($this->settings);
        if (is_wp_error($validation)) {
            return $validation;
        }

        // First get user ID from username
        $user_id = $this->get_user_id($this->settings['username']);
        if (is_wp_error($user_id)) {
            return $user_id;
        }

        // Then fetch videos
        $url = $this->api_base . '/video/list/';
        
        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->settings['access_token']
            ),
            'body' => array(
                'user_id' => $user_id,
                'max_count' => 20,
                'fields' => 'id,create_time,share_url,video_description,statistics,embed_html,embed_link,thumbnail_url'
            )
        );

        $response = $this->request($url, $args);
        if (is_wp_error($response)) {
            return $response;
        }

        if (empty($response['videos'])) {
            return new WP_Error(
                'no_videos',
                __('No videos found', 'msa-automatic')
            );
        }

        $items = $this->format_items($response['videos']);
        $filters = get_post_meta($campaign_id, '_msa_filters', true) ?: array();

        return $this->apply_filters($items, $filters);
    }

    /**
     * Get user ID from username
     *
     * @param string $username TikTok username
     * @return string|WP_Error User ID or WP_Error on failure
     */
    protected function get_user_id($username) {
        $url = $this->api_base . '/user/info/query/';
        
        $response = $this->request($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->settings['access_token']
            ),
            'body' => array(
                'username' => $username
            )
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        if (empty($response['user']['id'])) {
            return new WP_Error(
                'user_not_found',
                __('TikTok user not found', 'msa-automatic')
            );
        }

        return $response['user']['id'];
    }

    /**
     * Get item title
     *
     * @param array $item Raw item data
     * @return string
     */
    protected function get_item_title($item) {
        // Use first line or first few words of description as title
        if (!empty($item['video_description'])) {
            $lines = explode("\n", $item['video_description']);
            $first_line = trim($lines[0]);
            if (!empty($first_line)) {
                return wp_trim_words($first_line, 10);
            }
            return wp_trim_words($item['video_description'], 10);
        }
        return __('TikTok Video', 'msa-automatic');
    }

    /**
     * Get item content
     *
     * @param array $item Raw item data
     * @return string
     */
    protected function get_item_content($item) {
        $content = '';

        // Add description
        if (!empty($item['video_description'])) {
            $content .= wp_kses_post($item['video_description']) . "\n\n";
        }

        // Add embedded video
        if (!empty($item['embed_html'])) {
            $content .= $item['embed_html'];
        } else {
            // Fallback to link if embed code not available
            $content .= sprintf(
                '<p><a href="%s" target="_blank">%s</a></p>',
                esc_url($item['share_url']),
                __('Watch on TikTok', 'msa-automatic')
            );
        }

        // Add statistics if available
        if (!empty($item['statistics'])) {
            $content .= '<div class="tiktok-stats">';
            $content .= sprintf(
                '<span class="likes">%s %s</span> ',
                number_format_i18n($item['statistics']['like_count']),
                __('Likes', 'msa-automatic')
            );
            $content .= sprintf(
                '<span class="comments">%s %s</span> ',
                number_format_i18n($item['statistics']['comment_count']),
                __('Comments', 'msa-automatic')
            );
            $content .= sprintf(
                '<span class="shares">%s %s</span>',
                number_format_i18n($item['statistics']['share_count']),
                __('Shares', 'msa-automatic')
            );
            $content .= '</div>';
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
        return $this->settings['username'];
    }

    /**
     * Get item date
     *
     * @param array $item Raw item data
     * @return string
     */
    protected function get_item_date($item) {
        return !empty($item['create_time']) ? date('c', $item['create_time']) : '';
    }

    /**
     * Get item URL
     *
     * @param array $item Raw item data
     * @return string
     */
    protected function get_item_url($item) {
        return !empty($item['share_url']) ? $item['share_url'] : '';
    }

    /**
     * Get item image URL
     *
     * @param array $item Raw item data
     * @return string
     */
    protected function get_item_image($item) {
        return !empty($item['thumbnail_url']) ? $item['thumbnail_url'] : '';
    }

    /**
     * Get item additional metadata
     *
     * @param array $item Raw item data
     * @return array
     */
    protected function get_item_meta($item) {
        $meta = array(
            'tiktok_id' => $item['id']
        );

        if (!empty($item['statistics'])) {
            $meta['likes'] = $item['statistics']['like_count'];
            $meta['comments'] = $item['statistics']['comment_count'];
            $meta['shares'] = $item['statistics']['share_count'];
            $meta['views'] = $item['statistics']['view_count'];
        }

        if (!empty($item['embed_link'])) {
            $meta['embed_link'] = $item['embed_link'];
        }

        // Extract hashtags from description
        if (!empty($item['video_description'])) {
            preg_match_all('/#([^\s#]+)/', $item['video_description'], $matches);
            if (!empty($matches[1])) {
                $meta['hashtags'] = $matches[1];
            }
        }

        return $meta;
    }

    /**
     * Get access token using client credentials
     *
     * @return string|WP_Error Access token or WP_Error on failure
     */
    public function get_access_token() {
        $url = 'https://open-api.tiktok.com/oauth/access_token/';
        
        $response = $this->request($url, array(
            'method' => 'POST',
            'body' => array(
                'client_key' => $this->settings['client_key'],
                'client_secret' => $this->settings['client_secret'],
                'grant_type' => 'client_credentials'
            )
        ), false);

        if (is_wp_error($response)) {
            return $response;
        }

        if (empty($response['access_token'])) {
            return new WP_Error(
                'token_error',
                __('Failed to get access token', 'msa-automatic')
            );
        }

        return $response['access_token'];
    }

    /**
     * Refresh access token
     *
     * @param string $refresh_token Refresh token
     * @return string|WP_Error New access token or WP_Error on failure
     */
    public function refresh_access_token($refresh_token) {
        $url = 'https://open-api.tiktok.com/oauth/refresh_token/';
        
        $response = $this->request($url, array(
            'method' => 'POST',
            'body' => array(
                'client_key' => $this->settings['client_key'],
                'refresh_token' => $refresh_token,
                'grant_type' => 'refresh_token'
            )
        ), false);

        if (is_wp_error($response)) {
            return $response;
        }

        if (empty($response['access_token'])) {
            return new WP_Error(
                'token_error',
                __('Failed to refresh access token', 'msa-automatic')
            );
        }

        return $response['access_token'];
    }
}
