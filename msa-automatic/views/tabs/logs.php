<?php
if (!defined('ABSPATH')) {
    exit;
}

$log_type = isset($_GET['log_type']) ? sanitize_text_field($_GET['log_type']) : 'all';
$log_level = isset($_GET['log_level']) ? sanitize_text_field($_GET['log_level']) : 'all';
$per_page = isset($_GET['per_page']) ? absint($_GET['per_page']) : 50;
$page = isset($_GET['paged']) ? absint($_GET['paged']) : 1;

$logs = msa_logger()->get_logs($log_type, $per_page);
$total_size = msa_logger()->get_log_size($log_type);
?>

<div class="msa-logs-wrapper">
    <div class="msa-logs-header">
        <div class="msa-logs-filters">
            <form method="get">
                <input type="hidden" name="page" value="msa-automatic">
                <input type="hidden" name="tab" value="logs">

                <select name="log_type" id="filter-by-type">
                    <option value="all" <?php selected($log_type, 'all'); ?>>
                        <?php _e('All Logs', 'msa-automatic'); ?>
                    </option>
                    <option value="general" <?php selected($log_type, 'general'); ?>>
                        <?php _e('General Logs', 'msa-automatic'); ?>
                    </option>
                    <option value="error" <?php selected($log_type, 'error'); ?>>
                        <?php _e('Error Logs', 'msa-automatic'); ?>
                    </option>
                </select>

                <select name="log_level" id="filter-by-level">
                    <option value="all" <?php selected($log_level, 'all'); ?>>
                        <?php _e('All Levels', 'msa-automatic'); ?>
                    </option>
                    <option value="debug" <?php selected($log_level, 'debug'); ?>>
                        <?php _e('Debug', 'msa-automatic'); ?>
                    </option>
                    <option value="info" <?php selected($log_level, 'info'); ?>>
                        <?php _e('Info', 'msa-automatic'); ?>
                    </option>
                    <option value="warning" <?php selected($log_level, 'warning'); ?>>
                        <?php _e('Warning', 'msa-automatic'); ?>
                    </option>
                    <option value="error" <?php selected($log_level, 'error'); ?>>
                        <?php _e('Error', 'msa-automatic'); ?>
                    </option>
                </select>

                <select name="per_page" id="filter-per-page">
                    <option value="25" <?php selected($per_page, 25); ?>>25</option>
                    <option value="50" <?php selected($per_page, 50); ?>>50</option>
                    <option value="100" <?php selected($per_page, 100); ?>>100</option>
                    <option value="200" <?php selected($per_page, 200); ?>>200</option>
                </select>

                <?php submit_button(__('Filter', 'msa-automatic'), 'secondary', 'filter_action', false); ?>
            </form>
        </div>

        <div class="msa-logs-actions">
            <button type="button" class="button msa-clear-logs" data-type="<?php echo esc_attr($log_type); ?>">
                <?php _e('Clear Logs', 'msa-automatic'); ?>
            </button>
            <button type="button" class="button msa-download-logs" data-type="<?php echo esc_attr($log_type); ?>">
                <?php _e('Download Logs', 'msa-automatic'); ?>
            </button>
        </div>
    </div>

    <div class="msa-logs-info">
        <span class="msa-log-size">
            <?php printf(
                __('Log Size: %s', 'msa-automatic'),
                msa_logger()->format_size($total_size)
            ); ?>
        </span>
    </div>

    <div class="msa-logs-table-wrapper">
        <table class="wp-list-table widefat fixed striped msa-logs-table">
            <thead>
                <tr>
                    <th class="column-timestamp"><?php _e('Timestamp', 'msa-automatic'); ?></th>
                    <th class="column-level"><?php _e('Level', 'msa-automatic'); ?></th>
                    <th class="column-message"><?php _e('Message', 'msa-automatic'); ?></th>
                    <th class="column-context"><?php _e('Context', 'msa-automatic'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($logs)): ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td class="column-timestamp">
                                <?php echo esc_html(MSA_Utils::format_date($log['timestamp'])); ?>
                                <div class="row-actions">
                                    <span class="view">
                                        <a href="#" class="msa-view-log-details" data-log='<?php echo esc_attr(json_encode($log)); ?>'>
                                            <?php _e('View Details', 'msa-automatic'); ?>
                                        </a>
                                    </span>
                                </div>
                            </td>
                            <td class="column-level">
                                <span class="msa-log-level msa-log-level-<?php echo esc_attr(strtolower($log['level'])); ?>">
                                    <?php echo esc_html($log['level']); ?>
                                </span>
                            </td>
                            <td class="column-message">
                                <?php echo esc_html($log['message']); ?>
                            </td>
                            <td class="column-context">
                                <?php if (!empty($log['context'])): ?>
                                    <pre><?php echo esc_html(json_encode($log['context'], JSON_PRETTY_PRINT)); ?></pre>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="msa-no-logs">
                            <?php _e('No logs found.', 'msa-automatic'); ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php
    $total_pages = ceil(msa_logger()->get_total_logs_count($log_type, $log_level) / $per_page);
    if ($total_pages > 1):
        ?>
        <div class="msa-logs-pagination tablenav">
            <div class="tablenav-pages">
                <?php
                echo paginate_links(array(
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'prev_text' => __('&laquo;'),
                    'next_text' => __('&raquo;'),
                    'total' => $total_pages,
                    'current' => $page
                ));
                ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Log Details Modal -->
