<?php
/**
 * Public read-only REST endpoints for agent tools.
 *
 * @package WP_Agentic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exposes safe public content APIs used by WebMCP tools.
 */
class WP_Agentic_REST {
	const NAMESPACE = 'wp-agentic/v1';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register read-only REST routes.
	 *
	 * @return void
	 */
	public static function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'context' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/search',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'search' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'query'    => array( 'type' => 'string' ),
					'search'   => array( 'type' => 'string' ),
					'per_page' => array( 'type' => 'integer' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/content',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'content' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'id'   => array( 'type' => 'integer' ),
					'url'  => array( 'type' => 'string' ),
					'slug' => array( 'type' => 'string' ),
					'type' => array( 'type' => 'string' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/recent',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'recent' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'per_page' => array( 'type' => 'integer' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/context',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'context' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/contact',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'contact' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Search public content.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function search( $request ) {
		if ( ! self::available() ) {
			return self::disabled_error();
		}

		$query = (string) ( $request->get_param( 'query' ) ?: $request->get_param( 'search' ) );
		$query = sanitize_text_field( $query );
		if ( '' === $query ) {
			return new WP_Error( 'wp_agentic_missing_query', __( 'Missing search query.', 'wp-agentic' ), array( 'status' => 400 ) );
		}

		$posts = self::query_posts(
			array(
				's'              => $query,
				'posts_per_page' => self::per_page( $request ),
			)
		);

		return rest_ensure_response(
			array(
				'query'   => $query,
				'results' => array_map( array( __CLASS__, 'prepare_summary' ), $posts ),
			)
		);
	}

	/**
	 * Read one public post or page by id, URL, or slug.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function content( $request ) {
		if ( ! self::available() ) {
			return self::disabled_error();
		}

		$post = self::resolve_post( $request );
		if ( ! $post ) {
			return new WP_Error( 'wp_agentic_not_found', __( 'Public content not found.', 'wp-agentic' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response( self::prepare_content( $post ) );
	}

	/**
	 * List recent public content.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function recent( $request ) {
		if ( ! self::available() ) {
			return self::disabled_error();
		}

		$posts = self::query_posts(
			array(
				'posts_per_page' => self::per_page( $request ),
			)
		);

		return rest_ensure_response(
			array(
				'results' => array_map( array( __CLASS__, 'prepare_summary' ), $posts ),
			)
		);
	}

	/**
	 * Return site-level agent context.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function context() {
		if ( ! self::available() ) {
			return self::disabled_error();
		}

		$settings = WP_Agentic_Settings::get();

		return rest_ensure_response(
			array(
				'name'            => sanitize_text_field( $settings['publisher_name'] ?? get_bloginfo( 'name' ) ),
				'url'             => esc_url_raw( $settings['publisher_url'] ?? home_url( '/' ) ),
				'contact_url'     => esc_url_raw( $settings['contact_url'] ?? home_url( 'contato/' ) ),
				'content_signal'  => WP_Agentic_Settings::content_signal_value( $settings ),
				'exposed_types'   => WP_Agentic_Settings::exposed_post_types( $settings ),
				'agent_resources' => array(
					'llms'         => home_url( 'llms.txt' ),
					'api_catalog'  => home_url( '.well-known/api-catalog' ),
					'agent_skills' => home_url( '.well-known/agent-skills/index.json' ),
					'rest_api'     => home_url( 'wp-json/' ),
				),
			)
		);
	}

	/**
	 * Return safe contact handoff information.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function contact() {
		if ( ! self::available() ) {
			return self::disabled_error();
		}

		$settings = WP_Agentic_Settings::get();

		return rest_ensure_response(
			array(
				'contact_url'        => esc_url_raw( $settings['contact_url'] ?? home_url( 'contato/' ) ),
				'human_confirmation' => true,
				'actions'            => array(),
				'note'               => 'Open the public contact page. WP Agentic does not submit forms automatically.',
			)
		);
	}

	/**
	 * Whether public agent REST endpoints are available.
	 *
	 * @return bool
	 */
	private static function available() {
		if ( ! WP_Agentic_Settings::enabled() ) {
			return false;
		}

		$settings = WP_Agentic_Settings::get();

		return ! empty( $settings['enable_webmcp'] );
	}

	/**
	 * Disabled route error.
	 *
	 * @return WP_Error
	 */
	private static function disabled_error() {
		return new WP_Error( 'wp_agentic_disabled', __( 'WP Agentic read tools are disabled.', 'wp-agentic' ), array( 'status' => 404 ) );
	}

	/**
	 * Run a public-content query.
	 *
	 * @param array<string,mixed> $args Query overrides.
	 * @return array<int,WP_Post>
	 */
	private static function query_posts( $args ) {
		$settings = WP_Agentic_Settings::get();
		$query    = new WP_Query(
			wp_parse_args(
				$args,
				array(
					'post_type'           => WP_Agentic_Settings::exposed_post_types( $settings ),
					'post_status'         => 'publish',
					'ignore_sticky_posts' => true,
					'no_found_rows'       => true,
					'orderby'             => 'date',
					'order'               => 'DESC',
				)
			)
		);

		return is_array( $query->posts ) ? $query->posts : array();
	}

	/**
	 * Resolve one post from request params.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_Post|null
	 */
	private static function resolve_post( $request ) {
		$settings      = WP_Agentic_Settings::get();
		$allowed_types = WP_Agentic_Settings::exposed_post_types( $settings );
		$id            = absint( $request->get_param( 'id' ) );

		if ( $id > 0 ) {
			$post = get_post( $id );
			return self::is_public_allowed_post( $post, $allowed_types ) ? $post : null;
		}

		$url = esc_url_raw( (string) $request->get_param( 'url' ) );
		if ( '' !== $url && function_exists( 'url_to_postid' ) ) {
			$post_id = url_to_postid( $url );
			if ( $post_id > 0 ) {
				$post = get_post( $post_id );
				return self::is_public_allowed_post( $post, $allowed_types ) ? $post : null;
			}
		}

		$slug = sanitize_title( (string) $request->get_param( 'slug' ) );
		if ( '' === $slug ) {
			return null;
		}

		$type = sanitize_key( (string) $request->get_param( 'type' ) );
		$args = array(
			'name'           => $slug,
			'post_type'      => in_array( $type, $allowed_types, true ) ? $type : $allowed_types,
			'post_status'    => 'publish',
			'posts_per_page' => 1,
		);

		if ( 'page' === $type ) {
			unset( $args['name'] );
			$args['pagename'] = $slug;
		}

		$posts = self::query_posts( $args );

		return isset( $posts[0] ) ? $posts[0] : null;
	}

	/**
	 * Check post visibility and type.
	 *
	 * @param mixed             $post Post object.
	 * @param array<int,string> $allowed_types Post types.
	 * @return bool
	 */
	private static function is_public_allowed_post( $post, $allowed_types ) {
		return $post instanceof WP_Post && 'publish' === get_post_status( $post ) && in_array( $post->post_type, $allowed_types, true );
	}

	/**
	 * Prepare a compact post summary.
	 *
	 * @param WP_Post $post Post.
	 * @return array<string,mixed>
	 */
	private static function prepare_summary( $post ) {
		return array(
			'id'            => (int) $post->ID,
			'type'          => $post->post_type,
			'title'         => html_entity_decode( wp_strip_all_tags( get_the_title( $post ) ), ENT_QUOTES, 'UTF-8' ),
			'url'           => get_permalink( $post ),
			'excerpt'       => self::excerpt( $post ),
			'date'          => get_the_date( DATE_W3C, $post ),
			'date_modified' => get_the_modified_date( DATE_W3C, $post ),
		);
	}

	/**
	 * Prepare full content payload.
	 *
	 * @param WP_Post $post Post.
	 * @return array<string,mixed>
	 */
	private static function prepare_content( $post ) {
		return array_merge(
			self::prepare_summary( $post ),
			array(
				'markdown' => WP_Agentic_Markdown::markdown_for_post( $post ),
			)
		);
	}

	/**
	 * Build a safe excerpt.
	 *
	 * @param WP_Post $post Post.
	 * @return string
	 */
	private static function excerpt( $post ) {
		$excerpt = has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_trim_words( preg_replace( '#<[^>]+>#', ' ', $post->post_content ), 40 );

		return html_entity_decode( wp_strip_all_tags( $excerpt ), ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Bounded per_page param.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return int
	 */
	private static function per_page( $request ) {
		$per_page = absint( $request->get_param( 'per_page' ) );
		if ( $per_page < 1 ) {
			$per_page = 10;
		}

		return min( $per_page, 20 );
	}
}
