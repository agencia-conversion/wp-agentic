<?php
/**
 * Markdown content negotiation.
 *
 * @package WP_Agentic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Serves public WordPress content as Markdown when requested.
 */
class WP_Agentic_Markdown {
	/**
	 * Maybe serve the current request as Markdown.
	 *
	 * @return void
	 */
	public static function maybe_serve_markdown() {
		if ( ! WP_Agentic_Settings::enabled() ) {
			return;
		}

		$settings = WP_Agentic_Settings::get();
		if ( empty( $settings['enable_markdown'] ) || ! self::current_request_accepts_markdown() || ! self::is_allowed_request() ) {
			return;
		}

		$markdown = self::markdown_for_current_query();
		if ( '' === trim( $markdown ) ) {
			return;
		}

		self::send_markdown( $markdown );
	}

	/**
	 * Parse an Accept header for text/markdown support.
	 *
	 * @param string $accept Accept header.
	 * @return bool
	 */
	public static function accepts_markdown( $accept ) {
		if ( '' === trim( $accept ) ) {
			return false;
		}

		$parts = explode( ',', strtolower( $accept ) );
		foreach ( $parts as $part ) {
			$media = trim( explode( ';', $part )[0] );
			if ( 'text/markdown' === $media ) {
				return false === strpos( $part, 'q=0' );
			}
		}

		return false;
	}

	/**
	 * Convert HTML into conservative Markdown.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function html_to_markdown( $html ) {
		if ( function_exists( 'strip_shortcodes' ) ) {
			$html = strip_shortcodes( $html );
		}

		$html = preg_replace( '#<(script|style|noscript|svg|iframe|form|button|nav|header|footer)\b[^>]*>.*?</\1>#is', '', $html );
		$html = preg_replace( '#<br\s*/?>#i', "\n", $html );
		$html = preg_replace( '#</p>#i', "\n\n", $html );
		$html = preg_replace( '#<h1[^>]*>(.*?)</h1>#is', "\n# $1\n\n", $html );
		$html = preg_replace( '#<h2[^>]*>(.*?)</h2>#is', "\n## $1\n\n", $html );
		$html = preg_replace( '#<h3[^>]*>(.*?)</h3>#is', "\n### $1\n\n", $html );
		$html = preg_replace( '#<h4[^>]*>(.*?)</h4>#is', "\n#### $1\n\n", $html );
		$html = preg_replace( '#<li[^>]*>(.*?)</li>#is', "\n- $1", $html );
		$html = preg_replace_callback(
			'#<a\s+[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)</a>#is',
			function ( $matches ) {
				$text = trim( wp_strip_all_tags( $matches[2] ) );
				$url  = trim( html_entity_decode( $matches[1], ENT_QUOTES, 'UTF-8' ) );

				if ( '' === $text || '' === $url ) {
					return $text;
				}

				return '[' . $text . '](' . $url . ')';
			},
			$html
		);

		$text = wp_strip_all_tags( $html );
		$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
		$text = preg_replace( "/[ \t]+\n/", "\n", $text );
		$text = preg_replace( "/\n{3,}/", "\n\n", $text );

