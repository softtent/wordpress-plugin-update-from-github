<?php
namespace SoftTent\Types;

class AutoUpdate {

    /**
     * Plugin file
     *
     * @var string
     */
    protected string $plugin_file;

    /**
     * Plugin slug
     *
     * @var string
     */
    protected string $plugin_slug;

    /**
     * Github Author
     *
     * @var string
     */
    protected string $github_author;

    /**
     * Github Repository
     *
     * @var string
     */
    protected string $github_repository;

    /**
     * Repository
     *
     * @var string
     */
    protected string $repository;

    /**
     * Access token
     *
     * @var string
     */
    protected string $access_token;

    /**
     * Plugin
     *
     * @var string
     */
    protected string $plugin;

    public function __construct( $args = [] ) {

        $this->plugin_file       = isset( $args['plugin_file'] ) ? sanitize_text_field( $args['plugin_file'] ) : '';
        $this->plugin_slug       = isset( $args['plugin_slug'] ) ? sanitize_text_field( $args['plugin_slug'] ) : '';
        $this->plugin            = $this->plugin_file . '/' . $this->plugin_slug . '.php';
        $this->github_author     = isset( $args['github_author'] ) ? sanitize_text_field( $args['github_author'] ) : '';
        $this->github_repository = isset( $args['github_repository'] ) ? sanitize_text_field( $args['github_repository'] ) : '';
        $this->repository        = $this->github_author . '/' . $this->github_repository;
        $this->access_token      = isset( $args['access_token'] ) ? sanitize_text_field( $args['access_token'] ) : '';

        if ( $this->github_author && $this->github_repository && $this->plugin_file && $this->plugin_slug ) {
            $this->run_updater();
        }
    }

    /**
     * Plugin updater
     *
     * @return void
     */
    public function run_updater(): void {
        add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_update_from_github' ] );
        add_filter( 'http_request_args', [ $this, 'set_authorization_token' ], 10, 2 );
        add_filter( 'upgrader_source_selection', [ $this, 'rename_folder_after_download' ], 10, 4 );
        add_filter( 'plugins_api', [ $this, 'show_new_version_changelog' ], 20, 3 );
    }

    /**
     * Get headers
     *
     * @return array
     */
    public function get_headers(): array {
        $headers = [
            'User-Agent' => $this->plugin_slug . ' updater',
        ];

        if ( ! empty( $this->access_token ) ) {
            $headers['Authorization'] = 'token ' . $this->access_token;
        }

        return $headers;
    }

    /**
     * Check auto update from GitHub.
     *
     * @since 0.1.0
     *
     * @param object $transient
     *
     * @return object
     */
    public function check_update_from_github( $transient ) {

        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        $response = $this->get_repo_info( $transient );

        if ( ! empty( $response ) ) {
            $transient->response[ $this->plugin ] = $response;
        }

        return $transient;
    }

    public function get_repo_info( $transient = null ) {

        $url = wp_sprintf( 'https://api.github.com/repos/%s/releases/latest', esc_attr( $this->repository ) );

        $response = wp_remote_get( $url, [ 'headers' => $this->get_headers() ] );

        // Bail if response is not valid.
        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
            return;
        }

        $response = json_decode( wp_remote_retrieve_body( $response ) );

        if ( ! $response || empty( $response->tag_name ) ) {
            return;
        }

        $transient = isset( $transient ) ? $transient : get_site_transient( 'update_plugins' );

        if ( empty( $transient ) ) {
            return;
        }

        $transient = (array) $transient;

        $remote_version = ltrim( $response->tag_name, 'v' );
        $plugin_data    = get_plugin_data( WP_PLUGIN_DIR . '/' . $this->plugin );

        if ( version_compare( $remote_version, $plugin_data['Version'], '>' ) ) {
            return (object) [
                'slug'        => $this->plugin_slug,
                'plugin'      => $this->plugin,
                'new_version' => $response->tag_name,
                'url'         => $response->html_url,
                'package'     => $response->zipball_url,
                'sections'    => [
                    'description' => $response->body,
                ],
            ];
        }

        return null;
    }

    /**
     * Set authorization token for the request.
     *
     * @param  array  $args Args.
     * @param  string $url  URL.
     * @return array
     */
    public function set_authorization_token( array $args, string $url ): array {
        $transient = get_site_transient( 'update_plugins' );

        if ( empty( $transient ) ) {
            return $args;
        }

        // new version.
        if ( empty( $transient->response[ $this->plugin ] ) ) {
            return $args;
        }

        $version = $transient->response[ $this->plugin ]->new_version;

        $expected_url = wp_sprintf( 'https://api.github.com/repos/%s/zipball/%s', esc_attr( $this->repository ), esc_attr( $version ) );

        if ( $url !== $expected_url ) {
            return $args;
        }

        // set headers.
        $args['headers'] = $this->get_headers();

        return $args;
    }

    /**
     * Set upgrader source selection
     *
     * Zip from Github contains a folder with the name of the commit hash. So we need to rename it to the plugin slug.
     *
     * @param  string $source        Source.
     * @param  string $remote_source Remote source.
     * @param  object $upgrader      Upgrader.
     * @param  array  $hook_extra    Hook extra.
     *
     * @return string
     */
    public function rename_folder_after_download( $source, $remote_source, $upgrader, $hook_extra ) {

        if ( $hook_extra['plugin'] !== $this->plugin ) {
            return $source;
        }

        global $wp_filesystem;

        $new_source = trailingslashit( $remote_source ) . $this->plugin_file;

        // Check if source and new path are different
        if ( $source !== $new_source ) {
            if ( $wp_filesystem->move( $source, $new_source ) ) {
                return trailingslashit( $new_source );
            } else {
                return new \WP_Error( 'folder_rename_failed', esc_html__( 'Failed to rename plugin folder during update.', 'your-text-domain' ) );
            }
        }

        return $source;
    }

    /**
     * Show new version changelog.
     *
     * @return array
     */
    public function show_new_version_changelog( $res, $action, $args ) {

        if ( $action !== 'plugin_information' ) {
            return $res;
        }

        if ( $args->slug !== $this->plugin_slug ) {
            return $res;
        }

        $plugin_updates = get_site_transient( 'update_plugins' );
        $description    = '';
        $new_version    = '';
        $download_url   = '';

        if ( isset( $plugin_updates->response[ $this->plugin ] ) ) {
            $plugin_data = $plugin_updates->response[ $this->plugin ];

            $description  = $plugin_data->sections['description'];
            $new_version  = $plugin_data->new_version;
            $download_url = $plugin_data->package;
        }

        $plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $this->plugin );

        $res = (object) [
            'name'          => $plugin_data['Name'],
            'slug'          => $this->plugin_slug,
            'version'       => $new_version,
            'sections'      => [
                'changelog' => $description,
            ],
            'download_link' => $download_url,
        ];

        return $res;
    }
}
