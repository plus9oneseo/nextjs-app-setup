<?php
if (!defined('ABSPATH')) {
    exit;
}

class MSA_Campaign_Edit {
    private static $instance = null;
    private $fetcher_loader;
    private $translator_loader;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->fetcher_loader = msa_fetcher_loader();
        $this->translator_loader = msa_translator_loader();
        $this->init_hooks();
    }

    private function init_hooks() {
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post_msa_campaign', array($this, 'save_campaign'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    public function enqueue_scripts($hook) {
        global $post_type;

        if ('post.php' !== $hook && 'post-new.php' !== $hook) {
            return;
        }

        if ('msa_campaign' !== $post_type) {
            return;
        }

        wp_enqueue_style(
            'msa-automatic-admin',
            MSA_AUTOMATIC_URL . 'css/msa-automatic-admin.css',
            array(),
            MSA_AUTOMATIC_VERSION
        );

        wp_enqueue_script(
            'msa-automatic-admin-edit',
            MSA_AUTOMATIC_URL . 'js/msa-automatic-admin-edit.js',
            array('jquery'),
            MSA_AUTOMATIC_VERSION,
            true
        );

        wp_localize_script('msa-automatic-admin-edit', 'msaAutomatic', array(
            'nonce' => wp_create_nonce('msa_automatic_nonce'),
            'i18n' => array(
                'testing' => __('Testing...', 'msa-automatic'),
                'testConnection' => __('Test Connection', 'msa-automatic'),
                'running' => __('Running...', 'msa-automatic'),
                'runNow' => __('Run Now', 'msa-automatic'),
                'keyword' => __('Keyword', 'msa-automatic'),
                'length' => __('Length', 'msa-automatic'),
                'date' => __('Date', 'msa-automatic'),
                'remove' => __('Remove', 'msa-automatic'),
                'requiredFields' => __('Please fill in all required fields.', 'msa-automatic')
            )
        ));
    }

    public function add_meta_boxes() {
        add_meta_box(
            'msa_campaign_settings',
            __('Campaign Settings', 'msa-automatic'),
            array($this, 'render_settings_meta_box'),
            'msa_campaign',
            'normal',
            'high'
        );

        add_meta_box(
            'msa_campaign_filters',
            __('Content Filters', 'msa-automatic'),
            array($this, 'render_filters_meta_box'),
            'msa_campaign',
            'normal',
            'default'
        );

        add_meta_box(
            'msa_campaign_template',
            __('Post Template', 'msa-automatic'),
            array($this, 'render_template_meta_box'),
            'msa_campaign',
            'normal',
            'default'
        );

        add_meta_box(
            'msa_campaign_schedule',
            __('Schedule Settings', 'msa-automatic'),
            array($this, 'render_schedule_meta_box'),
            'msa_campaign',
            'side',
            'default'
        );

        add_meta_box(
            'msa_campaign_status',
            __('Campaign Status', 'msa-automatic'),
            array($this, 'render_status_meta_box'),
            'msa_campaign',
            'side',
            'high'
        );
    }

    public function render_settings_meta_box($post) {
        wp_nonce_field('msa_campaign_settings', 'msa_campaign_nonce');
        $settings = $this->get_campaign_settings($post->ID);
        $fetchers = $this->fetcher_loader->get_available_fetchers();
        ?>
        <div id="msa-campaign-editor">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="msa_fetcher_type"><?php _e('Content Source', 'msa-automatic'); ?></label>
                    </th>
                    <td>
                        <select name="msa_fetcher_type" id="msa_fetcher_type" class="regular-text">
                            <option value=""><?php _e('Select Source', 'msa-automatic'); ?></option>
                            <?php foreach ($fetchers as $type => $info): ?>
                                <option value="<?php echo esc_attr($type); ?>" <?php selected($settings['fetcher_type'], $type); ?>>
                                    <?php echo esc_html($info['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>

            <?php foreach ($fetchers as $type => $info): ?>
                <div class="msa-fetcher-settings" data-type="<?php echo esc_attr($type); ?>">
                    <h3><?php echo esc_html($info['name']); ?> <?php _e('Settings', 'msa-automatic'); ?></h3>
                    <?php $this->render_fetcher_settings($type, $info, $settings); ?>
                </div>
            <?php endforeach; ?>

            <hr>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="msa_enable_translation">
                            <?php _e('Enable Translation', 'msa-automatic'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="checkbox" 
                               name="msa_enable_translation" 
                               id="msa_enable_translation"
                               <?php checked($settings['enable_translation']); ?>>
                    </td>
                </tr>
            </table>

            <div class="msa-translation-settings">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="msa_translator_type">
                                <?php _e('Translation Service', 'msa-automatic'); ?>
                            </label>
                        </th>
                        <td>
                            <select name="msa_translator_type" id="msa_translator_type">
                                <?php
                                $translators = $this->translator_loader->get_available_translators();
                                foreach ($translators as $type => $info):
                                ?>
                                    <option value="<?php echo esc_attr($type); ?>" <?php selected($settings['translator_type'], $type); ?>>
                                        <?php echo esc_html($info['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="msa_target_language">
                                <?php _e('Target Language', 'msa-automatic'); ?>
                            </label>
                        </th>
                        <td>
                            <select name="msa_target_language" id="msa_target_language">
                                <?php
                                $languages = $this->get_supported_languages();
                                foreach ($languages as $code => $name):
                                ?>
                                    <option value="<?php echo esc_attr($code); ?>" <?php selected($settings['target_language'], $code); ?>>
                                        <?php echo esc_html($name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        <?php
    }

    public function render_filters_meta_box($post) {
        $filters = get_post_meta($post->ID, '_msa_filters', true) ?: array();
        ?>
        <div id="msa_filters">
            <?php
            if (!empty($filters)):
                foreach ($filters as $filter):
                    ?>
                    <div class="msa-filter">
                        <select name="filter_type[]" class="filter-type">
                            <option value="keyword" <?php selected($filter['type'], 'keyword'); ?>>
                                <?php _e('Keyword', 'msa-automatic'); ?>
                            </option>
                            <option value="length" <?php selected($filter['type'], 'length'); ?>>
                                <?php _e('Length', 'msa-automatic'); ?>
                            </option>
                            <option value="date" <?php selected($filter['type'], 'date'); ?>>
                                <?php _e('Date', 'msa-automatic'); ?>
                            </option>
                        </select>
                        <input type="text" 
                               name="filter_value[]" 
                               class="filter-value" 
                               value="<?php echo esc_attr($filter['value']); ?>">
                        <button type="button" class="button remove-filter">
                            <?php _e('Remove', 'msa-automatic'); ?>
                        </button>
                    </div>
                    <?php
                endforeach;
            endif;
            ?>
        </div>
        <p>
            <button type="button" class="button" id="add_filter">
                <?php _e('Add Filter', 'msa-automatic'); ?>
            </button>
        </p>
        <?php
    }

    public function render_template_meta_box($post) {
        $template = get_post_meta($post->ID, '_msa_template', true);
        ?>
        <p>
            <textarea name="msa_template" 
                      id="msa_template" 
                      class="large-text" 
                      rows="10"><?php echo esc_textarea($template); ?></textarea>
        </p>
        <p class="description">
            <?php _e('Available tags: {title}, {content}, {author}, {date}, {url}, {image}', 'msa-automatic'); ?>
        </p>
        <div id="msa_template_preview" class="msa-template-preview"></div>
        <?php
    }

    public function render_schedule_meta_box($post) {
        $schedule = $this->get_schedule_settings($post->ID);
        ?>
        <p>
            <label for="msa_schedule_type"><?php _e('Schedule Type', 'msa-automatic'); ?></label>
            <select name="msa_schedule_type" id="msa_schedule_type" class="widefat">
                <option value="immediate" <?php selected($schedule['type'], 'immediate'); ?>>
                    <?php _e('Immediate', 'msa-automatic'); ?>
                </option>
                <option value="scheduled" <?php selected($schedule['type'], 'scheduled'); ?>>
                    <?php _e('Scheduled', 'msa-automatic'); ?>
                </option>
                <option value="recurring" <?php selected($schedule['type'], 'recurring'); ?>>
                    <?php _e('Recurring', 'msa-automatic'); ?>
                </option>
            </select>
        </p>

        <div class="msa-schedule-settings" data-type="scheduled">
            <p>
                <label for="msa_schedule_time"><?php _e('Schedule Time', 'msa-automatic'); ?></label>
                <input type="datetime-local" 
                       name="msa_schedule_time" 
                       id="msa_schedule_time" 
                       class="widefat"
                       value="<?php echo esc_attr($schedule['time']); ?>">
            </p>
        </div>

        <div class="msa-schedule-settings" data-type="recurring">
            <p>
                <label for="msa_recurring_interval"><?php _e('Interval', 'msa-automatic'); ?></label>
                <select name="msa_recurring_interval" id="msa_recurring_interval" class="widefat">
                    <option value="hourly" <?php selected($schedule['interval'], 'hourly'); ?>>
                        <?php _e('Hourly', 'msa-automatic'); ?>
                    </option>
                    <option value="daily" <?php selected($schedule['interval'], 'daily'); ?>>
                        <?php _e('Daily', 'msa-automatic'); ?>
                    </option>
                    <option value="weekly" <?php selected($schedule['interval'], 'weekly'); ?>>
                        <?php _e('Weekly', 'msa-automatic'); ?>
                    </option>
                </select>
            </p>
        </div>
        <?php
    }

    public function render_status_meta_box($post) {
        $status = get_post_meta($post->ID, '_msa_status', true) ?: 'inactive';
        $last_run = get_post_meta($post->ID, '_msa_last_run', true);
        $last_error = get_post_meta($post->ID, '_msa_last_error', true);
        ?>
        <p>
            <label for="msa_status"><?php _e('Status', 'msa-automatic'); ?></label>
            <select name="msa_status" id="msa_status" class="widefat">
                <option value="active" <?php selected($status, 'active'); ?>>
                    <?php _e('Active', 'msa-automatic'); ?>
                </option>
                <option value="paused" <?php selected($status, 'paused'); ?>>
                    <?php _e('Paused', 'msa-automatic'); ?>
                </option>
                <option value="inactive" <?php selected($status, 'inactive'); ?>>
                    <?php _e('Inactive', 'msa-automatic'); ?>
                </option>
            </select>
        </p>

        <?php if ($last_run): ?>
            <p>
                <strong><?php _e('Last Run:', 'msa-automatic'); ?></strong><br>
                <?php echo MSA_Utils::format_date($last_run); ?>
            </p>
        <?php endif; ?>

        <?php if ($last_error): ?>
            <p class="msa-error-message">
                <strong><?php _e('Last Error:', 'msa-automatic'); ?></strong><br>
                <?php echo esc_html($last_error); ?>
            </p>
        <?php endif; ?>

        <p>
            <button type="button" 
                    class="button button-primary" 
                    id="run_now" 
                    data-id="<?php echo esc_attr($post->ID); ?>">
                <?php _e('Run Now', 'msa-automatic'); ?>
            </button>
        </p>
        <?php
    }

    private function render_fetcher_settings($type, $info, $settings) {
        $fetcher_settings = isset($settings['fetcher_settings'][$type]) ? $settings['fetcher_settings'][$type] : array();
        foreach ($info['settings'] as $key => $label):
            ?>
            <p>
                <label for="msa_fetcher_<?php echo esc_attr($type . '_' . $key); ?>">
                    <?php echo esc_html($label); ?>
                </label>
                <input type="text" 
                       name="msa_fetcher_settings[<?php echo esc_attr($type); ?>][<?php echo esc_attr($key); ?>]" 
                       id="msa_fetcher_<?php echo esc_attr($type . '_' . $key); ?>" 
                       class="regular-text"
                       value="<?php echo esc_attr(isset($fetcher_settings[$key]) ? $fetcher_settings[$key] : ''); ?>">
            </p>
            <?php
        endforeach;

        if (!empty($info['settings'])):
            ?>
            <p>
                <button type="button" 
                        class="button" 
                        id="test_connection" 
                        data-type="<?php echo esc_attr($type); ?>">
                    <?php _e('Test Connection', 'msa-automatic'); ?>
                </button>
            </p>
            <?php
        endif;
    }

    public function save_campaign($post_id) {
        if (!isset($_POST['msa_campaign_nonce']) || 
            !wp_verify_nonce($_POST['msa_campaign_nonce'], 'msa_campaign_settings')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save fetcher settings
        if (isset($_POST['msa_fetcher_type'])) {
            update_post_meta($post_id, '_msa_fetcher_type', sanitize_text_field($_POST['msa_fetcher_type']));
        }

        if (isset($_POST['msa_fetcher_settings'])) {
            update_post_meta($post_id, '_msa_fetcher_settings', $this->sanitize_fetcher_settings($_POST['msa_fetcher_settings']));
        }

        // Save translation settings
        update_post_meta($post_id, '_msa_enable_translation', isset($_POST['msa_enable_translation']));
        
        if (isset($_POST['msa_translator_type'])) {
            update_post_meta($post_id, '_msa_translator_type', sanitize_text_field($_POST['msa_translator_type']));
        }

        if (isset($_POST['msa_target_language'])) {
            update_post_meta($post_id, '_msa_target_language', sanitize_text_field($_POST['msa_target_language']));
        }

        // Save filters
        if (isset($_POST['filter_type']) && isset($_POST['filter_value'])) {
            $filters = array();
            foreach ($_POST['filter_type'] as $i => $type) {
                if (!empty($_POST['filter_value'][$i])) {
                    $filters[] = array(
                        'type' => sanitize_text_field($type),
                        'value' => sanitize_text_field($_POST['filter_value'][$i])
                    );
                }
            }
            update_post_meta($post_id, '_msa_filters', $filters);
        }

        // Save template
        if (isset($_POST['msa_template'])) {
            update_post_meta($post_id, '_msa_template', wp_kses_post($_POST['msa_template']));
        }

        // Save schedule settings
        if (isset($_POST['msa_schedule_type'])) {
            update_post_meta($post_id, '_msa_schedule_type', sanitize_text_field($_POST['msa_schedule_type']));
        }

        if (isset($_POST['msa_schedule_time'])) {
            update_post_meta($post_id, '_msa_schedule_time', sanitize_text_field($_POST['msa_schedule_time']));
        }

        if (isset($_POST['msa_recurring_interval'])) {
            update_post_meta($post_id, '_msa_recurring_interval', sanitize_text_field($_POST['msa_recurring_interval']));
        }

        // Save status
        if (isset($_POST['msa_status'])) {
            update_post_meta($post_id, '_msa_status', sanitize_text_field($_POST['msa_status']));
        }
    }

    private function get_campaign_settings($post_id) {
        return array(
            'fetcher_type' => get_post_meta($post_id, '_msa_fetcher_type', true),
            'fetcher_settings' => get_post_meta($post_id, '_msa_fetcher_settings', true) ?: array(),
            'enable_translation' => get_post_meta($post_id, '_msa_enable_translation', true),
            'translator_type' => get_post_meta($post_id, '_msa_translator_type', true),
            'target_language' => get_post_meta($post_id, '_msa_target_language', true)
        );
    }

    private function get_schedule_settings($post_id) {
        return array(
            'type' => get_post_meta($post_id, '_msa_schedule_type', true) ?: 'immediate',
            'time' => get_post_meta($post_id, '_msa_schedule_time', true),
            'interval' => get_post_meta($post_id, '_msa_recurring_interval', true) ?: 'daily'
        );
    }

    private function sanitize_fetcher_settings($settings) {
        $sanitized = array();
        foreach ($settings as $type => $values) {
            $sanitized[$type] = array_map('sanitize_text_field', $values);
        }
        return $sanitized;
    }

    private function get_supported_languages() {
        return array(
            'en' => __('English', 'msa-automatic'),
            'es' => __('Spanish', 'msa-automatic'),
            'fr' => __('French', 'msa-automatic'),
            'de' => __('German', 'msa-automatic'),
            'it' => __('Italian', 'msa-automatic'),
            'pt' => __('Portuguese', 'msa-automatic'),
            'ru' => __('Russian', 'msa-automatic'),
            'ja' => __('Japanese', 'msa-automatic'),
            'ko' => __('Korean', 'msa-automatic'),
            'zh' => __('Chinese', 'msa-automatic')
        );
    }
}

// Initialize the campaign edit page
function msa_campaign_edit() {
    return MSA_Campaign_Edit::get_instance();
}

msa_campaign_edit();