		return trim( $text ) . "\n";
	}

	/**
	 * Build Markdown for a public post object.
	 *
	 * @param WP_Post|int $post Post object or ID.
	 * @return string
	 */
	public static function markdown_for_post( $post ) {
		$post = get_post( $post );
		if ( ! $post || 'publish' !== get_post_status( $post ) ) {
			return '';
		}

		setup_postdata( $post );
		$title       = get_the_title( $post );
		$url         = get_permalink( $post );
		$description = has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_trim_words( self::plain_text( preg_replace( '#<[^>]+>#', ' ', $post->post_content ) ), 40 );
		$content     = apply_filters( 'the_content', $post->post_content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Applying the core content filter is intentional.
		$frontmatter = self::frontmatter(
			array(
				'title'          => self::plain_text( $title ),
				'canonical_url'  => esc_url_raw( $url ),
				'description'    => self::plain_text( $description ),
				'date_published' => get_the_date( DATE_W3C, $post ),
				'date_modified'  => get_the_modified_date( DATE_W3C, $post ),
				'author'         => self::plain_text( get_the_author_meta( 'display_name', (int) $post->post_author ) ),
				'content_type'   => get_post_type( $post ),
				'categories'     => self::term_names( $post, 'category' ),
			)
		);
		wp_reset_postdata();

		$markdown  = $frontmatter;
		$markdown .= '# ' . self::plain_text( $title ) . "\n\n";
		$markdown .= 'Source: ' . esc_url_raw( $url ) . "\n\n";
		$markdown .= self::html_to_markdown( $content );

		return $markdown;
	}

	/**
	 * Estimate tokens for diagnostics.
	 *
	 * @param string $markdown Markdown.
	 * @return int
	 */
	public static function estimate_tokens( $markdown ) {
		$words = preg_split( '/\s+/', trim( $markdown ) );
		$count = is_array( $words ) && '' !== trim( $markdown ) ? count( $words ) : 0;

		return (int) ceil( $count * 1.33 );
	}

	/**
	 * Check current Accept header.
	 *
	 * @return bool
	 */
	private static function current_request_accepts_markdown() {
		$accept = isset( $_SERVER['HTTP_ACCEPT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT'] ) ) : '';

		return self::accepts_markdown( $accept );
	}

	/**
	 * Guard routes and methods that must stay untouched.
	 *
	 * @return bool
	 */
	private static function is_allowed_request() {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
		if ( ! in_array( $method, array( 'GET', 'HEAD' ), true ) ) {
			return false;
		}

		if ( is_admin() || wp_doing_ajax() || is_feed() || is_robots() || is_trackback() || is_embed() ) {
			return false;
		}

		$uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		if ( preg_match( '#/(wp-admin|wp-login\.php|wp-json|xmlrpc\.php|wp-content|wp-includes|\.well-known)/#i', $uri ) ) {
			return false;
		}

		$path = wp_parse_url( $uri, PHP_URL_PATH );
		if ( preg_match( '#/(robots\.txt|llms\.txt)$#i', $path ?: '' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Generate Markdown for the current query.
	 *
	 * @return string
	 */
	private static function markdown_for_current_query() {
		if ( is_singular() ) {
			return self::singular_markdown();
		}

		return self::archive_markdown();
	}

	/**
	 * Markdown for a single public post/page.
	 *
	 * @return string
	 */
	private static function singular_markdown() {
		$post = get_post();

		return $post ? self::markdown_for_post( $post ) : '';
	}

	/**
	 * Markdown for home, archives, and searches.
	 *
	 * @return string
	 */
	private static function archive_markdown() {
		global $wp_query;

		$title = self::archive_title();
		$items = array();

		if ( $wp_query && ! empty( $wp_query->posts ) ) {
			foreach ( $wp_query->posts as $post ) {
				if ( 'publish' !== get_post_status( $post ) ) {
					continue;
				}

				$excerpt = has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_trim_words( wp_strip_all_tags( $post->post_content ), 40 );
				$items[] = '- [' . self::plain_text( get_the_title( $post ) ) . '](' . esc_url_raw( get_permalink( $post ) ) . '): ' . self::plain_text( $excerpt );
			}
		}

		$url      = esc_url_raw( home_url( add_query_arg( null, null ) ) );
		$markdown = self::frontmatter(
			array(
				'title'         => self::plain_text( $title ),
				'canonical_url' => $url,
				'content_type'  => is_search() ? 'search' : ( is_archive() ? 'archive' : 'home' ),
			)
		);
		$markdown .= '# ' . self::plain_text( $title ) . "\n\n";
		$markdown .= 'Source: ' . $url . "\n\n";
		$markdown .= empty( $items ) ? "No public content found.\n" : implode( "\n", $items ) . "\n";

		return $markdown;
	}

	/**
	 * Human title for the current archive view.
	 *
	 * @return string
	 */
	private static function archive_title() {
		if ( is_search() ) {
			return 'Search results for ' . get_search_query();
		}

		if ( is_archive() ) {
			return get_the_archive_title();
		}

		return get_bloginfo( 'name' );
	}

	/**
	 * Send Markdown and exit.
	 *
	 * @param string $markdown Markdown.
	 * @return never
	 */
	private static function send_markdown( $markdown ) {
		if ( function_exists( 'do_action' ) ) {
				do_action( 'litespeed_control_set_nocache', 'wp-agentic-markdown' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- LiteSpeed exposes this third-party cache hook.
		}

		nocache_headers();
		status_header( 200 );
		header( 'Content-Type: text/markdown; charset=UTF-8' );
		header( 'Vary: Accept', false );
		header( 'X-Markdown-Tokens: ' . self::estimate_tokens( $markdown ) );
		header( 'X-WP-Agentic: 1' );
		header( 'Content-Signal: ' . WP_Agentic_Settings::content_signal_value() );
		echo $markdown; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Build YAML frontmatter for Markdown responses.
	 *
	 * @param array<string,mixed> $data Metadata.
	 * @return string
	 */
	private static function frontmatter( $data ) {
		$lines = array( '---' );

		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) ) {
				if ( empty( $value ) ) {
					continue;
				}

				$lines[] = sanitize_key( $key ) . ':';
				foreach ( $value as $item ) {
					$lines[] = '  - "' . self::yaml_escape( $item ) . '"';
				}
				continue;
			}

			$value = trim( (string) $value );
			if ( '' === $value ) {
				continue;
			}

			$lines[] = sanitize_key( $key ) . ': "' . self::yaml_escape( $value ) . '"';
		}

		$lines[] = '---';

		return implode( "\n", $lines ) . "\n\n";
	}

	/**
	 * Get term names for frontmatter.
	 *
	 * @param WP_Post $post Post.
	 * @param string  $taxonomy Taxonomy.
	 * @return array<int,string>
	 */
	private static function term_names( $post, $taxonomy ) {
		$terms = get_the_terms( $post, $taxonomy );
		if ( ! is_array( $terms ) ) {
			return array();
		}

		return array_values(
			array_map(
				function ( $term ) {
					return self::plain_text( $term->name );
				},
				$terms
			)
		);
	}

	/**
	 * Escape a scalar value for double-quoted YAML.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private static function yaml_escape( $value ) {
		return str_replace( array( '\\', '"' ), array( '\\\\', '\"' ), self::plain_text( (string) $value ) );
	}

	/**
	 * Plain text helper.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function plain_text( $text ) {
		return trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( html_entity_decode( (string) $text, ENT_QUOTES, 'UTF-8' ) ) ) );
	}
}
