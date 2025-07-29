# ShortNr TYPO3 Extension - AI Memory

## Vision & Philosophy
URL shortener with encode/decode capabilities, condition-based routing, and YAML configuration.
**Priority**: Clean Code AND Performance (middleware processes every request)
**Philosophy**: "Enable, Don't Enforce" - Extensible for diverse site requirements
**Principles**: OOP messaging pattern, TDD, PHP 8.4 (min 8.1+), TYPO3 12.4/13.4

## Project Status
- **Architecture**: Established with comprehensive test coverage
- **Stage**: Early WIP with missing features

## Core Architecture

### File Structure
```
Classes/
├── Cache/                                   # Caching system
│   ├── CacheAdapter/FastArrayFileCache.php # Atomic write PHP cache
│   └── CacheManager.php                    # TYPO3 cache bridge
├── Config/                                 # Configuration system
│   ├── ConfigInterface.php, ConfigLoader.php, DTO/Config.php
│   └── ExtensionSetup.php
├── Domain/Repository/ShortNrRepository.php # Database operations
├── Exception/                              # Exception hierarchy
├── Middleware/ShortNumberMiddleware.php    # Main request handler
├── Service/
│   ├── PlatformAdapter/                    # TYPO3 abstractions
│   │   ├── FileSystem/, Typo3/            # Interface wrappers
│   └── Url/
│       ├── AbstractUrlService.php         # Base with DI processor injection
│       ├── DecoderService.php            # URL decoding + caching
│       ├── EncoderService.php            # URL encoding (placeholder)
│       ├── Condition/
│       │   ├── ConditionService.php       # Dual-phase processing
│       │   └── Operators/                 # 12 operators with 4-interface system
│       │       ├── Interfaces: Operator, Query, Result, Wrapping
│       │       ├── Implementations: And, Equal, ArrayIn, Between, Greater, Less, Isset, Not, RegexMatch, StringContains/Starts/Ends
│       │       └── DTO/OperatorHistory*   # Loop prevention
│       └── Processor/                     # URL type handlers
│           ├── ProcessorInterface.php, BaseProcessor.php
│           └── PageProcessor.php, PluginProcessor.php (placeholders)
└── ViewHelpers/ShortUrlViewHelper.php     # Fluid integration (placeholder)
```

### Missing Components
- EncoderService business logic, ShortUrlViewHelper logic
- PageProcessor/PluginProcessor implementations
- TYPO3 multi-language UID handling

### Completed Systems
- **4-Interface Operator System**: Complete architecture (Operator/Query/Result/Wrapping)
- **12 Operator Implementations**: All condition operators including AndOperator
- **ShortNrRepository**: Database ops with condition system integration
- **Exception Hierarchy**: Comprehensive error handling
- **Operator History DTO**: Infinite loop prevention

### Key Patterns
- **DI + Auto-Discovery**: Constructor injection, Symfony tags, interface segregation
- **Multi-Level Caching**: Runtime → File → YAML with atomic writes
- **Dual-Phase Processing**: QueryOperators (SQL) + ResultOperators (PHP filtering)
- **Generator Performance**: Lazy eval, early returns in ConditionService
- **Clean Abstractions**: TYPO3 utilities behind interfaces

## Core Components

### Configuration System
- **ConfigLoader**: Multi-level caching (Runtime→File→YAML), returns ConfigInterface DTOs
- **Config DTO**: Route inheritance from `_default`, runtime caching for expensive ops
- **Pattern**: `routeConfig[key] ?? _default[key] ?? null`

### Caching System
- **FastArrayFileCache**: Atomic writes (temp→rename), PHP array serialization
- **CacheManager**: TYPO3 bridge with graceful degradation

### Condition System (Dual-Phase Processing)
- **ConditionService**: Two-phase processing with operator injection
  - **Phase 1 (Query)**: QueryOperators build SQL WHERE conditions
  - **Phase 2 (Result)**: ResultOperators filter PHP result arrays
- **Wrapping Operators**: Enable nested logic (AND/NOT) with recursive callbacks
- **Runtime Caching**: URI+regex composite keys prevent duplicate processing

### URL Processing
- **AbstractUrlService**: Base with processor injection, config access, condition integration
- **DecoderService**: 24h caching, processor delegation, candidate iteration
- **ProcessorInterface**: Type identification via config `types`, auto-discovery DI

### Abstractions
- **PathResolverInterface**: TYPO3 path resolution isolation
- **FileSystemInterface**: File operations abstraction

## Testing

### Quality Standards
**DO**: Behavioral testing, DataProviders, interface mocking, refactor-safe contracts
**DON'T**: Reflection testing, implementation details, wrapper testing, mock configuration validation

### Test Commands
```bash
./docker.sh exec /var/www/html/.Build/bin/phpunit                    # All tests
./docker.sh exec /var/www/html/.Build/bin/phpunit path/to/TestFile   # Specific test
./docker.sh exec /var/www/html/.Build/bin/phpunit --coverage-html var/coverage
./docker.sh exec /var/www/html/.Build/bin/phpunit --filter="methodName"
```

