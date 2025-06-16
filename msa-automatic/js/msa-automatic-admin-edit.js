(function($) {
    'use strict';

    // Initialize when document is ready
    $(document).ready(function() {
        initTabs();
        initFetcherSettings();
        initTranslationSettings();
        initScheduleSettings();
        initFilters();
        initTemplatePreview();
        initTestConnections();
        initCampaignRun();
    });

    // Initialize tabs
    function initTabs() {
        $('.msa-tabs-nav a').on('click', function(e) {
            e.preventDefault();
            var tab = $(this).attr('href').substring(1);
            
            // Update active states
            $('.msa-tabs-nav li').removeClass('active');
            $(this).parent().addClass('active');
            
            $('.msa-tab').removeClass('active');
            $('#' + tab).addClass('active');
        });
    }

    // Initialize fetcher settings
    function initFetcherSettings() {
        $('#msa_fetcher_type').on('change', function() {
            var type = $(this).val();
            
            // Hide all settings sections
            $('.msa-dynamic-settings').hide();
            
            // Show selected fetcher settings
            if (type) {
                $('#msa_fetcher_settings').show();
                loadFetcherSettings(type);
            }
        }).trigger('change');
    }

    // Load fetcher settings via AJAX
    function loadFetcherSettings(type) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'msa_get_fetcher_settings',
                type: type,
                nonce: msaAutomatic.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#msa_fetcher_settings').html(response.data.html);
                }
            }
        });
    }

    // Initialize translation settings
    function initTranslationSettings() {
        $('#msa_enable_translation').on('change', function() {
            $('.msa-translation-settings').toggle(this.checked);
        }).trigger('change');

        $('#msa_translator_type').on('change', function() {
            var type = $(this).val();
            if (type) {
                loadTranslatorSettings(type);
            }
        });
    }

    // Load translator settings via AJAX
    function loadTranslatorSettings(type) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'msa_get_translator_settings',
                type: type,
                nonce: msaAutomatic.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#msa_translator_settings').html(response.data.html);
                }
            }
        });
    }

    // Initialize schedule settings
    function initScheduleSettings() {
        $('#msa_schedule_type').on('change', function() {
            var type = $(this).val();
            
            // Hide all schedule settings
            $('.msa-schedule-time, .msa-recurring-interval').hide();
            
            // Show relevant settings
            if (type === 'scheduled') {
                $('.msa-schedule-time').show();
            } else if (type === 'recurring') {
                $('.msa-recurring-interval').show();
            }
        }).trigger('change');
    }

    // Initialize filters
    function initFilters() {
        // Add new filter
        $('#msa_add_filter').on('click', function() {
            var template = $('#msa_filter_template').html();
            $('#msa_filters').append(template);
        });

        // Remove filter
        $(document).on('click', '.msa-remove-filter', function() {
            $(this).closest('.msa-filter').remove();
        });
    }

    // Initialize template preview
    function initTemplatePreview() {
        var previewTimeout;
        
        $('#msa_template').on('input', function() {
            clearTimeout(previewTimeout);
            previewTimeout = setTimeout(updateTemplatePreview, 500);
        });
    }

    // Update template preview via AJAX
    function updateTemplatePreview() {
        var template = $('#msa_template').val();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'msa_preview_template',
                template: template,
                nonce: msaAutomatic.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#msa_template_preview').html(response.data.html);
                }
            }
        });
    }

    // Initialize test connections
    function initTestConnections() {
        // Test fetcher connection
        $('#msa_test_fetcher').on('click', function() {
            var $button = $(this);
            var type = $('#msa_fetcher_type').val();
            var settings = {};
            
            // Collect settings
            $('#msa_fetcher_settings input').each(function() {
                settings[$(this).attr('name')] = $(this).val();
            });

            testConnection($button, 'fetcher', type, settings);
        });

        // Test translator connection
        $('#msa_test_translator').on('click', function() {
            var $button = $(this);
            var type = $('#msa_translator_type').val();
            var settings = {};
            
            // Collect settings
            $('#msa_translator_settings input').each(function() {
                settings[$(this).attr('name')] = $(this).val();
            });

            testConnection($button, 'translator', type, settings);
        });
    }

    // Test connection via AJAX
    function testConnection($button, service, type, settings) {
        var originalText = $button.text();
        $button.text(msaAutomatic.i18n.testing).prop('disabled', true);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'msa_test_connection',
                service: service,
                type: type,
                settings: settings,
                nonce: msaAutomatic.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                } else {
                    alert(response.data.error);
                }
            },
            error: function() {
                alert(msaAutomatic.i18n.connectionError);
            },
            complete: function() {
                $button.text(originalText).prop('disabled', false);
            }
        });
    }

    // Initialize campaign run
    function initCampaignRun() {
        $('#msa_run_campaign').on('click', function() {
            var $button = $(this);
            var campaignId = $button.data('id');
            
            if (!campaignId) {
                return;
            }

            var originalText = $button.text();
            $button.text(msaAutomatic.i18n.running).prop('disabled', true);

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
                        alert(response.data.message);
                        location.reload();
                    } else {
                        alert(response.data.error);
                    }
                },
                error: function() {
                    alert(msaAutomatic.i18n.runError);
                },
                complete: function() {
                    $button.text(originalText).prop('disabled', false);
                }
            });
        });
    }

})(jQuery);
