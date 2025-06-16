<?php
if (!defined('ABSPATH')) {
    exit;
}

class MSA_Campaign_Processor {
    /**
     * Instance of this class
     * @var MSA_Campaign_Processor
     */
    private static $instance = null;

    /**
     * Logger instance
     * @var MSA_Logger
     */
    private $logger;

    /**
     * Get instance of this class
     *
     * @return MSA_Campaign_Processor
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
        $this->logger = msa_logger();
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('msa_process_campaign', array($this, 'process_campaign'));
        add_action('msa_process_scheduled_campaigns', array($this, 'process_scheduled_campaigns'));
    }

    /**
     * Process a campaign
     *
     * @param int $campaign_id Campaign ID
     * @return bool|WP_Error True on success or WP_Error on failure
     */
    public function process_campaign($campaign_id) {
        $campaign = get_post($campaign_id);
        if (!$campaign || 'msa_campaign' !== $campaign->post_type) {
            return new WP_Error(
                'invalid_campaign',
                __('Invalid campaign ID', 'msa-automatic')
            );
        }

        $status = get_post_meta($campaign_id, '_msa_status', true);
        if ('active' !== $status) {
            return new WP_Error(
                'inactive_campaign',
                __('Campaign is not active', 'msa-automatic')
            );
        }

        try {
            // Start processing
            $this->logger->info(sprintf(
                __('Starting campaign processing: %s', 'msa-automatic'),
                $campaign->post_title
            ));

            // Fetch content
            $items = $this->fetch_content($campaign_id);
            if (is_wp_error($items)) {
                throw new Exception($items->get_error_message());
            }

            if (empty($items)) {
                $this->logger->info(__('No items found to process', 'msa-automatic'));
                return true;
            }

            // Process each item
            foreach ($items as $item) {
                $result = $this->process_item($campaign_id, $item);
                if (is_wp_error($result)) {
                    $this->logger->error($result->get_error_message());
                    continue;
                }
            }

            // Update last run time
            update_post_meta($campaign_id, '_msa_last_run', current_time('mysql'));
            delete_post_meta($campaign_id, '_msa_last_error');

            $this->logger->info(sprintf(
                __('Campaign processing completed: %s', 'msa-automatic'),
                $campaign->post_title
            ));

            return true;

        } catch (Exception $e) {
            $error_message = $e->getMessage();
            update_post_meta($campaign_id, '_msa_last_error', $error_message);
            
            $this->logger->error(sprintf(
                __('Campaign processing failed: %s - %s', 'msa-automatic'),
                $campaign->post_title,
                $error_message
            ));

            return new WP_Error('processing_failed', $error_message);
        }
    }

