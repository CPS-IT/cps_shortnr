# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**cps_shortnr** is a TYPO3 CMS extension (v13.4+) that provides URL shortening and routing capabilities through a middleware-based architecture. It intercepts incoming requests and redirects short URLs to their full counterparts using configurable patterns.

## Technology Stack

- **PHP**: 8.2+
- **TYPO3**: v13.4+ (CMS framework)
- **Pattern Engine**: brannow/typed-pattern-engine (custom pattern matching/generation)
- **Testing**: PHPUnit 12.3, TYPO3 Testing Framework 9.2

## Development Commands

### Dependency Management
```bash
composer install              # Install dependencies
```

### Testing
```bash
.Build/bin/phpunit           # Run all unit tests
.Build/bin/phpunit Tests/Unit/Specific/TestFile.php  # Run specific test
```

Tests are located in `Tests/Unit/` and use TYPO3's testing framework. Coverage reports are generated in `Tests/.phpunit_result/`.

## Architecture Overview

### Core Request Flow

1. **Middleware Entry Point** (`ShortNumberMiddleware`)
   - Registered in `Configuration/RequestMiddlewares.php`
   - Intercepts GET requests before TYPO3's site routing
   - Uses `DecoderService` to match incoming URLs against patterns
   - Returns 301 redirect if match found, otherwise passes to next middleware

2. **Configuration System**
   - YAML-based configuration loaded via `ConfigLoader`
   - Configs dispatched through PSR-14 events (`ShortNrConfigPathEvent`, `ShortNrConfigLoadedEvent`)
   - Patterns compiled using `TypedPatternEngine` and cached
   - Two-tier caching: `FastArrayFileCache` (file-based) + `CacheManager` (TYPO3 cache framework)

3. **Processor Architecture**
   - `ProcessorInterface` implementations handle different URL types
   - `PageProcessor`: Handles TYPO3 page URLs
   - `PluginProcessor`: Handles plugin/extension URLs
   - `NotFoundProcessor`: Fallback for unmatched patterns
   - Processors are tagged via DI (`cps_shortnr.processors`) and injected into `AbstractUrlService`

4. **Encode/Decode Services**
   - `DecoderService`: Converts short URLs → full URLs (used by middleware)
   - `EncoderService`: Generates short URLs from full URLs (used by ViewHelpers/APIs)
   - Both extend `AbstractUrlService` and share processor pool

### Dependency Injection

All services configured in `Configuration/Services.yaml`:
- Autowiring enabled for all `CPSIT\ShortNr\` classes
- Operators tagged by type (query/result/direct) and injected into `ConditionService`
- Processors tagged and injected into URL services
- Platform adapters use interface aliases for TYPO3 integration

### Condition System

The `ConditionService` provides a flexible condition evaluation system:
- **QueryOperators**: Modify database queries (e.g., `EqualOperator`, `BetweenOperator`)
- **ResultOperators**: Filter results post-query (e.g., `RegexMatchOperator`)
- **DirectOperators**: Evaluate conditions without DB access (e.g., `IssetOperator`)
- **WrappingOperators**: Transform other operators (e.g., `NotOperator`)

Operators implement priority-based sorting via `SortPriorityIterableTrait`.

### Caching Strategy

**Two-layer cache:**
1. **FastArrayFileCache**: File-based array cache in `.Build/var/cache/code/cps_shortnr/`
   - Stores compiled patterns and heuristics
   - Validates against YAML config file modification times
   - Cache invalidation: `ConfigLoader::clearCache()`

2. **TYPO3 Cache Framework**: Runtime cache via `CacheManager`
   - Cache key: `cps_shortnr` (see `ExtensionSetup::CACHE_KEY`)
   - Tagged caching for selective invalidation
   - Cache clearing hook: `ClearCacheDataHandlerHook`

### Language Overlay System

`LanguageOverlayService` + `ShortNrRepository::resolveCorrectUidWithLanguageUid()`:
- Resolves translated records based on `sys_language_uid`
- Falls back to default language (0) if translation missing
- Loads missing fields via `loadMissingFields()` to minimize DB queries

## Key Concepts

### Pattern Compilation
- Patterns defined in YAML config are compiled to PHP objects (`CompiledPattern`)
- Compiled patterns are dehydrated (serialized) and cached
- `PatternHeuristic` provides fast pre-filtering before full pattern matching

### Config Items
- `ConfigItemInterface`: Scoped accessor for configuration sections
- `ConfigEnum`: Enum-based keys for type-safe config access
- Each config item has a type (page/plugin) matched to a processor

### Platform Adapters
Abstractions for TYPO3-specific functionality (in `Classes/Service/PlatformAdapter/`):
- `FileSystemInterface`: File operations (wraps PHP functions for testability)
- `PathResolverInterface`: TYPO3 path resolution (FILE: prefix handling)
- `SiteResolverInterface`: TYPO3 site configuration access
- `PageTreeResolverInterface`: Page tree navigation

## Common Patterns

### Adding a New Processor
1. Create class implementing `ProcessorInterface` in `Classes/Service/Url/Processor/`
2. Implement `getType()`, `decode()`, and `encode()` methods
3. Auto-tagged via `_instanceof` in `Services.yaml`

### Adding a New Condition Operator
1. Implement `QueryOperatorInterface`, `ResultOperatorInterface`, or `DirectOperatorInterface`
2. Extend `OperatorInterface` for common operator contract
3. Implement `getPriority()` via `PriorityAwareInterface`
4. Auto-tagged via `Services.yaml`

### Working with Config
```php
// Access config via ConfigLoader
$config = $configLoader->getConfig();
$configItem = $config->getItem('my_config_name');

// Get values with enum keys
$pattern = $configItem->getPattern();
$type = $configItem->getType();
$tableName = $configItem->getTableName();
```

## Important Notes

- Extension key: `cps_shortnr` (matches `ExtensionSetup::EXT_KEY`)
- Build directory: `.Build/` (vendor, public web root)
- Main branch: `master` (use for PRs)
- Cache invalidation is automatic on YAML config changes (mtime-based)
- All exceptions extend `ShortNrException` for type-specific handling
- Pattern-related exceptions have specialized types: `ShortNrPatternCompilationException`, `ShortNrPatternParseException`, etc.