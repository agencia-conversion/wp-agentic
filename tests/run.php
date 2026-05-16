<?php
define( 'WP_AGENTIC_TESTING', true );
define( 'WP_AGENTIC_VERSION', '0.1.2' );
define( 'ABSPATH', __DIR__ . '/' );

function wp_strip_all_tags( $text ) {
	return strip_tags( $text );
}

function wp_parse_args( $args, $defaults = array() ) {
	return array_merge( $defaults, is_array( $args ) ? $args : array() );
}

function home_url( $path = '' ) {
	return 'https://example.com/' . ltrim( $path, '/' );
}

function get_bloginfo( $show = '' ) {
	return 'Example Site';
}

function esc_url_raw( $url ) {
	return filter_var( $url, FILTER_SANITIZE_URL );
}

function sanitize_text_field( $text ) {
	return trim( strip_tags( (string) $text ) );
}

function sanitize_key( $key ) {
	return strtolower( preg_replace( '/[^a-z0-9_\\-]/', '', (string) $key ) );
}

function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function trailingslashit( $string ) {
	return rtrim( $string, '/' ) . '/';
}

function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component );
}

function has_action( $hook ) {
	return false;
}

function current_user_can( $capability ) {
	return true;
}

function __( $text, $domain = 'default' ) {
	unset( $domain );
	return $text;
}

function esc_html_e( $text, $domain = 'default' ) {
	echo esc_html( __( $text, $domain ) );
}

