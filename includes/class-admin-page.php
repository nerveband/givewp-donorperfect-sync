<?php
/**
 * Admin settings page for GiveWP → DonorPerfect Sync.
 * Provides: settings, sync log, backfill tool, match report.
 */

if (!defined('ABSPATH')) exit;

class GWDP_Admin_Page {

    private static ?self $instance = null;

    public static function instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function add_menu(): void {
        // Place right after GiveWP's menu (priority 25.1 → appears just below it)
        add_menu_page(
            'GiveWP2DP',
            'GiveWP2DP',
            'manage_options',
            'gwdp-sync',
            [$this, 'render_page'],
            'dashicons-update',
            25.1
        );
    }

    public function register_settings(): void {
        register_setting('gwdp_settings', 'gwdp_sync_enabled');
        register_setting('gwdp_settings', 'gwdp_api_key', [
            'sanitize_callback' => function ($val) {
                return trim(wp_unslash($val ?? ''));
            },
        ]);
        register_setting('gwdp_settings', 'gwdp_default_gl_code');
        register_setting('gwdp_settings', 'gwdp_default_campaign');
        register_setting('gwdp_settings', 'gwdp_default_solicit_code');
        register_setting('gwdp_settings', 'gwdp_gateway_map');
    }

    public function enqueue_assets(string $hook): void {
        if ($hook !== 'toplevel_page_gwdp-sync') return;
        wp_enqueue_style('gwdp-admin', GWDP_PLUGIN_URL . 'assets/admin.css', [], GWDP_VERSION);
        wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js', [], '4.4.7', true);
        wp_enqueue_script('gwdp-admin', GWDP_PLUGIN_URL . 'assets/admin.js', ['jquery', 'chartjs'], GWDP_VERSION, true);

        $sync = GWDP_Donation_Sync::instance();
        $localize = [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('gwdp_admin_nonce'),
        ];

        $tab = sanitize_text_field($_GET['tab'] ?? 'dashboard');
        if ($tab === 'dashboard') {
            $stats = $sync->get_stats();
            $chart_data = $sync->get_chart_data();
            $localize['charts'] = [
                'status' => [
                    'success' => $stats['success'],
                    'error'   => $stats['error'],
                    'skipped' => $stats['skipped'],
                ],
                'donors' => [
                    'created' => $stats['donors_created'],
                    'matched' => $stats['donors_matched'],
                ],
                'types'          => $chart_data['types'],
                'amount_buckets' => $chart_data['amount_buckets'],
                'timeline'       => $chart_data['timeline'],
            ];
            $localize['log_url'] = admin_url('admin.php?page=gwdp-sync&tab=log');
        }

        wp_localize_script('gwdp-admin', 'gwdp', $localize);
    }

    public function render_page(): void {
        $sync = GWDP_Donation_Sync::instance();
        $stats = $sync->get_stats();
        $tab = sanitize_text_field($_GET['tab'] ?? 'dashboard');
        ?>
        <div class="wrap gwdp-wrap">
            <h1>GiveWP2DP</h1>

            <nav class="nav-tab-wrapper">
                <a href="?page=gwdp-sync&tab=dashboard" class="nav-tab <?php echo $tab === 'dashboard' ? 'nav-tab-active' : ''; ?>">Dashboard</a>
                <a href="?page=gwdp-sync&tab=settings" class="nav-tab <?php echo $tab === 'settings' ? 'nav-tab-active' : ''; ?>">Settings</a>
                <a href="?page=gwdp-sync&tab=log" class="nav-tab <?php echo $tab === 'log' ? 'nav-tab-active' : ''; ?>">Sync Log</a>
                <a href="?page=gwdp-sync&tab=backfill" class="nav-tab <?php echo $tab === 'backfill' ? 'nav-tab-active' : ''; ?>">Backfill</a>
                <a href="?page=gwdp-sync&tab=match" class="nav-tab <?php echo $tab === 'match' ? 'nav-tab-active' : ''; ?>">Match Report</a>
                <a href="?page=gwdp-sync&tab=docs" class="nav-tab <?php echo $tab === 'docs' ? 'nav-tab-active' : ''; ?>">Documentation</a>
                <a href="?page=gwdp-sync&tab=changelog" class="nav-tab <?php echo $tab === 'changelog' ? 'nav-tab-active' : ''; ?>">Changelog</a>
            </nav>

            <div class="gwdp-tab-content">
                <?php
                match ($tab) {
                    'settings'  => $this->render_settings(),
                    'log'       => $this->render_log($sync),
                    'backfill'  => $this->render_backfill($sync),
                    'match'     => $this->render_match(),
                    'docs'      => $this->render_docs(),
                    'changelog' => $this->render_changelog(),
                    default     => $this->render_dashboard($stats),
                };
                ?>
            </div>
        </div>
        <?php
    }

    // ─── Dashboard ───