### Docker Environment
```bash
./docker.sh up -d          # Start (smart building, auto-start on exec)
./docker.sh exec [cmd]     # Execute with UID/GID injection
```

## Configuration

### YAML Schema (`Configuration/config.yaml`)
```yaml
shortNr:
    _default:
        # support page id or hard-coded slug (detects it via is_numeric)
        notFound: "/"
        # placeholder are based on the regex match groups {match-1}, {match-2}, {match-3}, ... etc
        regex: "/^([a-zA-Z]+?)(\\d+)[-]?(\\d+)?$/"
        regexGroupMapping: # regex group mapping
            prefix: "{match-1}"
            uid: "{match-2}"
            languageUid: "{match-3}"
        condition: # database conditions
            uid: "{match-2}"
            sysLanguageUid: "{match-3}"
        languageParentField: "l10n_parent"  # default TYPO3 field name

    pages: # default page
        type: page # currently support only page resolvent, later also plugins, and other custom types
        prefix: PAGE
        table: pages
        slug: slug
        condition:
            uid: "{match-2}"
            sysLanguageUid: "{match-3}"
            is_event:
                contains: "test2"
                not:
                    eq: 1
                    contains: "test"
                #not: 1 # is_event not 1 (is_event != 1)
            score:
                gte: 50 # gte = greater than equal (score >= 5) (also supports gt)
            ranking:
                lt: 30 # lt = lower than (ranking < 30) (also supports lte)
            status: [ "active", "pending" ]    # implicit IN
            name:
                contains: "test"
            lastName:
                not:
                    contains: "test"
            version:
                not:
                    ends: '-rc'
                starts: 'v'
            street:
                ends: 'road'
            surname:
                isset: true # any name is ok as long the variable exists
            email:
                match: "^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}$"  # regex
            age:
                not:
                    between: [ 18, 65 ]     # shorthand for >= 18 AND <= 65
            blocked_users:
                not: [ "spam", "bot" ]  # opposite of implicit IN
```

**Key Concepts**: Route inheritance from `_default`, `{match-N}` placeholders, custom processors via `types`, user-defined route names

### DI Configuration (`Configuration/Services.yaml`)
Auto-discovery with operator/processor tagging:
```yaml
  _instanceof:
      CPSIT\ShortNr\Service\Url\Condition\Operators\QueryOperatorInterface:
          tags: ['cps_shortnr.query.operators']
      CPSIT\ShortNr\Service\Url\Condition\Operators\ResultOperatorInterface:
          tags: ['cps_shortnr.result.operators']
      CPSIT\ShortNr\Service\Url\Processor\ProcessorInterface:
          tags: [ 'cps_shortnr.processors' ]

  CPSIT\ShortNr\Service\Url\Condition\ConditionService:
      arguments:
          $queryOperators: !tagged 'cps_shortnr.query.operators'
          $resultOperators: !tagged 'cps_shortnr.result.operators'

  CPSIT\ShortNr\Service\Url\AbstractUrlService:
      arguments:
          $processors: !tagged 'cps_shortnr.processors'
```

## Development Guidelines

### Development Patterns
- **Interface-first**: Create interface → implementation → DI configuration → tests
- **Constructor injection**: All dependencies injected, mock via interfaces
- **Atomic operations**: Temp files for safe writes, runtime caching for performance
- **Modern PHP**: Use language features first, zero tolerance for deprecations
- **Test behavior**: DataProviders, willReturnCallback(), avoid implementation details

## Implementation Details

### Cache Strategy
- **Files**: `{TYPO3_VAR_PATH}/cache/code/cps_shortnr/config{md5}.php` (executable PHP arrays)
- **Atomicity**: temp→rename prevents corruption, mtime-based invalidation
- **Performance**: Runtime cache prevents duplicate reads, PHP arrays > JSON

### Error Handling
- **ConfigLoader**: Graceful degradation (empty array)
- **FastArrayFileCache**: Throws ShortNrCacheException
- **CacheManager**: Null fallback on TYPO3 failures

## Operator System

### Dual-Phase Processing
**Phase 1 (QueryOperator)**: Build SQL WHERE conditions before execution (leverages DB indexes)
- Examples: EqualOperator, LessOperator, GreaterOperator, IssetOperator

**Phase 2 (ResultOperator)**: Filter PHP result arrays after execution (complex logic)
- Examples: RegexMatchOperator, StringContainsOperator

**WrappingOperator**: Extends both interfaces for nested logic (AND/NOT)
- Recursive callbacks with OperatorHistory preventing infinite loops

## Future Development
- PageProcessor/PluginProcessor database logic, TYPO3 multi-language handling
- EncoderService with collision handling, ViewHelper integration
- Cache warming, monitoring metrics

## Session Updates
Update SESSION_UPDATE.md with:
- Move completed items from "Missing Components"
- Add max 3 insights (WHY they matter, not just WHAT was done)
- Keep scannable with consistent terminology
- Zero tolerance for deprecations
- Performance context: Sub-1ms middleware goal, teams choose complexity level

---

