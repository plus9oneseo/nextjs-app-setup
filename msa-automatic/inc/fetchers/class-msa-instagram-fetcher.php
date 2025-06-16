<?php
if (!defined('ABSPATH')) {
    exit;
}

class MSA_Instagram_Fetcher extends MSA_Base_Fetcher {
    private $graph_version = 'v18.0';
    private $access_token;

    protected function validate_settings() {
        if (empty($this->settings['api_key'])) {
            $this->log_error(__('Instagram Business Account ID is required', 'msa-automatic'));
            return false;
        }

        if (empty($this->settings['api_secret'])) {
            $this->log_error(__('Instagram Access Token is required', 'msa-automatic'));
            return false;
        }

        $this->access_token = $this->settings['api_secret'];
        return true;
    }

    protected function do_fetch() {
        try {
            $content_type = $this->settings['content_type'] ?: 'user';
            $user_id = $this->settings['api_key']; // Instagram Business Account ID
            $max_items = intval($this->settings['max_items']);

            switch ($content_type) {
                case 'user':
                    return $this->fetch_user_media($user_id, $max_items);
                case 'hashtag':
                    $hashtag = $this->settings['search_query'];
                    return $this->fetch_hashtag_media($user_id, $hashtag, $max_items);
                case 'tagged':
                    return $this->fetch_tagged_media($user_id, $max_items);
                default:
                    throw new Exception(__('Invalid content type specified', 'msa-automatic'));
            }
        } catch (Exception $e) {
            $this->log_error($e->getMessage());
            return $this->errors;
        }
    }

    private function fetch_user_media($user_id, $limit = 10) {
        $url = sprintf(
            'https://graph.facebook.com/%s/%s/media?access_token=%s&limit=%d&fields=id,caption,media_type,media_url,thumbnail_url,permalink,timestamp,like_count,comments_count',
            $this->graph_version,
            $user_id,
            $this->access_token,
            $limit
        );

        $response = $this->make_request($url);
        $data = json_decode($response, true);

        if (empty($data['data'])) {
            throw new Exception(__('No Instagram posts found', 'msa-automatic'));
        }

        return array_map(function($post) {
            return $this->format_post($post);
        }, $data['data']);
    }

    private function fetch_hashtag_media($user_id, $hashtag, $limit = 10) {
        // First, get the hashtag ID
        $hashtag_url = sprintf(
            'https://graph.facebook.com/%s/ig_hashtag_search?user_id=%s&q=%s&access_token=%s',
            $this->graph_version,
            $user_id,
            urlencode($hashtag),
            $this->access_token
        );

        $hashtag_response = $this->make_request($hashtag_url);
        $hashtag_data = json_decode($hashtag_response, true);

        if (empty($hashtag_data['data'][0]['id'])) {
            throw new Exception(__('Hashtag not found', 'msa-automatic'));
        }

        $hashtag_id = $hashtag_data['data'][0]['id'];

        // Then, get the media
        $url = sprintf(
            'https://graph.facebook.com/%s/%s/recent_media?user_id=%s&access_token=%s&limit=%d&fields=id,caption,media_type,media_url,thumbnail_url,permalink,timestamp,like_count,comments_count',
            $this->graph_version,
            $hashtag_id,
            $user_id,
            $this->access_token,
            $limit
        );

        $response = $this->make_request($url);
        $data = json_decode($response, true);

        if (empty($data['data'])) {
            throw new Exception(__('No posts found for this hashtag', 'msa-automatic'));
        }

        return array_map(function($post) {
            return $this->format_post($post);
        }, $data['data']);
    }

    private function fetch_tagged_media($user_id, $limit = 10) {
        $url = sprintf(
            'https://graph.facebook.com/%s/%s/tags?access_token=%s&limit=%d&fields=id,caption,media_type,media_url,thumbnail_url,permalink,timestamp,like_count,comments_count',
            $this->graph_version,
            $user_id,
            $this->access_token,
            $limit
        );

        $response = $this->make_request($url);
        $data = json_decode($response, true);

        if (empty($data['data'])) {
            throw new Exception(__('No tagged posts found', 'msa-automatic'));
        }

        return array_map(function($post) {
            return $this->format_post($post);
        }, $data['data']);
    }

    private function format_post($post) {
        // Get media URL based on type
        $media_url = $post['media_url'];
        if ($post['media_type'] === 'VIDEO') {
            $media_url = $post['thumbnail_url'] ?? $post['media_url'];
        }

        // Extract hashtags from caption
        $hashtags = array();
        if (!empty($post['caption'])) {
            preg_match_all('/#([^\s#]+)/', $post['caption'], $matches);
            $hashtags = $matches[1] ?? array();
        }

        return $this->sanitize_content(array(
            'title' => wp_trim_words($post['caption'] ?? '', 10, '...'),
            'content' => $post['caption'] ?? '',
            'author' => 'Instagram User', // Instagram API doesn't provide author name directly
            'date' => $post['timestamp'],
            'url' => $post['permalink'],
            'image' => $media_url,
            'meta' => array(
                'instagram_id' => $post['id'],
                'media_type' => $post['media_type'],
                'engagement' => array(
                    'likes' => $post['like_count'] ?? 0,
                    'comments' => $post['comments_count'] ?? 0
                ),
                'hashtags' => $hashtags
            )
        ));
    }

    protected function handle_rate_limit() {
        $headers = wp_remote_retrieve_headers($this->last_response);
        
        // Instagram Graph API has a per-hour rate limit
        if (isset($headers['x-app-usage'])) {
            $usage = json_decode($headers['x-app-usage'], true);
            
            // If we're close to the rate limit, wait before making more requests
            if (isset($usage['call_count']) && $usage['call_count'] > 90) {
                sleep(60); // Wait for 1 minute
            }
        }
    }

    private function refresh_long_lived_token() {
        // Instagram access tokens need to be refreshed periodically
        $refresh_url = sprintf(
            'https://graph.facebook.com/%s/refresh_access_token?grant_type=ig_refresh_token&access_token=%s',
            $this->graph_version,
            $this->access_token
        );

        try {
            $response = $this->make_request($refresh_url);
            $data = json_decode($response, true);

            if (!empty($data['access_token'])) {
                // Store the new token
                update_post_meta($this->campaign_id, '_msa_api_secret', $data['access_token']);
                $this->access_token = $data['access_token'];
            }
        } catch (Exception $e) {
            $this->log_error(__('Failed to refresh Instagram access token', 'msa-automatic'));
        }
    }
}