    private function render_dashboard(array $stats): void {
        $enabled = get_option('gwdp_sync_enabled', '0') === '1';
        ?>
        <div class="gwdp-status-banner <?php echo $enabled ? 'gwdp-status-on' : 'gwdp-status-off'; ?>">
            <strong>Real-time sync is <?php echo $enabled ? 'ON' : 'OFF'; ?></strong>
            <?php if ($enabled): ?>
                <span>— New donations are automatically being sent to DonorPerfect.</span>
            <?php else: ?>
                <span>— New donations are NOT being synced. Your donor data is safe. <a href="?page=gwdp-sync&tab=settings">Enable in Settings</a></span>
            <?php endif; ?>
        </div>

        <div class="gwdp-stats-grid">
            <div class="gwdp-stat-card" data-tip="Donations successfully sent to DonorPerfect. Each synced donation has a matching gift record in DP.">
                <div class="gwdp-stat-number"><?php echo esc_html($stats['success']); ?></div>
                <div class="gwdp-stat-label">Synced</div>
            </div>
            <div class="gwdp-stat-card gwdp-stat-error" data-tip="Donations that failed to sync. Check the Sync Log tab for details. Common causes: API connection issues, missing codes in DP, or invalid data.">
                <div class="gwdp-stat-number"><?php echo esc_html($stats['error']); ?></div>
                <div class="gwdp-stat-label">Errors</div>
            </div>
            <div class="gwdp-stat-card" data-tip="Donations that were already synced or had no valid data to send. These are safe to ignore.">
                <div class="gwdp-stat-number"><?php echo esc_html($stats['skipped']); ?></div>
                <div class="gwdp-stat-label">Skipped</div>
            </div>
            <div class="gwdp-stat-card" data-tip="New donor records created in DonorPerfect because no existing donor with the same email was found.">
                <div class="gwdp-stat-number"><?php echo esc_html($stats['donors_created']); ?></div>
                <div class="gwdp-stat-label">Donors Created</div>
            </div>
            <div class="gwdp-stat-card" data-tip="Donations linked to existing DonorPerfect donors by matching email address. No duplicate donor was created.">
                <div class="gwdp-stat-number"><?php echo esc_html($stats['donors_matched']); ?></div>
                <div class="gwdp-stat-label">Donors Matched</div>
            </div>
            <div class="gwdp-stat-card" data-tip="DonorPerfect &quot;pledges&quot; created to group recurring payments together. Each GiveWP subscription gets one, and all its payments are linked under it. This is just an organizational grouping -- not a fundraising goal.">
                <div class="gwdp-stat-number"><?php echo esc_html($stats['pledges_created']); ?></div>
                <div class="gwdp-stat-label">Recurring Groups</div>
            </div>
            <div class="gwdp-stat-card" data-tip="Individual recurring donation payments synced to DP. Each monthly payment from a GiveWP subscription becomes one gift in DonorPerfect.">
                <div class="gwdp-stat-number"><?php echo esc_html($stats['recurring_gifts']); ?></div>
                <div class="gwdp-stat-label">Recurring Gifts</div>
            </div>
            <div class="gwdp-stat-card" data-tip="Single, non-recurring donations synced to DonorPerfect.">
                <div class="gwdp-stat-number"><?php echo esc_html($stats['onetime_gifts']); ?></div>
                <div class="gwdp-stat-label">One-Time Gifts</div>
            </div>
        </div>

        <?php if ($stats['last_sync']): ?>
            <p class="gwdp-last-sync">Last successful sync: <strong><?php echo esc_html($stats['last_sync']); ?></strong></p>
        <?php endif; ?>

        <div class="gwdp-charts-row">
            <div class="gwdp-chart-card">
                <h3>Sync Status</h3>
                <div class="gwdp-chart-wrap gwdp-chart-doughnut"><canvas id="gwdp-chart-status"></canvas></div>
                <p class="gwdp-chart-hint">Click a segment to view in Sync Log</p>
            </div>
            <div class="gwdp-chart-card">
                <h3>Donation Types</h3>
                <div class="gwdp-chart-wrap gwdp-chart-doughnut"><canvas id="gwdp-chart-types"></canvas></div>
                <p class="gwdp-chart-hint">Click a segment to filter by type</p>
            </div>
            <div class="gwdp-chart-card">
                <h3>Donor Matching</h3>
                <div class="gwdp-chart-wrap gwdp-chart-doughnut"><canvas id="gwdp-chart-donors"></canvas></div>
            </div>
        </div>

        <div class="gwdp-charts-row">
            <div class="gwdp-chart-card gwdp-chart-wide">
                <h3>Gift Amounts</h3>
                <div class="gwdp-chart-wrap gwdp-chart-bar"><canvas id="gwdp-chart-amounts"></canvas></div>
            </div>
            <div class="gwdp-chart-card gwdp-chart-wide">
                <h3>Sync Timeline</h3>
                <div class="gwdp-chart-wrap gwdp-chart-bar"><canvas id="gwdp-chart-timeline"></canvas></div>
            </div>
        </div>

        <h3>Recent Activity</h3>
        <?php $this->render_log_table(GWDP_Donation_Sync::instance()->get_log(10)); ?>
        <?php
    }

    // ─── Settings ───

