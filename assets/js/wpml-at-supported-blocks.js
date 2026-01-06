/**
 * Supported Blocks Admin Page Scripts
 */
(function($) {
	'use strict';

	$(document).ready(function() {
		// Initialize DataTable
		if ($.fn.DataTable) {
			// Check if table exists and has correct structure
			var $table = $('#wpml-at-blocks-table');
			if (!$table.length) {
				console.error('Table #wpml-at-blocks-table not found');
				return;
			}

			// Count columns from header to ensure consistency
			var columnCount = $table.find('thead th').length;
			if (columnCount !== 6) {
				console.warn('Expected 6 columns but found ' + columnCount);
			}

			var table = $table.DataTable({
				'pageLength': 25,
				'order': [[1, 'asc']], // Sort by block name (column index 1)
				'columnDefs': [
					{ 'orderable': false, 'targets': [0, 3, 5] } // Disable sorting on Sr.No (0), toggle (3), and Attributes (5) columns
				],
				'autoWidth': false,
				'language': {
					'search': 'Search:',
					'lengthMenu': 'Show _MENU_ blocks',
					'info': 'Showing _START_ to _END_ of _TOTAL_ blocks',
					'infoEmpty': 'No blocks found',
					'infoFiltered': '(filtered from _MAX_ total blocks)',
					'zeroRecords': 'No matching blocks found'
				},
				'drawCallback': function(settings) {
					// Update serial numbers based on current page and display order
					var api = this.api();
					var pageInfo = api.page.info();
					var startIndex = pageInfo.start;
					
					api.rows({page: 'current'}).nodes().each(function(row, index) {
						var serialNumber = startIndex + index + 1;
						$(row).find('td').eq(0).html(serialNumber);
					});
				}
			});

			// Filter by category
			$('#wpml-at-blocks-category').on('change', function() {
				var category = $(this).val();
				if (category === 'all') {
					table.column(1).search('').draw(); // Block name column (index 1)
				} else {
					// Search in block name column for the category prefix
					table.column(1).search('^' + category + '/', true, false).draw();
				}
			});

			// Filter by status (supported/unsupported)
			$('#wpml-at-blocks-filter').on('change', function() {
				var status = $(this).val();
				if (status === 'all') {
					// Remove status filter - search in status column
					table.column(4).search('').draw(); // Status column (index 4)
				} else {
					// Filter by status
					table.column(4).search(status, true, false).draw();
				}
			});

			// Save changes button
			$('#wpml-at-save-changes').on('click', function() {
				var $button = $(this);
				var $message = $('#wpml-at-save-message');
				var blocks = {};

				$('.wpml-at-block-toggle').each(function() {
					var blockName = $(this).val();
					blocks[blockName] = $(this).prop('checked') ? 1 : 0;
				});

				$button.prop('disabled', true);
				$message.removeClass('success error').addClass('saving').text(wpmlAtSupportedBlocks.i18n.saving);

				$.ajax({
					url: wpmlAtSupportedBlocks.ajaxUrl,
					type: 'POST',
					data: {
						action: 'wpml_at_bulk_save_block_support',
						nonce: wpmlAtSupportedBlocks.nonce,
						blocks: blocks
					},
					success: function(response) {
						if (response.success) {
							$message.removeClass('saving').addClass('success').text(wpmlAtSupportedBlocks.i18n.saved);
							setTimeout(function() {
								$message.text('');
							}, 3000);
							
							// Update status column
							$('.wpml-at-block-toggle').each(function() {
								var $toggle = $(this);
								var blockName = $toggle.val();
								var isEnabled = $toggle.prop('checked');
								var $row = $toggle.closest('tr');
								var $statusCell = $row.find('td').eq(4); // Status column is now index 4
								
								if (isEnabled) {
									$statusCell.html('<span class="wpml-at-status wpml-at-status-supported">Supported</span>');
									$row.attr('data-block-status', 'supported');
								} else {
									$statusCell.html('<span class="wpml-at-status wpml-at-status-unsupported">Unsupported</span>');
									$row.attr('data-block-status', 'unsupported');
								}
							});
						} else {
							$message.removeClass('saving').addClass('error').text(response.data.message || wpmlAtSupportedBlocks.i18n.error);
						}
					},
					error: function() {
						$message.removeClass('saving').addClass('error').text(wpmlAtSupportedBlocks.i18n.error);
					},
					complete: function() {
						$button.prop('disabled', false);
					}
				});
			});

			// Custom filter function for status
			$.fn.dataTable.ext.search.push(
				function(settings, data, dataIndex) {
					var statusFilter = $('#wpml-at-blocks-filter').val();
					var categoryFilter = $('#wpml-at-blocks-category').val();
					
					if (statusFilter === 'all' && categoryFilter === 'all') {
						return true;
					}

					var row = settings.aoData[dataIndex].nTr;
					var rowStatus = $(row).data('block-status') || '';
					var rowCategory = $(row).data('plugin-category') || 'core';

					// Check status filter
					if (statusFilter !== 'all') {
						if (rowStatus !== statusFilter) {
							return false;
						}
					}

					// Check category filter
					if (categoryFilter !== 'all') {
						if (rowCategory !== categoryFilter) {
							return false;
						}
					}

					return true;
				}
			);

			// Re-draw table when filters change
			$('#wpml-at-blocks-category, #wpml-at-blocks-filter').on('change', function() {
				table.draw();
			});
		} else {
			console.error('DataTables library not loaded');
		}
	});

})(jQuery);

