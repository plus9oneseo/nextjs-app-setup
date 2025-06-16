<?php
if (!defined('ABSPATH')) {
    exit;
}

class MSA_YouTube_Fetcher extends MSA_Base_Fetcher {
    /**
     * Fetcher type identifier
     * @var string
     */
    protected $type = 'youtube';

    /**
     * YouTube API base URL
     * @var string
     */
    protected $api_base = 'https://www.googleapis.com/youtube/v3';

    /**
     * Get required settings fields
     *
     * @return array
     */
    public function get_required_settings() {
        return array(
            'api_key' => __('API Key', 'msa-automatic'),
            'channel_id' => __('Channel ID', 'msa-automatic'),
            'max_results' => __('Max Results', 'msa-automatic')
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

        // Try to get channel information
        $url = add_query_arg(array(
            'part' => 'snippet',
            'id' => $this->settings['channel_id'],
            'key' => $this->settings['api_key']
        ), $this->api_base . '/channels');

        $response = $this->request($url);
        if (is_wp_error($response)) {
            return $response;
        }

        if (empty($response['items'])) {
            return new WP_Error(
                'channel_not_found',
                __('YouTube channel not found', 'msa-automatic')
            );
        }

        return true;
    }

    /**
     * Fetch content from YouTube
     *
     * @param int $campaign_id Campaign ID
     * @return array|WP_Error Array of fetched items or WP_Error on failure
     */
    public function fetch($campaign_id) {
        $validation = $this->validate_settings($this->settings);
        if (is_wp_error($validation)) {
            return $validation;
        }

        // First get upload playlist ID
        $playlist_id = $this->get_uploads_playlist_id();
        if (is_wp_error($playlist_id)) {
            return $playlist_id;
        }

        // Then fetch videos from the playlist
        $url = add_query_arg(array(
            'part' => 'snippet,contentDetails,statistics',
            'playlistId' => $playlist_id,
            'maxResults' => min(50, absint($this->settings['max_results'])),
            'key' => $this->settings['api_key']
        ), $this->api_base . '/playlistItems');

        $response = $this->request($url);
        if (is_wp_error($response)) {
            return $response;
        }

        if (empty($response['items'])) {
            return new WP_Error(
                'no_videos',
                __('No videos found', 'msa-automatic')
            );
        }

        // Get detailed video information
        $video_ids = array();
        foreach ($response['items'] as $item) {
            $video_ids[] = $item['contentDetails']['videoId'];
        }

        $videos_url = add_query_arg(array(
            'part' => 'snippet,contentDetails,statistics',
            'id' => implode(',', $video_ids),
            'key' => $this->settings['api_key']
        ), $this->api_base . '/videos');

        $videos_response = $this->request($videos_url);
        if (is_wp_error($videos_response)) {
            return $videos_response;
        }

        // Create lookup table for video details
        $video_details = array();
        foreach ($videos_response['items'] as $video) {
            $video_details[$video['id']] = $video;
        }

        // Merge video details with playlist items
        foreach ($response['items'] as &$item) {
            $video_id = $item['contentDetails']['videoId'];
            if (isset($video_details[$video_id])) {
                $item['statistics'] = $video_details[$video_id]['statistics'];
                $item['contentDetails'] = $video_details[$video_id]['contentDetails'];
            }
        }

        $items = $this->format_items($response['items']);
        $filters = get_post_meta($campaign_id, '_msa_filters', true) ?: array();

        return $this->apply_filters($items, $filters);
    }

    /**
     * Get uploads playlist ID for channel
     *
     * @return string|WP_Error Playlist ID or WP_Error on failure
     */
    protected function get_uploads_playlist_id() {
        $url = add_query_arg(array(
            'part' => 'contentDetails',
            'id' => $this->settings['channel_id'],
            'key' => $this->settings['api_key']
        ), $this->api_base . '/channels');

        $response = $this->request($url);
        if (is_wp_error($response)) {
            return $response;
        }

        if (empty($response['items'][0]['contentDetails']['relatedPlaylists']['uploads'])) {
            return new WP_Error(
                'no_uploads',
                __('Could not find uploads playlist', 'msa-automatic')
            );
        }

        return $response['items'][0]['contentDetails']['relatedPlaylists']['uploads'];
    }

    /**
     * Get item title
     *
     * @param array $item Raw item data
     * @return string
     */
    protected function get_item_title($item) {
        return !empty($item['snippet']['title']) ? $item['snippet']['title'] : '';
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
        if (!empty($item['snippet']['description'])) {
            $content .= wp_kses_post($item['snippet']['description']) . "\n\n";
        }

        // Add embedded video
        $video_id = $item['contentDetails']['videoId'];
        $content .= sprintf(
            '<div class="youtube-video-container"><iframe width="560" height="315" src="https://www.youtube.com/embed/%s" frameborder="0" allowfullscreen></iframe></div>',
            esc_attr($video_id)
        );

        // Add video duration if available
        if (!empty($item['contentDetails']['duration'])) {
            $duration = new DateInterval($item['contentDetails']['duration']);
            $content .= sprintf(
                '<p>%s: %02d:%02d</p>',
                __('Duration', 'msa-automatic'),
                $duration->i,
                $duration->s
            );
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
        return !empty($item['snippet']['channelTitle']) ? $item['snippet']['channelTitle'] : '';
    }

    /**
     * Get item date
     *
     * @param array $item Raw item data
     * @return string
     */
    protected function get_item_date($item) {
        return !empty($item['snippet']['publishedAt']) ? $item['snippet']['publishedAt'] : '';
    }

    /**
     * Get item URL
     *
     * @param array $item Raw item data
     * @return string
     */
    protected function get_item_url($item) {
        $video_id = $item['contentDetails']['videoId'];
        return sprintf('https://www.youtube.com/watch?v=%s', $video_id);
    }

    /**
     * Get item image URL
     *
     * @param array $item Raw item data
     * @return string
     */
    protected function get_item_image($item) {
        if (!empty($item['snippet']['thumbnails'])) {
            $thumbnails = $item['snippet']['thumbnails'];
            
            // Try to get the highest quality thumbnail
            if (!empty($thumbnails['maxres'])) {
                return $thumbnails['maxres']['url'];
            } elseif (!empty($thumbnails['high'])) {
                return $thumbnails['high']['url'];
            } elseif (!empty($thumbnails['medium'])) {
                return $thumbnails['medium']['url'];
            } elseif (!empty($thumbnails['default'])) {
                return $thumbnails['default']['url'];
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
            'video_id' => $item['contentDetails']['videoId']
        );

        // Add statistics if available
        if (!empty($item['statistics'])) {
            $meta['views'] = $item['statistics']['viewCount'];
            $meta['likes'] = $item['statistics']['likeCount'];
            $meta['comments'] = $item['statistics']['commentCount'];
        }

        // Add video details
        if (!empty($item['contentDetails']['duration'])) {
            $meta['duration'] = $item['contentDetails']['duration'];
        }

        if (!empty($item['contentDetails']['definition'])) {
            $meta['definition'] = $item['contentDetails']['definition'];
        }

        // Add tags
        if (!empty($item['snippet']['tags'])) {
            $meta['tags'] = $item['snippet']['tags'];
        }

        return $meta;
    }

    /**
     * Format duration to human readable string
     *
     * @param string $duration Duration in ISO 8601 format
     * @return string Formatted duration
     */
    protected function format_duration($duration) {
        try {
            $interval = new DateInterval($duration);
            $parts = array();

            if ($interval->h > 0) {
                $parts[] = $interval->h . 'h';
            }
            if ($interval->i > 0) {
                $parts[] = $interval->i . 'm';
            }
            if ($interval->s > 0 || empty($parts)) {
                $parts[] = $interval->s . 's';
            }

            return implode(' ', $parts);
        } catch (Exception $e) {
            return '';
        }
    }
}
