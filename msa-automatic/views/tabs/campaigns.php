<?php
if (!defined('ABSPATH')) {
    exit;
}

$campaigns_table = new MSA_Campaigns_List_Table();
$campaigns_table->prepare_items();

$campaign_types = msa_fetcher_loader()->get_available_fetchers();
?>

<div class="msa-campaigns-wrapper">
    <div class="msa-campaigns-header">
        <div class="msa-campaigns-filters">
            <form method="get">
                <input type="hidden" name="page" value="msa-automatic">
                <input type="hidden" name="tab" value="campaigns">
                
                <select name="type" id="filter-by-type">
                    <option value=""><?php _e('All Types', 'msa-automatic'); ?></option>
                    <?php foreach ($campaign_types as $type => $info): ?>
                        <option value="<?php echo esc_attr($type); ?>" <?php selected(isset($_GET['type']) ? $_GET['type'] : '', $type); ?>>
                            <?php echo esc_html($info['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="status" id="filter-by-status">
                    <option value=""><?php _e('All Statuses', 'msa-automatic'); ?></option>
                    <option value="active" <?php selected(isset($_GET['status']) ? $_GET['status'] : '', 'active'); ?>>
                        <?php _e('Active', 'msa-automatic'); ?>
                    </option>
                    <option value="paused" <?php selected(isset($_GET['status']) ? $_GET['status'] : '', 'paused'); ?>>
                        <?php _e('Paused', 'msa-automatic'); ?>
                    </option>
                    <option value="error" <?php selected(isset($_GET['status']) ? $_GET['status'] : '', 'error'); ?>>
                        <?php _e('Error', 'msa-automatic'); ?>
                    </option>
                </select>

                <?php submit_button(__('Filter', 'msa-automatic'), 'secondary', 'filter_action', false); ?>
            </form>
        </div>

        <div class="msa-campaigns-actions">
            <div class="alignleft actions bulkactions">
                <select name="action" id="bulk-action-selector-top">
                    <option value="-1"><?php _e('Bulk Actions', 'msa-automatic'); ?></option>
                    <option value="activate"><?php _e('Activate', 'msa-automatic'); ?></option>
                    <option value="pause"><?php _e('Pause', 'msa-automatic'); ?></option>
                    <option value="delete"><?php _e('Delete', 'msa-automatic'); ?></option>
                </select>
                <input type="submit" class="button action" value="<?php esc_attr_e('Apply', 'msa-automatic'); ?>">
            </div>
        </div>
    </div>

    <form id="msa-campaigns-form" method="post">
        <?php
        $campaigns_table->display();
        ?>
    </form>

    <div class="msa-campaigns-summary">
        <div class="msa-summary-box">
            <h3><?php _e('Campaign Statistics', 'msa-automatic'); ?></h3>
            <?php
            $stats = msa_campaign_processor()->get_campaign_stats();
            ?>
            <div class="msa-stats-grid">
                <div class="msa-stat-item">
                    <span class="msa-stat-label"><?php _e('Total Posts', 'msa-automatic'); ?></span>
                    <span class="msa-stat-value"><?php echo number_format_i18n($stats['total_posts']); ?></span>
                </div>
                <div class="msa-stat-item">
                    <span class="msa-stat-label"><?php _e('Posts Today', 'msa-automatic'); ?></span>
                    <span class="msa-stat-value"><?php echo number_format_i18n($stats['posts_today']); ?></span>
                </div>
                <div class="msa-stat-item">
                    <span class="msa-stat-label"><?php _e('Success Rate', 'msa-automatic'); ?></span>
                    <span class="msa-stat-value"><?php echo number_format_i18n($stats['success_rate'], 1); ?>%</span>
                </div>
                <div class="msa-stat-item">
                    <span class="msa-stat-label"><?php _e('Active Campaigns', 'msa-automatic'); ?></span>
                    <span class="msa-stat-value"><?php echo number_format_i18n($stats['active_campaigns']); ?></span>
                </div>
            </div>
        </div>

        <div class="msa-summary-box">
            <h3><?php _e('Recent Activity', 'msa-automatic'); ?></h3>
            <div class="msa-recent-activity">
                <?php
                $recent_logs = msa_logger()->get_logs('all', 5);
                if (!empty($recent_logs)):
                    foreach ($recent_logs as $log):
                        ?>
                        <div class="msa-activity-item">
                            <span class="msa-activity-time"><?php echo esc_html(MSA_Utils::time_diff($log['timestamp'])); ?></span>
                            <span class="msa-activity-level msa-level-<?php echo esc_attr($log['level']); ?>">
                                <?php echo esc_html(ucfirst($log['level'])); ?>
                            </span>
                            <span class="msa-activity-message"><?php echo esc_html($log['message']); ?></span>
                        </div>
                        <?php
                    endforeach;
                else:
                    ?>
                    <p class="msa-no-activity"><?php _e('No recent activity', 'msa-automatic'); ?></p>
                    <?php
                endif;
                ?>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Handle bulk actions
    $('#msa-campaigns-form').on('submit', function(e) {
        e.preventDefault();
        var action = $('#bulk-action-selector-top').val();
        var campaigns = [];
        
        $('input[name="campaign[]"]:checked').each(function() {
            campaigns.push($(this).val());
        });

        if (action === '-1' || campaigns.length === 0) {
            return;
        }

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'msa_bulk_campaign_action',
                bulk_action: action,
                campaigns: campaigns,
                nonce: msaAutomatic.nonce
            },
            success: function(response) {
                if (response.success) {
                    window.location.reload();
                } else {
                    alert(response.data.message);
                }
            }
        });
    });

    // Handle run now button
    $('.msa-run-now').on('click', function(e) {
        e.preventDefault();
        var campaignId = $(this).data('id');
        var $button = $(this);

        $button.prop('disabled', true).text(msaAutomatic.i18n.running);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'msa_run_campaign',
                campaign_id: campaignId,
                nonce: msaAutomatic.nonce
            },
            success: function(response) {
                if (response.success) {
                    window.location.reload();
                } else {
                    alert(response.data.message);
                    $button.prop('disabled', false).text(msaAutomatic.i18n.runNow);
                }
            }
        });
    });
});
</script>
