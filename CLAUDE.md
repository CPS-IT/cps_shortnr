# Claude Memory - ShortNr TYPO3 Extension

## Vision

URL shortner that works with encode and decode URLs. it can handle Pages and plugin calls.
The configuration is handled over a YAML config file. the upmost Priority is Clean Code AND Performance, since the middleware is between EVERY request

OOP and TDD is important, in case of OOP it is used, when possible, and not in conflict with performance or clean code, his true origin form "messaging". messaging refers to the way objects interact and communicate with each other by sending messages. This mechanism involves invoking methods on objects, which can lead to changes in the object's state or the return of a value. Essentially, objects don't directly access each other's internal data or methods; instead, they send messages to request specific actions or information

## Project Overview
- **Extension**: ShortNr URL shortener for TYPO3
- **TYPO3 Versions**: 12.4 and 13.4
- **PHP**: Written in 8.4, compatible with 8.1+
- **Status**: Core architecture established, comprehensive test coverage implemented
- **Stage**: The Project is still early Work in Progress with many features and functions missing

## Architecture Overview

### Core Components
```
Classes/
├── Cache/
│   ├── CacheAdapter/FastArrayFileCache.php - PHP array file cache with atomic writes
│   └── CacheManager.php - TYPO3 cache integration
├── Config/
│   ├── ConfigInterface.php - Configuration access interface
│   ├── ConfigLoader.php - Multi-level caching YAML config loader (returns ConfigInterface)
│   ├── DTO/
│   │   └── Config.php - Configuration data transfer object with inheritance logic
│   └── ExtensionSetup.php - TYPO3 extension configuration
├── Middleware/
│   └── ShortNumberMiddleware.php - Main HTTP request handler
├── Service/
│   ├── PlatformAdapter/
│   │   ├── FileSystem/
│   │   │   ├── FileSystemInterface.php - File operations abstraction
│   │   │   └── FileSystem.php - Concrete implementation
│   │   └── Typo3/
│   │       ├── PathResolverInterface.php - TYPO3 path resolution abstraction
│   │       └── Typo3PathResolver.php - GeneralUtility::getFileAbsFileName wrapper
│   └── Url/
│       ├── AbstractUrlService.php - Base class for URL services
│       ├── DecoderService.php - URL decoding service (placeholder)
│       └── EncoderService.php - URL encoding service (placeholder)
├── ViewHelpers/
│   └── ShortUrlViewHelper.php - Fluid ViewHelper for generating short URLs
└── Exception/ - Custom exceptions
```

### What is missing

* URL decoder Service business logic (placeholder exists)
* URL encoder Service business logic (placeholder exists)
* ShortUrlViewHelper business logic (placeholder implementation)
* AbstractUrlService common functionality
* tbd...

### Key Architectural Patterns
- **Dependency Injection**: All services use constructor injection
- **Interface Segregation**: Separate interfaces for file operations vs path resolution
- **Multi-level Caching**: Runtime → File cache → YAML parsing chain
- **Atomic Operations**: Safe file writes using temp files + rename
- **Clean Abstractions**: TYPO3 utilities isolated behind interfaces

## Detailed Component Analysis

### ConfigLoader (`Classes/Config/ConfigLoader.php:37`)
**Purpose**: Sophisticated config loading with multi-level caching, returns DTO objects
**Architecture**: 
- **DTO Pattern**: Returns ConfigInterface instead of raw arrays
- **Runtime Cache**: Static array for request-level caching
- **File Cache**: PHP serialized arrays stored as executable PHP files
- **YAML Parsing**: Symfony/Yaml component for config files
- **Cache Invalidation**: File modification time comparison

**Key Methods**:
- `getConfig()`: Main entry point, returns ConfigInterface wrapping cached data
- `getConfigArray()`: Private method handling cache validation flow
- `isConfigCacheValid()`: Compares YAML vs cache file modification times
- `getConfigFileSuffix()`: Generates MD5 hash for cache file naming
- `prepareConfigFilePath()`: Handles FILE: prefix removal + path resolution

**Dependencies**: CacheManager, ExtensionConfiguration, FileSystemInterface, PathResolverInterface

### Config DTO (`Classes/Config/DTO/Config.php`)
**Purpose**: Configuration data transfer object with inheritance logic and runtime caching
**Architecture**:
- **Value Inheritance**: Route configs inherit from `_default` section automatically
- **Internal Caching**: Runtime cache for expensive operations (config names filtering)
- **Type Safety**: Strongly typed getters with null coalescing for optional values
- **ConfigInterface**: Implements configuration access interface