    private function render_settings(): void {
        if (isset($_POST['gwdp_settings_nonce']) && wp_verify_nonce($_POST['gwdp_settings_nonce'], 'gwdp_save_settings')) {
            update_option('gwdp_sync_enabled', isset($_POST['gwdp_sync_enabled']) ? '1' : '0');
            // Do NOT use sanitize_text_field() — it strips percent-encoded chars (%2f, %2b)
            // that are part of valid DonorPerfect API keys (base64-encoded with URL encoding).
            update_option('gwdp_api_key', trim(wp_unslash($_POST['gwdp_api_key'] ?? '')));
            update_option('gwdp_default_gl_code', sanitize_text_field($_POST['gwdp_default_gl_code'] ?? 'UN'));
            update_option('gwdp_default_campaign', sanitize_text_field($_POST['gwdp_default_campaign'] ?? ''));
            update_option('gwdp_default_solicit_code', sanitize_text_field($_POST['gwdp_default_solicit_code'] ?? ''));

            $gw_map = [];
            if (!empty($_POST['gwdp_gw_keys']) && !empty($_POST['gwdp_gw_values'])) {
                $keys = array_map('sanitize_text_field', $_POST['gwdp_gw_keys']);
                $vals = array_map('sanitize_text_field', $_POST['gwdp_gw_values']);
                foreach ($keys as $i => $key) {
                    if ($key !== '' && isset($vals[$i])) {
                        $gw_map[$key] = $vals[$i];
                    }
                }
            }
            update_option('gwdp_gateway_map', wp_json_encode($gw_map));

            echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
        }

        $enabled  = get_option('gwdp_sync_enabled', '0');
        $api_key  = get_option('gwdp_api_key', '');
        $gl_code  = get_option('gwdp_default_gl_code', 'UN');
        $campaign = get_option('gwdp_default_campaign', '');
        $solicit  = get_option('gwdp_default_solicit_code', '');
        $gw_map   = json_decode(get_option('gwdp_gateway_map', '{}'), true) ?: [];
        ?>
        <form method="post">
            <?php wp_nonce_field('gwdp_save_settings', 'gwdp_settings_nonce'); ?>

            <h2>Sync Control</h2>
            <div class="gwdp-help-text"><strong>Safe by default:</strong> Real-time sync is OFF when you first install. No donor data will be sent to DonorPerfect until you explicitly turn it on. Use Preview and Match Report to verify everything first.</div>
            <table class="form-table">
                <tr>
                    <th>Real-Time Sync<?php echo $this->info('Controls whether new donations are automatically sent to DonorPerfect as they come in. When OFF, donations are only recorded in GiveWP. You can always sync past donations later using the Backfill tab.'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="gwdp_sync_enabled" value="1" <?php checked($enabled, '1'); ?>>
                            Enable automatic sync of new donations to DonorPerfect
                        </label>
                        <p class="description">When OFF, new donations will NOT be sent to DonorPerfect. Use the Backfill tab to sync historical data.</p>
                    </td>
                </tr>
            </table>

            <h2>DonorPerfect API</h2>
            <table class="form-table">
                <tr>
                    <th>API Key<?php echo $this->info('Your DonorPerfect XML API key. Find it in DonorPerfect under Admin > My Settings > API Keys. This key is stored securely in your WordPress database and is never shared externally.'); ?></th>
                    <td>
                        <input type="password" name="gwdp_api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text" autocomplete="off">
                        <button type="button" class="button" id="gwdp-toggle-key">Show</button>
                    </td>
                </tr>
                <tr>
                    <th>Connection Tests<?php echo $this->info('Test your connection before syncing any data. "Test API Connection" checks that your API key works. "Validate Codes" checks that required codes (GL, campaign, ONETIME, RECURRING) exist in DonorPerfect.'); ?></th>
                    <td>
                        <button type="button" class="button" id="gwdp-test-api">Test API Connection</button>
                        <button type="button" class="button" id="gwdp-test-codes">Validate Codes</button>
                        <div id="gwdp-api-test-result" class="gwdp-test-results"></div>
                    </td>
                </tr>
            </table>

            <h2>Default Field Mapping</h2>
            <div class="gwdp-help-text">These codes tell DonorPerfect how to categorize each gift. They must already exist in your DonorPerfect system (DPCODES table). Use the <strong>Validate Codes</strong> button above to check.</div>
            <table class="form-table">
                <tr>
                    <th>GL Code<?php echo $this->info('The General Ledger code determines which accounting fund receives the donation in DonorPerfect. Common examples: "UN" for Unrestricted, "GF" for General Fund. Ask your accountant or DP admin if unsure.'); ?></th>
                    <td>
                        <input type="text" name="gwdp_default_gl_code" value="<?php echo esc_attr($gl_code); ?>" class="small-text" placeholder="UN">
                        <p class="description">DonorPerfect GL code for donations (e.g. UN for Unrestricted). Must exist in DPCODES.</p>
                    </td>
                </tr>
                <tr>
                    <th>Campaign<?php echo $this->info('Links donations to a specific campaign or fund drive in DonorPerfect. Leave blank if you do not use campaigns. If set, this code must already exist in DonorPerfect.'); ?></th>
                    <td>
                        <input type="text" name="gwdp_default_campaign" value="<?php echo esc_attr($campaign); ?>" class="regular-text" placeholder="Leave blank for none">
                        <p class="description">DonorPerfect campaign code. Must exist in DPCODES. Leave blank for none.</p>
                    </td>
                </tr>
                <tr>
                    <th>Solicit Code<?php echo $this->info('An optional high-level solicit code for all donations. The sub-solicit code is set automatically: ONETIME for single gifts, RECURRING for subscription payments. Leave blank if not needed.'); ?></th>
                    <td>
                        <input type="text" name="gwdp_default_solicit_code" value="<?php echo esc_attr($solicit); ?>" class="regular-text" placeholder="Leave blank for none">
                        <p class="description">Sub-solicit code is set automatically: ONETIME or RECURRING.</p>
                    </td>
                </tr>
            </table>

            <h2>Gateway &rarr; Gift Type Mapping<?php echo $this->info('Maps how donors paid (Stripe, PayPal, etc.) to DonorPerfect gift type codes. This helps your team see payment methods in DP reports. Common mappings: stripe=CC, paypal=PAYPAL, manual=CK (check).'); ?></h2>
            <p class="description">Map GiveWP payment gateways to DonorPerfect gift type codes (CC, PAYPAL, CK, ACH, etc.)</p>
            <table class="widefat gwdp-gateway-map" id="gwdp-gateway-table">
                <thead>
                    <tr><th>GiveWP Gateway ID</th><th>DP Gift Type</th><th></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($gw_map as $key => $val): ?>
                    <tr>
                        <td><input type="text" name="gwdp_gw_keys[]" value="<?php echo esc_attr($key); ?>" class="regular-text"></td>
                        <td><input type="text" name="gwdp_gw_values[]" value="<?php echo esc_attr($val); ?>" class="small-text"></td>
                        <td><button type="button" class="button gwdp-remove-row">Remove</button></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <button type="button" class="button" id="gwdp-add-gateway">+ Add Gateway</button>

            <?php submit_button('Save Settings'); ?>
        </form>
        <?php
    }

    // ─── Sync Log ───

    private function render_log(GWDP_Donation_Sync $sync): void {
        $filter = sanitize_text_field($_GET['status'] ?? '');
        $page_num = max(1, (int) ($_GET['paged'] ?? 1));
        $per_page = 50;
        $offset = ($page_num - 1) * $per_page;
        $rows = $sync->get_log($per_page, $offset, $filter);
        ?>
        <h2>Sync Log<?php echo $this->info('A record of every donation sync attempt. Shows whether each donation was successfully sent to DonorPerfect, along with the DP donor and gift IDs assigned. Use the filters to find specific entries.'); ?></h2>
        <div class="gwdp-log-actions">
            <div class="gwdp-log-filters">
                <a href="?page=gwdp-sync&tab=log" class="button <?php echo $filter === '' ? 'button-primary' : ''; ?>">All</a>
                <a href="?page=gwdp-sync&tab=log&status=success" class="button <?php echo $filter === 'success' ? 'button-primary' : ''; ?>">Success</a>
                <a href="?page=gwdp-sync&tab=log&status=error" class="button <?php echo $filter === 'error' ? 'button-primary' : ''; ?>">Errors</a>
                <a href="?page=gwdp-sync&tab=log&status=skipped" class="button <?php echo $filter === 'skipped' ? 'button-primary' : ''; ?>">Skipped</a>
            </div>
            <div class="gwdp-log-tools">
                <button type="button" class="button" id="gwdp-export-log">Export CSV</button>
                <button type="button" class="button gwdp-btn-danger" id="gwdp-clear-log">Clear Log</button>
            </div>
        </div>
        <?php $this->render_log_table($rows); ?>

        <div class="gwdp-pagination">
            <?php if ($page_num > 1): ?>
                <a href="?page=gwdp-sync&tab=log&status=<?php echo esc_attr($filter); ?>&paged=<?php echo $page_num - 1; ?>" class="button">&laquo; Previous</a>
            <?php endif; ?>
            <?php if (count($rows) === $per_page): ?>
                <a href="?page=gwdp-sync&tab=log&status=<?php echo esc_attr($filter); ?>&paged=<?php echo $page_num + 1; ?>" class="button">Next &raquo;</a>
            <?php endif; ?>
        </div>
        <?php
    }

    // ─── Backfill ───

    private function render_backfill(GWDP_Donation_Sync $sync): void {
        $total_give = $sync->get_give_donation_count();
        $stats = $sync->get_stats();
        ?>
        <h2>Historical Backfill<?php echo $this->info('Use this tool to sync donations that were made before the plugin was installed, or while real-time sync was turned off. It processes past donations in small batches to avoid overloading your server or the DP API.'); ?></h2>
        <div class="gwdp-help-text"><strong>No data is changed in GiveWP.</strong> Backfill only creates records in DonorPerfect. Your GiveWP donation data is never modified. Donations that were already synced are automatically skipped.</div>

        <?php
        global $wpdb;
        $syncable = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}posts
             WHERE post_type = 'give_payment'
             AND post_status IN ('publish', 'give_subscription')"
        );
        $remaining = max(0, $syncable - $stats['success']);

