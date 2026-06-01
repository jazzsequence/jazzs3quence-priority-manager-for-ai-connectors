=== AI Connector Priority ===
Contributors: jazzs3quence
Tags: ai, llm, connectors, providers, priority
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 8.2
Stable tag: 1.1.0
License: MIT
License URI: https://opensource.org/licenses/MIT

Choose which AI provider to use for each task type when multiple providers are connected.

== Description ==

When you have multiple AI providers connected via Settings → Connectors, WordPress uses a built-in default provider. This plugin lets you choose which provider to use for each task type from an admin settings page.

Go to **Settings → AI Priority** to set your preferred provider for:

* **Text generation** — title generation, excerpt, summarization, content resizing, editorial notes, meta descriptions, comment moderation
* **Image generation** — featured image generation, inline image generation
* **Vision** — alt text generation, image analysis

Any active AI provider plugin is automatically detected — including third-party providers beyond the built-in Anthropic, Google, and OpenAI options.

Requires the [AI plugin](https://wordpress.org/plugins/ai/) and at least one active AI provider plugin.

== Installation ==

1. Search for "AI Connector Priority" in the WordPress plugins screen and click **Install Now**, or upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin through the Plugins screen
3. Go to **Settings → AI Priority** to configure your preferred provider

== Frequently Asked Questions ==

= Does this work without the AI plugin? =

No — this plugin hooks into the AI plugin's `wpai_preferred_*_models` filters. It has no effect if the AI plugin is not active.

= What happens if my preferred provider's plugin is deactivated? =

This plugin removes deactivated providers from the model list before it reaches the AI plugin. If your preferred provider is deactivated, the AI plugin uses whatever provider comes first in its default list.

= How does this interact with the AI plugin's Developer Mode? =

Developer Mode (Settings → AI → Developer Mode) configures a specific provider and model for individual AI features (e.g. Title Generation, Alt Text). This plugin sets a preferred provider per task type (text, image, vision), which covers multiple features each.

When Developer Mode has a provider and model set for a specific feature, that feature ignores this plugin's preference entirely — the Developer Mode selection wins. Features without a Developer Mode override use this plugin's selection as normal.

The settings page will show a notice next to any task type that has at least one feature with a Developer Mode override active.

== Changelog ==

= 1.1.0 =
* Simplified to a single provider selection per task type — the plugin now correctly reflects that the AI plugin selects one provider per request, not a sequential fallback chain
* Provider discovery is now fully dynamic — any installed AI provider plugin is detected automatically via the WordPress connector registry, including third-party providers not bundled with the AI plugin
* Only providers whose plugin is actually active are shown in the settings UI; a notice is displayed when no provider plugins are active
* The plugin no longer hard-codes Anthropic, Google, and OpenAI — any registered connector appears in the selection automatically
* Added migration for 1.0.x saved settings (ordered array format) to the new single-provider format

= 1.0.0 =
* Initial release
