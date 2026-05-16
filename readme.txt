=== Agent Readiness ===
Contributors: conversion
Tags: ai, agents, markdown, llms.txt, robots.txt
Requires at least: 5.9
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 0.1.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Agent readiness for WordPress: Markdown negotiation, llms.txt, API catalog, agent skills, and AI content signals.

== Description ==

Agent Readiness helps WordPress sites expose public, read-only discovery surfaces for AI agents without pretending to support capabilities that are not implemented.

Features:

* Markdown negotiation for public content with `Accept: text/markdown`.
* Content Signals in `robots.txt`.
* Generated `llms.txt` and `/.well-known/llms.txt`.
* Generated `/.well-known/api-catalog`.
* Generated `/.well-known/agent-skills/index.json` and virtual `SKILL.md` files.
* Read-only WebMCP tool registration for compatible browsers.
* Public read-only REST endpoints for site search, recent content, single content reads, site context, and contact handoff.
* Admin settings page with a global kill switch.

Agent Readiness does not publish fake OAuth, MCP, A2A, or commerce metadata.

== Installation ==

1. Upload the plugin ZIP in Plugins > Add New > Upload Plugin.
2. Activate Agent Readiness.
3. Open Settings > Agent Readiness.
4. Review metadata and enabled modules.
5. Purge site cache after activation.

== Frequently Asked Questions ==

= Does this change the HTML site? =

No. Normal browser and crawler requests continue to receive HTML. Markdown is returned only when the request explicitly sends `Accept: text/markdown`.

= Does this create an MCP server? =

No. Agent Readiness publishes only real read-only resources. It does not publish an MCP Server Card unless a real MCP server exists.

== Changelog ==

= 0.1.3 =
* Rename the public plugin name and distribution slug to Agent Readiness for WordPress.org directory compatibility.
* Add Conversion branding assets to the admin header and footer.
* Update plugin credit links to conversion.ag.
* Keep existing settings keys for smoother upgrades from earlier builds.

= 0.1.2 =
* Redesign the admin settings page as a richer dashboard with module explanations, scanner impact, diagnostics, and version footer.
* Add WPGraphQL status detection and installation guidance when WPGraphQL is not detected.
* Document why WebMCP is different from an MCP Server Card and keep fake OAuth, MCP Server Card, A2A, and commerce metadata unpublished.

= 0.1.1 =
* Add read-only WebMCP tools.
* Add public read-only Agent Readiness REST endpoints.
* Update Agent Skills discovery to v0.2 with virtual SKILL.md artifacts and SHA-256 digests.
* Add Markdown frontmatter and Content-Signal response headers.
* Add explicit Link headers for agent resources.

= 0.1.0 =
* Initial release.
