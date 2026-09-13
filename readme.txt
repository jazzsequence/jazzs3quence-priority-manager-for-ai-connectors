=== jazzs3quence Priority Manager for AI Connectors ===
Contributors: jazzs3quence
Tags: ai, llm, connectors, providers, priority
Requires at least: 7.0
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: 1.3.0
License: MIT
License URI: https://opensource.org/licenses/MIT

Choose which AI provider to use for each task type when multiple providers are connected.

== Description ==

When you have multiple AI providers connected via Settings → Connectors, WordPress uses a built-in default provider. This plugin lets you choose which provider to use for each task type from an admin settings page.

Go to **Settings → AI Priority** to set your preferred provider for:

* **Text generation** — title generation, excerpt, summarization, content resizing, editorial notes, meta descriptions, content classification, content translation, slug generation, suggested replies, comment moderation
* **Image generation** — featured image generation, inline image generation
* **Vision** — alt text generation, image analysis

Any active AI provider plugin is automatically detected — including third-party providers beyond the built-in Anthropic, Google, and OpenAI options.

Requires the [AI plugin](https://wordpress.org/plugins/ai/) and at least one active AI provider plugin.

== Installation ==

1. Search for "jazzs3quence Priority Manager for AI Connectors" in the WordPress plugins screen and click **Install Now**, or upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin through the Plugins screen
3. Go to **Settings → AI Priority** to configure your preferred provider

== Frequently Asked Questions ==

= What happens if my preferred provider's plugin is deactivated? =

This plugin removes deactivated providers from the model list before it reaches the AI plugin. If your preferred provider is deactivated, the AI plugin uses whatever provider comes first in its default list.

= Are there AI features this plugin does not affect? =

Most AI plugin features ask the AI plugin for its preferred model list, which is what this plugin reorders. A few features pin their own model list instead — Type Ahead is the current example — so they always use the models the AI plugin hard-codes for them regardless of what you select here.

= How does this interact with the AI plugin's Developer Mode? =

Developer Mode (Settings → AI → Developer Mode) configures a specific provider and model for individual AI features (e.g. Title Generation, Alt Text). This plugin sets a preferred provider per task type (text, image, vision), which covers multiple features each.

When Developer Mode has a provider and model set for a specific feature, that feature ignores this plugin's preference entirely — the Developer Mode selection wins. Features without a Developer Mode override use this plugin's selection as normal.

The settings page will show a notice next to any task type that has at least one feature with a Developer Mode override active.

== Changelog ==

= 1.3.0 =
* Added the AI plugin 1.3.0 text features to Developer Mode override detection: Content Translation (`content-translation`), Slug Generation (`slug-generation`) and Suggest Reply (`suggest-reply`). Overrides on those features are now reported on the settings page instead of being silently ignored
* Documented that Type Ahead pins its own model list and is therefore unaffected by this plugin's selection
* Updated the task type descriptions to list the features each one actually covers
* Tested up to WordPress 7.1

= 1.2.1 =
* Move inline admin CSS to a separate file (`assets/css/admin.css`) and enqueue via `wp_enqueue_style()` on `admin_enqueue_scripts`

= 1.2.0 =
* Renamed plugin to jazzs3quence Priority Manager for AI Connectors; updated slug, namespace (`Jazzs3quence\AIPriorityManager`), and Packagist package name

= 1.1.1 =
* Normalized internal prefixes to `aicp_` throughout — option key, page slug, nonce action/field, and form field names

= 1.1.0 =
* Simplified to a single provider selection per task type — the plugin now correctly reflects that the AI plugin selects one provider per request, not a sequential fallback chain
* Provider discovery is now fully dynamic — any installed AI provider plugin is detected automatically via the WordPress connector registry, including third-party providers not bundled with the AI plugin
* Only providers whose plugin is actually active are shown in the settings UI; a notice is displayed when no provider plugins are active
* The plugin no longer hard-codes Anthropic, Google, and OpenAI — any registered connector appears in the selection automatically
* Added migration for 1.0.x saved settings (ordered array format) to the new single-provider format
* Settings page now shows an inline notice when the AI plugin's Developer Mode is overriding the provider selection for one or more features in a task type; the selector is disabled when the entire task type is overridden
* Added a Configure link to the plugin entry on the Plugins screen

= 1.0.0 =
* Initial release
