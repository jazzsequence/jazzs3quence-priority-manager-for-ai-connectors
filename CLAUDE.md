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

1. `get_registered_providers()` returns the full provider registry via the `ai_connector_priority_providers` filter. Built-in definitions cover Anthropic, Google, and OpenAI; third parties add entries via that filter.
2. `get_active_providers()` filters the registry to only providers whose `plugin` file passes `is_plugin_active()`. Providers added via filter without a `plugin` key are always considered active.
3. `get_providers_for_task( $task )` returns active providers that declare support for the given task, as `id => label` pairs.
4. `get_priorities()` reads `wp_options` key `ai_connector_priority` (constant `OPTION_KEY`), merging with defaults derived from the currently active providers.
5. `build_model_list( $task )` walks the saved provider order, **skips any provider that is not currently active**, and calls `get_models_for_provider()` for each active one, producing an ordered `[provider_id, model_id]` array.
6. Three `add_filter` calls attach `build_model_list()` results to `wpai_preferred_text_models`, `wpai_preferred_image_models`, and `wpai_preferred_vision_models`.

The admin page (`render_page()`) handles both GET (display) and POST (save) in one function. It shows a warning notice when no provider plugins are active. Nonce verification happens before `save_priorities()` is called. `save_priorities()` validates submitted providers against `get_providers_for_task()` (active only) and calls `sanitize_provider_order()` which deduplicates and ensures every valid provider appears exactly once.

### Extensibility: adding a custom provider

Hook into `ai_connector_priority_providers` and append your provider definition:

```php
add_filter( 'ai_connector_priority_providers', function( array $providers ): array {
    $providers['myprovider'] = [
        'label'  => 'My Provider',
        // omit 'plugin' to always treat as active, or set to 'slug/slug.php'
        'tasks'  => [ 'text', 'vision' ],
        'models' => [
            'text'   => [ [ 'myprovider', 'model-id' ] ],
            'vision' => [ [ 'myprovider', 'model-id' ] ],
        ],
    ];
    return $providers;
} );
```

### Key constraint: Anthropic excluded from image tasks

Anthropic's entry in `get_registered_providers()` declares `'tasks' => [ 'text', 'vision' ]` — no `image`. This means `get_providers_for_task( 'image' )` never includes Anthropic. The constraint lives in the provider definition, not in conditional logic elsewhere.

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