        // Get breakdown of non-syncable statuses
        $excluded = $wpdb->get_results(
            "SELECT post_status, COUNT(*) as cnt FROM {$wpdb->prefix}posts
             WHERE post_type = 'give_payment'
             AND post_status NOT IN ('publish', 'give_subscription')
             GROUP BY post_status ORDER BY cnt DESC",
            ARRAY_A
        );
        $excluded_total = array_sum(array_column($excluded, 'cnt'));

        $status_labels = [
            'abandoned'         => 'Donor started checkout but didn\'t complete payment',
            'failed'            => 'Payment was attempted but declined or errored',
            'pending'           => 'Payment initiated but not yet confirmed',
            'refunded'          => 'Payment was refunded after completion',
            'cancelled'         => 'Donation was cancelled',
            'revoked'           => 'Donation access was revoked',
            'give_subscription' => 'Active recurring subscription payment',
            'preapproval'       => 'Pre-approved but not yet charged',
        ];
        ?>
        <div class="gwdp-backfill-info">
            <p><strong>Completed donations:</strong> <?php echo esc_html($syncable); ?></p>
            <p><strong>Already synced:</strong> <?php echo esc_html($stats['success']); ?></p>
            <p><strong>Remaining to sync:</strong> <?php echo $remaining; ?></p>
            <?php if ($excluded_total > 0): ?>
            <div class="gwdp-excluded-donations">
                <p><strong>Not synced (<?php echo $excluded_total; ?>):</strong> These donations are excluded because they were never completed.</p>
                <table class="widefat" style="max-width:600px;">
                    <thead><tr><th>Status</th><th>Count</th><th>Meaning</th></tr></thead>
                    <tbody>
                    <?php foreach ($excluded as $row):
                        $status = $row['post_status'];
                        $label = $status_labels[$status] ?? 'Unknown status';
                        $give_url = admin_url('edit.php?post_type=give_payment&post_status=' . urlencode($status));
                    ?>
                        <tr>
                            <td><a href="<?php echo esc_url($give_url); ?>"><?php echo esc_html($status); ?></a></td>
                            <td><a href="<?php echo esc_url($give_url); ?>"><?php echo esc_html($row['cnt']); ?></a></td>
                            <td><?php echo esc_html($label); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <?php
        // Get the original php.ini value, not the WordPress-modified runtime value
        $ini_all = ini_get_all();
        $max_exec = (int) ($ini_all['max_execution_time']['global_value'] ?? ini_get('max_execution_time'));
        $exec_class = $max_exec > 0 && $max_exec < 60 ? 'gwdp-notice-warn' : 'gwdp-notice-ok';
        ?>
        <div class="gwdp-server-notice <?php echo $exec_class; ?>">
            <strong>Server timeout:</strong> <code>max_execution_time = <?php echo $max_exec; ?>s</code>
            <?php if ($max_exec > 0 && $max_exec < 60): ?>
                <br>Your server's PHP timeout is low (<strong><?php echo $max_exec; ?>s</strong>). Each batch syncs 5 donations, and each donation requires 1&ndash;3 API calls to DonorPerfect. If a batch exceeds this timeout, the backfill will auto-retry, but increasing the limit will make it smoother.
                <details style="margin-top:6px;">
                    <summary style="cursor:pointer;color:#0073aa;">How to increase the timeout</summary>
                    <ul style="margin:8px 0 0 16px;font-size:13px;">
                        <li><strong>cPanel / Bluehost:</strong> Go to cPanel &rarr; MultiPHP INI Editor &rarr; select your domain &rarr; set <code>max_execution_time</code> to <code>120</code> or <code>300</code></li>
                        <li><strong>php.ini:</strong> Add <code>max_execution_time = 120</code> to your site's <code>php.ini</code> file</li>
                        <li><strong>.htaccess:</strong> Add <code>php_value max_execution_time 120</code> to your <code>.htaccess</code> file</li>
                        <li><strong>wp-config.php:</strong> Add <code>set_time_limit(120);</code> near the top of the file</li>
                    </ul>
                    <p style="font-size:13px;margin-top:6px;">A value of <strong>120</strong> (2 minutes) is recommended for backfill operations. You can lower it back after the backfill completes.</p>
                </details>
            <?php else: ?>
                <?php if ($max_exec === 0): ?>
                    &mdash; No timeout limit (unlimited). Backfill should run without issues.
                <?php else: ?>
                    &mdash; This should be sufficient for backfill batches.
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <div class="gwdp-backfill-controls">
            <h3>Step 1: Preview (Dry Run)<?php echo $this->info('The preview shows you exactly what would happen for each donation -- which donors would be matched or created, whether a recurring group would be set up -- without actually sending anything to DonorPerfect. Always run this first.'); ?></h3>
            <p>See what would happen without making any changes. <strong>Nothing is sent to DonorPerfect during preview.</strong></p>
            <button type="button" class="button button-secondary" id="gwdp-backfill-preview">Run Preview (50 donations)</button>

