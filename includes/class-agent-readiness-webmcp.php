<?php
/**
 * Browser-side WebMCP tool registration.
 *
 * @package Agent_Readiness
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers read-only tools for browsers that expose navigator.modelContext.
 */
class Agent_Readiness_WebMCP {
	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_footer', array( __CLASS__, 'render_script' ), 20 );
	}

	/**
	 * Print the WebMCP registration script on public HTML pages.
	 *
	 * @return void
	 */
	public static function render_script() {
		if ( ! Agent_Readiness_Settings::enabled() || is_admin() || is_feed() || is_robots() || is_embed() ) {
			return;
		}

		$settings = Agent_Readiness_Settings::get();
		if ( empty( $settings['enable_webmcp'] ) ) {
			return;
		}

		$config = array(
			'endpoints' => array(
				'search'  => esc_url_raw( rest_url( 'agent-readiness/v1/search' ) ),
				'content' => esc_url_raw( rest_url( 'agent-readiness/v1/content' ) ),
				'recent'  => esc_url_raw( rest_url( 'agent-readiness/v1/recent' ) ),
				'context' => esc_url_raw( rest_url( 'agent-readiness/v1/context' ) ),
				'contact' => esc_url_raw( rest_url( 'agent-readiness/v1/contact' ) ),
			),
		);
		?>
<script id="agent-readiness-webmcp">
(function () {
  var modelContext = navigator.modelContext;
  if (!modelContext) {
    return;
  }

  var config = <?php echo wp_json_encode( $config, JSON_UNESCAPED_SLASHES ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>;

  function withParams(url, params) {
    var next = new URL(url, window.location.origin);
    Object.keys(params || {}).forEach(function (key) {
      var value = params[key];
      if (value !== undefined && value !== null && value !== '') {
        next.searchParams.set(key, String(value));
      }
    });
    return next.toString();
  }

  async function getJson(url, params) {
    var response = await fetch(withParams(url, params || {}), {
      method: 'GET',
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' }
    });
    var body = await response.json().catch(function () { return {}; });
    if (!response.ok) {
      return { error: body.message || 'Request failed', status: response.status };
    }
    return body;
  }

  var tools = [
    {
      name: 'search_posts',
      description: 'Search public posts and pages on this WordPress site.',
      inputSchema: {
        type: 'object',
        properties: {
          query: { type: 'string', description: 'Search query.' },
          per_page: { type: 'integer', minimum: 1, maximum: 20, description: 'Maximum results to return.' }
        },
        required: ['query']
      },
      execute: function (input) { return getJson(config.endpoints.search, input || {}); },
      annotations: { readOnlyHint: true }
    },
    {
      name: 'read_post',
      description: 'Read a public post or page by id, URL, or slug.',
      inputSchema: {
        type: 'object',
        properties: {
          id: { type: 'integer', description: 'WordPress post ID.' },
          url: { type: 'string', format: 'uri', description: 'Canonical public URL.' },
          slug: { type: 'string', description: 'Post or page slug.' },
          type: { type: 'string', description: 'Optional post type, usually post or page.' }
        }
      },
      execute: function (input) { return getJson(config.endpoints.content, input || {}); },
      annotations: { readOnlyHint: true }
    },
    {
      name: 'list_recent_posts',
      description: 'List recent public posts and pages from this WordPress site.',
      inputSchema: {
        type: 'object',
        properties: {
          per_page: { type: 'integer', minimum: 1, maximum: 20, description: 'Maximum results to return.' }
        }
      },
      execute: function (input) { return getJson(config.endpoints.recent, input || {}); },
      annotations: { readOnlyHint: true }
    },
    {
      name: 'get_site_context',
      description: 'Get public agent-readiness context, discovery URLs, and content policy for this site.',
      inputSchema: { type: 'object', properties: {} },
      execute: function () { return getJson(config.endpoints.context, {}); },
      annotations: { readOnlyHint: true }
    },
    {
      name: 'contact_conversion',
      description: 'Get the public contact URL. This tool does not submit forms.',
      inputSchema: { type: 'object', properties: {} },
      execute: function () { return getJson(config.endpoints.contact, {}); },
      annotations: { readOnlyHint: true }
    }
  ];

  if (typeof modelContext.registerTool === 'function') {
    tools.forEach(function (tool) {
      try {
        modelContext.registerTool(tool);
      } catch (error) {}
    });
    return;
  }

  if (typeof modelContext.provideContext === 'function') {
    try {
      modelContext.provideContext({ tools: tools });
    } catch (error) {}
  }
})();
</script>
		<?php
	}
}