<div id="msa-log-details-modal" class="msa-modal" style="display: none;">
    <div class="msa-modal-content">
        <span class="msa-modal-close">&times;</span>
        <h2><?php _e('Log Details', 'msa-automatic'); ?></h2>
        <div class="msa-modal-body">
            <div class="msa-log-detail">
                <strong><?php _e('Timestamp:', 'msa-automatic'); ?></strong>
                <span class="msa-log-timestamp"></span>
            </div>
            <div class="msa-log-detail">
                <strong><?php _e('Level:', 'msa-automatic'); ?></strong>
                <span class="msa-log-level"></span>
            </div>
            <div class="msa-log-detail">
                <strong><?php _e('Message:', 'msa-automatic'); ?></strong>
                <span class="msa-log-message"></span>
            </div>
            <div class="msa-log-detail">
                <strong><?php _e('Context:', 'msa-automatic'); ?></strong>
                <pre class="msa-log-context"></pre>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Handle clear logs
    $('.msa-clear-logs').on('click', function(e) {
        e.preventDefault();
        if (!confirm(msaAutomatic.i18n.confirmClearLogs)) {
            return;
        }

        var $button = $(this);
        var logType = $button.data('type');

        $button.prop('disabled', true);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'msa_clear_logs',
                log_type: logType,
                nonce: msaAutomatic.nonce
            },
            success: function(response) {
                if (response.success) {
                    window.location.reload();
                } else {
                    alert(response.data.message);
                    $button.prop('disabled', false);
                }
            }
        });
    });

    // Handle download logs
    $('.msa-download-logs').on('click', function(e) {
        e.preventDefault();
        var logType = $(this).data('type');
        window.location.href = ajaxurl + '?action=msa_download_logs&log_type=' + logType + '&nonce=' + msaAutomatic.nonce;
    });

    // Handle log details modal
    $('.msa-view-log-details').on('click', function(e) {
        e.preventDefault();
        var log = $(this).data('log');
        
        $('#msa-log-details-modal .msa-log-timestamp').text(log.timestamp);
        $('#msa-log-details-modal .msa-log-level').text(log.level);
        $('#msa-log-details-modal .msa-log-message').text(log.message);
        
        if (log.context) {
            $('#msa-log-details-modal .msa-log-context').text(JSON.stringify(log.context, null, 2)).show();
        } else {
            $('#msa-log-details-modal .msa-log-context').hide();
        }
        
        $('#msa-log-details-modal').show();
    });

    // Close modal
    $('.msa-modal-close').on('click', function() {
        $('#msa-log-details-modal').hide();
    });

    // Close modal on outside click
    $(window).on('click', function(e) {
        if ($(e.target).hasClass('msa-modal')) {
            $('.msa-modal').hide();
        }
    });
});
</script>
