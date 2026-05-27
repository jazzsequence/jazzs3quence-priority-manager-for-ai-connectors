# AI Connector Priority

![WordPress 7.0+](https://img.shields.io/badge/WordPress-7.0%2B-0073aa?logo=wordpress&logoColor=white)
![GitHub Release](https://img.shields.io/github/v/release/jazzsequence/ai-connector-priority)
![GitHub License](https://img.shields.io/github/license/jazzsequence/ai-connector-priority)
[![CI](https://github.com/jazzsequence/ai-connector-priority/actions/workflows/ci.yml/badge.svg)](https://github.com/jazzsequence/ai-connector-priority/actions/workflows/ci.yml)

A WordPress plugin that adds an admin settings page to configure which AI provider is tried first for each task type — text generation, image generation, and vision — when multiple providers are connected.

## Requirements

- WordPress 7.0+
- PHP 8.2+
- The [AI plugin](https://wordpress.org/plugins/ai/) (wordpress.org/plugins/ai)
- At least one AI provider plugin (`ai-provider-for-anthropic`, `ai-provider-for-google`, `ai-provider-for-openai`)

## Installation

Install via Composer:

```bash
composer require jazzsequence/ai-connector-priority
```

Or drop `ai-connector-priority.php` into your `mu-plugins` directory.

## Usage

Once activated, go to **Settings → AI Priority** to configure the provider order for each task type.

### Task types

| Task | Used for |
|------|----------|
| **Text generation** | Title generation, excerpt, summarization, content resizing, editorial notes, meta descriptions |
| **Image generation** | Featured image generation, inline image generation, image editing |
| **Vision** | Alt text generation, image analysis |

### Provider priority

For each task type, select your 1st, 2nd, and 3rd choice provider. The AI plugin will try the first-choice provider's models first. If no model from that provider is registered, it falls back to the next choice.

Note: Anthropic does not support image generation and is excluded from that task type.

### How fallback works

The fallback applies at **model selection time** (whether a model is registered in the active provider plugin), not at runtime. If the selected model's API call fails, the user sees an error rather than an automatic retry.

## Option key

Settings are stored in `wp_options` under `ai_connector_priority`.

## License

MIT
