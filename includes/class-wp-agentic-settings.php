<?php
/**
 * Settings model and sanitization.
 *
 * @package WP_Agentic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores defaults and sanitizes persisted settings.
 */
class WP_Agentic_Settings {
	const OPTION_NAME = 'wp_agentic_settings';

	/**
	 * Default settings.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		$site_name = self::wp_value( 'get_bloginfo', array( 'name' ), 'WordPress Site' );
		$home_url  = self::wp_value( 'home_url', array( '/' ), 'https://example.com/' );

		return array(
			'enabled'                   => 1,
			'enable_content_signals'    => 1,
			'enable_llms'               => 1,
			'enable_api_catalog'        => 1,
			'enable_agent_skills'       => 1,
			'enable_markdown'           => 1,
			'enable_webmcp'             => 1,
			'exposed_post_types'        => array( 'post', 'page' ),
			'publisher_name'            => $site_name,
			'publisher_url'             => $home_url,
			'contact_url'               => self::trailingslash( $home_url ) . 'contato/',
			'content_signal_ai_train'   => 'yes',
			'content_signal_search'     => 'yes',
			'content_signal_ai_input'   => 'yes',
			'include_graphql_if_active' => 1,
		);
	}

	/**
	 * Current settings merged with defaults.
	 *
	 * @return array<string,mixed>
	 */
	public static function get() {
		$settings = function_exists( 'get_option' ) ? get_option( self::OPTION_NAME, array() ) : array();

		return wp_parse_args( is_array( $settings ) ? $settings : array(), self::defaults() );
	}

	/**
	 * Is the whole plugin enabled?
	 *
	 * @return bool
	 */
	public static function enabled() {
		$settings = self::get();

		return ! empty( $settings['enabled'] );
	}

	/**
	 * Sanitize settings from wp-admin.
	 *
	 * @param array<string,mixed> $input Raw input.
	 * @return array<string,mixed>
	 */
	public static function sanitize( $input ) {
		$defaults = self::defaults();
		$input    = is_array( $input ) ? $input : array();
		$output   = $defaults;

		$booleans = array(
			'enabled',
			'enable_content_signals',
			'enable_llms',
			'enable_api_catalog',
			'enable_agent_skills',
			'enable_markdown',
			'enable_webmcp',
			'include_graphql_if_active',
		);

		foreach ( $booleans as $key ) {
			$output[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
		}

		$output['publisher_name'] = self::sanitize_text( $input['publisher_name'] ?? $defaults['publisher_name'] );
		$output['publisher_url']  = self::sanitize_url( $input['publisher_url'] ?? $defaults['publisher_url'] );
		$output['contact_url']    = self::sanitize_url( $input['contact_url'] ?? $defaults['contact_url'] );

		foreach ( array( 'content_signal_ai_train', 'content_signal_search', 'content_signal_ai_input' ) as $key ) {
			$value          = isset( $input[ $key ] ) ? (string) $input[ $key ] : 'yes';
			$output[ $key ] = in_array( $value, array( 'yes', 'no' ), true ) ? $value : 'yes';
		}

		$post_types = isset( $input['exposed_post_types'] ) && is_array( $input['exposed_post_types'] ) ? $input['exposed_post_types'] : $defaults['exposed_post_types'];
		$output['exposed_post_types'] = self::sanitize_post_types( $post_types );

		return $output;
	}

	/**
	 * Return public post types exposed to agent read APIs.
	 *
	 * @param array<string,mixed>|null $settings Settings override.
	 * @return array<int,string>
	 */
	public static function exposed_post_types( $settings = null ) {
		$settings   = is_array( $settings ) ? $settings : self::get();
		$post_types = isset( $settings['exposed_post_types'] ) && is_array( $settings['exposed_post_types'] ) ? $settings['exposed_post_types'] : array( 'post', 'page' );

		return self::sanitize_post_types( $post_types );
	}

	/**
	 * Build the Content-Signal header value.
	 *
	 * @param array<string,mixed>|null $settings Settings override.
	 * @return string
	 */
	public static function content_signal_value( $settings = null ) {
		$settings = is_array( $settings ) ? $settings : self::get();

		return sprintf(
			'ai-train=%s, search=%s, ai-input=%s',
			self::yes_no( $settings['content_signal_ai_train'] ?? 'yes' ),
			self::yes_no( $settings['content_signal_search'] ?? 'yes' ),
			self::yes_no( $settings['content_signal_ai_input'] ?? 'yes' )
		);
	}

	/**
	 * Normalize yes/no values.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private static function yes_no( $value ) {
		return 'no' === $value ? 'no' : 'yes';
	}

	/**
	 * WordPress-compatible text sanitization with fallback for tests.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private static function sanitize_text( $value ) {
		$value = (string) $value;

		return function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $value ) : self::fallback_strip_tags( $value );
	}

	/**
	 * Strip HTML tags when WordPress is unavailable in isolated tests.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private static function fallback_strip_tags( $value ) {
		$value = preg_replace( '#<(script|style)\b[^>]*>.*?</\1>#is', '', $value );
		$value = preg_replace( '#<[^>]*>#', '', (string) $value );

		return trim( (string) $value );
	}

	/**
	 * WordPress-compatible URL sanitization with fallback for tests.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private static function sanitize_url( $value ) {
		$value = (string) $value;

		return function_exists( 'esc_url_raw' ) ? esc_url_raw( $value ) : filter_var( $value, FILTER_SANITIZE_URL );
	}

	/**
	 * Sanitize a list of public post type names.
	 *
	 * @param array<mixed> $post_types Raw post type names.
	 * @return array<int,string>
	 */
	private static function sanitize_post_types( $post_types ) {
		$allowed = array( 'post', 'page' );
		if ( function_exists( 'get_post_types' ) ) {
			$allowed = array_values(
				array_filter(
					get_post_types( array( 'public' => true ), 'names' ),
					function ( $post_type ) {
						return 'attachment' !== $post_type;
					}
				)
			);
		}

		$clean = array();
		foreach ( $post_types as $post_type ) {
			$post_type = sanitize_key( (string) $post_type );
			if ( in_array( $post_type, $allowed, true ) ) {
				$clean[] = $post_type;
			}
		}

		$clean = array_values( array_unique( $clean ) );

		return empty( $clean ) ? array( 'post', 'page' ) : $clean;
	}

	/**
	 * Call a WordPress function when available.
	 *
	 * @param string       $function Function name.
	 * @param array<mixed> $args Arguments.
	 * @param mixed        $fallback Fallback value.
	 * @return mixed
	 */
	private static function wp_value( $function, $args, $fallback ) {
		return function_exists( $function ) ? call_user_func_array( $function, $args ) : $fallback;
	}

	/**
	 * Add a trailing slash.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	private static function trailingslash( $url ) {
		return function_exists( 'trailingslashit' ) ? trailingslashit( $url ) : rtrim( $url, '/' ) . '/';
	}
}
