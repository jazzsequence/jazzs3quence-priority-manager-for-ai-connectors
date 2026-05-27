=== AI Connector Priority ===
Contributors: jazzsequence
Tags: ai, llm, connectors, providers, priority
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 8.2
Stable tag: 1.0.0
License: MIT
License URI: https://opensource.org/licenses/MIT

Configure which AI provider is tried first for each task type when multiple providers are connected.

== Description ==

When you have multiple AI providers connected via Settings → Connectors, WordPress picks a model using a built-in preference list. This plugin lets you override that list from an admin settings page instead of code.

Go to **Settings → AI Priority** to set your preferred provider order for:

* **Text generation** — title generation, excerpt, summarization, content resizing, editorial notes, meta descriptions
* **Image generation** — featured image, inline image generation, image editing
* **Vision** — alt text generation, image analysis

Requires the [AI plugin](https://wordpress.org/plugins/ai/) and at least one active AI provider plugin.

== Installation ==

1. Upload `ai-connector-priority.php` to `/wp-content/mu-plugins/`, or install the plugin through the WordPress plugins screen
2. If installed as a regular plugin, activate it through the Plugins screen
3. Go to **Settings → AI Priority** to configure your provider preferences

== Frequently Asked Questions ==

= Does this work without the AI plugin? =

No — this plugin hooks into the AI plugin's `wpai_preferred_*_models` filters. It has no effect if the AI plugin is not active.

= What happens if my first-choice provider isn't installed? =

The AI plugin automatically falls through to the next provider in your list at model selection time.

= What if a model's API call fails at runtime? =

The fallback only applies at model selection time (whether the provider plugin is registered), not after an API call fails. A failed API call surfaces as an error to the user.

= Will this conflict with the AI plugin's Developer Mode setting? =

Developer Mode (Settings → AI → Developer Mode) sets a specific provider and model per feature and takes precedence over this plugin's priority list.

== Changelog ==

= 1.0.0 =
* Initial release
