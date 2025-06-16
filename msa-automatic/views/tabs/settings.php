<?php
if (!defined('ABSPATH')) {
    exit;
}

$settings_manager = msa_settings_manager();
$active_section = isset($_GET['section']) ? sanitize_text_field($_GET['section']) : 'general';
?>

<div class="msa-settings-wrapper">
    <div class="msa-settings-navigation">
        <ul class="subsubsub">
            <li>
                <a href="<?php echo add_query_arg('section', 'general'); ?>" 
                   class="<?php echo $active_section === 'general' ? 'current' : ''; ?>">
                    <?php _e('General', 'msa-automatic'); ?>
                </a> |
            </li>
            <li>
                <a href="<?php echo add_query_arg('section', 'api'); ?>" 
                   class="<?php echo $active_section === 'api' ? 'current' : ''; ?>">
                    <?php _e('API Settings', 'msa-automatic'); ?>
                </a> |
            </li>
            <li>
                <a href="<?php echo add_query_arg('section', 'translation'); ?>" 
                   class="<?php echo $active_section === 'translation' ? 'current' : ''; ?>">
                    <?php _e('Translation', 'msa-automatic'); ?>
                </a> |
            </li>
            <li>
                <a href="<?php echo add_query_arg('section', 'advanced'); ?>" 
                   class="<?php echo $active_section === 'advanced' ? 'current' : ''; ?>">
                    <?php _e('Advanced', 'msa-automatic'); ?>
                </a>
            </li>
        </ul>
    </div>

    <div class="msa-settings-content">
        <form method="post" action="options.php" id="msa-settings-form">
            <?php settings_fields('msa_automatic_settings'); ?>

            <?php if ($active_section === 'general'): ?>
                <h2><?php _e('General Settings', 'msa-automatic'); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="default_post_status"><?php _e('Default Post Status', 'msa-automatic'); ?></label>
                        </th>
                        <td>
                            <select name="msa_automatic_settings[default_post_status]" id="default_post_status">
                                <option value="draft" <?php selected($settings_manager->get_option('default_post_status'), 'draft'); ?>>
                                    <?php _e('Draft', 'msa-automatic'); ?>
                                </option>
                                <option value="publish" <?php selected($settings_manager->get_option('default_post_status'), 'publish'); ?>>
                                    <?php _e('Published', 'msa-automatic'); ?>
                                </option>
                                <option value="pending" <?php selected($settings_manager->get_option('default_post_status'), 'pending'); ?>>
                                    <?php _e('Pending Review', 'msa-automatic'); ?>
                                </option>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="default_post_author"><?php _e('Default Post Author', 'msa-automatic'); ?></label>
                        </th>
                        <td>
                            <?php
                            wp_dropdown_users(array(
                                'name' => 'msa_automatic_settings[default_post_author]',
                                'id' => 'default_post_author',
                                'selected' => $settings_manager->get_option('default_post_author'),
                                'show_option_none' => __('Select an author', 'msa-automatic')
                            ));
                            ?>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="post_format"><?php _e('Default Post Format', 'msa-automatic'); ?></label>
                        </th>
                        <td>
                            <select name="msa_automatic_settings[post_format]" id="post_format">
                                <option value="standard" <?php selected($settings_manager->get_option('post_format'), 'standard'); ?>>
                                    <?php _e('Standard', 'msa-automatic'); ?>
                                </option>
                                <option value="aside" <?php selected($settings_manager->get_option('post_format'), 'aside'); ?>>
                                    <?php _e('Aside', 'msa-automatic'); ?>
                                </option>
                                <option value="gallery" <?php selected($settings_manager->get_option('post_format'), 'gallery'); ?>>
                                    <?php _e('Gallery', 'msa-automatic'); ?>
                                </option>
                                <option value="link" <?php selected($settings_manager->get_option('post_format'), 'link'); ?>>
                                    <?php _e('Link', 'msa-automatic'); ?>
                                </option>
                                <option value="image" <?php selected($settings_manager->get_option('post_format'), 'image'); ?>>
                                    <?php _e('Image', 'msa-automatic'); ?>
                                </option>
                                <option value="quote" <?php selected($settings_manager->get_option('post_format'), 'quote'); ?>>
                                    <?php _e('Quote', 'msa-automatic'); ?>
                                </option>
                                <option value="status" <?php selected($settings_manager->get_option('post_format'), 'status'); ?>>
                                    <?php _e('Status', 'msa-automatic'); ?>
                                </option>
                                <option value="video" <?php selected($settings_manager->get_option('post_format'), 'video'); ?>>
                                    <?php _e('Video', 'msa-automatic'); ?>
                                </option>
                                <option value="audio" <?php selected($settings_manager->get_option('post_format'), 'audio'); ?>>
                                    <?php _e('Audio', 'msa-automatic'); ?>
                                </option>
                                <option value="chat" <?php selected($settings_manager->get_option('post_format'), 'chat'); ?>>
                                    <?php _e('Chat', 'msa-automatic'); ?>
                                </option>
                            </select>
                        </td>
                    </tr>
                </table>

            <?php elseif ($active_section === 'api'): ?>
                <h2><?php _e('API Settings', 'msa-automatic'); ?></h2>

                <div class="msa-api-settings-tabs">
                    <div class="nav-tab-wrapper">
                        <?php
                        $apis = array(
                            'facebook' => __('Facebook', 'msa-automatic'),
                            'twitter' => __('Twitter', 'msa-automatic'),
                            'youtube' => __('YouTube', 'msa-automatic'),
                            'instagram' => __('Instagram', 'msa-automatic'),
                            'tiktok' => __('TikTok', 'msa-automatic')
                        );

                        foreach ($apis as $api => $label):
                            ?>
                            <a href="#" class="nav-tab" data-api="<?php echo esc_attr($api); ?>">
                                <?php echo esc_html($label); ?>
                            </a>
                            <?php
                        endforeach;
                        ?>
                    </div>

                    <?php foreach ($apis as $api => $label): ?>
                        <div class="msa-api-settings" data-api="<?php echo esc_attr($api); ?>">
                            <?php include MSA_AUTOMATIC_PATH . 'views/settings/' . $api . '.php'; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

            <?php elseif ($active_section === 'translation'): ?>
                <h2><?php _e('Translation Settings', 'msa-automatic'); ?></h2>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="default_translator"><?php _e('Default Translator', 'msa-automatic'); ?></label>
                        </th>
                        <td>
                            <select name="msa_automatic_settings[default_translator]" id="default_translator">
                                <option value="yandex" <?php selected($settings_manager->get_option('default_translator'), 'yandex'); ?>>
                                    <?php _e('Yandex Translate', 'msa-automatic'); ?>
                                </option>
                                <option value="google" <?php selected($settings_manager->get_option('default_translator'), 'google'); ?>>
                                    <?php _e('Google Translate', 'msa-automatic'); ?>
                                </option>
                                <option value="deepl" <?php selected($settings_manager->get_option('default_translator'), 'deepl'); ?>>
                                    <?php _e('DeepL', 'msa-automatic'); ?>
                                </option>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="yandex_api_key"><?php _e('Yandex API Key', 'msa-automatic'); ?></label>
                        </th>
                        <td>
                            <input type="password" 
                                   name="msa_automatic_settings[yandex_api_key]" 
                                   id="yandex_api_key" 
                                   value="<?php echo esc_attr($settings_manager->get_option('yandex_api_key')); ?>" 
                                   class="regular-text">
                        </td>
                    </tr>
                </table>

            <?php elseif ($active_section === 'advanced'): ?>
                <h2><?php _e('Advanced Settings', 'msa-automatic'); ?></h2>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="log_retention"><?php _e('Log Retention (days)', 'msa-automatic'); ?></label>
                        </th>
                        <td>
                            <input type="number" 
                                   name="msa_automatic_settings[log_retention]" 
                                   id="log_retention" 
                                   value="<?php echo esc_attr($settings_manager->get_option('log_retention', 30)); ?>" 
                                   min="1" 
                                   max="365">
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="batch_size"><?php _e('Batch Processing Size', 'msa-automatic'); ?></label>
                        </th>
                        <td>
                            <input type="number" 
                                   name="msa_automatic_settings[batch_size]" 
                                   id="batch_size" 
                                   value="<?php echo esc_attr($settings_manager->get_option('batch_size', 10)); ?>" 
                                   min="1" 
                                   max="100">
                        </td>
                    </tr>
                </table>
            <?php endif; ?>

            <?php submit_button(); ?>
        </form>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Handle API settings tabs
    $('.msa-api-settings-tabs .nav-tab').on('click', function(e) {
        e.preventDefault();
        var api = $(this).data('api');
        
        $('.msa-api-settings-tabs .nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        $('.msa-api-settings').hide();
        $('.msa-api-settings[data-api="' + api + '"]').show();
    });

    // Show first API tab by default
    $('.msa-api-settings-tabs .nav-tab:first').click();

    // Handle test connection buttons
    $('.msa-test-api-connection').on('click', function(e) {
        e.preventDefault();
        var $button = $(this);
        var api = $button.data('api');
        
        $button.prop('disabled', true).text(msaAutomatic.i18n.testing);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'msa_test_api_connection',
                api: api,
                settings: $('#msa-settings-form').serialize(),
                nonce: msaAutomatic.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                } else {
                    alert(response.data.message);
                }
                $button.prop('disabled', false).text(msaAutomatic.i18n.testConnection);
            }
        });
    });
});
</script>