    /**
     * Process scheduled campaigns
     */
    public function process_scheduled_campaigns() {
        $campaigns = get_posts(array(
            'post_type' => 'msa_campaign',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_msa_status',
                    'value' => 'active'
                )
            )
        ));

        foreach ($campaigns as $campaign) {
            $schedule_type = get_post_meta($campaign->ID, '_msa_schedule_type', true);
            
            switch ($schedule_type) {
                case 'scheduled':
                    $this->process_scheduled_campaign($campaign);
                    break;

                case 'recurring':
                    $this->process_recurring_campaign($campaign);
                    break;
            }
        }
    }

    /**
     * Process a scheduled campaign
     *
     * @param WP_Post $campaign Campaign post object
     */
    private function process_scheduled_campaign($campaign) {
        $schedule_time = get_post_meta($campaign->ID, '_msa_schedule_time', true);
        if (empty($schedule_time)) {
            return;
        }

        $current_time = current_time('timestamp');
        $scheduled_time = strtotime($schedule_time);

        if ($current_time >= $scheduled_time) {
            $this->process_campaign($campaign->ID);
            
            // Update status to inactive after processing
            update_post_meta($campaign->ID, '_msa_status', 'inactive');
        }
    }

    /**
     * Process a recurring campaign
     *
     * @param WP_Post $campaign Campaign post object
     */
    private function process_recurring_campaign($campaign) {
        $last_run = get_post_meta($campaign->ID, '_msa_last_run', true);
        $interval = get_post_meta($campaign->ID, '_msa_recurring_interval', true);

        if (empty($last_run)) {
            $this->process_campaign($campaign->ID);
            return;
        }

        $current_time = current_time('timestamp');
        $last_run_time = strtotime($last_run);
        $next_run = $this->calculate_next_run($last_run_time, $interval);

        if ($current_time >= $next_run) {
            $this->process_campaign($campaign->ID);
        }
    }

    /**
     * Calculate next run time for recurring campaign
     *
     * @param int $last_run Last run timestamp
     * @param string $interval Interval type
     * @return int Next run timestamp
     */
    private function calculate_next_run($last_run, $interval) {
        switch ($interval) {
            case 'hourly':
                return $last_run + HOUR_IN_SECONDS;

            case 'daily':
                return $last_run + DAY_IN_SECONDS;

            case 'weekly':
                return $last_run + WEEK_IN_SECONDS;

            default:
                return $last_run + DAY_IN_SECONDS;
        }
    }

    /**
     * Fetch content for campaign
     *
     * @param int $campaign_id Campaign ID
     * @return array|WP_Error Array of items or WP_Error on failure
     */
    private function fetch_content($campaign_id) {
        $fetcher = msa_fetcher_loader()->get_campaign_fetcher($campaign_id);
        if (is_wp_error($fetcher)) {
            return $fetcher;
        }

        return $fetcher->fetch($campaign_id);
    }

    /**
     * Process a single item
     *
     * @param int $campaign_id Campaign ID
     * @param array $item Item data
     * @return bool|WP_Error True on success or WP_Error on failure
     */
    private function process_item($campaign_id, $item) {
        // Check if item already exists
        if ($this->item_exists($campaign_id, $item)) {
            return new WP_Error(
                'duplicate_item',
                __('Item already exists', 'msa-automatic')
            );
        }

        // Translate content if enabled
        $enable_translation = get_post_meta($campaign_id, '_msa_enable_translation', true);
        if ($enable_translation) {
            $item = $this->translate_item($campaign_id, $item);
            if (is_wp_error($item)) {
                return $item;
            }
        }

        // Format content using template
        $content = $this->format_content($campaign_id, $item);
        if (is_wp_error($content)) {
            return $content;
        }

        // Create post
        $post_id = $this->create_post($campaign_id, $item, $content);
        if (is_wp_error($post_id)) {
            return $post_id;
        }

        // Store item meta
        $this->store_item_meta($post_id, $campaign_id, $item);

        return true;
    }

    /**
     * Check if item already exists
     *
     * @param int $campaign_id Campaign ID
     * @param array $item Item data
     * @return bool Whether item exists
     */
    private function item_exists($campaign_id, $item) {
        $args = array(
            'post_type' => 'post',
            'meta_query' => array(
                array(
                    'key' => '_msa_campaign_id',
                    'value' => $campaign_id
                ),
                array(
                    'key' => '_msa_item_url',
                    'value' => $item['url']
                )
            )
        );

        $query = new WP_Query($args);
        return $query->have_posts();
    }

    /**
     * Translate item content
     *
     * @param int $campaign_id Campaign ID
     * @param array $item Item data
     * @return array|WP_Error Translated item or WP_Error on failure
     */
    private function translate_item($campaign_id, $item) {
        $translator = msa_translator_loader()->get_campaign_translator($campaign_id);
        if (is_wp_error($translator)) {
            return $translator;
        }

        $target_lang = get_post_meta($campaign_id, '_msa_target_language', true);
        if (empty($target_lang)) {
            return new WP_Error(
                'no_target_language',
                __('No target language specified', 'msa-automatic')
            );
        }

        try {
            // Translate title
            $translated_title = $translator->translate($item['title'], $target_lang);
            if (!is_wp_error($translated_title)) {
                $item['title'] = $translated_title;
            }

            // Translate content
            $translated_content = $translator->translate($item['content'], $target_lang);
            if (!is_wp_error($translated_content)) {
                $item['content'] = $translated_content;
            }

            return $item;

        } catch (Exception $e) {
            return new WP_Error(
                'translation_failed',
                $e->getMessage()
            );
        }
    }

    /**
     * Format content using template
     *
     * @param int $campaign_id Campaign ID
     * @param array $item Item data
     * @return string|WP_Error Formatted content or WP_Error on failure
     */
    private function format_content($campaign_id, $item) {
        $template = get_post_meta($campaign_id, '_msa_template', true);
        if (empty($template)) {
            // Use default template if none specified
            $template = '{content}';
        }

        // Replace template variables
        $replacements = array(
            '{title}' => $item['title'],
            '{content}' => $item['content'],
            '{author}' => $item['author'],
            '{date}' => $item['date'],
            '{url}' => $item['url'],
            '{image}' => $item['image']
        );

        // Allow additional custom replacements
        $replacements = apply_filters('msa_template_replacements', $replacements, $item, $campaign_id);

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    /**
     * Create WordPress post
     *
     * @param int $campaign_id Campaign ID
     * @param array $item Item data
     * @param string $content Formatted content
     * @return int|WP_Error Post ID or WP_Error on failure
     */
    private function create_post($campaign_id, $item, $content) {
        $post_data = array(
            'post_title' => $item['title'],
            'post_content' => $content,
            'post_status' => 'publish',
            'post_author' => get_post_field('post_author', $campaign_id),
            'post_type' => 'post',
            'post_date' => get_date_from_gmt($item['date'])
        );

        // Allow modification of post data
        $post_data = apply_filters('msa_post_data', $post_data, $item, $campaign_id);

        $post_id = wp_insert_post($post_data, true);
        if (is_wp_error($post_id)) {
            return $post_id;
        }

        // Set featured image if available
        if (!empty($item['image'])) {
            $this->set_featured_image($post_id, $item['image']);
        }

        return $post_id;
    }

    /**
     * Store item metadata
     *
     * @param int $post_id Post ID
     * @param int $campaign_id Campaign ID
     * @param array $item Item data
     */
    private function store_item_meta($post_id, $campaign_id, $item) {
        update_post_meta($post_id, '_msa_campaign_id', $campaign_id);
        update_post_meta($post_id, '_msa_item_url', $item['url']);
        update_post_meta($post_id, '_msa_item_author', $item['author']);
        update_post_meta($post_id, '_msa_item_date', $item['date']);

        if (!empty($item['meta'])) {
            update_post_meta($post_id, '_msa_item_meta', $item['meta']);
        }
    }

    /**
     * Set featured image for post
     *
     * @param int $post_id Post ID
     * @param string $image_url Image URL
     * @return int|WP_Error Attachment ID or WP_Error on failure
     */
    private function set_featured_image($post_id, $image_url) {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        // Download image
        $tmp = download_url($image_url);
        if (is_wp_error($tmp)) {
            return $tmp;
        }

        $file_array = array(
            'name' => basename($image_url),
            'tmp_name' => $tmp
        );

        // Upload image
        $attachment_id = media_handle_sideload($file_array, $post_id);
        if (is_wp_error($attachment_id)) {
            @unlink($tmp);
            return $attachment_id;
        }

        // Set as featured image
        set_post_thumbnail($post_id, $attachment_id);

        return $attachment_id;
    }
}

/**
 * Get instance of campaign processor
 *
 * @return MSA_Campaign_Processor
 */
function msa_campaign_processor() {
    return MSA_Campaign_Processor::get_instance();
}