**Key Methods**:
- `getConfigNames()`: Returns filtered route names (excludes `_default` and `types`)
- `getProcessorClass()`: Maps route type to processor class via types section
- `getValue()`: Core inheritance method with fallback to `_default`
- Route accessors: `getPrefix()`, `getType()`, `getTableName()`, `getCondition()`, etc.

**Inheritance Pattern**: `routeConfig[key] ?? _default[key] ?? null`

### FastArrayFileCache (`Classes/Cache/CacheAdapter/FastArrayFileCache.php`)
**Purpose**: High-performance array caching with atomic writes
**Features**:
- **Atomic Writes**: tempnam() → file_put_contents() → rename() pattern
- **Runtime Cache**: Prevents duplicate file reads within request
- **PHP Code Generation**: Stores arrays as executable PHP files (`return [...]`)
- **Safe Directory Creation**: Ensures cache directories exist

**Key Methods**:
- `writeArrayFileCache()`: Atomic write with error handling
- `readArrayFileCache()`: Read with runtime cache fallback
- `invalidateFileCache()`: Clear cache files and runtime cache
- `generatePhpArrayCode()`: Creates executable PHP from array

### CacheManager (`Classes/Cache/CacheManager.php`)
**Purpose**: Bridge between custom cache and TYPO3 cache system
**Features**:
- **TYPO3 Integration**: Connects to TYPO3 cache framework
- **Graceful Degradation**: Falls back when TYPO3 cache unavailable
- **Exception Handling**: Catches all cache-related exceptions

### Path Resolution Abstraction
**Design Decision**: Separate PathResolverInterface from FileSystemInterface
**Rationale**: 
- Path resolution ≠ File system operations
- Better testability (can mock path resolution independently)
- TYPO3 utilities isolated from core file operations

## Testing Architecture

### Test Structure
```
Tests/Unit/
├── Cache/
│   ├── CacheAdapter/FastArrayFileCacheTest.php
│   └── CacheManagerTest.php
├── Config/
│   ├── ConfigLoaderTest.php
│   └── ExtensionSetupTest.php
├── Middleware/ShortNumberMiddlewareTest.php
└── ViewHelpers/ShortUrlViewHelperTest.php
```

### Testing Strategy & Quality Standards
- **Behavioral Focus**: Test contracts and outcomes, not implementation details
- **DataProviders**: Extensively used for testing multiple scenarios efficiently
- **Mock Abstractions**: FileSystemInterface and PathResolverInterface fully mockable
- **Static Cache Management**: Proper cleanup between tests using reflection
- **Refactoring Safety**: Tests survive legitimate architectural changes
- **No Wrapper Testing**: Platform adapters (FileSystem, PathResolver) excluded from testing as TYPO3-only wrappers
- **Comprehensive Coverage**: Normal flows, edge cases, error conditions

### High-Quality Test Characteristics
1. **Test behavior, not implementation** - Validate "what" the code does, not "how" it does it
2. **Survive refactoring** - Tests should pass when internal implementation changes without contract changes
3. **Interface-based mocking** - Mock dependencies via interfaces, never concrete classes
4. **Object messaging validation** - Test communication between objects without exposing internals
5. **DataProvider usage** - Prefer comprehensive scenarios over single-case methods
6. **No reflection testing** - Never test private properties or methods directly
7. **Contract assertions** - Assert on public behavior and outcomes
8. **Remove anti-patterns** - Delete tests that provide false confidence (mock configuration testing)
9. **Eliminate redundancy** - Never duplicate test scenarios between methods and DataProviders
10. **Comprehensive edge cases** - Include boundary conditions, null values, complex parameters

### Low-Quality Test Anti-Patterns to Avoid
- **Reflection-based testing** - Testing private properties/methods breaks encapsulation
- **Implementation detail testing** - Tests that break when refactoring legitimate code
- **Mock configuration testing** - Circular tests that validate mock setup rather than behavior
- **Meaningless instantiation tests** - Tests that only verify object creation
- **Wrapper function testing** - Testing simple TYPO3/framework wrappers without added logic
- **Test method redundancy** - Separate test methods that duplicate DataProvider scenarios
- **Shallow edge case coverage** - Missing boundary conditions, null handling, complex parameter structures

### Test Commands
```bash
# Run all tests
docker exec php-shortnr /var/www/html/.Build/bin/phpunit

# Run specific test class
docker exec php-shortnr /var/www/html/.Build/bin/phpunit Tests/Unit/Config/ConfigLoaderTest.php

# Run with coverage
docker exec php-shortnr /var/www/html/.Build/bin/phpunit --coverage-html var/coverage

# Run specific test method
docker exec php-shortnr /var/www/html/.Build/bin/phpunit --filter="testMethodName"
```

