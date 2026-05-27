# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

Single-file WordPress plugin (`ai-connector-priority.php`) that overrides the WordPress AI plugin's default provider selection order via three filters. Everything — constants, functions, hooks, and the admin settings page — lives in that one file under the `AiConnectorPriority` namespace.

## Dependencies

- **WordPress AI plugin** (`wordpress.org/plugins/ai`) — required; this plugin hooks exclusively into its `wpai_preferred_*_models` filters. Without it, the plugin has no effect.
- At least one AI provider plugin: `ai-provider-for-anthropic`, `ai-provider-for-google`, or `ai-provider-for-openai`.
- PHP 8.1+, WordPress 7.0+

## Architecture

The plugin's data flow:

1. `get_priorities()` reads `wp_options` key `ai_connector_priority` (constant `OPTION_KEY`), merging with hardcoded defaults.
2. `build_model_list( $task )` walks the saved provider order and calls `get_models_for_provider( $provider, $task )` for each, producing an ordered `[provider_id, model_id]` array.
3. Three `add_filter` calls attach `build_model_list()` results to `wpai_preferred_text_models`, `wpai_preferred_image_models`, and `wpai_preferred_vision_models`.

The admin page (`render_page()`) handles both GET (display) and POST (save) in one function. Nonce verification happens before `save_priorities()` is called. `save_priorities()` always calls `sanitize_provider_order()` which deduplicates and ensures every valid provider appears exactly once in the saved array.

### Key constraint: Anthropic excluded from image tasks

`get_providers_for_task()` removes `anthropic` from the provider list when `$task === 'image'`. This is intentional — Anthropic has no image-generation capability. The image task's `get_models_for_provider()` map also has no `anthropic` entry. Both must stay in sync if providers are ever added.

### Fallback behavior

Fallback is at **model selection time only** (whether a provider plugin is registered), not at API-call time. A failed API call surfaces as a user-facing error, not a retry.

## Development

No build step. Deploy by dropping `ai-connector-priority.php` into `mu-plugins/`, or install as a regular plugin. Can also be required via Composer.

### Commands

```bash
composer install                  # installs deps; wpunit-helpers copies bin scripts
chmod +x bin/*.sh                 # make installed bin scripts executable (once after install)

composer lint                     # PHPCS
composer lint:phpcbf              # auto-fix PHPCS violations

composer test:phpunit             # unit tests (no WordPress required)
composer test:install             # install WordPress test suite (no DB)
composer test:install:withdb      # install WordPress test suite (creates DB)
composer test:integration         # integration tests (requires test suite installed)
composer test                     # unit + integration
```

### First-time local integration test setup

Requires WP-CLI and a local MySQL-compatible database.

```bash
composer install
chmod +x bin/*.sh
composer test:install:withdb      # creates wordpress_test DB as root with no password
composer test:integration
```

To override DB credentials: `bin/install-local-tests.sh --dbuser=myuser --dbpass=mypass`
