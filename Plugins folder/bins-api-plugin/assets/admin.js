jQuery(document).ready(function ($) {
    console.log('BIN API Admin JS v3.3 Loaded');

    if (typeof binApiAdmin === 'undefined') {
        console.error('BIN API Error: Localized script data (binApiAdmin) not found.');
        $('.bin-api-dashboard').prepend('<div class="notice notice-error is-dismissible"><p>Error: Admin script configuration missing. Actions may not work.</p></div>');
        return;
    }

    // --- Key Table Search/Filter (Unchanged) ---
    $('#bin-api-key-search').on('keyup', function () {
        var searchTerm = $(this).val().toLowerCase();
        var currentFilter = getCurrentStatusFilter(); // Get current status filter
        $('#managed-keys-table-body tr').each(function () {
            var $row = $(this);
            // Only filter rows matching the current status view
            if (currentFilter === 'all' && $row.hasClass('status-trashed')) { return; } // Skip trashed in 'all' view
            if (currentFilter !== 'all' && !$row.hasClass('status-' + currentFilter)) { return; } // Skip if status doesn't match filter

            var userLogin = ($row.data('user-login') || '').toLowerCase(); var apiKey = ($row.data('api-key') || '').toLowerCase(); var keyPrefix = apiKey.substring(0, 8);
            if (userLogin.includes(searchTerm) || keyPrefix.includes(searchTerm)) { $row.show(); } else { $row.hide(); }
        });
    });

    // --- Helper Function for AJAX ---
    function performAjaxAction(ajaxData, $button, processingText, successCallback, errorCallback) {
        var $originalButtonHTML = $button.html();
        var isBulkApply = $button.hasClass('bulk-apply-button'); // Check if it's a bulk apply button
        var $spinner = isBulkApply ? $button.next('.spinner') : $button.find('.spinner'); // Find spinner differently for bulk
        if(!$spinner.length && !isBulkApply) { // If no spinner inside, create one? simpler to expect it or just change text
           $button.prop('disabled', true).html(processingText + '...'); // Simpler feedback
        } else {
            $button.prop('disabled', true).html(processingText);
             if($spinner.length) $spinner.addClass('is-active');
        }


        $.post(binApiAdmin.ajax_url, ajaxData, function (response) {
            console.log('AJAX Response:', response);
             $button.prop('disabled', false).html($originalButtonHTML);
             if ($spinner.length) $spinner.removeClass('is-active');

            if (response.success) {
                if (typeof successCallback === 'function') { successCallback(response.data); }
            } else {
                var errorMsg = response.data && response.data.message ? response.data.message : 'Unknown error occurred.';
                alert('Error: ' + errorMsg);
                if (typeof errorCallback === 'function') { errorCallback(response.data); }
            }
        }).fail(function (xhr, status, error) {
            $button.prop('disabled', false).html($originalButtonHTML);
             if ($spinner.length) $spinner.removeClass('is-active');
            console.error('BIN API AJAX Error:', status, error, xhr.responseText);
            alert('AJAX Request Failed: ' + status + ' - ' + error);
            if (typeof errorCallback === 'function') { errorCallback({ message: 'AJAX Request Failed' }); }
        });
    }

    // --- AJAX Actions for Single Key Management Buttons ---
    $('#managed-keys-table').on('click', '.ajax-action-button', function (e) {
        e.preventDefault();
        var $button = $(this);
        var action = $button.data('action');
        var apiKey = $button.data('key');
        var targetStatus = $button.data('target-status'); // For set_status
        var nonce, ajaxAction, confirmText, processingText;

        // Determine parameters based on action
        switch (action) {
            case 'set_status': // Handles activate, deactivate, suspend, unsuspend (restore), trash
                ajaxAction = 'set_bin_key_status'; nonce = binApiAdmin.nonce_set_status;
                if (targetStatus === 'active') { confirmText = binApiAdmin.text_confirm_activate; processingText = binApiAdmin.text_activating; }
                else if (targetStatus === 'inactive') { confirmText = binApiAdmin.text_confirm_deactivate; processingText = binApiAdmin.text_deactivating; }
                else if (targetStatus === 'suspended') { confirmText = binApiAdmin.text_confirm_suspend; processingText = binApiAdmin.text_suspending; }
                else if (targetStatus === 'trashed') { confirmText = binApiAdmin.text_confirm_trash; processingText = binApiAdmin.text_trashing; }
                 // Restore case (usually button text is 'Restore', target status is 'inactive' or 'active')
                 else if ($button.text().toLowerCase().includes(binApiAdmin.text_restore.toLowerCase())) { // Check button text for restore context
                    confirmText = binApiAdmin.text_confirm_restore; processingText = binApiAdmin.text_restoring;
                    // targetStatus should be set correctly by the button's data-target-status attribute (e.g., 'inactive')
                 }
                else { return; } // Unknown target status
                break;
            case 'reset_requests':
                ajaxAction = 'reset_bin_requests'; nonce = binApiAdmin.nonce_reset;
                confirmText = binApiAdmin.text_confirm_reset; processingText = binApiAdmin.text_resetting;
                break;
            case 'delete_key_permanently': // Renamed action
                 ajaxAction = 'delete_bin_key_permanently'; nonce = binApiAdmin.nonce_delete;
                 confirmText = binApiAdmin.text_confirm_delete_perm; processingText = binApiAdmin.text_deleting;
                 break;
            default:
                console.error('Unknown single action:', action); return;
        }

        if (!confirm(confirmText)) { return; }

        var ajaxData = { action: ajaxAction, nonce: nonce, api_key: apiKey, new_status: targetStatus /* only used by set_status */ };

        performAjaxAction(ajaxData, $button, processingText,
            function(data) { // Success Callback
                var $row = $button.closest('tr');
                 var currentFilter = getCurrentStatusFilter();

                if (action === 'set_status') {
                    // If the view is filtered and the new status doesn't match, hide the row.
                    // Exception: If restoring from trash, keep visible even if filter isn't 'inactive'.
                    var newStatus = data.new_status;
                    if (currentFilter !== 'all' && currentFilter !== newStatus && !$button.text().toLowerCase().includes(binApiAdmin.text_restore.toLowerCase())) {
                         $row.fadeOut(400, function() { $(this).remove(); });
                    } else {
                        // Update row visually if it remains visible
                        updateRowStatus($row, newStatus, binApiAdmin);
                    }
                } else if (action === 'reset_requests') {
                     $row.find('td:nth-child(6), td:nth-child(7), td:nth_child(8), td:nth-child(9)').text('0'); // Adjust column indices
                     $button.html('✓ ' + binApiAdmin.text_reset);
                     setTimeout(function() { $button.html(binApiAdmin.text_reset); }, 1500);
                 } else if (action === 'delete_key_permanently') {
                      $row.fadeOut(400, function() { $(this).remove(); });
                 }
            },
            function(data) { /* Error already handled */ }
        );
    });

    // --- Bulk Actions ---
    // Select/Deselect All Checkbox Handler (Unchanged)
    $('#cb-select-all-1, #cb-select-all-2').on('click', function() { var i = $(this).prop('checked'); $('#managed-keys-table-body input[type="checkbox"][name="bulk_keys[]"]').prop('checked', i); $('#cb-select-all-1, #cb-select-all-2').prop('checked', i); });
    $('#managed-keys-table-body').on('change', 'input[type="checkbox"][name="bulk_keys[]"]', function() { if (!$(this).prop('checked')) { $('#cb-select-all-1, #cb-select-all-2').prop('checked', false); } else if ($('#managed-keys-table-body input[type="checkbox"][name="bulk_keys[]"]:checked').length === $('#managed-keys-table-body input[type="checkbox"][name="bulk_keys[]"]').length) { $('#cb-select-all-1, #cb-select-all-2').prop('checked', true); } });

    // Apply Bulk Action Button Handler (Modified for new actions)
    $('.bulk-apply-button').on('click', function(e) {
        e.preventDefault();
        var $button = $(this); var whichBulk = $button.attr('id') === 'doaction' ? 'top' : 'bottom';
        var bulkAction = $('#bulk-action-selector-' + whichBulk).val();
        var selectedKeys = []; $('#managed-keys-table-body input[type="checkbox"][name="bulk_keys[]"]:checked').each(function() { selectedKeys.push($(this).val()); });
        if (selectedKeys.length === 0) { alert(binApiAdmin.text_no_keys_selected); return; }
        if (bulkAction === '-1') { alert(binApiAdmin.text_no_action_selected); return; }

        var confirmText = 'Apply selected bulk action?'; // Default
        if (bulkAction === 'bulk_activate') confirmText = binApiAdmin.text_confirm_bulk_activate;
        else if (bulkAction === 'bulk_deactivate') confirmText = binApiAdmin.text_confirm_bulk_deactivate;
        else if (bulkAction === 'bulk_suspend') confirmText = binApiAdmin.text_confirm_bulk_suspend;
        else if (bulkAction === 'bulk_unsuspend') confirmText = binApiAdmin.text_confirm_bulk_unsuspend;
        else if (bulkAction === 'bulk_trash') confirmText = binApiAdmin.text_confirm_bulk_trash;
        else if (bulkAction === 'bulk_restore') confirmText = binApiAdmin.text_confirm_bulk_restore;
        else if (bulkAction === 'bulk_delete_permanently') confirmText = binApiAdmin.text_confirm_bulk_delete_perm;
        else if (bulkAction === 'bulk_reset') confirmText = binApiAdmin.text_confirm_bulk_reset;

        if (!confirm(confirmText)) { return; }

        var ajaxData = { action: 'bulk_bin_key_action', nonce: binApiAdmin.nonce_bulk, bulk_action: bulkAction, api_keys: selectedKeys };

        performAjaxAction(ajaxData, $button, binApiAdmin.text_applying,
            function(data) { // Success Callback for Bulk
                console.log("Bulk action successful:", data.message);
                $('#bulk-action-message').text(data.message).removeClass('notice-error').addClass('notice notice-success').show().delay(5000).fadeOut();

                var currentFilter = getCurrentStatusFilter();

                // Update UI for affected rows
                if (data.results) {
                    $.each(data.results, function(apiKey, result) {
                        var $row = $('#managed-keys-table-body tr[data-api-key="' + apiKey + '"]');
                        if (!$row.length) return;

                        if (result.deleted || data.action === 'bulk_trash' || data.action === 'bulk_restore') {
                             // If view doesn't match new state after trash/restore/delete, remove row
                             if ((data.action === 'bulk_delete_permanently') ||
                                 (data.action === 'bulk_trash' && currentFilter !== 'trashed') ||
                                 (data.action === 'bulk_restore' && currentFilter === 'trashed'))
                             {
                                $row.fadeOut(400, function() { $(this).remove(); });
                             } else if (result.new_status) {
                                // If restoring and staying on the same page (e.g. restoring TO 'inactive' but filter is 'all')
                                updateRowStatus($row, result.new_status, binApiAdmin);
                             }

                        } else if (result.new_status) { // Other status changes
                            updateRowStatus($row, result.new_status, binApiAdmin);
                        } else if (result.reset) { // Reset action
                             $row.find('td:nth-child(6), td:nth-child(7), td:nth-child(8), td:nth-child(9)').text('0'); // Adjust column indices
                        }
                    });
                }
                // Deselect checkboxes and dropdown
                $('#cb-select-all-1, #cb-select-all-2').prop('checked', false);
                $('#managed-keys-table-body input[type="checkbox"][name="bulk_keys[]"]').prop('checked', false);
                $('#bulk-action-selector-top, #bulk-action-selector-bottom').val('-1');
            },
            function(data) { // Error Callback for Bulk
                 $('#bulk-action-message').text('Error: ' + data.message).removeClass('notice-success').addClass('notice notice-error').show().delay(5000).fadeOut();
            }
        );
    }); // End Bulk Actions


     // --- Key Generation (Unchanged) ---
    $('#bin-api-generate-key-button').on('click', function(e) { e.preventDefault(); var $button = $(this); var selectedUserId = $('#bin-api-generate-user').val(); var resultDiv = $('#bin-api-generate-key-result'); if (!selectedUserId) { alert('Please select a user.'); return; } var ajaxData = { action: 'generate_bin_key', nonce: binApiAdmin.nonce_generate, user_id: selectedUserId }; resultDiv.removeClass('notice-error notice-success').hide(); performAjaxAction(ajaxData, $button, binApiAdmin.text_generating, function(data) { resultDiv.addClass('notice-success').html('<p>' + data.message + '</p>' + '<p><strong>Key:</strong> <code>' + data.api_key + '</code></p>' + '<p><small>Refresh page to see key.</small></p>' ).show(); $('#bin-api-generate-user').val(''); }, function(data) { resultDiv.addClass('notice-error').html('<p>' + data.message + '</p>').show(); }); });

     // --- NEW: Key Details Modal ---
     var $modal = $('#bin-api-key-details-modal');
     var $modalBody = $('#bin-api-key-details-body');

     // Open Modal on "Details" click
     $('#managed-keys-table').on('click', '.key-details-button', function(e) {
         e.preventDefault();
         var $button = $(this);
         var apiKey = $button.data('key');

         $modalBody.html('<p><em>' + binApiAdmin.text_fetching + '</em></p>');
         $modal.show(); // Show modal with loading message

         var ajaxData = {
             action: 'get_bin_key_details',
             nonce: binApiAdmin.nonce_get_details,
             api_key: apiKey
         };

         // Use regular AJAX here, not helper, as we don't need button state mgmt
         $.post(binApiAdmin.ajax_url, ajaxData, function(response) {
             if (response.success) {
                 var details = response.data;
                 var html = '<table class="form-table"><tbody>'; // Use WP style table
                 html += '<tr><th scope="row">API Key:</th><td><code>' + details.api_key + '</code></td></tr>';
                 html += '<tr><th scope="row">Status:</th><td>' + ucfirst(details.status || 'Unknown') + '</td></tr>';
                 html += '<tr><th scope="row">User:</th><td>' + (details.display_name || 'N/A') + ' (ID: ' + (details.user_id || 'N/A') + ')</td></tr>';
                 html += '<tr><th scope="row">User Email:</th><td>' + (details.user_email || 'N/A') + '</td></tr>';
                 if(details.counts) {
                    html += '<tr><th scope="row">Requests Today:</th><td>' + (details.counts.today !== null ? details.counts.today : 'N/A') + '</td></tr>';
                    html += '<tr><th scope="row">Requests Week:</th><td>' + (details.counts.week !== null ? details.counts.week : 'N/A') + '</td></tr>';
                    html += '<tr><th scope="row">Requests Month:</th><td>' + (details.counts.month !== null ? details.counts.month : 'N/A') + '</td></tr>';
                    html += '<tr><th scope="row">Requests Total:</th><td>' + (details.counts.total !== null ? details.counts.total : 'N/A') + '</td></tr>';
                 }
                 // Add more rows here if other details are fetched (e.g., created_at, notes)
                 html += '</tbody></table>';
                 $modalBody.html(html);
             } else {
                 $modalBody.html('<p style="color: red;">Error: ' + response.data.message + '</p>');
             }
         }).fail(function() {
              $modalBody.html('<p style="color: red;">AJAX request failed.</p>');
         });
     });

     // Close modal
     $modal.on('click', '.modal-close', function(e) {
         e.preventDefault();
         $modal.hide();
     });
     // Close modal if clicking outside the content area
     $modal.on('click', function(e) {
         if ($(e.target).is($modal)) { // Check if the click is directly on the modal background
             $modal.hide();
         }
     });
     // --- END Details Modal ---


    // --- Utility Functions ---
     function ucfirst(str) { if (!str) return ''; return str.charAt(0).toUpperCase() + str.slice(1); }

     // Get current status filter from URL query parameter
     function getCurrentStatusFilter() {
         var urlParams = new URLSearchParams(window.location.search);
         return urlParams.get('key_status') || 'all'; // Default to 'all'
     }

     // Function to update a table row's status display and buttons
     function updateRowStatus($row, newStatus, translations) {
         if(!$row || !$row.length) return;

         var $statusSpan = $row.find('.status-column .key-status-text');
         var $actionsDiv = $row.find('.actions-column .button-group');
         var apiKey = $row.data('api-key');

         // Update status text and row class
         var newStatusDisplay = translations['text_' + newStatus] || ucfirst(newStatus);
         $statusSpan.text(newStatusDisplay);
         $row.removeClass('status-active status-inactive status-suspended status-trashed').addClass('status-' + newStatus);

         // Determine background color based on status
         var bgColor = '#808080'; // Default gray for unknown/other
            if (newStatus === 'active') bgColor = '#4CAF50'; // Green
            else if (newStatus === 'inactive') bgColor = '#DC143C'; // Crimson
            else if (newStatus === 'suspended') bgColor = '#FFA500'; // Orange
            else if (newStatus === 'trashed') bgColor = '#AAAAAA'; // Lighter gray for trashed
         $statusSpan.css('background-color', bgColor);

         // --- Rebuild Action Buttons ---
         $actionsDiv.empty(); // Clear existing buttons
         if (newStatus === 'trashed') {
             $actionsDiv.append('<button type="button" class="button button-small button-primary ajax-set-status-button" data-action="set_status" data-target-status="inactive" data-key="'+apiKey+'">'+translations.text_restore+'</button>');
             $actionsDiv.append('<button type="button" class="button button-small delete-button ajax-action-button" data-action="delete_key_permanently" data-key="'+apiKey+'">'+translations.text_delete_perm+'</button>');
         } else {
              // Add 'Details' button for non-trashed items
             $actionsDiv.append('<button type="button" class="button button-small key-details-button" data-key="'+apiKey+'">'+translations.text_details+'</button>');

             if (newStatus === 'active') {
                 $actionsDiv.append('<button type="button" class="button button-small ajax-set-status-button" data-action="set_status" data-target-status="inactive" data-key="'+apiKey+'">'+translations.text_deactivate+'</button>');
                 $actionsDiv.append('<button type="button" class="button button-small ajax-set-status-button" data-action="set_status" data-target-status="suspended" data-key="'+apiKey+'">'+translations.text_suspend+'</button>');
             } else if (newStatus === 'suspended') {
                 $actionsDiv.append('<button type="button" class="button button-small button-primary ajax-set-status-button" data-action="set_status" data-target-status="active" data-key="'+apiKey+'">'+translations.text_unsuspend+'</button>');
             } else if (newStatus === 'inactive') {
                 $actionsDiv.append('<button type="button" class="button button-small button-primary ajax-set-status-button" data-action="set_status" data-target-status="active" data-key="'+apiKey+'">'+translations.text_reactivate+'</button>');
             }
              // Always add Reset and Trash buttons for non-trashed items
             $actionsDiv.append('<button type="button" class="button button-small ajax-action-button" data-action="reset_requests" data-key="'+apiKey+'">'+translations.text_reset+'</button>');
             $actionsDiv.append('<button type="button" class="button button-small trash-button ajax-set-status-button" data-action="set_status" data-target-status="trashed" data-key="'+apiKey+'">'+translations.text_trash+'</button>');
         }
     } // End updateRowStatus

}); // End jQuery document ready