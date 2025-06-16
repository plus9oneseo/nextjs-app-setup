<?php
if (!defined('ABSPATH')) {
    exit;
}

$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'campaigns';
?>

<div class="wrap msa-automatic-admin">
    <h1 class="wp-heading-inline"><?php _e('MSA Automatic', 'msa-automatic'); ?></h1>
    
    <a href="<?php echo admin_url('post-new.php?post_type=msa_campaign'); ?>" class="page-title-action">
        <?php _e('Add New Campaign', 'msa-automatic'); ?>
    </a>

    <hr class="wp-header-end">

    <nav class="nav-tab-wrapper wp-clearfix">
        <a href="?page=msa-automatic&tab=campaigns" 
           class="nav-tab <?php echo $active_tab === 'campaigns' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Campaigns', 'msa-automatic'); ?>
        </a>
        <a href="?page=msa-automatic&tab=logs" 
           class="nav-tab <?php echo $active_tab === 'logs' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Logs', 'msa-automatic'); ?>
        </a>
        <a href="?page=msa-automatic&tab=settings" 
           class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Settings', 'msa-automatic'); ?>
        </a>
        <a href="?page=msa-automatic&tab=tools" 
           class="nav-tab <?php echo $active_tab === 'tools' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Tools', 'msa-automatic'); ?>
        </a>
    </nav>

    <div class="msa-content-wrapper">
        <?php
        switch ($active_tab) {
            case 'campaigns':
                include MSA_AUTOMATIC_PATH . 'views/tabs/campaigns.php';
                break;
            
            case 'logs':
                include MSA_AUTOMATIC_PATH . 'views/tabs/logs.php';
                break;
            
            case 'settings':
                include MSA_AUTOMATIC_PATH . 'views/tabs/settings.php';
                break;
            
            case 'tools':
                include MSA_AUTOMATIC_PATH . 'views/tabs/tools.php';
                break;
        }
        ?>
    </div>

    <div class="msa-footer">
        <div class="msa-footer-stats">
            <?php
            $campaigns = wp_count_posts('msa_campaign');
            $active_campaigns = get_posts(array(
                'post_type' => 'msa_campaign',
                'post_status' => 'publish',
                'meta_query' => array(
                    array(
                        'key' => '_msa_status',
                        'value' => 'active'
                    )
                ),
                'numberposts' => -1
            ));
            ?>
            <span class="msa-stat">
                <?php printf(
                    __('Total Campaigns: %d', 'msa-automatic'),
                    $campaigns->publish + $campaigns->draft
                ); ?>
            </span>
            <span class="msa-stat">
                <?php printf(
                    __('Active Campaigns: %d', 'msa-automatic'),
                    count($active_campaigns)
                ); ?>
            </span>
            <span class="msa-stat">
                <?php printf(
                    __('Version: %s', 'msa-automatic'),
                    MSA_AUTOMATIC_VERSION
                ); ?>
            </span>
        </div>
        <div class="msa-footer-links">
            <a href="https://example.com/docs" target="_blank">
                <?php _e('Documentation', 'msa-automatic'); ?>
            </a>
            <a href="https://example.com/support" target="_blank">
                <?php _e('Support', 'msa-automatic'); ?>
            </a>
            <a href="https://example.com/changelog" target="_blank">
                <?php _e('Changelog', 'msa-automatic'); ?>
            </a>
        </div>
    </div>
</div>

<script type="text/template" id="tmpl-msa-campaign-row">
    <tr data-id="{{ data.id }}">
        <td class="column-cb check-column">
            <input type="checkbox" name="campaign[]" value="{{ data.id }}">
        </td>
        <td class="column-title">
            <strong>
                <a href="<?php echo admin_url('post.php?action=edit&post='); ?>{{ data.id }}" class="row-title">
                    {{ data.title }}
                </a>
            </strong>
            <div class="row-actions">
                <span class="edit">
                    <a href="<?php echo admin_url('post.php?action=edit&post='); ?>{{ data.id }}">
                        <?php _e('Edit', 'msa-automatic'); ?>
                    </a> |
                </span>
                <span class="trash">
                    <a href="#" class="submitdelete" data-id="{{ data.id }}">
                        <?php _e('Delete', 'msa-automatic'); ?>
                    </a>
                </span>
            </div>
        </td>
        <td class="column-type">{{ data.type }}</td>
        <td class="column-schedule">{{ data.schedule }}</td>
        <td class="column-status">
            <span class="msa-status msa-status-{{ data.status }}">{{ data.status }}</span>
        </td>
        <td class="column-last-run">{{ data.lastRun }}</td>
        <td class="column-actions">
            <button type="button" class="button msa-run-now" data-id="{{ data.id }}">
                <?php _e('Run Now', 'msa-automatic'); ?>
            </button>
        </td>
    </tr>
</script>

<script type="text/template" id="tmpl-msa-log-row">
    <tr data-id="{{ data.id }}">
        <td class="column-timestamp">{{ data.timestamp }}</td>
        <td class="column-level">
            <span class="msa-log-level msa-log-level-{{ data.level }}">{{ data.level }}</span>
        </td>
        <td class="column-message">{{ data.message }}</td>
        <td class="column-campaign">
            <# if (data.campaignId) { #>
                <a href="<?php echo admin_url('post.php?action=edit&post='); ?>{{ data.campaignId }}">
                    {{ data.campaignTitle }}
                </a>
            <# } else { #>
                -
            <# } #>
        </td>
    </tr>
</script>