## Configuration & Dependency Injection

### URL Shortener Configuration Schema (`Configuration/config.yaml`)

The configuration follows a hierarchical structure with global settings, defaults, and specific route definitions:

```yaml
shortNr:                  # Root configuration key (was ShortNr)
  types:                  # Processor type mappings
    page: "\\CPSIT\\ShortNr\\Service\\Processor\\PageProcessor"
    plugin: "\\CPSIT\\ShortNr\\Service\\Processor\\PluginProcessor"
  
  _default:               # Default matching rules for all routes
    notFound: "/fehler-404"  # Fallback URL for 404 errors (moved to _default)
    regex: "/^([a-zA-Z]+?)(\\d+)[-]?(\\d+)?$/"  # Pattern: prefix + ID + optional language
    regexGroupMapping:    # Maps regex groups to placeholders
      prefix: "{match-1}"
      id: "{match-2}"
      languageId: "{match-3}"
    condition:            # Database query conditions
      uid: "{match-2}"
      sysLanguageUid: "{match-3}"
  
  # Route definitions inherit from _default and override specific settings
  pages:                  # User-defined route name
    type: page            # Processor type (from 'types' section)
    prefix: PAGE          # URL prefix pattern
    table: pages          # Database table
  
  press:                  # Custom route name (user-defined, not reserved)
    prefix: pm
    type: plugin
    table: tx_bmubarticles_domain_model_article
    condition:
      articleType: press
    pluginConfig:         # TYPO3 plugin configuration
      extension: BmubArticles
      plugin: Articles
      pid: 289
      action: show
      controller: Article
      objectName: article  # name for value for the UID
```

**Key Schema Concepts:**
- **Global Settings**: `notFound` URL and processor type mappings
- **Extensible Processor System**: Custom processor classes can be registered in `types` section
- **Default Template**: `_default` section provides base configuration inherited by all routes
- **Route Inheritance**: Each route inherits `_default` settings and overrides specific values
- **Dynamic Route Names**: Route identifiers (like `press`, `pages`) are completely user-defined, not reserved keywords
- **Regex Placeholders**: `{match-N}` placeholders map to regex capture groups
- **Database Conditions**: Dynamic conditions using placeholder substitution
- **Plugin Integration**: Full TYPO3 plugin configuration for complex routing
- **Custom Type Support**: Add exotic structures by implementing custom processors and registering them

### Services Configuration (`Configuration/Services.yaml`)
```yaml
services:
  CPSIT\ShortNr\Service\PlatformAdapter\FileSystem\FileSystemInterface:
    alias: CPSIT\ShortNr\Service\PlatformAdapter\FileSystem\FileSystem
  
  CPSIT\ShortNr\Service\PlatformAdapter\Typo3\PathResolverInterface:
    alias: CPSIT\ShortNr\Service\PlatformAdapter\Typo3\Typo3PathResolver
```

### Cache Configuration (`Classes/Config/ExtensionSetup.php`)
- Registers TYPO3 cache with key `cps_shortnr`
- Uses VariableFrontend + FileBackend
- Belongs to 'system' cache group

## Development Workflow

### Adding New Components
1. Create interface in appropriate Service namespace
2. Implement concrete class
3. Add to Services.yaml if needed
4. Create comprehensive test with dataProviders
5. Mock all dependencies via interfaces

### Testing New Features
1. Use dataProviders for multiple scenarios
2. Mock FileSystemInterface for all file operations
3. Mock PathResolverInterface for path resolution
4. Clear static caches in setUp/tearDown
5. Test normal flows, edge cases, and error conditions
6. **Test Quality over Quantity** - Remove tests that fight against architecture rather than fixing them
7. **Use willReturnCallback() for complex mock scenarios** - More reliable than willReturnMap() for interface-based mocking

### Key Patterns to Follow
- **Interface-first design**: Always create interface before implementation
- **Dependency injection**: Use constructor injection for all dependencies
- **Atomic operations**: Use temp files for safe writes
- **Runtime caching**: Cache expensive operations within request
- **Graceful degradation**: Handle failures without breaking system
- **Modern Language Features First**: Prefer modern PHP syntax and built-in language features over custom implementations - research existing language capabilities before adding defensive code or workarounds
- **Zero Tolerance for Deprecations**: All deprecation warnings must be resolved - they indicate future breaking changes and technical debt.

## Important Implementation Details

### Cache File Strategy
- **Location**: `{TYPO3_VAR_PATH}/cache/code/cps_shortnr/`
- **Naming**: `config{md5_hash}.php` 
- **Format**: Executable PHP files with `return [array];`
- **Atomic Writes**: temp file → rename pattern prevents corruption

