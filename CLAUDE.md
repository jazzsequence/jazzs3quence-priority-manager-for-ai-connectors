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
2. `get_provider_supported_tasks( $provider_id )` queries the AiClient registry directly via `findProviderModelsMetadataForSupport()` to determine which task types a provider supports. Results are cached in WordPress transients (24h TTL). Providers without credentials fall back to `['text', 'vision']` — never image, since image generation must be confirmed. The cache is cleared on every settings page load and on plugin activation/deactivation.
3. `get_providers_for_task( $task )` iterates active connectors and includes only those whose supported tasks (from step 2) contain the given task.
4. `get_priorities()` reads `wp_options` key `ai_connector_priority` (constant `OPTION_KEY`). Returns a single provider ID per task, defaulting to the first active provider for that task. Migrates the 1.0.x ordered-array format automatically.
5. `reorder_model_list( $models, $task )` puts the saved preferred provider's models at the front of the incoming list, drops models for inactive providers, and leaves all others in their original order.
6. Three named filter callbacks (`reorder_models_for_text`, `reorder_models_for_image`, `reorder_models_for_vision`) attach `reorder_model_list()` to the `wpai_preferred_*_models` filters.

The admin page (`render_page()`) shows one `<select>` per task type, populated from `get_providers_for_task()`. A warning notice appears when no provider plugins are active. `save_priorities()` validates the submitted provider ID against `get_providers_for_task()` before persisting.

### Maintenance: this plugin tracks the AI plugin's feature set

This plugin is tightly coupled to the WordPress AI plugin and must be updated reactively when the AI plugin adds new capabilities:

1. **New AI features** — When the AI plugin adds a new ability that calls `set_provider_model_preference()`, add its feature ID to the appropriate task type in `get_task_feature_map()`. Features that call `using_model_preference()` directly cannot have Developer Mode overrides and should not be added.

2. **New task types** — If the AI plugin introduces a new task type beyond text/image/vision (e.g. audio, video), add it to the task type maps throughout the plugin: `get_task_feature_map()`, `get_priorities()`, `reorder_model_list()`, the filter callbacks, and `render_page()`.

3. **New filter hooks** — If the AI plugin introduces new `wpai_preferred_*_models` filters, add corresponding filter callbacks following the pattern of `reorder_models_for_text/image/vision`.

To check for new features in the AI plugin: look for classes in `includes/Abilities/` that call `$this->set_provider_model_preference()` and cross-reference against the current `get_task_feature_map()` return value.

### Key: capability detection

Provider task support comes from the AiClient registry, not from the `wpai_preferred_*_models` filter output. The WP prompt builder's `isSupported()` ignores `using_provider()` and checks all registered providers — `findProviderModelsMetadataForSupport()` on the registry is the correct per-provider API. Vision is proxied to text generation support: every provider that registers text generation models also declares image input modality support.

### Key: capability cache

`get_provider_supported_tasks()` caches results in WordPress transients keyed `aicp_tasks_{provider_id}`. The cache is cleared on every settings page load so the UI always reflects current state. Filter hook invocations on other pages read from the cache without making API requests.

## Development

No build step. Install as a regular plugin, or drop `ai-connector-priority.php` into `mu-plugins/` (note: mu-plugins do not receive automatic updates through the WordPress admin).

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
