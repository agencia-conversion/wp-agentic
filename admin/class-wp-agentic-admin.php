<?php
/**
 * Admin settings screen.
 *
 * @package WP_Agentic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders and saves plugin settings.
 */
class WP_Agentic_Admin {
	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
	}

	/**
	 * Add settings page.
	 *
	 * @return void
	 */
	public static function admin_menu() {
		add_options_page(
			__( 'WP Agentic', 'wp-agentic' ),
			__( 'WP Agentic', 'wp-agentic' ),
			'manage_options',
			'wp-agentic',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Register settings.
	 *
	 * @return void
	 */
	public static function register_settings() {
		register_setting(
			'wp_agentic',
			WP_Agentic_Settings::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'WP_Agentic_Settings', 'sanitize' ),
				'default'           => WP_Agentic_Settings::defaults(),
			)
		);
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings          = WP_Agentic_Settings::get();
		$enabled           = ! empty( $settings['enabled'] );
		$graphql_available = self::graphql_available();
		$routes            = self::diagnostic_routes();
		?>
		<div class="wrap wp-agentic-wrap">
			<?php self::render_styles(); ?>

			<div class="wp-agentic-hero">
				<div>
					<p class="wp-agentic-kicker"><?php esc_html_e( 'Agent readiness for WordPress', 'wp-agentic' ); ?></p>
					<h1><?php esc_html_e( 'WP Agentic', 'wp-agentic' ); ?></h1>
					<p class="wp-agentic-hero-copy"><?php esc_html_e( 'Expose public, read-only discovery surfaces for AI agents without publishing fake capabilities.', 'wp-agentic' ); ?></p>
				</div>
				<div class="wp-agentic-hero-meta">
					<?php self::status_badge( $enabled ? __( 'Enabled', 'wp-agentic' ) : __( 'Disabled', 'wp-agentic' ), $enabled ? 'good' : 'muted' ); ?>
					<span class="wp-agentic-version"><?php echo esc_html( 'v' . WP_AGENTIC_VERSION ); ?></span>
				</div>
			</div>

			<form method="post" action="options.php">
				<?php settings_fields( 'wp_agentic' ); ?>

				<div class="wp-agentic-section">
					<div class="wp-agentic-section-heading">
						<h2><?php esc_html_e( 'Core Controls', 'wp-agentic' ); ?></h2>
						<p><?php esc_html_e( 'The global switch is the fastest rollback path. Turning it off restores the normal WordPress behavior for public requests.', 'wp-agentic' ); ?></p>
					</div>
					<div class="wp-agentic-grid wp-agentic-grid-2">
						<?php
						self::module_card(
							array(
								'title'       => __( 'Global Kill Switch', 'wp-agentic' ),
								'key'         => 'enabled',
								'settings'    => $settings,
								'status'      => $enabled ? __( 'Active', 'wp-agentic' ) : __( 'Paused', 'wp-agentic' ),
								'tone'        => $enabled ? 'good' : 'muted',
								'description' => __( 'Controls every WP Agentic route, header, REST endpoint, Markdown response, and browser tool.', 'wp-agentic' ),
								'impact'      => __( 'Rollback and operational safety.', 'wp-agentic' ),
							)
						);
						self::metadata_card( $settings );
						?>
					</div>
				</div>

				<div class="wp-agentic-section">
					<div class="wp-agentic-section-heading">
						<h2><?php esc_html_e( 'Modules', 'wp-agentic' ); ?></h2>
						<p><?php esc_html_e( 'Each module maps to a concrete public capability used by agent-readiness scanners and AI crawlers.', 'wp-agentic' ); ?></p>
					</div>
					<div class="wp-agentic-grid">
						<?php
						self::module_card(
							array(
								'title'       => __( 'Content Signals', 'wp-agentic' ),
								'key'         => 'enable_content_signals',
								'settings'    => $settings,
								'status'      => self::enabled_label( $settings, 'enable_content_signals' ),
								'tone'        => self::enabled_tone( $settings, 'enable_content_signals' ),
								'description' => __( 'Adds an explicit Content-Signal line to robots.txt and Markdown responses.', 'wp-agentic' ),
								'url'         => home_url( 'robots.txt' ),
								'impact'      => __( 'Content policy discovery.', 'wp-agentic' ),
								'extra'       => self::content_signal_controls( $settings ),
							)
						);
						self::module_card(
							array(
								'title'       => __( 'llms.txt', 'wp-agentic' ),
								'key'         => 'enable_llms',
								'settings'    => $settings,
								'status'      => self::enabled_label( $settings, 'enable_llms' ),
								'tone'        => self::enabled_tone( $settings, 'enable_llms' ),
								'description' => __( 'Publishes /llms.txt and /.well-known/llms.txt with site context and agent resources.', 'wp-agentic' ),
								'url'         => home_url( 'llms.txt' ),
								'impact'      => __( 'Agent-readable site overview.', 'wp-agentic' ),
							)
						);
						self::module_card(
							array(
								'title'       => __( 'API Catalog', 'wp-agentic' ),
								'key'         => 'enable_api_catalog',
								'settings'    => $settings,
								'status'      => self::enabled_label( $settings, 'enable_api_catalog' ),
								'tone'        => self::enabled_tone( $settings, 'enable_api_catalog' ),
								'description' => __( 'Publishes a linkset catalog pointing to WordPress REST, WP Agentic REST, sitemap, llms.txt, Agent Skills, and WPGraphQL when active.', 'wp-agentic' ),
								'url'         => home_url( '.well-known/api-catalog' ),
								'impact'      => __( 'Structured API discovery.', 'wp-agentic' ),
							)
						);
						self::module_card(
							array(
								'title'       => __( 'Agent Skills v0.2', 'wp-agentic' ),
								'key'         => 'enable_agent_skills',
								'settings'    => $settings,
								'status'      => self::enabled_label( $settings, 'enable_agent_skills' ),
								'tone'        => self::enabled_tone( $settings, 'enable_agent_skills' ),
								'description' => __( 'Publishes a skills index and virtual SKILL.md files for read-only content discovery, search, article reading, recent posts, and contact handoff.', 'wp-agentic' ),
								'url'         => home_url( '.well-known/agent-skills/index.json' ),
								'impact'      => __( 'Agent capability discovery.', 'wp-agentic' ),
							)
						);
						self::module_card(
							array(
								'title'       => __( 'Markdown Negotiation', 'wp-agentic' ),
								'key'         => 'enable_markdown',
								'settings'    => $settings,
								'status'      => self::enabled_label( $settings, 'enable_markdown' ),
								'tone'        => self::enabled_tone( $settings, 'enable_markdown' ),
								'description' => __( 'Returns clean Markdown with frontmatter only when a public GET or HEAD request asks for Accept: text/markdown.', 'wp-agentic' ),
								'url'         => home_url( '/' ),
								'impact'      => __( 'Content Site score and agent parsing quality.', 'wp-agentic' ),
							)
						);
						self::module_card(
							array(
								'title'       => __( 'WebMCP Read-only Tools', 'wp-agentic' ),
								'key'         => 'enable_webmcp',
								'settings'    => $settings,
								'status'      => self::enabled_label( $settings, 'enable_webmcp' ),
								'tone'        => self::enabled_tone( $settings, 'enable_webmcp' ),
								'description' => __( 'Registers browser tools when navigator.modelContext exists. Tools only search, list, read public content, get context, or return the contact URL.', 'wp-agentic' ),
								'url'         => rest_url( 'wp-agentic/v1/context' ),
								'impact'      => __( 'WebMCP scanner check and browser-agent integration.', 'wp-agentic' ),
								'extra'       => self::post_types_control( $settings ),
							)
						);
						self::graphql_card( $settings, $graphql_available );
						?>
					</div>
				</div>

				<?php submit_button( __( 'Save WP Agentic Settings', 'wp-agentic' ), 'primary large' ); ?>
			</form>

			<div class="wp-agentic-section">
				<div class="wp-agentic-section-heading">
					<h2><?php esc_html_e( 'Protocol Boundaries', 'wp-agentic' ); ?></h2>
					<p><?php esc_html_e( 'These checks stay unpublished until the site has the real service behind them.', 'wp-agentic' ); ?></p>
				</div>
				<div class="wp-agentic-grid wp-agentic-grid-4">
					<?php self::protocol_card( __( 'MCP Server Card', 'wp-agentic' ), __( 'Not published', 'wp-agentic' ), __( 'WP Agentic currently implements WebMCP in the browser, not a remote MCP server endpoint with transports, auth, and server capabilities.', 'wp-agentic' ) ); ?>
					<?php self::protocol_card( __( 'OAuth Discovery', 'wp-agentic' ), __( 'Not published', 'wp-agentic' ), __( 'OAuth metadata should only exist when the site exposes real OAuth authorization and protected-resource behavior.', 'wp-agentic' ) ); ?>
					<?php self::protocol_card( __( 'A2A Agent Card', 'wp-agentic' ), __( 'Not published', 'wp-agentic' ), __( 'A2A requires an actual agent service. WP Agentic only exposes public read resources in this release.', 'wp-agentic' ) ); ?>
					<?php self::protocol_card( __( 'Commerce Metadata', 'wp-agentic' ), __( 'Not published', 'wp-agentic' ), __( 'Payment, checkout, or transaction metadata is intentionally absent unless a safe commerce flow is implemented.', 'wp-agentic' ) ); ?>
				</div>
			</div>

			<div class="wp-agentic-section">
				<div class="wp-agentic-section-heading">
					<h2><?php esc_html_e( 'Diagnostics', 'wp-agentic' ); ?></h2>
					<p><?php esc_html_e( 'Use these generated URLs and commands to validate the public behavior after saving settings or purging cache.', 'wp-agentic' ); ?></p>
				</div>
				<div class="wp-agentic-diagnostics">
					<div>
						<h3><?php esc_html_e( 'Generated Endpoints', 'wp-agentic' ); ?></h3>
						<table class="widefat striped">
							<tbody>
							<?php foreach ( $routes as $label => $url ) : ?>
								<tr>
									<th scope="row"><?php echo esc_html( $label ); ?></th>
									<td><a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $url ); ?></a></td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					</div>
					<div>
						<h3><?php esc_html_e( 'Checklist', 'wp-agentic' ); ?></h3>
						<ul class="wp-agentic-checklist">
							<?php self::checklist_item( __( 'Markdown negotiation enabled', 'wp-agentic' ), $enabled && ! empty( $settings['enable_markdown'] ) ); ?>
							<?php self::checklist_item( __( 'Agent Skills v0.2 enabled', 'wp-agentic' ), $enabled && ! empty( $settings['enable_agent_skills'] ) ); ?>
							<?php self::checklist_item( __( 'WebMCP tools enabled', 'wp-agentic' ), $enabled && ! empty( $settings['enable_webmcp'] ) ); ?>
							<?php self::checklist_item( __( 'WPGraphQL detected', 'wp-agentic' ), $graphql_available ); ?>
							<?php self::checklist_item( __( 'Link headers enabled', 'wp-agentic' ), $enabled && ( ! empty( $settings['enable_llms'] ) || ! empty( $settings['enable_api_catalog'] ) || ! empty( $settings['enable_agent_skills'] ) ) ); ?>
						</ul>
						<h3><?php esc_html_e( 'curl Commands', 'wp-agentic' ); ?></h3>
						<div class="wp-agentic-code-list">
							<code>curl -I <?php echo esc_html( home_url( '/' ) ); ?></code>
							<code>curl -I -H 'Accept: text/markdown' <?php echo esc_html( home_url( '/' ) ); ?></code>
							<code>curl <?php echo esc_html( home_url( 'robots.txt' ) ); ?></code>
							<code>curl <?php echo esc_html( home_url( '.well-known/api-catalog' ) ); ?></code>
							<code>curl <?php echo esc_html( home_url( '.well-known/agent-skills/index.json' ) ); ?></code>
							<code>curl <?php echo esc_html( home_url( '.well-known/agent-skills/search-site/SKILL.md' ) ); ?></code>
							<code>curl <?php echo esc_html( rest_url( 'wp-agentic/v1/context' ) ); ?></code>
						</div>
					</div>
				</div>
			</div>

			<div class="wp-agentic-footer">
				<span><?php echo esc_html( 'WP Agentic v' . WP_AGENTIC_VERSION ); ?></span>
				<a href="<?php echo esc_url( 'https://github.com/agencia-conversion/wp-agentic/releases/tag/v' . WP_AGENTIC_VERSION ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'GitHub release', 'wp-agentic' ); ?></a>
				<span><?php esc_html_e( 'by Conversion', 'wp-agentic' ); ?></span>
			</div>
		</div>
		<?php
	}

	/**
	 * Render admin CSS.
	 *
	 * @return void
	 */
	private static function render_styles() {
		?>
		<style>
			.wp-agentic-wrap{max-width:1240px}
			.wp-agentic-hero{background:#0f172a;color:#fff;border-radius:8px;padding:28px 32px;margin:22px 0;display:flex;align-items:flex-start;justify-content:space-between;gap:24px}
			.wp-agentic-hero h1{color:#fff;font-size:34px;line-height:1.15;margin:3px 0 8px;letter-spacing:0}
			.wp-agentic-kicker{margin:0;color:#93c5fd;font-weight:700;text-transform:uppercase;font-size:12px;letter-spacing:.08em}
			.wp-agentic-hero-copy{font-size:15px;max-width:680px;margin:0;color:#dbeafe}
			.wp-agentic-hero-meta{display:flex;align-items:center;gap:10px;flex-wrap:wrap;justify-content:flex-end}
			.wp-agentic-version{border:1px solid rgba(255,255,255,.24);border-radius:999px;padding:5px 10px;color:#e2e8f0;font-weight:700}
			.wp-agentic-section{margin:24px 0}
			.wp-agentic-section-heading{display:flex;align-items:flex-end;justify-content:space-between;gap:20px;margin:0 0 12px}
			.wp-agentic-section-heading h2{margin:0;font-size:20px}
			.wp-agentic-section-heading p{margin:0;color:#4b5563;max-width:720px}
			.wp-agentic-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}
			.wp-agentic-grid-2{grid-template-columns:1fr 2fr}
			.wp-agentic-grid-4{grid-template-columns:repeat(4,minmax(0,1fr))}
			.wp-agentic-card{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:18px;box-shadow:0 1px 2px rgba(0,0,0,.04)}
			.wp-agentic-card-header{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:10px}
			.wp-agentic-card h3{font-size:16px;margin:0;color:#111827}
			.wp-agentic-card p{color:#4b5563;margin:8px 0 0}
			.wp-agentic-card small{color:#6b7280}
			.wp-agentic-toggle{display:flex;align-items:center;gap:8px;font-weight:700;margin-top:14px}
			.wp-agentic-badge{display:inline-flex;align-items:center;border-radius:999px;padding:4px 9px;font-size:12px;font-weight:700;border:1px solid transparent;white-space:nowrap}
			.wp-agentic-badge-good{background:#ecfdf5;color:#047857;border-color:#a7f3d0}
			.wp-agentic-badge-warn{background:#fffbeb;color:#92400e;border-color:#fde68a}
			.wp-agentic-badge-muted{background:#f3f4f6;color:#4b5563;border-color:#e5e7eb}
			.wp-agentic-route{margin-top:12px;padding-top:12px;border-top:1px solid #eef0f3;word-break:break-all}
			.wp-agentic-impact{margin-top:12px;color:#111827}
			.wp-agentic-fields{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-top:12px}
			.wp-agentic-field label{display:block;font-weight:700;margin-bottom:4px}
			.wp-agentic-field input[type=text],.wp-agentic-field input[type=url],.wp-agentic-field select{width:100%;max-width:100%}
			.wp-agentic-inline-fields{display:flex;gap:14px;flex-wrap:wrap;margin-top:12px}
			.wp-agentic-inline-fields label{font-weight:700}
			.wp-agentic-post-types{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:6px;margin-top:12px}
			.wp-agentic-protocol h3{display:flex;align-items:center;justify-content:space-between;gap:10px}
			.wp-agentic-diagnostics{display:grid;grid-template-columns:1.2fr .8fr;gap:18px}
			.wp-agentic-checklist{background:#fff;border:1px solid #dcdcde;border-radius:8px;margin:0 0 18px;padding:12px 16px}
			.wp-agentic-checklist li{display:flex;align-items:center;gap:8px;margin:8px 0;color:#111827}
			.wp-agentic-check{display:inline-flex;align-items:center;justify-content:center;width:20px;height:20px;border-radius:999px;font-size:12px;font-weight:700}
			.wp-agentic-check-yes{background:#ecfdf5;color:#047857}
			.wp-agentic-check-no{background:#f3f4f6;color:#6b7280}
			.wp-agentic-code-list{display:grid;gap:8px}
			.wp-agentic-code-list code{display:block;white-space:normal;background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:9px;line-height:1.45}
			.wp-agentic-footer{border-top:1px solid #dcdcde;margin:28px 0 0;padding:16px 0;color:#4b5563;display:flex;gap:12px;align-items:center;flex-wrap:wrap}
			@media (max-width:1100px){.wp-agentic-grid,.wp-agentic-grid-2,.wp-agentic-grid-4,.wp-agentic-diagnostics{grid-template-columns:1fr 1fr}.wp-agentic-fields{grid-template-columns:1fr}}
			@media (max-width:782px){.wp-agentic-hero,.wp-agentic-section-heading{display:block}.wp-agentic-hero-meta{justify-content:flex-start;margin-top:16px}.wp-agentic-grid,.wp-agentic-grid-2,.wp-agentic-grid-4,.wp-agentic-diagnostics{grid-template-columns:1fr}.wp-agentic-post-types{grid-template-columns:1fr}}
		</style>
		<?php
	}

	/**
	 * Render a module card.
	 *
	 * @param array<string,mixed> $args Card arguments.
	 * @return void
	 */
	private static function module_card( $args ) {
		$key      = $args['key'];
		$settings = $args['settings'];
		?>
		<div class="wp-agentic-card">
			<div class="wp-agentic-card-header">
				<h3><?php echo esc_html( $args['title'] ); ?></h3>
				<?php self::status_badge( $args['status'], $args['tone'] ); ?>
			</div>
			<p><?php echo esc_html( $args['description'] ); ?></p>
			<?php if ( ! empty( $args['url'] ) ) : ?>
				<div class="wp-agentic-route">
					<small><?php esc_html_e( 'URL / status', 'wp-agentic' ); ?></small><br>
					<a href="<?php echo esc_url( $args['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $args['url'] ); ?></a>
				</div>
			<?php endif; ?>
			<div class="wp-agentic-impact">
				<small><?php esc_html_e( 'Scanner impact', 'wp-agentic' ); ?></small><br>
				<strong><?php echo esc_html( $args['impact'] ); ?></strong>
			</div>
			<label class="wp-agentic-toggle">
				<input type="checkbox" name="<?php echo esc_attr( WP_Agentic_Settings::OPTION_NAME . '[' . $key . ']' ); ?>" value="1" <?php checked( ! empty( $settings[ $key ] ) ); ?>>
				<?php esc_html_e( 'Enabled', 'wp-agentic' ); ?>
			</label>
			<?php
			if ( ! empty( $args['extra'] ) ) {
				echo $args['extra']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			?>
		</div>
		<?php
	}

	/**
	 * Render site metadata card.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @return void
	 */
	private static function metadata_card( $settings ) {
		?>
		<div class="wp-agentic-card">
			<div class="wp-agentic-card-header">
				<h3><?php esc_html_e( 'Site Metadata', 'wp-agentic' ); ?></h3>
				<?php self::status_badge( __( 'Used in discovery', 'wp-agentic' ), 'good' ); ?>
			</div>
			<p><?php esc_html_e( 'These fields identify the publisher, canonical site URL, and human contact handoff used in llms.txt, Agent Skills, and REST context.', 'wp-agentic' ); ?></p>
			<div class="wp-agentic-fields">
				<?php self::text_field( 'publisher_name', __( 'Publisher name', 'wp-agentic' ), $settings ); ?>
				<?php self::url_field( 'publisher_url', __( 'Publisher URL', 'wp-agentic' ), $settings ); ?>
				<?php self::url_field( 'contact_url', __( 'Contact URL', 'wp-agentic' ), $settings ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render WPGraphQL card.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @param bool                $available Whether WPGraphQL is available.
	 * @return void
	 */
	private static function graphql_card( $settings, $available ) {
		?>
		<div class="wp-agentic-card">
			<div class="wp-agentic-card-header">
				<h3><?php esc_html_e( 'WPGraphQL', 'wp-agentic' ); ?></h3>
				<?php self::status_badge( $available ? __( 'Active', 'wp-agentic' ) : __( 'Not detected', 'wp-agentic' ), $available ? 'good' : 'warn' ); ?>
			</div>
			<p><?php esc_html_e( 'WPGraphQL is optional. When it is active and this toggle is enabled, WP Agentic advertises the GraphQL endpoint in the API Catalog.', 'wp-agentic' ); ?></p>
			<?php if ( $available ) : ?>
				<div class="wp-agentic-route">
					<small><?php esc_html_e( 'Detected endpoint', 'wp-agentic' ); ?></small><br>
					<a href="<?php echo esc_url( home_url( 'graphql' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( home_url( 'graphql' ) ); ?></a>
				</div>
			<?php else : ?>
				<div class="wp-agentic-route">
					<small><?php esc_html_e( 'Recommendation', 'wp-agentic' ); ?></small><br>
					<a href="https://wordpress.org/plugins/wp-graphql/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Install WPGraphQL from WordPress.org', 'wp-agentic' ); ?></a>
				</div>
			<?php endif; ?>
			<div class="wp-agentic-impact">
				<small><?php esc_html_e( 'Scanner impact', 'wp-agentic' ); ?></small><br>
				<strong><?php esc_html_e( 'Optional API discoverability. Not required for WebMCP or Markdown.', 'wp-agentic' ); ?></strong>
			</div>
			<label class="wp-agentic-toggle">
				<input type="checkbox" name="<?php echo esc_attr( WP_Agentic_Settings::OPTION_NAME . '[include_graphql_if_active]' ); ?>" value="1" <?php checked( ! empty( $settings['include_graphql_if_active'] ) ); ?>>
				<?php esc_html_e( 'Advertise when active', 'wp-agentic' ); ?>
			</label>
		</div>
		<?php
	}

	/**
	 * Return Content Signals controls as HTML.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @return string
	 */
	private static function content_signal_controls( $settings ) {
		ob_start();
		?>
		<div class="wp-agentic-inline-fields">
			<?php self::yes_no_field( 'content_signal_ai_train', __( 'AI training', 'wp-agentic' ), $settings ); ?>
			<?php self::yes_no_field( 'content_signal_search', __( 'Search', 'wp-agentic' ), $settings ); ?>
			<?php self::yes_no_field( 'content_signal_ai_input', __( 'AI input', 'wp-agentic' ), $settings ); ?>
		</div>
		<p><small><?php echo esc_html( WP_Agentic_Settings::content_signal_value( $settings ) ); ?></small></p>
		<?php
		return ob_get_clean();
	}

	/**
	 * Return exposed post types control as HTML.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @return string
	 */
	private static function post_types_control( $settings ) {
		$name     = WP_Agentic_Settings::OPTION_NAME . '[exposed_post_types][]';
		$selected = WP_Agentic_Settings::exposed_post_types( $settings );
		$types    = get_post_types( array( 'public' => true ), 'objects' );
		unset( $types['attachment'] );

		ob_start();
		?>
		<div class="wp-agentic-route">
			<small><?php esc_html_e( 'Exposed public post types', 'wp-agentic' ); ?></small>
			<div class="wp-agentic-post-types">
				<?php foreach ( $types as $type ) : ?>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $type->name ); ?>" <?php checked( in_array( $type->name, $selected, true ) ); ?>>
						<?php echo esc_html( $type->labels->singular_name ?? $type->name ); ?> <code><?php echo esc_html( $type->name ); ?></code>
					</label>
				<?php endforeach; ?>
			</div>
			<p><small><?php esc_html_e( 'Only published public content from these types can be returned by REST and WebMCP tools.', 'wp-agentic' ); ?></small></p>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render a protocol boundary card.
	 *
	 * @param string $title Title.
	 * @param string $status Status.
	 * @param string $description Description.
	 * @return void
	 */
	private static function protocol_card( $title, $status, $description ) {
		?>
		<div class="wp-agentic-card wp-agentic-protocol">
			<h3>
				<span><?php echo esc_html( $title ); ?></span>
				<?php self::status_badge( $status, 'muted' ); ?>
			</h3>
			<p><?php echo esc_html( $description ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render a checklist row.
	 *
	 * @param string $label Label.
	 * @param bool   $passed Passed.
	 * @return void
	 */
	private static function checklist_item( $label, $passed ) {
		?>
		<li>
			<span class="wp-agentic-check <?php echo esc_attr( $passed ? 'wp-agentic-check-yes' : 'wp-agentic-check-no' ); ?>"><?php echo esc_html( $passed ? 'ok' : '-' ); ?></span>
			<span><?php echo esc_html( $label ); ?></span>
		</li>
		<?php
	}

	/**
	 * Render text field.
	 *
	 * @param string              $key Settings key.
	 * @param string              $label Label.
	 * @param array<string,mixed> $settings Settings.
	 * @return void
	 */
	private static function text_field( $key, $label, $settings ) {
		self::input_field( $key, $label, $settings, 'text' );
	}

	/**
	 * Render URL field.
	 *
	 * @param string              $key Settings key.
	 * @param string              $label Label.
	 * @param array<string,mixed> $settings Settings.
	 * @return void
	 */
	private static function url_field( $key, $label, $settings ) {
		self::input_field( $key, $label, $settings, 'url' );
	}

	/**
	 * Render input field.
	 *
	 * @param string              $key Settings key.
	 * @param string              $label Label.
	 * @param array<string,mixed> $settings Settings.
	 * @param string              $type Input type.
	 * @return void
	 */
	private static function input_field( $key, $label, $settings, $type ) {
		$name = WP_Agentic_Settings::OPTION_NAME . '[' . $key . ']';
		?>
		<div class="wp-agentic-field">
			<label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
			<input type="<?php echo esc_attr( $type ); ?>" id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $settings[ $key ] ?? '' ); ?>">
		</div>
		<?php
	}

	/**
	 * Render yes/no select.
	 *
	 * @param string              $key Settings key.
	 * @param string              $label Label.
	 * @param array<string,mixed> $settings Settings.
	 * @return void
	 */
	private static function yes_no_field( $key, $label, $settings ) {
		$name  = WP_Agentic_Settings::OPTION_NAME . '[' . $key . ']';
		$value = $settings[ $key ] ?? 'yes';
		?>
		<label for="<?php echo esc_attr( $key ); ?>">
			<?php echo esc_html( $label ); ?><br>
			<select id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $name ); ?>">
				<option value="yes" <?php selected( $value, 'yes' ); ?>><?php esc_html_e( 'yes', 'wp-agentic' ); ?></option>
				<option value="no" <?php selected( $value, 'no' ); ?>><?php esc_html_e( 'no', 'wp-agentic' ); ?></option>
			</select>
		</label>
		<?php
	}

	/**
	 * Render status badge.
	 *
	 * @param string $label Label.
	 * @param string $tone Tone.
	 * @return void
	 */
	private static function status_badge( $label, $tone ) {
		$tone = in_array( $tone, array( 'good', 'warn', 'muted' ), true ) ? $tone : 'muted';
		?>
		<span class="wp-agentic-badge wp-agentic-badge-<?php echo esc_attr( $tone ); ?>"><?php echo esc_html( $label ); ?></span>
		<?php
	}

	/**
	 * Enabled label for a setting.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @param string              $key Settings key.
	 * @return string
	 */
	private static function enabled_label( $settings, $key ) {
		return ! empty( $settings[ $key ] ) ? __( 'Enabled', 'wp-agentic' ) : __( 'Disabled', 'wp-agentic' );
	}

	/**
	 * Enabled tone for a setting.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @param string              $key Settings key.
	 * @return string
	 */
	private static function enabled_tone( $settings, $key ) {
		return ! empty( $settings[ $key ] ) ? 'good' : 'muted';
	}

	/**
	 * Diagnostic routes.
	 *
	 * @return array<string,string>
	 */
	private static function diagnostic_routes() {
		return array(
			'robots.txt'        => home_url( 'robots.txt' ),
			'llms.txt'          => home_url( 'llms.txt' ),
			'well-known llms'   => home_url( '.well-known/llms.txt' ),
			'API Catalog'       => home_url( '.well-known/api-catalog' ),
			'Agent Skills'      => home_url( '.well-known/agent-skills/index.json' ),
			'Skill Markdown'    => home_url( '.well-known/agent-skills/search-site/SKILL.md' ),
			'REST context'      => rest_url( 'wp-agentic/v1/context' ),
			'REST search'       => rest_url( 'wp-agentic/v1/search?query=marketing' ),
			'REST recent'       => rest_url( 'wp-agentic/v1/recent' ),
			'WebMCP script'     => home_url( '/' ) . '#wp-agentic-webmcp',
			'WPGraphQL'         => home_url( 'graphql' ),
		);
	}

	/**
	 * Detect WPGraphQL.
	 *
	 * @return bool
	 */
	private static function graphql_available() {
		return class_exists( 'WPGraphQL' ) || function_exists( 'graphql' ) || has_action( 'graphql_register_types' );
	}
}