function esc_attr( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function esc_url( $url ) {
	return filter_var( $url, FILTER_SANITIZE_URL );
}

function checked( $checked ) {
	if ( $checked ) {
		echo 'checked="checked"';
	}
}

function selected( $selected, $current ) {
	if ( (string) $selected === (string) $current ) {
		echo 'selected="selected"';
	}
}

function settings_fields( $option_group ) {
	echo '<input type="hidden" name="option_page" value="' . esc_attr( $option_group ) . '">';
}

function submit_button( $text = 'Save Changes', $type = 'primary', $name = 'submit' ) {
	unset( $type );
	echo '<button name="' . esc_attr( $name ) . '">' . esc_html( $text ) . '</button>';
}

function rest_url( $path = '' ) {
	return 'https://example.com/wp-json/' . ltrim( $path, '/' );
}

function get_post_types( $args = array(), $output = 'names' ) {
	unset( $args );
	if ( 'objects' === $output ) {
		$post        = new stdClass();
		$post->name  = 'post';
		$post->labels = (object) array( 'singular_name' => 'Post' );
		$page        = new stdClass();
		$page->name  = 'page';
		$page->labels = (object) array( 'singular_name' => 'Page' );

		return array(
			'post' => $post,
			'page' => $page,
		);
	}

	return array( 'post', 'page' );
}

require_once __DIR__ . '/../includes/class-wp-agentic-settings.php';
require_once __DIR__ . '/../includes/class-wp-agentic-markdown.php';
require_once __DIR__ . '/../includes/class-wp-agentic-routes.php';
require_once __DIR__ . '/../admin/class-wp-agentic-admin.php';

$failures = 0;

function assert_true( $condition, $message ) {
	global $failures;
	if ( ! $condition ) {
		$failures++;
		echo "FAIL: {$message}\n";
		return;
	}

	echo "PASS: {$message}\n";
}

$settings = WP_Agentic_Settings::sanitize(
	array(
		'publisher_name'          => '<b>Conversion</b>',
		'publisher_url'           => 'https://www.conversion.com.br/',
		'contact_url'             => 'https://www.conversion.com.br/contato/',
		'content_signal_ai_train' => 'yes',
		'content_signal_search'   => 'yes',
		'content_signal_ai_input' => 'yes',
		'enabled'                 => '1',
	)
);

assert_true( 'Conversion' === $settings['publisher_name'], 'settings sanitize publisher name' );
assert_true( 'ai-train=yes, search=yes, ai-input=yes' === WP_Agentic_Settings::content_signal_value( $settings ), 'content signal value' );
assert_true( in_array( 'post', WP_Agentic_Settings::exposed_post_types( $settings ), true ), 'settings expose posts by default' );

assert_true( WP_Agentic_Markdown::accepts_markdown( 'text/html, text/markdown;q=1' ), 'Accept header detects text/markdown' );
assert_true( ! WP_Agentic_Markdown::accepts_markdown( 'text/html, application/json' ), 'Accept header rejects missing markdown' );
assert_true( ! WP_Agentic_Markdown::accepts_markdown( 'text/markdown;q=0, text/html' ), 'Accept header rejects q=0' );

$markdown = WP_Agentic_Markdown::html_to_markdown( '<h1>Hello</h1><p>Read <a href="https://example.com/x">this</a>.</p><script>alert(1)</script>' );
assert_true( false !== strpos( $markdown, '# Hello' ), 'HTML h1 converts to Markdown heading' );
assert_true( false !== strpos( $markdown, '[this](https://example.com/x)' ), 'HTML link converts to Markdown link' );
assert_true( false === strpos( $markdown, 'alert' ), 'script content removed from Markdown' );
assert_true( false === strpos( WP_Agentic_Markdown::html_to_markdown( '<nav>Menu</nav><p>Body</p>' ), 'Menu' ), 'navigation content removed from Markdown' );

$catalog = WP_Agentic_Routes::api_catalog( $settings );
assert_true( isset( $catalog['linkset'][0]['service-desc'][0]['href'] ), 'API catalog exposes service-desc' );
assert_true( 'https://example.com/wp-json/' === $catalog['linkset'][0]['service-desc'][0]['href'], 'API catalog points to REST API' );

$skills = WP_Agentic_Routes::agent_skills( $settings );
assert_true( 'https://schemas.agentskills.io/discovery/0.2.0/schema.json' === $skills['$schema'], 'agent skills exposes v0.2 schema' );
assert_true( 5 === count( $skills['skills'] ), 'agent skills exposes five skills' );
assert_true( 'read-site-content' === $skills['skills'][0]['name'], 'agent skills includes read-site-content' );
assert_true( 0 === strpos( $skills['skills'][0]['digest'], 'sha256:' ), 'agent skills includes sha256 digest' );

$skill_md = WP_Agentic_Routes::agent_skill_markdown( 'search-site', $settings );
assert_true( false !== strpos( $skill_md, 'name: search-site' ), 'SKILL.md includes frontmatter name' );
assert_true( false !== strpos( $skill_md, 'wp-json/wp-agentic/v1/search' ), 'SKILL.md includes REST endpoint' );

$llms = WP_Agentic_Routes::llms_text( $settings );
assert_true( false !== strpos( $llms, '## Agent resources' ), 'llms.txt includes agent resources' );
assert_true( false !== strpos( $llms, 'Content-Signal: ai-train=yes, search=yes, ai-input=yes' ), 'llms.txt includes Content-Signal' );

ob_start();
WP_Agentic_Admin::render_page();
$admin_absent = ob_get_clean();
assert_true( false !== strpos( $admin_absent, 'WP Agentic v0.1.2' ), 'admin footer exposes version' );
assert_true( false !== strpos( $admin_absent, 'MCP Server Card' ), 'admin explains MCP Server Card boundary' );
assert_true( false !== strpos( $admin_absent, 'Not detected' ), 'admin shows WPGraphQL absent state' );
assert_true( false !== strpos( $admin_absent, 'https://wordpress.org/plugins/wp-graphql/' ), 'admin links to WPGraphQL plugin when absent' );
assert_true( false !== strpos( $admin_absent, 'wp_agentic_settings[enable_webmcp]' ), 'admin preserves WebMCP toggle name' );

eval( 'class WPGraphQL {}' );

ob_start();
WP_Agentic_Admin::render_page();
$admin_present = ob_get_clean();
assert_true( false !== strpos( $admin_present, 'https://example.com/graphql' ), 'admin shows WPGraphQL endpoint when active' );
assert_true( false !== strpos( $admin_present, 'Advertise when active' ), 'admin preserves WPGraphQL advertise toggle' );

if ( $failures > 0 ) {
	echo "{$failures} failure(s)\n";
	exit( 1 );
}

echo "All tests passed\n";