            <h3>Step 2: Sync<?php echo $this->info('This sends donations to DonorPerfect for real. Donations are processed in small batches of 5 with a short pause between each to be gentle on the API. If a batch times out, it will automatically retry. You can stop at any time -- donations already synced will remain in DP.'); ?></h3>
            <p>Send donations to DonorPerfect in batches of 5 (with auto-retry on timeout).</p>
            <button type="button" class="button button-primary" id="gwdp-backfill-run" disabled>Start Backfill</button>
            <button type="button" class="button" id="gwdp-backfill-stop" style="display:none;">Stop</button>
        </div>

        <div id="gwdp-backfill-progress" style="display:none;">
            <div class="gwdp-progress-bar">
                <div class="gwdp-progress-fill" style="width:0%"></div>
            </div>
            <p class="gwdp-progress-text">Processing...</p>
        </div>

        <h3>Results</h3>
        <div id="gwdp-backfill-results">
            <p class="description">Run a preview or backfill to see results here.</p>
        </div>

        <hr style="margin: 24px 0;">

        <h3>Sync a Single Donation<?php echo $this->info('Use this to manually sync one specific donation by its GiveWP ID number. Helpful for testing, retrying a failed donation, or syncing a donation that was missed. The donation ID is the number shown in the GiveWP Donations list.'); ?></h3>
        <p>Enter a GiveWP donation ID to sync just that one donation to DonorPerfect. Useful for retrying a failed sync or testing with a single record.</p>
        <div class="gwdp-single-sync">
            <input type="number" id="gwdp-single-id" placeholder="e.g. 1234" min="1">
            <button type="button" class="button" id="gwdp-sync-single">Sync This Donation</button>
            <span id="gwdp-single-result"></span>
        </div>
        <p class="description">Find donation IDs in <strong>GiveWP &rarr; Donations</strong> in your WordPress admin.</p>
        <?php
    }

    // ─── Match Report ───

    private function render_match(): void {
        ?>
        <h2>Donor Match Report<?php echo $this->info('This is a read-only report. It checks each GiveWP donor\'s email address against DonorPerfect to show you which donors already exist in DP and which would be created as new. No data is created or modified.'); ?></h2>
        <div class="gwdp-help-text"><strong>Read-only:</strong> This report only looks up data. It does not create, modify, or delete any records in GiveWP or DonorPerfect. Run it as many times as you like.</div>
        <p>See how GiveWP donors will match to DonorPerfect records. Donors are matched by <strong>email address</strong>.</p>

        <button type="button" class="button button-primary" id="gwdp-match-report">Generate Match Report</button>

        <div id="gwdp-match-loading" style="display:none;">
            <span class="spinner is-active" style="float:none;"></span> Checking donors... This may take a moment.
        </div>

        <div id="gwdp-match-results" style="display:none;">
            <div class="gwdp-match-summary">
                <span class="gwdp-match-total">Total: <strong id="gwdp-match-total">0</strong></span>
                <span class="gwdp-match-found">Matched: <strong id="gwdp-match-found">0</strong></span>
                <span class="gwdp-match-new">New: <strong id="gwdp-match-new">0</strong></span>
            </div>
            <table class="widefat striped" id="gwdp-match-table">
                <thead>
                    <tr>
                        <th>GiveWP ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>DP Donor ID</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
        <?php
    }

    // ─── Documentation ───

    private function render_docs(): void {
        ?>
        <div class="gwdp-docs">

        <h2>How Give2DP Works</h2>

        <div class="gwdp-docs-section">
            <h3>Overview</h3>
            <p>This plugin connects <strong>GiveWP</strong> (your WordPress donation form) to <strong>DonorPerfect</strong> (your donor management system). When someone makes a donation through GiveWP, this plugin automatically creates the corresponding donor record and gift in DonorPerfect.</p>
        </div>

        <div class="gwdp-docs-section">
            <h3>One-Time Donations</h3>
            <ol>
                <li>A donor completes a one-time donation via your GiveWP form</li>
                <li>The plugin searches DonorPerfect for an existing donor with the same email address</li>
                <li>If found, it uses the existing DP donor record. If not, it creates a new one.</li>
                <li>A gift is created in DonorPerfect with <code>sub_solicit_code = ONETIME</code></li>
                <li>The sync is logged with the DP donor ID and gift ID</li>
            </ol>
        </div>

        <div class="gwdp-docs-section">
            <h3>Recurring Donations &amp; "Pledges"</h3>
            <p>When a donor sets up a recurring donation in GiveWP (e.g. $25/month), this plugin uses DonorPerfect's <strong>pledge</strong> feature to group all of that subscription's payments together.</p>

            <div class="gwdp-help-text">
                <strong>What "pledge" means here:</strong> In DonorPerfect, a "pledge" is simply a way to group related recurring payments under one record. It does <em>not</em> mean the donor has pledged a specific total amount. GiveWP recurring donations are <strong>open-ended</strong> &mdash; the donor gives monthly (or at another interval) until they cancel. There is no target amount or fulfillment date. The DP pledge is set to <code>total=0</code> (open-ended) to reflect this.
            </div>

            <p><strong>How it works:</strong></p>
            <ol>
                <li><strong>First payment:</strong> Creates a DP pledge to represent the subscription, plus a gift linked to that pledge</li>
                <li><strong>Renewal payments:</strong> Each subsequent payment creates a new gift linked to the same pledge, so they're all grouped together</li>
            </ol>
            <p>The plugin remembers which GiveWP subscription maps to which DP pledge, so renewals are always linked correctly.</p>

            <table class="widefat gwdp-docs-table">
                <thead><tr><th>GiveWP Event</th><th>DonorPerfect Action</th><th>Sub-Solicit</th></tr></thead>
                <tbody>
                    <tr><td>One-time donation</td><td>Create gift</td><td>ONETIME</td></tr>
                    <tr><td>First recurring payment</td><td>Create pledge (recurring group) + linked gift</td><td>RECURRING</td></tr>
                    <tr><td>Renewal payment</td><td>Create gift linked to existing pledge</td><td>RECURRING</td></tr>
                </tbody>
            </table>
        </div>

        <div class="gwdp-docs-section">
            <h3>Donor Matching</h3>
            <p>Donors are matched by <strong>email address</strong>. When a donation comes in, the plugin runs:</p>
            <code>SELECT TOP 1 donor_id FROM dp WHERE email='donor@example.com'</code>
            <p>If a match is found, the existing DP donor is used. If no match, a new donor is created via <code>dp_savedonor</code>.</p>
        </div>

        <div class="gwdp-docs-section">
            <h3>Settings Explained</h3>
            <table class="widefat gwdp-docs-table">
                <thead><tr><th>Setting</th><th>What It Does</th></tr></thead>
                <tbody>
                    <tr><td><strong>Real-Time Sync</strong></td><td>When ON, every new donation is automatically synced to DP. When OFF, nothing is sent — use Backfill to sync manually.</td></tr>
                    <tr><td><strong>API Key</strong></td><td>Your DonorPerfect XML API key. Get it from DP Admin > My Settings > API Keys.</td></tr>
                    <tr><td><strong>GL Code</strong></td><td>Default General Ledger code assigned to gifts (e.g. <code>UN</code> for Unrestricted). Must exist in DP's DPCODES table.</td></tr>
                    <tr><td><strong>Campaign</strong></td><td>Default campaign code assigned to gifts. Must exist in DPCODES. Leave blank for none.</td></tr>
                    <tr><td><strong>Solicit Code</strong></td><td>Optional solicit code. Sub-solicit is set automatically (ONETIME or RECURRING).</td></tr>
                    <tr><td><strong>Gateway Mapping</strong></td><td>Maps how donors paid (Stripe, PayPal, etc.) to DP gift type codes so your team can see payment methods in reports. E.g. <code>stripe</code> &rarr; <code>CC</code>, <code>paypal</code> &rarr; <code>PAYPAL</code>, <code>manual</code> &rarr; <code>CK</code>.</td></tr>
                </tbody>
            </table>
        </div>

        <div class="gwdp-docs-section">
            <h3>Using the Tabs</h3>
            <table class="widefat gwdp-docs-table">
                <thead><tr><th>Tab</th><th>Purpose</th></tr></thead>
                <tbody>
                    <tr><td><strong>Dashboard</strong></td><td>Overview of sync stats (success/error counts, donors created/matched, recent activity).</td></tr>
                    <tr><td><strong>Settings</strong></td><td>Configure API key, field mappings, gateway mappings. Test your connection and validate codes.</td></tr>
                    <tr><td><strong>Sync Log</strong></td><td>View every sync attempt with status, DP IDs, and error messages. Filter by status.</td></tr>
                    <tr><td><strong>Backfill</strong></td><td>Sync historical donations. Run a Preview first (dry run, no data sent), then Start Backfill. You can also sync a single donation by ID.</td></tr>
                    <tr><td><strong>Match Report</strong></td><td>Preview how GiveWP donors will map to DP records. Read-only — no data is modified.</td></tr>
                </tbody>
            </table>
        </div>

        <div class="gwdp-docs-section">
            <h3>Recommended First Steps</h3>
            <ol>
                <li>Enter your API key in <strong>Settings</strong> and click <strong>Test API Connection</strong></li>
                <li>Click <strong>Validate Codes</strong> to confirm your GL code, campaign, and sub-solicit codes exist in DP</li>
                <li>Go to <strong>Match Report</strong> to preview how donors will be matched</li>
                <li>Go to <strong>Backfill</strong> and run a <strong>Preview</strong> of 50 donations to see what would happen</li>
                <li>When satisfied, run the actual <strong>Backfill</strong> to sync historical donations</li>
                <li>Enable <strong>Real-Time Sync</strong> in Settings to start syncing new donations automatically</li>
            </ol>
        </div>

        <div class="gwdp-docs-section">
            <h3>Prerequisites in DonorPerfect</h3>
            <p>Before syncing, ensure these codes exist in your DonorPerfect system:</p>
            <ul>
                <li><strong>ONETIME</strong> — sub_solicit_code for one-time donations</li>
                <li><strong>RECURRING</strong> — sub_solicit_code for recurring donations</li>
                <li>Your <strong>GL code</strong> (e.g. <code>UN</code>) in the GL_CODE field of DPCODES</li>
                <li>Your <strong>campaign code</strong> (if using one) in the CAMPAIGN field of DPCODES</li>
            </ul>
            <p>Use the <strong>Validate Codes</strong> button in Settings to check these automatically.</p>
        </div>

        <div class="gwdp-docs-section">
            <h3>Database Tables</h3>
            <p>The plugin creates two tables on activation:</p>
            <ul>
                <li><code><?php echo esc_html($GLOBALS['wpdb']->prefix); ?>gwdp_sync_log</code> — logs every sync attempt with Give donation ID, DP donor/gift/pledge IDs, status, and errors</li>
                <li><code><?php echo esc_html($GLOBALS['wpdb']->prefix); ?>gwdp_pledge_map</code> — maps GiveWP subscription IDs to DP pledge IDs for linking renewal payments</li>
            </ul>
        </div>

        <div class="gwdp-docs-section">
            <h3>About</h3>
            <p>
                <strong>GiveWP2DP</strong> v<?php echo esc_html(GWDP_VERSION); ?><br>
                By <a href="https://ashrafali.net" target="_blank">Ashraf Ali</a><br>
                <a href="https://github.com/nerveband/givewp2dp" target="_blank">GitHub Repository</a> &middot; MIT License
            </p>
        </div>

        </div>
        <?php
    }

    // ─── Changelog ───

    private function render_changelog(): void {
        $file = GWDP_PLUGIN_DIR . 'CHANGELOG.md';
        if (!file_exists($file)) {
            echo '<p>Changelog not found.</p>';
            return;
        }
        $md = file_get_contents($file);
        $lines = explode("\n", $md);
        echo '<div class="gwdp-docs">';
        $in_list = false;
        $in_section = false;
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            } elseif (str_starts_with($line, '# ')) {
                if ($in_list) { echo '</ul>'; $in_list = false; }
                if ($in_section) { echo '</div>'; $in_section = false; }
                echo '<h2>' . esc_html(substr($line, 2)) . '</h2>';
            } elseif (str_starts_with($line, '## ')) {
                if ($in_list) { echo '</ul>'; $in_list = false; }
                if ($in_section) { echo '</div>'; $in_section = false; }
                echo '<div class="gwdp-docs-section"><h3>' . esc_html(substr($line, 3)) . '</h3>';
                $in_section = true;
            } elseif (str_starts_with($line, '### ')) {
                if ($in_list) { echo '</ul>'; $in_list = false; }
                echo '<h4 style="margin:12px 0 4px;color:#1d2327;">' . esc_html(substr($line, 4)) . '</h4>';
                echo '<ul>';
                $in_list = true;
            } elseif (str_starts_with($line, '- ')) {
                if (!$in_list) { echo '<ul>'; $in_list = true; }
                $text = substr($line, 2);
                $text = preg_replace('/\*\*([^*]+)\*\*/', "\x00STRONG\x01\\1\x00/STRONG\x01", $text);
                $text = preg_replace('/`([^`]+)`/', "\x00CODE\x01\\1\x00/CODE\x01", $text);
                $text = esc_html($text);
                $text = str_replace(["\x00STRONG\x01", "\x00/STRONG\x01"], ['<strong>', '</strong>'], $text);
                $text = str_replace(["\x00CODE\x01", "\x00/CODE\x01"], ['<code>', '</code>'], $text);
                echo '<li>' . $text . '</li>';
            } else {
                if ($in_list) { echo '</ul>'; $in_list = false; }
                echo '<p>' . esc_html($line) . '</p>';
            }
        }
        if ($in_list) echo '</ul>';
        if ($in_section) echo '</div>';
        echo '</div>';
    }

    // ─── Info tooltip helper ───

    private function info(string $text): string {
        return ' <span class="gwdp-info" tabindex="0"><span class="gwdp-info-icon">i</span><span class="gwdp-info-tip">' . esc_html($text) . '</span></span>';
    }

    // ─── Shared Log Table ───

    private function render_log_table(array $rows): void {
        if (empty($rows)) {
            echo '<p class="description">No sync records found.</p>';
            return;
        }
        ?>
        <table class="widefat striped gwdp-log-table">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Give #</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Donor Action<?php echo $this->info('"Matched" means an existing DP donor was found by email. "Created" means a new DP donor record was created.'); ?></th>
                    <th>DP Donor</th>
                    <th>DP Gift</th>
                    <th>DP Pledge<?php echo $this->info('For recurring donations, this is the DonorPerfect pledge ID that groups all payments from this subscription together. One-time donations will not have a pledge.'); ?></th>
                    <th>Status</th>
                    <th>Error</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                <tr class="gwdp-row-<?php echo esc_attr($row['status']); ?>">
                    <td><?php echo esc_html($row['synced_at']); ?></td>
                    <td><?php echo esc_html($row['give_donation_id']); ?></td>
                    <td><?php echo esc_html($row['donation_type'] ?? '—'); ?></td>
                    <td><?php echo $row['donation_amount'] ? '$' . esc_html(number_format((float)$row['donation_amount'], 2)) : '—'; ?></td>
                    <td><?php echo esc_html($row['donor_action'] ?? '—'); ?></td>
                    <td><?php echo $row['dp_donor_id'] ? '#' . esc_html($row['dp_donor_id']) : '—'; ?></td>
                    <td><?php echo $row['dp_gift_id'] ? '#' . esc_html($row['dp_gift_id']) : '—'; ?></td>
                    <td><?php echo $row['dp_pledge_id'] ? '#' . esc_html($row['dp_pledge_id']) : '—'; ?></td>
                    <td><span class="gwdp-badge gwdp-badge-<?php echo esc_attr($row['status']); ?>"><?php echo esc_html($row['status']); ?></span></td>
                    <td class="gwdp-error-cell"><?php echo esc_html($row['error_message'] ?? ''); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
}