### Error Handling Patterns
- **ConfigLoader**: Returns empty array on errors (graceful degradation)
- **FastArrayFileCache**: Throws ShortNrCacheException on write failures
- **CacheManager**: Returns null on TYPO3 cache failures
- **PathResolver**: Delegates to TYPO3 GeneralUtility (may throw)

### Performance Considerations
- **Runtime cache**: Prevents duplicate file reads/parsing
- **File modification time checks**: Efficient cache invalidation
- **Atomic writes**: Prevents cache corruption during writes
- **PHP array serialization**: Faster than JSON for large arrays

## Future Development Notes
- **Middleware**: Currently placeholder, needs URL shortening logic
- **Database layer**: Will need URL storage/retrieval
- **Cache warming**: Consider background cache warming for large configs
- **Monitoring**: Add cache hit/miss metrics
- **Path resolution**: Consider caching resolved paths for performance

## Session Update Instructions (For Claude Code)

At the end of each session, update this document with:

### Required Updates
- [ ] Move completed items from "What is missing" to appropriate sections
- [ ] Add new architectural insights to "Detailed Component Analysis"
- [ ] Update test commands if new ones were used
- [ ] Add any new patterns discovered to "Key Patterns to Follow"
- [ ] Add any new Files you found in the Architecture Overview (Core Components)

### Quality Checklist
- [ ] Keep explanations concise but complete
- [ ] Use consistent terminology throughout
- [ ] Maintain existing structure and formatting
- [ ] Remove outdated information
- [ ] Ensure code examples are accurate
- [ ] Keep file structure format consistent (path - description pattern)

### Update Format
**Session Date**: [Date]
**Changes Made**: Brief summary
**New Insights**: Key learnings

### Quality Standards
- Maximum 3 new insights per session (avoid information overload), ranked by what you think is most important
- Each insight must include WHY it matters, not just WHAT was done
- Session updates: default to short bullet points, use longer explanations only when necessary for clarity
- Remove redundant or outdated information
- Keep the document scannable with clear headers
- Deprecation warnings are NOT acceptable - fix immediately or document why they must remain
- Run tests with full error reporting to catch all deprecations and warnings
- Treat deprecations as bugs that must be resolved before considering code complete
- Use camelCase consistently

---

## Session Updates

### Session Date: 2025-01-11
**Changes Made**: Test suite cleanup - 24 failing tests fixed, 4 problematic tests removed, 100% passing suite achieved

**New Insights**:

1. **Test Quality over Quantity** - Remove tests that fight architecture rather than fix them - maintains Clean Code vision and prevents architectural violations

2. **Mock Configuration for Interface-based DI** - `willReturnCallback()` more reliable than `willReturnMap()` for complex interface mocking - critical for FileSystemInterface/PathResolverInterface patterns

3. **Test Architecture Alignment** - Tests must follow same patterns as codebase (DataProviders, interface mocking, static cache management) - validates object messaging without exposing implementation details

### Session Date: 2025-01-11
**Changes Made**: Created comprehensive ExtensionSetupTest with 14 data provider scenarios

**New Insights**:

1. **Global State Testing Patterns** - ExtensionSetup static testing requires careful GLOBALS management with setUp/tearDown - ensures test isolation while validating TYPO3 integration patterns

2. **Data Provider Edge Case Coverage** - Testing null/false values and partial configurations validates graceful degradation - critical for extension setup robustness in diverse TYPO3 environments

### Session Date: 2025-01-13
**Changes Made**: Test quality improvements - removed low-quality tests, rewrote middleware tests with behavioral focus, excluded PlatformAdapter from coverage

**New Insights**:

1. **Platform Adapter Testing Strategy** - Wrapper classes for TYPO3 utilities should be excluded from testing and coverage - they provide no business logic and testing them violates the principle of not testing framework code

2. **Behavioral Middleware Testing** - Middleware tests should focus on HTTP request/response contracts using PSR-15 patterns with DataProviders for request scenarios - validates actual middleware behavior rather than dependency injection mechanics

3. **Test Quality Enforcement** - Established concrete criteria for high-quality tests: behavioral focus, refactoring safety, interface mocking, contract assertions - provides clear guidelines for maintaining test architecture alignment

### Session Date: 2025-01-13
**Changes Made**: Created ShortUrlViewHelper placeholder with modern TYPO3 12/13 standards, updated architecture documentation

**New Insights**:

1. **Modern TYPO3 ViewHelper Standards** - TYPO3 12/13 requires final classes with strict typing, no deprecated traits, and clean argument registration via `initializeArguments()` - ensures future-proof ViewHelper development aligned with current framework evolution

