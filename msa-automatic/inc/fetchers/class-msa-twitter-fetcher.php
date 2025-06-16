<?php
if (!defined('ABSPATH')) {
    exit;
}

class MSA_Twitter_Fetcher extends MSA_Base_Fetcher {
    /**
     * Fetcher type identifier
     * @var string
     */
    protected $type = 'twitter';

    /**
     * Twitter API version
     * @var string
     */
    protected $api_version = '2';

    /**
     * Twitter API base URL
     * @var string
     */
    protected $api_base = 'https://api.twitter.com';

    /**
     * Get required settings fields
     *
     * @return array
     */
    public function get_required_settings() {
        return array(
            'api_key' => __('API Key', 'msa-automatic'),
            'api_secret' => __('API Secret', 'msa-automatic'),
            'bearer_token' => __('Bearer Token', 'msa-automatic'),
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

        // Try to get user information to test connection
        $url = sprintf(
            '%s/%s/users/by/username/%s',
            $this->api_base,
            $this->api_version,
            $this->settings['username']
        );

        $response = $this->request($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->settings['bearer_token']
            )
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        return true;
    }

    /**
     * Fetch content from Twitter
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

        // Then fetch tweets
        $url = sprintf(
            '%s/%s/users/%s/tweets',
            $this->api_base,
            $this->api_version,
            $user_id
        );

        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->settings['bearer_token']
            ),
            'body' => array(
                'max_results' => 100,
                'tweet.fields' => 'created_at,entities,public_metrics,attachments',
                'expansions' => 'attachments.media_keys,author_id',
                'media.fields' => 'url,preview_image_url'
            )
        );

        $response = $this->request($url, $args);
        if (is_wp_error($response)) {
            return $response;
        }

        if (empty($response['data'])) {
            return new WP_Error(
                'no_data',
                __('No tweets found', 'msa-automatic')
            );
        }

        // Process includes for media attachments
        $media_lookup = array();
        if (!empty($response['includes']['media'])) {
            foreach ($response['includes']['media'] as $media) {
                $media_lookup[$media['media_key']] = $media;
            }
        }

        // Add media data to tweets
        foreach ($response['data'] as &$tweet) {
            if (!empty($tweet['attachments']['media_keys'])) {
                $tweet['media'] = array();
                foreach ($tweet['attachments']['media_keys'] as $media_key) {
                    if (isset($media_lookup[$media_key])) {
                        $tweet['media'][] = $media_lookup[$media_key];
                    }
                }
            }
        }

        $items = $this->format_items($response['data']);
        $filters = get_post_meta($campaign_id, '_msa_filters', true) ?: array();

        return $this->apply_filters($items, $filters);
    }

    /**
     * Get user ID from username
     *
     * @param string $username Twitter username
     * @return string|WP_Error User ID or WP_Error on failure
     */
    protected function get_user_id($username) {
        $url = sprintf(
            '%s/%s/users/by/username/%s',
            $this->api_base,
            $this->api_version,
            $username
        );

        $response = $this->request($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->settings['bearer_token']
            )
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        if (empty($response['data']['id'])) {
            return new WP_Error(
                'user_not_found',
                __('Twitter user not found', 'msa-automatic')
            );
        }

        return $response['data']['id'];
    }

    /**
     * Get item title
     *
     * @param array $item Raw item data
     * @return string
     */
    protected function get_item_title($item) {
        // Use first line or first few words as title
        $text = $item['text'];
        $lines = explode("\n", $text);
        $first_line = trim($lines[0]);

        if (!empty($first_line)) {
            return wp_trim_words($first_line, 10);
        }

        return __('Tweet', 'msa-automatic');
    }

    /**
     * Get item content
     *
     * @param array $item Raw item data
     * @return string
     */
    protected function get_item_content($item) {
        $content = wp_kses_post($item['text']);

        // Add media attachments
        if (!empty($item['media'])) {
            $content .= "\n\n";
            foreach ($item['media'] as $media) {
                switch ($media['type']) {
                    case 'photo':
                        $content .= sprintf(
                            '<img src="%s" alt="%s" class="twitter-media">',
                            esc_url($media['url']),
                            __('Tweet Image', 'msa-automatic')
                        );
                        break;

                    case 'video':
                    case 'animated_gif':
                        if (!empty($media['preview_image_url'])) {
                            $content .= sprintf(
                                '<img src="%s" alt="%s" class="twitter-media">',
                                esc_url($media['preview_image_url']),
                                __('Tweet Video Preview', 'msa-automatic')
                            );
                        }
                        break;
                }
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
        return $this->settings['username'];
    }

    /**
     * Get item date
     *
     * @param array $item Raw item data
     * @return string
     */
    protected function get_item_date($item) {
        return !empty($item['created_at']) ? $item['created_at'] : '';
    }

    /**
     * Get item URL
     *
     * @param array $item Raw item data
     * @return string
     */
    protected function get_item_url($item) {
        return sprintf(
            'https://twitter.com/%s/status/%s',
            $this->settings['username'],
            $item['id']
        );
    }

    /**
     * Get item image URL
     *
     * @param array $item Raw item data
     * @return string
     */
    protected function get_item_image($item) {
        if (!empty($item['media'])) {
            foreach ($item['media'] as $media) {
                if ($media['type'] === 'photo') {
                    return $media['url'];
                } elseif (in_array($media['type'], array('video', 'animated_gif'))) {
                    return $media['preview_image_url'];
                }
            }
        }
        return '';
    }

    /**
     * Get item additional metadata
     *
     * @param array $item Raw item data
     * @return array
     */
    protected function get_item_meta($item) {
        $meta = array(
            'twitter_id' => $item['id']
        );

        if (!empty($item['public_metrics'])) {
            $meta['likes'] = $item['public_metrics']['like_count'];
            $meta['retweets'] = $item['public_metrics']['retweet_count'];
            $meta['replies'] = $item['public_metrics']['reply_count'];
            $meta['quotes'] = $item['public_metrics']['quote_count'];
        }

        if (!empty($item['entities']['hashtags'])) {
            $meta['hashtags'] = wp_list_pluck($item['entities']['hashtags'], 'tag');
        }

        if (!empty($item['entities']['mentions'])) {
            $meta['mentions'] = wp_list_pluck($item['entities']['mentions'], 'username');
        }

        return $meta;
    }
}
