# AI Connector Priority

![WordPress 7.0+](https://img.shields.io/badge/WordPress-7.0%2B-0073aa?logo=wordpress&logoColor=white)
![GitHub Release](https://img.shields.io/github/v/release/jazzsequence/ai-connector-priority)
![GitHub License](https://img.shields.io/github/license/jazzsequence/ai-connector-priority)
[![CI](https://github.com/jazzsequence/ai-connector-priority/actions/workflows/ci.yml/badge.svg)](https://github.com/jazzsequence/ai-connector-priority/actions/workflows/ci.yml)

A WordPress plugin that adds an admin settings page to configure which AI provider is used for each task type — text generation, image generation, and vision — when multiple providers are connected.

## Requirements

- WordPress 7.0+
- PHP 8.2+
- The [AI plugin](https://wordpress.org/plugins/ai/) (wordpress.org/plugins/ai)
- At least one active AI provider plugin

## Installation

Install from the [WordPress plugin directory](https://wordpress.org/plugins/ai-connector-priority/), or install manually:

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate through the **Plugins** screen

Alternatively, install via Composer:

```bash
composer require jazzsequence/ai-connector-priority
```

Or drop `ai-connector-priority.php` into your `mu-plugins` directory. Note that mu-plugins do not receive automatic updates through the WordPress admin.

## Usage

Once activated, go to **Settings → AI Priority** to choose your preferred provider for each task type.

### Task types

| Task | Used for |
|------|----------|
| **Text generation** | Title generation, excerpt, summarization, content resizing, editorial notes, meta descriptions, comment moderation |
| **Image generation** | Featured image generation, inline image generation |
| **Vision** | Alt text generation, image analysis |

### Provider selection

For each task type, choose which provider you want to use. Only providers whose plugin is active and which support that task type are shown.

Provider capabilities are detected automatically from the AI plugin's registry. Providers without credentials configured show for text and vision tasks only, since image generation is a specialized capability that requires confirmation.

### How provider selection works

The AI plugin selects a provider from a preference list. This plugin moves your chosen provider's models to the front of that list.

## Option key

Settings are stored in `wp_options` under `ai_connector_priority`.

## License

MIT
