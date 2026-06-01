# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

Single-file WordPress plugin (`ai-connector-priority.php`) that lets users choose which AI provider the WordPress AI plugin uses for each task type (text, image, vision). Everything — constants, functions, hooks, and the admin settings page — lives in that one file under the `AiConnectorPriority` namespace.

## How the AI plugin selects a provider

The WordPress AI plugin's `PromptBuilder` receives an ordered list of `[provider, model]` pairs via the `wpai_preferred_*_models` filters. It intersects those preferences with models available in the AiClient registry and selects **the first match** — one provider, one model, one API call. There is no sequential fallback chain and no runtime retry on API failure.

This plugin moves the user's chosen provider to the front of that list. All other active providers follow in their default order, but only the first one is ever used per request.

## Dependencies

- **WordPress AI plugin** (`wordpress.org/plugins/ai`) — required; this plugin hooks exclusively into its `wpai_preferred_*_models` filters and uses `\WordPress\AI\get_ai_connectors()`. Without it, the plugin has no effect.
- At least one active AI provider plugin (Anthropic, Google, OpenAI, or any third-party provider registered with the WordPress AI plugin).
- PHP 8.2+, WordPress 7.0+

## Architecture

The plugin's data flow:

1. `get_active_connectors()` calls `\WordPress\AI\get_ai_connectors(true)`, which returns only providers whose plugin is currently active (checked via `is_plugin_active()` for built-in providers; always active for custom providers with no plugin file key).
2. `get_providers_for_task( $task )` intersects the AI plugin's default model list for that task with the active connectors, returning `id => label` pairs. Providers that have no models in a task's list are excluded (e.g. Anthropic does not appear for image tasks).
3. `get_priorities()` reads `wp_options` key `ai_connector_priority` (constant `OPTION_KEY`). Returns a single provider ID per task, defaulting to the first active provider. Migrates the 1.0.x ordered-array format automatically.
4. `reorder_model_list( $models, $task )` puts the saved preferred provider's models at the front of the incoming list, drops models for inactive providers, and leaves all others in their original order.
5. Three named filter callbacks (`reorder_models_for_text`, `reorder_models_for_image`, `reorder_models_for_vision`) attach `reorder_model_list()` to the `wpai_preferred_*_models` filters.

The admin page (`render_page()`) shows one `<select>` per task type, populated from `get_providers_for_task()`. A warning notice appears when no provider plugins are active. `save_priorities()` validates the submitted provider ID against `get_providers_for_task()` before persisting.

### Key: get_default_models_for_task()

This function temporarily removes our reorder hook, then calls the AI plugin's own helper (`\WordPress\AI\get_preferred_models_for_text_generation()` etc.) to get the baseline model list including the AI plugin's built-in defaults. Calling `apply_filters()` directly with `[]` would bypass those defaults. After fetching the list, the hook is re-added.

### Key constraint: providers not in a task's model list are excluded

`get_providers_for_task()` only returns providers that appear in the AI plugin's default model list for that task. A provider that doesn't register models in `wpai_preferred_*_models` won't appear in the UI for that task. This is correct — if the AI plugin doesn't know a provider supports a task, neither should this plugin.

## Development

No build step. Install as a regular plugin or drop `ai-connector-priority.php` into `mu-plugins/`.

### Commands

```bash
composer install                  # installs deps; wpunit-helpers copies bin scripts
chmod +x bin/*.sh                 # make installed bin scripts executable (once after install)

composer lint                     # PHPCS
composer lint:phpcbf              # auto-fix PHPCS violations

composer test                     # unit + integration (always run both)
composer test:phpunit             # unit tests only
composer test:install             # install WordPress test suite (no DB)
composer test:install:withdb      # install WordPress test suite (creates DB)
composer test:integration         # integration tests (requires test suite installed)
```

### First-time local integration test setup

Requires WP-CLI and a local MySQL-compatible database.

```bash
composer install
chmod +x bin/*.sh
composer test:install:withdb      # creates wordpress_test DB as root with no password
composer test                     # run both unit and integration
```

To override DB credentials: `bin/install-local-tests.sh --dbuser=myuser --dbpass=mypass`
