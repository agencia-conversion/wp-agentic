<?php
/**
 * Admin settings screen.
 *
 * @package Agent_Readiness
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders and saves plugin settings.
 */
class Agent_Readiness_Admin {
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
			__( 'Agent Readiness', 'agent-readiness' ),
			__( 'Agent Readiness', 'agent-readiness' ),
			'manage_options',
			'agent-readiness',
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
			Agent_Readiness_Settings::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'Agent_Readiness_Settings', 'sanitize' ),
				'default'           => Agent_Readiness_Settings::defaults(),
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

		$settings          = Agent_Readiness_Settings::get();
		$enabled           = ! empty( $settings['enabled'] );
		$graphql_available = self::graphql_available();
		$routes            = self::diagnostic_routes();
		?>
		<div class="wrap agent-readiness-wrap">
			<?php self::render_styles(); ?>

			<div class="agent-readiness-hero">
				<div>
					<div class="agent-readiness-brand">
						<a href="https://conversion.ag/" target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e( 'Conversion', 'agent-readiness' ); ?>">
							<img src="<?php echo esc_url( AGENT_READINESS_URL . 'assets/conversion-logo-white.svg' ); ?>" alt="<?php esc_attr_e( 'Conversion', 'agent-readiness' ); ?>">
						</a>
						<span class="agent-readiness-brand-divider" aria-hidden="true"></span>
						<h1><?php esc_html_e( 'Agent Readiness', 'agent-readiness' ); ?></h1>
					</div>
					<p class="agent-readiness-kicker"><?php esc_html_e( 'Agent readiness for WordPress', 'agent-readiness' ); ?></p>
					<p class="agent-readiness-hero-copy"><?php esc_html_e( 'Expose public, read-only discovery surfaces for AI agents without publishing fake capabilities.', 'agent-readiness' ); ?></p>
				</div>
				<div class="agent-readiness-hero-meta">
					<?php self::status_badge( $enabled ? __( 'Enabled', 'agent-readiness' ) : __( 'Disabled', 'agent-readiness' ), $enabled ? 'good' : 'muted' ); ?>
					<span class="agent-readiness-version"><?php echo esc_html( 'v' . AGENT_READINESS_VERSION ); ?></span>
				</div>
			</div>

			<form method="post" action="options.php">
				<?php settings_fields( 'wp_agentic' ); ?>

				<div class="agent-readiness-section">
					<div class="agent-readiness-section-heading">
						<h2><?php esc_html_e( 'Core Controls', 'agent-readiness' ); ?></h2>
						<p><?php esc_html_e( 'The global switch is the fastest rollback path. Turning it off restores the normal WordPress behavior for public requests.', 'agent-readiness' ); ?></p>
					</div>
					<div class="agent-readiness-grid agent-readiness-grid-2">
						<?php
						self::module_card(
							array(
								'title'       => __( 'Global Kill Switch', 'agent-readiness' ),
								'key'         => 'enabled',
								'settings'    => $settings,
								'status'      => $enabled ? __( 'Active', 'agent-readiness' ) : __( 'Paused', 'agent-readiness' ),
								'tone'        => $enabled ? 'good' : 'muted',
								'description' => __( 'Controls every Agent Readiness route, header, REST endpoint, Markdown response, and browser tool.', 'agent-readiness' ),
								'impact'      => __( 'Rollback and operational safety.', 'agent-readiness' ),
							)
						);
						self::metadata_card( $settings );
						?>
					</div>
				</div>

				<div class="agent-readiness-section">
					<div class="agent-readiness-section-heading">
						<h2><?php esc_html_e( 'Modules', 'agent-readiness' ); ?></h2>
						<p><?php esc_html_e( 'Each module maps to a concrete public capability used by agent-readiness scanners and AI crawlers.', 'agent-readiness' ); ?></p>
					</div>
					<div class="agent-readiness-grid">
						<?php
						self::module_card(
							array(
								'title'       => __( 'Content Signals', 'agent-readiness' ),
								'key'         => 'enable_content_signals',
								'settings'    => $settings,
								'status'      => self::enabled_label( $settings, 'enable_content_signals' ),
								'tone'        => self::enabled_tone( $settings, 'enable_content_signals' ),
								'description' => __( 'Adds an explicit Content-Signal line to robots.txt and Markdown responses.', 'agent-readiness' ),
								'url'         => home_url( 'robots.txt' ),
								'impact'      => __( 'Content policy discovery.', 'agent-readiness' ),
								'extra'       => self::content_signal_controls( $settings ),
							)
						);
						self::module_card(
							array(
								'title'       => __( 'llms.txt', 'agent-readiness' ),
								'key'         => 'enable_llms',
								'settings'    => $settings,
								'status'      => self::enabled_label( $settings, 'enable_llms' ),
								'tone'        => self::enabled_tone( $settings, 'enable_llms' ),
								'description' => __( 'Publishes /llms.txt and /.well-known/llms.txt with site context and agent resources.', 'agent-readiness' ),
								'url'         => home_url( 'llms.txt' ),
								'impact'      => __( 'Agent-readable site overview.', 'agent-readiness' ),
							)
						);
						self::module_card(
							array(
								'title'       => __( 'API Catalog', 'agent-readiness' ),
								'key'         => 'enable_api_catalog',
								'settings'    => $settings,
								'status'      => self::enabled_label( $settings, 'enable_api_catalog' ),
								'tone'        => self::enabled_tone( $settings, 'enable_api_catalog' ),
								'description' => __( 'Publishes a linkset catalog pointing to WordPress REST, Agent Readiness REST, sitemap, llms.txt, Agent Skills, and WPGraphQL when active.', 'agent-readiness' ),
								'url'         => home_url( '.well-known/api-catalog' ),
								'impact'      => __( 'Structured API discovery.', 'agent-readiness' ),
							)
						);
						self::module_card(
							array(
								'title'       => __( 'Agent Skills v0.2', 'agent-readiness' ),
								'key'         => 'enable_agent_skills',
								'settings'    => $settings,
								'status'      => self::enabled_label( $settings, 'enable_agent_skills' ),
								'tone'        => self::enabled_tone( $settings, 'enable_agent_skills' ),
								'description' => __( 'Publishes a skills index and virtual SKILL.md files for read-only content discovery, search, article reading, recent posts, and contact handoff.', 'agent-readiness' ),
								'url'         => home_url( '.well-known/agent-skills/index.json' ),
								'impact'      => __( 'Agent capability discovery.', 'agent-readiness' ),
							)
						);
						self::module_card(
							array(
								'title'       => __( 'Markdown Negotiation', 'agent-readiness' ),
								'key'         => 'enable_markdown',
								'settings'    => $settings,
								'status'      => self::enabled_label( $settings, 'enable_markdown' ),
								'tone'        => self::enabled_tone( $settings, 'enable_markdown' ),
								'description' => __( 'Returns clean Markdown with frontmatter only when a public GET or HEAD request asks for Accept: text/markdown.', 'agent-readiness' ),
								'url'         => home_url( '/' ),
								'impact'      => __( 'Content Site score and agent parsing quality.', 'agent-readiness' ),
							)
						);
						self::module_card(
							array(
								'title'       => __( 'WebMCP Read-only Tools', 'agent-readiness' ),
								'key'         => 'enable_webmcp',
								'settings'    => $settings,
								'status'      => self::enabled_label( $settings, 'enable_webmcp' ),
								'tone'        => self::enabled_tone( $settings, 'enable_webmcp' ),
								'description' => __( 'Registers browser tools when navigator.modelContext exists. Tools only search, list, read public content, get context, or return the contact URL.', 'agent-readiness' ),
								'url'         => rest_url( 'agent-readiness/v1/context' ),
								'impact'      => __( 'WebMCP scanner check and browser-agent integration.', 'agent-readiness' ),
								'extra'       => self::post_types_control( $settings ),
							)
						);
						self::graphql_card( $settings, $graphql_available );
						?>
					</div>
				</div>

				<?php submit_button( __( 'Save Agent Readiness Settings', 'agent-readiness' ), 'primary large' ); ?>
			</form>

			<div class="agent-readiness-section">
				<div class="agent-readiness-section-heading">
					<h2><?php esc_html_e( 'Protocol Boundaries', 'agent-readiness' ); ?></h2>
					<p><?php esc_html_e( 'These checks stay unpublished until the site has the real service behind them.', 'agent-readiness' ); ?></p>
				</div>
				<div class="agent-readiness-grid agent-readiness-grid-4">
					<?php self::protocol_card( __( 'MCP Server Card', 'agent-readiness' ), __( 'Not published', 'agent-readiness' ), __( 'Agent Readiness currently implements WebMCP in the browser, not a remote MCP server endpoint with transports, auth, and server capabilities.', 'agent-readiness' ) ); ?>
					<?php self::protocol_card( __( 'OAuth Discovery', 'agent-readiness' ), __( 'Not published', 'agent-readiness' ), __( 'OAuth metadata should only exist when the site exposes real OAuth authorization and protected-resource behavior.', 'agent-readiness' ) ); ?>
					<?php self::protocol_card( __( 'A2A Agent Card', 'agent-readiness' ), __( 'Not published', 'agent-readiness' ), __( 'A2A requires an actual agent service. Agent Readiness only exposes public read resources in this release.', 'agent-readiness' ) ); ?>
					<?php self::protocol_card( __( 'Commerce Metadata', 'agent-readiness' ), __( 'Not published', 'agent-readiness' ), __( 'Payment, checkout, or transaction metadata is intentionally absent unless a safe commerce flow is implemented.', 'agent-readiness' ) ); ?>
				</div>
			</div>

			<div class="agent-readiness-section">
				<div class="agent-readiness-section-heading">
					<h2><?php esc_html_e( 'Diagnostics', 'agent-readiness' ); ?></h2>
					<p><?php esc_html_e( 'Use these generated URLs and commands to validate the public behavior after saving settings or purging cache.', 'agent-readiness' ); ?></p>
				</div>
				<div class="agent-readiness-diagnostics">
					<div>
						<h3><?php esc_html_e( 'Generated Endpoints', 'agent-readiness' ); ?></h3>
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
						<h3><?php esc_html_e( 'Checklist', 'agent-readiness' ); ?></h3>
						<ul class="agent-readiness-checklist">
							<?php self::checklist_item( __( 'Markdown negotiation enabled', 'agent-readiness' ), $enabled && ! empty( $settings['enable_markdown'] ) ); ?>
							<?php self::checklist_item( __( 'Agent Skills v0.2 enabled', 'agent-readiness' ), $enabled && ! empty( $settings['enable_agent_skills'] ) ); ?>
							<?php self::checklist_item( __( 'WebMCP tools enabled', 'agent-readiness' ), $enabled && ! empty( $settings['enable_webmcp'] ) ); ?>
							<?php self::checklist_item( __( 'WPGraphQL detected', 'agent-readiness' ), $graphql_available ); ?>
							<?php self::checklist_item( __( 'Link headers enabled', 'agent-readiness' ), $enabled && ( ! empty( $settings['enable_llms'] ) || ! empty( $settings['enable_api_catalog'] ) || ! empty( $settings['enable_agent_skills'] ) ) ); ?>
						</ul>
						<h3><?php esc_html_e( 'curl Commands', 'agent-readiness' ); ?></h3>
						<div class="agent-readiness-code-list">
							<code>curl -I <?php echo esc_html( home_url( '/' ) ); ?></code>
							<code>curl -I -H 'Accept: text/markdown' <?php echo esc_html( home_url( '/' ) ); ?></code>
							<code>curl <?php echo esc_html( home_url( 'robots.txt' ) ); ?></code>
							<code>curl <?php echo esc_html( home_url( '.well-known/api-catalog' ) ); ?></code>
							<code>curl <?php echo esc_html( home_url( '.well-known/agent-skills/index.json' ) ); ?></code>
							<code>curl <?php echo esc_html( home_url( '.well-known/agent-skills/search-site/SKILL.md' ) ); ?></code>
							<code>curl <?php echo esc_html( rest_url( 'agent-readiness/v1/context' ) ); ?></code>
						</div>
					</div>
				</div>
			</div>

			<div class="agent-readiness-footer">
				<a class="agent-readiness-footer-logo" href="https://conversion.ag/" target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e( 'Conversion', 'agent-readiness' ); ?>">
					<img src="<?php echo esc_url( AGENT_READINESS_URL . 'assets/conversion-logo.svg' ); ?>" alt="<?php esc_attr_e( 'Conversion', 'agent-readiness' ); ?>">
				</a>
				<span><?php echo esc_html( 'Agent Readiness v' . AGENT_READINESS_VERSION ); ?></span>
				<a href="<?php echo esc_url( 'https://github.com/agencia-conversion/agent-readiness/releases/tag/v' . AGENT_READINESS_VERSION ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'GitHub release', 'agent-readiness' ); ?></a>
				<a href="https://conversion.ag/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'by Conversion', 'agent-readiness' ); ?></a>
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
			.agent-readiness-wrap{max-width:1240px}
			.agent-readiness-hero{background:#0f172a;color:#fff;border-radius:8px;padding:28px 32px;margin:22px 0;display:flex;align-items:flex-start;justify-content:space-between;gap:24px}
			.agent-readiness-brand{display:flex;align-items:center;gap:16px;margin:0 0 14px}
			.agent-readiness-brand a{display:inline-flex;align-items:center}
			.agent-readiness-brand img{display:block;width:166px;height:auto}
			.agent-readiness-brand-divider{width:1px;height:30px;background:rgba(255,255,255,.32)}
			.agent-readiness-hero h1{color:#fff;font-size:30px;line-height:1.15;margin:0;letter-spacing:0}
			.agent-readiness-kicker{margin:0 0 6px;color:#93c5fd;font-weight:700;text-transform:uppercase;font-size:12px;letter-spacing:.08em}
			.agent-readiness-hero-copy{font-size:15px;max-width:680px;margin:0;color:#dbeafe}
			.agent-readiness-hero-meta{display:flex;align-items:center;gap:10px;flex-wrap:wrap;justify-content:flex-end}
			.agent-readiness-version{border:1px solid rgba(255,255,255,.24);border-radius:999px;padding:5px 10px;color:#e2e8f0;font-weight:700}
			.agent-readiness-section{margin:24px 0}
			.agent-readiness-section-heading{display:flex;align-items:flex-end;justify-content:space-between;gap:20px;margin:0 0 12px}
			.agent-readiness-section-heading h2{margin:0;font-size:20px}
			.agent-readiness-section-heading p{margin:0;color:#4b5563;max-width:720px}
			.agent-readiness-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}
			.agent-readiness-grid-2{grid-template-columns:1fr 2fr}
			.agent-readiness-grid-4{grid-template-columns:repeat(4,minmax(0,1fr))}
			.agent-readiness-card{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:18px;box-shadow:0 1px 2px rgba(0,0,0,.04)}
			.agent-readiness-card-header{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:10px}
			.agent-readiness-card h3{font-size:16px;margin:0;color:#111827}
			.agent-readiness-card p{color:#4b5563;margin:8px 0 0}
			.agent-readiness-card small{color:#6b7280}
			.agent-readiness-toggle{display:flex;align-items:center;gap:8px;font-weight:700;margin-top:14px}
			.agent-readiness-badge{display:inline-flex;align-items:center;border-radius:999px;padding:4px 9px;font-size:12px;font-weight:700;border:1px solid transparent;white-space:nowrap}
			.agent-readiness-badge-good{background:#ecfdf5;color:#047857;border-color:#a7f3d0}
			.agent-readiness-badge-warn{background:#fffbeb;color:#92400e;border-color:#fde68a}
			.agent-readiness-badge-muted{background:#f3f4f6;color:#4b5563;border-color:#e5e7eb}
			.agent-readiness-route{margin-top:12px;padding-top:12px;border-top:1px solid #eef0f3;word-break:break-all}
			.agent-readiness-impact{margin-top:12px;color:#111827}
			.agent-readiness-fields{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-top:12px}
			.agent-readiness-field label{display:block;font-weight:700;margin-bottom:4px}
			.agent-readiness-field input[type=text],.agent-readiness-field input[type=url],.agent-readiness-field select{width:100%;max-width:100%}
			.agent-readiness-inline-fields{display:flex;gap:14px;flex-wrap:wrap;margin-top:12px}
			.agent-readiness-inline-fields label{font-weight:700}
			.agent-readiness-post-types{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:6px;margin-top:12px}
			.agent-readiness-protocol h3{display:flex;align-items:center;justify-content:space-between;gap:10px}
			.agent-readiness-diagnostics{display:grid;grid-template-columns:1.2fr .8fr;gap:18px}
			.agent-readiness-checklist{background:#fff;border:1px solid #dcdcde;border-radius:8px;margin:0 0 18px;padding:12px 16px}
			.agent-readiness-checklist li{display:flex;align-items:center;gap:8px;margin:8px 0;color:#111827}
			.agent-readiness-check{display:inline-flex;align-items:center;justify-content:center;width:20px;height:20px;border-radius:999px;font-size:12px;font-weight:700}
			.agent-readiness-check-yes{background:#ecfdf5;color:#047857}
			.agent-readiness-check-no{background:#f3f4f6;color:#6b7280}
			.agent-readiness-code-list{display:grid;gap:8px}
			.agent-readiness-code-list code{display:block;white-space:normal;background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:9px;line-height:1.45}
			.agent-readiness-footer{border-top:1px solid #dcdcde;margin:28px 0 0;padding:16px 0;color:#4b5563;display:flex;gap:12px;align-items:center;flex-wrap:wrap}
			.agent-readiness-footer-logo{display:inline-flex;align-items:center;margin-right:4px}
			.agent-readiness-footer-logo img{display:block;width:132px;height:auto}
			@media (max-width:1100px){.agent-readiness-grid,.agent-readiness-grid-2,.agent-readiness-grid-4,.agent-readiness-diagnostics{grid-template-columns:1fr 1fr}.agent-readiness-fields{grid-template-columns:1fr}}
			@media (max-width:782px){.agent-readiness-hero,.agent-readiness-section-heading{display:block}.agent-readiness-hero-meta{justify-content:flex-start;margin-top:16px}.agent-readiness-brand{gap:12px;align-items:flex-start;flex-direction:column}.agent-readiness-brand-divider{display:none}.agent-readiness-brand img{width:150px}.agent-readiness-grid,.agent-readiness-grid-2,.agent-readiness-grid-4,.agent-readiness-diagnostics{grid-template-columns:1fr}.agent-readiness-post-types{grid-template-columns:1fr}}
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
		<div class="agent-readiness-card">
			<div class="agent-readiness-card-header">
				<h3><?php echo esc_html( $args['title'] ); ?></h3>
				<?php self::status_badge( $args['status'], $args['tone'] ); ?>
			</div>
			<p><?php echo esc_html( $args['description'] ); ?></p>
			<?php if ( ! empty( $args['url'] ) ) : ?>
				<div class="agent-readiness-route">
					<small><?php esc_html_e( 'URL / status', 'agent-readiness' ); ?></small><br>
					<a href="<?php echo esc_url( $args['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $args['url'] ); ?></a>
				</div>
			<?php endif; ?>
			<div class="agent-readiness-impact">
				<small><?php esc_html_e( 'Scanner impact', 'agent-readiness' ); ?></small><br>
				<strong><?php echo esc_html( $args['impact'] ); ?></strong>
			</div>
			<label class="agent-readiness-toggle">
				<input type="checkbox" name="<?php echo esc_attr( Agent_Readiness_Settings::OPTION_NAME . '[' . $key . ']' ); ?>" value="1" <?php checked( ! empty( $settings[ $key ] ) ); ?>>
				<?php esc_html_e( 'Enabled', 'agent-readiness' ); ?>
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
		<div class="agent-readiness-card">
			<div class="agent-readiness-card-header">
				<h3><?php esc_html_e( 'Site Metadata', 'agent-readiness' ); ?></h3>
				<?php self::status_badge( __( 'Used in discovery', 'agent-readiness' ), 'good' ); ?>
			</div>
			<p><?php esc_html_e( 'These fields identify the publisher, canonical site URL, and human contact handoff used in llms.txt, Agent Skills, and REST context.', 'agent-readiness' ); ?></p>
			<div class="agent-readiness-fields">
				<?php self::text_field( 'publisher_name', __( 'Publisher name', 'agent-readiness' ), $settings ); ?>
				<?php self::url_field( 'publisher_url', __( 'Publisher URL', 'agent-readiness' ), $settings ); ?>
				<?php self::url_field( 'contact_url', __( 'Contact URL', 'agent-readiness' ), $settings ); ?>
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
		<div class="agent-readiness-card">
			<div class="agent-readiness-card-header">
				<h3><?php esc_html_e( 'WPGraphQL', 'agent-readiness' ); ?></h3>
				<?php self::status_badge( $available ? __( 'Active', 'agent-readiness' ) : __( 'Not detected', 'agent-readiness' ), $available ? 'good' : 'warn' ); ?>
			</div>
			<p><?php esc_html_e( 'WPGraphQL is optional. When it is active and this toggle is enabled, Agent Readiness advertises the GraphQL endpoint in the API Catalog.', 'agent-readiness' ); ?></p>
			<?php if ( $available ) : ?>
				<div class="agent-readiness-route">
					<small><?php esc_html_e( 'Detected endpoint', 'agent-readiness' ); ?></small><br>
					<a href="<?php echo esc_url( home_url( 'graphql' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( home_url( 'graphql' ) ); ?></a>
				</div>
			<?php else : ?>
				<div class="agent-readiness-route">
					<small><?php esc_html_e( 'Recommendation', 'agent-readiness' ); ?></small><br>
					<a href="https://wordpress.org/plugins/wp-graphql/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Install WPGraphQL from WordPress.org', 'agent-readiness' ); ?></a>
				</div>
			<?php endif; ?>
			<div class="agent-readiness-impact">
				<small><?php esc_html_e( 'Scanner impact', 'agent-readiness' ); ?></small><br>
				<strong><?php esc_html_e( 'Optional API discoverability. Not required for WebMCP or Markdown.', 'agent-readiness' ); ?></strong>
			</div>
			<label class="agent-readiness-toggle">
				<input type="checkbox" name="<?php echo esc_attr( Agent_Readiness_Settings::OPTION_NAME . '[include_graphql_if_active]' ); ?>" value="1" <?php checked( ! empty( $settings['include_graphql_if_active'] ) ); ?>>
				<?php esc_html_e( 'Advertise when active', 'agent-readiness' ); ?>
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
		<div class="agent-readiness-inline-fields">
			<?php self::yes_no_field( 'content_signal_ai_train', __( 'AI training', 'agent-readiness' ), $settings ); ?>
			<?php self::yes_no_field( 'content_signal_search', __( 'Search', 'agent-readiness' ), $settings ); ?>
			<?php self::yes_no_field( 'content_signal_ai_input', __( 'AI input', 'agent-readiness' ), $settings ); ?>
		</div>
		<p><small><?php echo esc_html( Agent_Readiness_Settings::content_signal_value( $settings ) ); ?></small></p>
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
		$name     = Agent_Readiness_Settings::OPTION_NAME . '[exposed_post_types][]';
		$selected = Agent_Readiness_Settings::exposed_post_types( $settings );
		$types    = get_post_types( array( 'public' => true ), 'objects' );
		unset( $types['attachment'] );

		ob_start();
		?>
		<div class="agent-readiness-route">
			<small><?php esc_html_e( 'Exposed public post types', 'agent-readiness' ); ?></small>
			<div class="agent-readiness-post-types">
				<?php foreach ( $types as $type ) : ?>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $type->name ); ?>" <?php checked( in_array( $type->name, $selected, true ) ); ?>>
						<?php echo esc_html( $type->labels->singular_name ?? $type->name ); ?> <code><?php echo esc_html( $type->name ); ?></code>
					</label>
				<?php endforeach; ?>
			</div>
			<p><small><?php esc_html_e( 'Only published public content from these types can be returned by REST and WebMCP tools.', 'agent-readiness' ); ?></small></p>
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
		<div class="agent-readiness-card agent-readiness-protocol">
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
			<span class="agent-readiness-check <?php echo esc_attr( $passed ? 'agent-readiness-check-yes' : 'agent-readiness-check-no' ); ?>"><?php echo esc_html( $passed ? 'ok' : '-' ); ?></span>
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
		$name = Agent_Readiness_Settings::OPTION_NAME . '[' . $key . ']';
		?>
		<div class="agent-readiness-field">
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
		$name  = Agent_Readiness_Settings::OPTION_NAME . '[' . $key . ']';
		$value = $settings[ $key ] ?? 'yes';
		?>
		<label for="<?php echo esc_attr( $key ); ?>">
			<?php echo esc_html( $label ); ?><br>
			<select id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $name ); ?>">
				<option value="yes" <?php selected( $value, 'yes' ); ?>><?php esc_html_e( 'yes', 'agent-readiness' ); ?></option>
				<option value="no" <?php selected( $value, 'no' ); ?>><?php esc_html_e( 'no', 'agent-readiness' ); ?></option>
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
		<span class="agent-readiness-badge agent-readiness-badge-<?php echo esc_attr( $tone ); ?>"><?php echo esc_html( $label ); ?></span>
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
		return ! empty( $settings[ $key ] ) ? __( 'Enabled', 'agent-readiness' ) : __( 'Disabled', 'agent-readiness' );
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
			'REST context'      => rest_url( 'agent-readiness/v1/context' ),
			'REST search'       => rest_url( 'agent-readiness/v1/search?query=marketing' ),
			'REST recent'       => rest_url( 'agent-readiness/v1/recent' ),
			'WebMCP script'     => home_url( '/' ) . '#agent-readiness-webmcp',
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
