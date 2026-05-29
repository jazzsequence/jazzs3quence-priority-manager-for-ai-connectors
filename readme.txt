=== AI Connector Priority ===
Contributors: jazzs3quence
Tags: ai, llm, connectors, providers, priority
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 8.2
Stable tag: 1.1.0
License: MIT
License URI: https://opensource.org/licenses/MIT

Configure which AI provider is tried first for each task type when multiple providers are connected.

== Description ==

When you have multiple AI providers connected via Settings → Connectors, WordPress picks a model using a built-in preference list. This plugin lets you override that list from an admin settings page instead of code.

Go to **Settings → AI Priority** to set your preferred provider order for:

* **Text generation** — title generation, excerpt, summarization, content resizing, editorial notes, meta descriptions, comment moderation
* **Image generation** — featured image generation, inline image generation
* **Vision** — alt text generation, image analysis

Requires the [AI plugin](https://wordpress.org/plugins/ai/) and at least one active AI provider plugin.

== Installation ==

1. Install the plugin through the WordPress plugins screen, or upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin through the Plugins screen
3. Go to **Settings → AI Priority** to configure your provider preferences

== Frequently Asked Questions ==

= Does this work without the AI plugin? =

No — this plugin hooks into the AI plugin's `wpai_preferred_*_models` filters. It has no effect if the AI plugin is not active.

= What happens if my first-choice provider isn't installed? =

This plugin removes inactive providers from the priority list before passing it to the AI plugin, so the AI plugin only ever sees providers that are currently active. The next active provider in your list becomes the effective first choice automatically.

= What if a model's API call fails at runtime? =

The fallback only applies at model selection time (whether the provider plugin is registered), not after an API call fails. A failed API call surfaces as an error to the user.

= Will this conflict with the AI plugin's Developer Mode setting? =

Developer Mode (Settings → AI → Developer Mode) sets a specific provider and model per feature and takes precedence over this plugin's priority list.

== Changelog ==

= 1.1.0 =
* Provider discovery is now fully dynamic — any installed AI provider plugin is detected automatically via the WordPress connector registry, including third-party providers not bundled with the AI plugin
* Only providers whose plugin is actually active are shown in the settings UI; a notice is displayed when no provider plugins are active
* The plugin no longer hard-codes Anthropic, Google, and OpenAI — any registered connector (e.g. DeepSeek, Vertex) appears in the priority list automatically

= 1.0.0 =
* Initial release
