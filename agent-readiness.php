<?php
/**
 * Plugin Name: Agent Readiness
 * Plugin URI: https://conversion.ag/
 * Description: Agent readiness for WordPress: Markdown negotiation, llms.txt, API catalog, agent skills, and AI content signals.
 * Version: 0.1.3
 * Requires at least: 5.9
 * Requires PHP: 7.4
 * Author: Conversion
 * Author URI: https://conversion.ag/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: agent-readiness
 *
 * @package Agent_Readiness
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AGENT_READINESS_VERSION', '0.1.3' );
define( 'AGENT_READINESS_FILE', __FILE__ );
define( 'AGENT_READINESS_PATH', plugin_dir_path( __FILE__ ) );
define( 'AGENT_READINESS_URL', plugin_dir_url( __FILE__ ) );

if ( ! defined( 'WP_AGENTIC_VERSION' ) ) {
	define( 'WP_AGENTIC_VERSION', AGENT_READINESS_VERSION );
}

require_once AGENT_READINESS_PATH . 'includes/class-agent-readiness-settings.php';
require_once AGENT_READINESS_PATH . 'includes/class-agent-readiness-markdown.php';
require_once AGENT_READINESS_PATH . 'includes/class-agent-readiness-rest.php';
require_once AGENT_READINESS_PATH . 'includes/class-agent-readiness-routes.php';
require_once AGENT_READINESS_PATH . 'includes/class-agent-readiness-webmcp.php';
require_once AGENT_READINESS_PATH . 'admin/class-agent-readiness-admin.php';
require_once AGENT_READINESS_PATH . 'includes/class-agent-readiness.php';

register_activation_hook( __FILE__, array( 'Agent_Readiness', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Agent_Readiness', 'deactivate' ) );

Agent_Readiness::init();
