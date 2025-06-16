<?php
if (!defined('ABSPATH')) {
    exit;
}

$action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
$tool = isset($_GET['tool']) ? sanitize_text_field($_GET['tool']) : '';
?>

<div class="msa-tools-wrapper">
    <div class="msa-tools-grid">
        <!-- System Information -->
        <div class="msa-tool-box">
            <h3><?php _e('System Information', 'msa-automatic'); ?></h3>
            <div class="msa-tool-content">
                <table class="widefat">
                    <tr>
                        <td><?php _e('PHP Version', 'msa-automatic'); ?></td>
                        <td><?php echo PHP_VERSION; ?></td>
                    </tr>
                    <tr>
                        <td><?php _e('WordPress Version', 'msa-automatic'); ?></td>
                        <td><?php echo get_bloginfo('version'); ?></td>
                    </tr>
                    <tr>
                        <td><?php _e('Plugin Version', 'msa-automatic'); ?></td>
                        <td><?php echo MSA_AUTOMATIC_VERSION; ?></td>
                    </tr>
                    <tr>
                        <td><?php _e('Memory Limit', 'msa-automatic'); ?></td>
                        <td><?php echo ini_get('memory_limit'); ?></td>
                    </tr>
                    <tr>
                        <td><?php _e('Max Execution Time', 'msa-automatic'); ?></td>
                        <td><?php echo ini_get('max_execution_time'); ?> <?php _e('seconds', 'msa-automatic'); ?></td>
                    </tr>
                </table>
                <p class="description">
                    <?php _e('This information is useful for troubleshooting purposes.', 'msa-automatic'); ?>
                </p>
            </div>
        </div>

        <!-- Campaign Tools -->
        <div class="msa-tool-box">
            <h3><?php _e('Campaign Tools', 'msa-automatic'); ?></h3>
            <div class="msa-tool-content">
                <div class="msa-tool-actions">
                    <button type="button" class="button msa-tool-action" data-action="reset-campaigns">
                        <?php _e('Reset Campaign Status', 'msa-automatic'); ?>
                    </button>
                    <button type="button" class="button msa-tool-action" data-action="clear-campaign-logs">
                        <?php _e('Clear Campaign Logs', 'msa-automatic'); ?>
                    </button>
                    <button type="button" class="button msa-tool-action" data-action="export-campaigns">
                        <?php _e('Export Campaigns', 'msa-automatic'); ?>
                    </button>
                </div>
                <div class="msa-import-campaigns">
                    <h4><?php _e('Import Campaigns', 'msa-automatic'); ?></h4>
                    <form method="post" enctype="multipart/form-data">
                        <input type="file" name="campaign_import_file" accept=".json">
                        <input type="submit" class="button" value="<?php esc_attr_e('Import', 'msa-automatic'); ?>">
                        <?php wp_nonce_field('msa_import_campaigns'); ?>
                    </form>
                </div>
            </div>
        </div>

        <!-- Cache Management -->
        <div class="msa-tool-box">
            <h3><?php _e('Cache Management', 'msa-automatic'); ?></h3>
            <div class="msa-tool-content">
                <div class="msa-tool-actions">
                    <button type="button" class="button msa-tool-action" data-action="clear-api-cache">
                        <?php _e('Clear API Cache', 'msa-automatic'); ?>
                    </button>
                    <button type="button" class="button msa-tool-action" data-action="clear-translation-cache">
                        <?php _e('Clear Translation Cache', 'msa-automatic'); ?>
                    </button>
                </div>
                <p class="description">
                    <?php _e('Clear cached data to fetch fresh content from APIs.', 'msa-automatic'); ?>
                </p>
            </div>
        </div>

        <!-- Debug Tools -->
        <div class="msa-tool-box">
            <h3><?php _e('Debug Tools', 'msa-automatic'); ?></h3>
            <div class="msa-tool-content">
                <div class="msa-debug-mode">
                    <label>
                        <input type="checkbox" 
                               id="msa-debug-mode" 
                               <?php checked(get_option('msa_debug_mode')); ?>>
                        <?php _e('Enable Debug Mode', 'msa-automatic'); ?>
                    </label>
                    <p class="description">
                        <?php _e('Enables detailed logging for troubleshooting.', 'msa-automatic'); ?>
                    </p>
                </div>
                <div class="msa-test-api">
                    <h4><?php _e('Test API Connection', 'msa-automatic'); ?></h4>
                    <select id="msa-test-api-service">
                        <option value=""><?php _e('Select Service', 'msa-automatic'); ?></option>
                        <option value="facebook"><?php _e('Facebook', 'msa-automatic'); ?></option>
                        <option value="twitter"><?php _e('Twitter', 'msa-automatic'); ?></option>
                        <option value="youtube"><?php _e('YouTube', 'msa-automatic'); ?></option>
                        <option value="instagram"><?php _e('Instagram', 'msa-automatic'); ?></option>
                        <option value="tiktok"><?php _e('TikTok', 'msa-automatic'); ?></option>
                    </select>
                    <button type="button" class="button" id="msa-test-api-button">
                        <?php _e('Test Connection', 'msa-automatic'); ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Database Tools -->
        <div class="msa-tool-box">
            <h3><?php _e('Database Tools', 'msa-automatic'); ?></h3>
            <div class="msa-tool-content">
                <div class="msa-tool-actions">
                    <button type="button" class="button msa-tool-action" data-action="optimize-tables">
                        <?php _e('Optimize Database Tables', 'msa-automatic'); ?>
                    </button>
                    <button type="button" class="button msa-tool-action" data-action="repair-tables">
                        <?php _e('Repair Database Tables', 'msa-automatic'); ?>
                    </button>
                </div>
                <div class="msa-db-info">
                    <?php
                    global $wpdb;
                    $tables = $wpdb->get_results("SHOW TABLES LIKE '{$wpdb->prefix}msa_%'");
                    ?>
                    <h4><?php _e('Plugin Tables', 'msa-automatic'); ?></h4>
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th><?php _e('Table Name', 'msa-automatic'); ?></th>
                                <th><?php _e('Records', 'msa-automatic'); ?></th>
                                <th><?php _e('Size', 'msa-automatic'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tables as $table): ?>
                                <?php
                                $table_name = current($table);
                                $size = $wpdb->get_row("SHOW TABLE STATUS LIKE '{$table_name}'");
                                $records = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
                                ?>
                                <tr>
                                    <td><?php echo esc_html($table_name); ?></td>
                                    <td><?php echo number_format_i18n($records); ?></td>
                                    <td><?php echo size_format($size->Data_length + $size->Index_length); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Handle tool actions
    $('.msa-tool-action').on('click', function(e) {
        e.preventDefault();
        var $button = $(this);
        var action = $button.data('action');

        if (!confirm(msaAutomatic.i18n.confirmAction)) {
            return;
        }

        $button.prop('disabled', true);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'msa_tool_action',
                tool_action: action,
                nonce: msaAutomatic.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    if (response.data.reload) {
                        window.location.reload();
                    }
                } else {
                    alert(response.data.message);
                }
                $button.prop('disabled', false);
            }
        });
    });

    // Handle debug mode toggle
    $('#msa-debug-mode').on('change', function() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'msa_toggle_debug_mode',
                enabled: this.checked,
                nonce: msaAutomatic.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                } else {
                    alert(response.data.message);
                    $('#msa-debug-mode').prop('checked', !$('#msa-debug-mode').prop('checked'));
                }
            }
        });
    });

    // Handle API connection test
    $('#msa-test-api-button').on('click', function() {
        var service = $('#msa-test-api-service').val();
        if (!service) {
            alert(msaAutomatic.i18n.selectService);
            return;
        }

        var $button = $(this);
        $button.prop('disabled', true).text(msaAutomatic.i18n.testing);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'msa_test_api_connection',
                service: service,
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
