<?php
/**
 * GitHub Releases auto-updater for WordPress plugins.
 * Checks the GitHub API for new releases and hooks into WP's native update system.
 */

if (!defined('ABSPATH')) exit;

class GWDP_GitHub_Updater {

    private string $slug;
    private string $plugin_file;
    private string $github_repo;
    private string $current_version;
    private ?object $github_data = null;

    public function __construct(string $plugin_file, string $github_repo) {
        $this->plugin_file    = $plugin_file;
        $this->slug           = plugin_basename($plugin_file);
        $this->github_repo    = $github_repo;
        $this->current_version = GWDP_VERSION;

        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_update']);
        add_filter('plugins_api', [$this, 'plugin_info'], 10, 3);
        add_filter('upgrader_post_install', [$this, 'post_install'], 10, 3);
    }

    /**
     * Fetch latest release data from GitHub API (cached for 6 hours).
     */
    private function get_github_release(): ?object {
        if ($this->github_data !== null) {
            return $this->github_data;
        }

        $cache_key = 'gwdp_github_release';
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            $this->github_data = $cached;
            return $this->github_data;
        }

        $url = "https://api.github.com/repos/{$this->github_repo}/releases/latest";
        $response = wp_remote_get($url, [
            'headers' => ['Accept' => 'application/vnd.github.v3+json'],
            'timeout' => 10,
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response));
        if (!$body || empty($body->tag_name)) {
            return null;
        }

        $this->github_data = $body;
        set_transient($cache_key, $body, 6 * HOUR_IN_SECONDS);

        return $this->github_data;
    }

    /**
     * Check if a newer version is available on GitHub.
     */
    public function check_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        $release = $this->get_github_release();
        if (!$release) {
            return $transient;
        }

        $remote_version = ltrim($release->tag_name, 'v');

        if (version_compare($remote_version, $this->current_version, '>')) {
            $download_url = $release->zipball_url ?? '';

            // Prefer a .zip asset if attached to the release
            if (!empty($release->assets)) {
                foreach ($release->assets as $asset) {
                    if (str_ends_with($asset->name, '.zip')) {
                        $download_url = $asset->browser_download_url;
                        break;
                    }
                }
            }

            $transient->response[$this->slug] = (object) [
                'slug'        => dirname($this->slug),
                'plugin'      => $this->slug,
                'new_version' => $remote_version,
                'url'         => "https://github.com/{$this->github_repo}",
                'package'     => $download_url,
            ];
        }

        return $transient;
    }

    /**
     * Provide plugin info for the WP update details popup.
     */
    public function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information' || ($args->slug ?? '') !== dirname($this->slug)) {
            return $result;
        }

        $release = $this->get_github_release();
        if (!$release) {
            return $result;
        }

        $remote_version = ltrim($release->tag_name, 'v');

        return (object) [
            'name'          => 'GiveWP2DP',
            'slug'          => dirname($this->slug),
            'version'       => $remote_version,
            'author'        => '<a href="https://ashrafali.net">Ashraf Ali</a>',
            'homepage'      => "https://github.com/{$this->github_repo}",
            'requires'      => '6.0',
            'requires_php'  => '8.0',
            'downloaded'    => 0,
            'last_updated'  => $release->published_at ?? '',
            'sections'      => [
                'description'  => 'Syncs GiveWP donations to DonorPerfect in real-time with donor matching, recurring donation support, historical backfill, and comprehensive logging.',
                'changelog'    => nl2br(esc_html($release->body ?? 'See GitHub releases for details.')),
            ],
            'download_link' => $release->zipball_url ?? '',
        ];
    }

    /**
     * After install, rename the extracted directory to match the plugin slug.
     * GitHub zipballs extract as "owner-repo-hash/" — this renames to the expected folder.
     */
    public function post_install($response, $hook_extra, $result) {
        if (!isset($hook_extra['plugin']) || $hook_extra['plugin'] !== $this->slug) {
            return $result;
        }

        global $wp_filesystem;

        $plugin_dir = WP_PLUGIN_DIR . '/' . dirname($this->slug);
        $wp_filesystem->move($result['destination'], $plugin_dir);
        $result['destination'] = $plugin_dir;

        activate_plugin($this->slug);

        return $result;
    }
}
