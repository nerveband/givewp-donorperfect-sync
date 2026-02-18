/* GiveWP → DonorPerfect Sync Admin JS */
(function($) {
    'use strict';

    // ─── Dashboard Charts ───
    if (typeof gwdp !== 'undefined' && gwdp.charts && typeof Chart !== 'undefined') {
        var c = gwdp.charts;

        Chart.defaults.font.family = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
        Chart.defaults.font.size = 12;
        Chart.defaults.plugins.legend.position = 'bottom';
        Chart.defaults.plugins.legend.labels.padding = 12;
        Chart.defaults.plugins.legend.labels.usePointStyle = true;
        Chart.defaults.plugins.legend.labels.pointStyleWidth = 10;

        // -- Sync Status (doughnut) --
        var statusEl = document.getElementById('gwdp-chart-status');
        if (statusEl) {
            var statusChart = new Chart(statusEl, {
                type: 'doughnut',
                data: {
                    labels: ['Success', 'Errors', 'Skipped'],
                    datasets: [{
                        data: [c.status.success, c.status.error, c.status.skipped],
                        backgroundColor: ['#46b450', '#dc3232', '#dba617'],
                        borderWidth: 2,
                        borderColor: '#fff',
                        hoverOffset: 6
                    }]
                },
                options: {
                    cutout: '60%',
                    onClick: function(e, elements) {
                        if (!elements.length) return;
                        var filters = ['success', 'error', 'skipped'];
                        window.location.href = gwdp.log_url + '&status=' + filters[elements[0].index];
                    },
                    onHover: function(e, elements) {
                        e.native.target.style.cursor = elements.length ? 'pointer' : 'default';
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(ctx) {
                                    var total = ctx.dataset.data.reduce(function(a, b) { return a + b; }, 0);
                                    var pct = total > 0 ? Math.round(ctx.raw / total * 100) : 0;
                                    return ctx.label + ': ' + ctx.raw + ' (' + pct + '%)';
                                }
                            }
                        }
                    }
                }
            });
        }

        // -- Donation Types (doughnut) --
        var typesEl = document.getElementById('gwdp-chart-types');
        if (typesEl && c.types.length) {
            var typeLabels = { single: 'One-Time', subscription: 'Recurring (Initial)', renewal: 'Recurring (Renewal)' };
            var typeColors = { single: '#0073aa', subscription: '#00a32a', renewal: '#72aee6' };
            var typesChart = new Chart(typesEl, {
                type: 'doughnut',
                data: {
                    labels: c.types.map(function(t) { return typeLabels[t.donation_type] || t.donation_type; }),
                    datasets: [{
                        data: c.types.map(function(t) { return parseInt(t.cnt); }),
                        backgroundColor: c.types.map(function(t) { return typeColors[t.donation_type] || '#999'; }),
                        borderWidth: 2,
                        borderColor: '#fff',
                        hoverOffset: 6
                    }]
                },
                options: {
                    cutout: '60%',
                    onClick: function(e, elements) {
                        if (!elements.length) return;
                        // Could link to filtered log by type in future
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(ctx) {
                                    var amt = parseFloat(c.types[ctx.dataIndex].total_amt);
                                    return ctx.label + ': ' + ctx.raw + ' gifts ($' + amt.toLocaleString() + ')';
                                }
                            }
                        }
                    }
                }
            });
        }

        // -- Donor Matching (doughnut) --
        var donorsEl = document.getElementById('gwdp-chart-donors');
        if (donorsEl) {
            new Chart(donorsEl, {
                type: 'doughnut',
                data: {
                    labels: ['Matched Existing', 'Created New'],
                    datasets: [{
                        data: [c.donors.matched, c.donors.created],
                        backgroundColor: ['#0073aa', '#00a32a'],
                        borderWidth: 2,
                        borderColor: '#fff',
                        hoverOffset: 6
                    }]
                },
                options: {
                    cutout: '60%',
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(ctx) {
                                    var total = ctx.dataset.data.reduce(function(a, b) { return a + b; }, 0);
                                    var pct = total > 0 ? Math.round(ctx.raw / total * 100) : 0;
                                    return ctx.label + ': ' + ctx.raw + ' (' + pct + '%)';
                                }
                            }
                        }
                    }
                }
            });
        }

        // -- Amount Distribution (bar) --
        var amountsEl = document.getElementById('gwdp-chart-amounts');
        if (amountsEl && c.amount_buckets.length) {
            new Chart(amountsEl, {
                type: 'bar',
                data: {
                    labels: c.amount_buckets.map(function(b) { return b.bucket; }),
                    datasets: [{
                        label: 'Number of Gifts',
                        data: c.amount_buckets.map(function(b) { return parseInt(b.cnt); }),
                        backgroundColor: '#0073aa',
                        borderRadius: 4,
                        barPercentage: 0.7
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(ctx) {
                                    var amt = parseFloat(c.amount_buckets[ctx.dataIndex].total_amt);
                                    return ctx.raw + ' gifts totaling $' + amt.toLocaleString();
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { precision: 0 },
                            grid: { color: '#f0f0f0' }
                        },
                        x: {
                            grid: { display: false }
                        }
                    }
                }
            });
        }

        // -- Sync Timeline (bar) --
        var timelineEl = document.getElementById('gwdp-chart-timeline');
        if (timelineEl && c.timeline.length) {
            new Chart(timelineEl, {
                type: 'bar',
                data: {
                    labels: c.timeline.map(function(t) {
                        var d = new Date(t.day + 'T00:00:00');
                        return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                    }),
                    datasets: [{
                        label: 'Donations Synced',
                        data: c.timeline.map(function(t) { return parseInt(t.cnt); }),
                        backgroundColor: '#46b450',
                        borderRadius: 4,
                        barPercentage: 0.7
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(ctx) {
                                    var amt = parseFloat(c.timeline[ctx.dataIndex].total_amt);
                                    return ctx.raw + ' synced ($' + amt.toLocaleString() + ')';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { precision: 0 },
                            grid: { color: '#f0f0f0' }
                        },
                        x: {
                            grid: { display: false }
                        }
                    }
                }
            });
        }
    }

    // ─── API Key toggle ───
    $('#gwdp-toggle-key').on('click', function() {
        var $input = $('input[name="gwdp_api_key"]');
        if ($input.attr('type') === 'password') {
            $input.attr('type', 'text');
            $(this).text('Hide');
        } else {
            $input.attr('type', 'password');
            $(this).text('Show');
        }
    });

    // ─── Test API Connection ───
    $('#gwdp-test-api').on('click', function() {
        var $btn = $(this);
        var $result = $('#gwdp-api-test-result');
        $btn.prop('disabled', true);
        $result.html('<p style="color:#666">Testing API connection...</p>');

        $.post(gwdp.ajax_url, {
            action: 'gwdp_test_connection',
            nonce: gwdp.nonce
        }).done(function(response) {
            if (response.success) {
                $result.html('<p style="color:#46b450"><strong>&#10003; Connected!</strong> ' + response.data.message + '</p>');
            } else {
                $result.html('<p style="color:#dc3232"><strong>&#10007; Failed:</strong> ' + (response.data || 'Unknown error') + '</p>');
            }
        }).fail(function() {
            $result.html('<p style="color:#dc3232"><strong>&#10007;</strong> Request failed — check your server connection.</p>');
        }).always(function() {
            $btn.prop('disabled', false);
        });
    });

    // ─── Validate Codes ───
    $('#gwdp-test-codes').on('click', function() {
        var $btn = $(this);
        var $result = $('#gwdp-api-test-result');
        $btn.prop('disabled', true);
        $result.html('<p style="color:#666">Validating codes in DonorPerfect...</p>');

        $.post(gwdp.ajax_url, {
            action: 'gwdp_test_codes',
            nonce: gwdp.nonce
        }).done(function(response) {
            if (response.success) {
                var data = response.data;
                var html = '<table class="widefat gwdp-code-results"><thead><tr><th>Code Type</th><th>Value</th><th>Status</th></tr></thead><tbody>';

                if (data.gl_code) {
                    html += codeRow('GL Code', data.gl_code.code, data.gl_code.valid);
                }
                if (data.campaign) {
                    html += codeRow('Campaign', data.campaign.code, data.campaign.valid);
                }
                html += codeRow('Sub-Solicit: ONETIME', data.onetime.code, data.onetime.valid);
                html += codeRow('Sub-Solicit: RECURRING', data.recurring.code, data.recurring.valid);

                html += '</tbody></table>';
                $result.html(html);
            } else {
                $result.html('<p style="color:#dc3232"><strong>&#10007;</strong> ' + (response.data || 'Unknown error') + '</p>');
            }
        }).fail(function() {
            $result.html('<p style="color:#dc3232"><strong>&#10007;</strong> Request failed.</p>');
        }).always(function() {
            $btn.prop('disabled', false);
        });
    });

    function codeRow(label, code, valid) {
        var icon = valid ? '<span style="color:#46b450">&#10003; Found</span>' : '<span style="color:#dc3232">&#10007; Not found</span>';
        return '<tr><td>' + label + '</td><td><code>' + code + '</code></td><td>' + icon + '</td></tr>';
    }

    // ─── Gateway mapping ───
    $('#gwdp-add-gateway').on('click', function() {
        var row = '<tr>' +
            '<td><input type="text" name="gwdp_gw_keys[]" value="" class="regular-text" placeholder="gateway_id"></td>' +
            '<td><input type="text" name="gwdp_gw_values[]" value="" class="small-text" placeholder="CC"></td>' +
            '<td><button type="button" class="button gwdp-remove-row">Remove</button></td>' +
            '</tr>';
        $('#gwdp-gateway-table tbody').append(row);
    });

    $(document).on('click', '.gwdp-remove-row', function() {
        $(this).closest('tr').remove();
    });

    // ─── Backfill Preview ───
    $('#gwdp-backfill-preview').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).text('Running preview...');
        $('#gwdp-backfill-results').html('<p>Loading preview...</p>');

        $.post(gwdp.ajax_url, {
            action: 'gwdp_backfill_preview',
            nonce: gwdp.nonce,
            batch_size: 50,
            offset: 0
        }).done(function(response) {
            if (response.success) {
                renderBackfillResults(response.data, true);
                $('#gwdp-backfill-run').prop('disabled', false);
            } else {
                $('#gwdp-backfill-results').html('<p style="color:#dc3232">Error: ' + (response.data || 'Unknown') + '</p>');
            }
        }).fail(function() {
            $('#gwdp-backfill-results').html('<p style="color:#dc3232">Request failed.</p>');
        }).always(function() {
            $btn.prop('disabled', false).text('Run Preview (50 donations)');
        });
    });

    // ─── Backfill Run ───
    var backfillRunning = false;
    var backfillOffset = 0;
    var backfillTotal = 0;
    var backfillProcessed = 0;
    var backfillRetries = 0;
    var backfillXHR = null;
    var backfillTimer = null;

    $('#gwdp-backfill-run').on('click', function() {
        if (!confirm('This will send donations to DonorPerfect. Continue?')) return;

        backfillRunning = true;
        backfillOffset = 0;
        backfillProcessed = 0;
        backfillRetries = 0;
        $(this).prop('disabled', true);
        $('#gwdp-backfill-stop').show();
        $('#gwdp-backfill-progress').show();
        $('#gwdp-backfill-results').html('');

        runBackfillBatch();
    });

    $('#gwdp-backfill-stop').on('click', function() {
        backfillRunning = false;
        // Abort any in-flight request
        if (backfillXHR) { backfillXHR.abort(); backfillXHR = null; }
        // Cancel any pending retry timer
        if (backfillTimer) { clearTimeout(backfillTimer); backfillTimer = null; }
        $(this).hide();
        $('#gwdp-backfill-run').prop('disabled', false);
        $('.gwdp-progress-text').text('Stopped. Processed ' + backfillProcessed + ' donations.');
    });

    function runBackfillBatch() {
        if (!backfillRunning) return;

        backfillXHR = $.ajax({
            url: gwdp.ajax_url,
            type: 'POST',
            timeout: 120000, // 2 minute timeout per batch
            data: {
                action: 'gwdp_backfill_run',
                nonce: gwdp.nonce,
                batch_size: 5,
                offset: 0 // Always 0 — query skips already-synced donations
            }
        }).done(function(response) {
            backfillRetries = 0; // Reset retries on success

            if (!response.success) {
                backfillRunning = false;
                $('.gwdp-progress-text').text('Error: ' + (response.data || 'Unknown'));
                $('#gwdp-backfill-stop').hide();
                $('#gwdp-backfill-run').prop('disabled', false);
                return;
            }

            var data = response.data;
            backfillTotal = data.total_unsynced + backfillProcessed;
            backfillProcessed += data.processed;

            var pct = backfillTotal > 0 ? Math.round((backfillProcessed / backfillTotal) * 100) : 100;
            $('.gwdp-progress-fill').css('width', pct + '%');
            $('.gwdp-progress-text').text('Processed ' + backfillProcessed + ' of ~' + backfillTotal + ' (' + pct + '%)');

            renderBackfillResults(data, false);

            if (data.has_more && backfillRunning) {
                backfillTimer = setTimeout(runBackfillBatch, 500);
            } else {
                backfillRunning = false;
                $('#gwdp-backfill-stop').hide();
                $('#gwdp-backfill-run').prop('disabled', false);
                if (!data.has_more) {
                    $('.gwdp-progress-text').text('Complete! Processed ' + backfillProcessed + ' donations.');
                }
            }
        }).fail(function(jqXHR, textStatus) {
            backfillRetries++;
            if (backfillRunning) {
                // Auto-resume after a pause — server may have timed out but some donations still synced
                var delay = Math.min(backfillRetries * 2000, 10000); // Back off: 2s, 4s, 6s... max 10s
                $('.gwdp-progress-text').text('Request timed out, auto-resuming in ' + (delay/1000) + 's... (retry ' + backfillRetries + ', processed ' + backfillProcessed + ' so far)');
                backfillTimer = setTimeout(runBackfillBatch, delay);
            } else {
                $('.gwdp-progress-text').text('Stopped. Processed ' + backfillProcessed + ' donations.');
                $('#gwdp-backfill-stop').hide();
                $('#gwdp-backfill-run').prop('disabled', false);
            }
        });
    }

    function renderBackfillResults(data, isPreview) {
        var html = '<p><strong>' + (isPreview ? 'Preview' : 'Batch') + ':</strong> ';
        html += data.processed + ' processed, ' + data.total_unsynced + ' remaining</p>';

        if (data.items && data.items.length) {
            html += '<table class="widefat striped"><thead><tr>';
            html += '<th>Give #</th><th>Type</th><th>Amount</th>';
            if (isPreview) {
                html += '<th>Name</th><th>Email</th><th>Donor</th><th>Pledge</th><th>Status</th>';
            } else {
                html += '<th>Donor</th><th>DP Donor</th><th>DP Gift</th><th>DP Pledge</th><th>Status</th><th>Error</th>';
            }
            html += '</tr></thead><tbody>';

            data.items.forEach(function(item) {
                var statusClass = 'gwdp-badge-' + (item.status || 'preview');
                html += '<tr>';
                html += '<td>' + (item.donation_id || item.give_donation_id || '—') + '</td>';
                html += '<td>' + (item.type || item.donation_type || '—') + '</td>';
                html += '<td>$' + parseFloat(item.amount || item.donation_amount || 0).toFixed(2) + '</td>';

                if (isPreview) {
                    html += '<td>' + (item.name || '—') + '</td>';
                    html += '<td>' + (item.email || '—') + '</td>';
                    html += '<td>' + (item.donor_action || '—') + (item.dp_donor_id ? ' (#' + item.dp_donor_id + ')' : '') + '</td>';
                    html += '<td>' + (item.pledge_action || 'none') + '</td>';
                    html += '<td><span class="gwdp-badge ' + statusClass + '">' + (item.status || '') + '</span></td>';
                } else {
                    html += '<td>' + (item.donor_action || '—') + '</td>';
                    html += '<td>' + (item.dp_donor_id ? '#' + item.dp_donor_id : '—') + '</td>';
                    html += '<td>' + (item.dp_gift_id ? '#' + item.dp_gift_id : '—') + '</td>';
                    html += '<td>' + (item.dp_pledge_id ? '#' + item.dp_pledge_id : '—') + '</td>';
                    html += '<td><span class="gwdp-badge ' + statusClass + '">' + (item.status || '') + '</span></td>';
                    html += '<td style="color:#dc3232">' + (item.error || '') + '</td>';
                }
                html += '</tr>';
            });
            html += '</tbody></table>';
        }

        var $results = $('#gwdp-backfill-results');
        if (isPreview) {
            $results.html(html);
        } else {
            $results.append(html);
        }
    }

    // ─── Sync Single ───
    $('#gwdp-sync-single').on('click', function() {
        var id = parseInt($('#gwdp-single-id').val());
        if (!id || id <= 0) {
            $('#gwdp-single-result').text('Enter a valid donation ID').css('color', '#dc3232');
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true);
        $('#gwdp-single-result').text('Syncing...').css('color', '#666');

        $.post(gwdp.ajax_url, {
            action: 'gwdp_sync_single',
            nonce: gwdp.nonce,
            donation_id: id
        }).done(function(response) {
            if (response.success) {
                var d = response.data;
                if (d.status === 'success') {
                    $('#gwdp-single-result').text('Synced! DP Donor #' + d.dp_donor_id + ', Gift #' + d.dp_gift_id + (d.dp_pledge_id ? ', Pledge #' + d.dp_pledge_id : '')).css('color', '#46b450');
                } else {
                    $('#gwdp-single-result').text(d.status + ': ' + (d.error || '')).css('color', '#dc3232');
                }
            } else {
                $('#gwdp-single-result').text('Error: ' + (response.data || 'Unknown')).css('color', '#dc3232');
            }
        }).fail(function() {
            $('#gwdp-single-result').text('Request failed.').css('color', '#dc3232');
        }).always(function() {
            $btn.prop('disabled', false);
        });
    });

    // ─── Export Log ───
    $('#gwdp-export-log').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).text('Exporting...');

        $.post(gwdp.ajax_url, {
            action: 'gwdp_export_log',
            nonce: gwdp.nonce
        }).done(function(response) {
            if (response.success) {
                var blob = new Blob([response.data.csv], {type: 'text/csv'});
                var url = URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = 'givewp2dp-sync-log-' + new Date().toISOString().slice(0,10) + '.csv';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
            } else {
                alert('Export failed: ' + (response.data || 'Unknown error'));
            }
        }).fail(function() {
            alert('Export request failed.');
        }).always(function() {
            $btn.prop('disabled', false).text('Export CSV');
        });
    });

    // ─── Clear Log ───
    $('#gwdp-clear-log').on('click', function() {
        if (!confirm('Are you sure you want to clear the entire sync log? This cannot be undone.')) return;

        var $btn = $(this);
        $btn.prop('disabled', true);

        $.post(gwdp.ajax_url, {
            action: 'gwdp_clear_log',
            nonce: gwdp.nonce
        }).done(function(response) {
            if (response.success) {
                alert('Cleared ' + response.data.cleared + ' log entries.');
                location.reload();
            } else {
                alert('Failed: ' + (response.data || 'Unknown error'));
            }
        }).fail(function() {
            alert('Request failed.');
        }).always(function() {
            $btn.prop('disabled', false);
        });
    });

    // ─── Match Report ───
    $('#gwdp-match-report').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true);
        $('#gwdp-match-loading').show();
        $('#gwdp-match-results').hide();

        $.post(gwdp.ajax_url, {
            action: 'gwdp_match_report',
            nonce: gwdp.nonce
        }).done(function(response) {
            if (response.success) {
                var data = response.data;
                $('#gwdp-match-total').text(data.total);
                $('#gwdp-match-found').text(data.matched);
                $('#gwdp-match-new').text(data.new);

                var $tbody = $('#gwdp-match-table tbody');
                $tbody.empty();

                data.donors.forEach(function(d) {
                    var actionClass = d.dp_donor_id ? 'color:#46b450' : 'color:#0073aa';
                    $tbody.append(
                        '<tr>' +
                        '<td>' + d.give_donor_id + '</td>' +
                        '<td>' + d.name + '</td>' +
                        '<td>' + d.email + '</td>' +
                        '<td>' + (d.dp_donor_id ? '#' + d.dp_donor_id : '—') + '</td>' +
                        '<td style="' + actionClass + '">' + d.action + '</td>' +
                        '</tr>'
                    );
                });

                $('#gwdp-match-results').show();
            } else {
                alert('Error: ' + (response.data || 'Unknown'));
            }
        }).fail(function() {
            alert('Request failed.');
        }).always(function() {
            $btn.prop('disabled', false);
            $('#gwdp-match-loading').hide();
        });
    });

})(jQuery);