2. **ViewHelper Architecture Strategy** - ViewHelpers should be dependency-free placeholders initially, designed for future service integration (EncodingService) rather than direct config access - maintains clean separation of concerns and prevents tight coupling to configuration layer

3. **Professional Interface Design** - Complete argument interfaces (target, type, language, absolute, parameters) designed upfront enable comprehensive usage patterns while maintaining backward compatibility - critical for extension adoption and developer experience

### Session Date: 2025-01-13
**Changes Made**: Created comprehensive ViewHelper test suite with canary pattern and fixed argument default handling

**New Insights**:

1. **Canary Test Pattern for Placeholders** - Tests that expect placeholder results serve as automatic reminders to update tests when real implementation is added - ensures test-implementation synchronization and prevents forgotten test updates when business logic changes

2. **ViewHelper Argument Handling** - TYPO3 ViewHelpers require explicit null coalescing for optional arguments (`$this->arguments['key'] ?? default`) even with `initializeArguments()` defaults - critical for preventing undefined array key warnings in strict PHP environments

3. **Standard PHPUnit for TYPO3 ViewHelpers** - Simple ViewHelpers without TYPO3 dependencies should use standard `TestCase` rather than TYPO3 testing framework - maintains architectural consistency and avoids unnecessary framework overhead for unit testing

### Session Date: 2025-01-13
**Changes Made**: Enhanced test quality standards, fixed ViewHelper test redundancy and edge case coverage

**New Insights**:

1. **Test Redundancy Detection** - Duplicate scenarios between test methods and DataProviders must be eliminated immediately - maintains DRY principles and prevents false confidence in coverage metrics

2. **DataProvider-First Testing Strategy** - Always consolidate test scenarios into DataProviders rather than separate methods - improves maintainability and ensures comprehensive scenario coverage without duplication

3. **Quality Standard Enforcement Process** - Systematic quality review (create → test → recheck → fix → document) prevents technical debt accumulation - establishes iterative improvement cycle for maintaining architectural alignment

### Session Date: 2025-01-13
**Changes Made**: Major architecture refactor - ConfigLoader now returns ConfigInterface DTO, created Config DTO with inheritance logic, added URL service placeholders, updated YAML schema

**New Insights**:

1. **DTO Pattern with Inheritance Logic** - Config DTO encapsulates YAML inheritance (`route[key] ?? _default[key] ?? null`) with runtime caching for expensive operations - eliminates direct array access throughout codebase and provides type-safe configuration access

2. **Configuration Schema Evolution** - Root key changed from `ShortNr` to `shortnr`, `notFound` moved to `_default` section for inheritance - demonstrates iterative schema refinement while maintaining backward compatibility patterns

3. **Service Placeholder Architecture** - Abstract base class with concrete service placeholders (EncoderService, DecoderService) establishes extension points for future URL processing logic - enables incremental development while maintaining clean abstractions

### Session Date: 2025-07-13
**Changes Made**: Updated all tests to use new Config DTO instead of array structure - migrated ConfigLoaderTest and MiddlewareTest to work with ConfigInterface pattern

**New Insights**:

1. **Modern PHP Defensive Programming** - The `??` operator elegantly handles undefined array keys in chains without requiring additional defensive programming - questioning necessity before adding protection prevents over-engineering and leverages language features effectively

2. **DTO Migration Testing Strategy** - Systematic test migration requires updating imports, assertions, mocks, and data providers to expect DTO objects rather than arrays - ensures type safety while maintaining behavioral test coverage during architecture transitions

3. **Array Processing Best Practices** - Using `array_values()` after `array_filter()` ensures proper sequential indexing when filtering preserves original keys - critical for maintaining predictable array structures in DTO methods that return filtered collections

### Session Date: 2025-07-13
**Changes Made**: Test compliance enforcement - removed 4 reflection-based tests that violated architectural principles, achieving 100% guideline compliance

**New Insights**:

1. **Architectural Violation Removal Strategy** - Remove tests that test private methods via reflection rather than attempting to refactor them - maintains encapsulation principles and prevents technical debt accumulation while preserving behavioral coverage through public API testing

2. **Test Suite Compliance Validation** - Regular compliance audits against established guidelines prevent gradual degradation of test quality - systematic removal of anti-patterns ensures tests remain aligned with OOP messaging principles and refactoring safety requirements

3. **Private Method Testing Philosophy** - Private methods are implementation details validated through public behavior testing - reflection-based testing of internals breaks encapsulation and creates brittle tests that fail during legitimate refactoring of internal logic
