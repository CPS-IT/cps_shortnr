# ShortNr - TYPO3 Short URL Extension

Routing alias enhancer via middleware for TYPO3 CMS. Generates and resolves short URLs for pages and plugin records using configurable patterns.

## Requirements

- PHP ^8.2
- TYPO3 ^13.4

## Dependencies

- [brannow/typed-pattern-engine](https://github.com/brannow/typed-pattern-engine) ^0.4 - AST-based pattern matching engine

## Installation

```bash
composer require cpsit/shortnr
```

The extension auto-registers its middleware and cache configuration.

## Configuration

### Default Configuration

The extension ships with a minimal default configuration in `Configuration/config.yaml`:

```yaml
shortNr:
  _default:
    notFound: "/"
    languageParentField: "l10n_parent"
    languageField: "sys_language_uid"
    identifierField: "uid"

  pages:
    type: page
    table: pages
    pattern: "p{uid:int(min=1)}(-{sys_language_uid:int(min=0,default=0)})"
```

### Pattern Syntax

Patterns use the typed-pattern-engine syntax:

| Syntax | Description |
|--------|-------------|
| `{field:int(min=1)}` | Integer with minimum value |
| `{field:int(min=0,default=0)}` | Integer with default |
| `(-{field})` | Optional segment (parentheses) |
| `PREFIX{field}` | Static prefix |

Example: `NEWS{uid:int(min=1)}(-{sys_language_uid:int(min=0,default=0)})` generates `NEWS123` or `NEWS123-1`

### Adding Custom Configuration

Create an event listener to register your configuration file:

```php
<?php
namespace Vendor\SitePackage\EventListener\ShortNr;

use CPSIT\ShortNr\Event\ShortNrConfigPathEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;

#[AsEventListener(
    identifier: 'site-package/shortNrConfigListener',
    event: ShortNrConfigPathEvent::class,
    method: 'loadConfig'
)]
class ConfigLoadingEventListener
{
    public function loadConfig(ShortNrConfigPathEvent $event): void
    {
        $event->addConfigPath('EXT:site_package/Configuration/cps_shortnr.yaml');
    }
}
```

### Config Merging Behavior

Configs are merged via `array_replace_recursive` - higher priority configs (default: 10) override lower ones (extension default: -1000).

- Every field can be overwritten by later configs
- Arrays merge recursively (add new keys, override existing)
- Set a field to `null` to remove it
- the priority can be altered at the ShortNrConfigPathEvent::addConfigPath call

### Configuration Examples

**Page type:**
```yaml
shortNr:
  pages:
    type: page
    table: pages
    pattern: "PAGE{uid:int(min=1)}(-{sys_language_uid:int(min=0,default=0)})"
```

**Plugin type (e.g., News):**
```yaml
shortNr:
  news:
    type: plugin
    table: tx_news_domain_model_news
    pattern: "NEWS{uid:int(min=1)}(-{sys_language_uid:int(min=0,default=0)})"
    plugin:
      extension: News
      plugin: Pi1
      pid: 43           # Page UID containing the plugin
      action: detail
      controller: News
      objectName: news  # Request parameter name
```

**With conditions:**
```yaml
shortNr:
  event:
    type: plugin
    table: tx_news_domain_model_news
    pattern: "EVENT{uid:int(min=1)}(-{sys_language_uid:int(min=0,default=0)})"
    plugin:
      extension: News
      plugin: Pi1
      pid: 474
      action: detail
      controller: News
      objectName: news
    condition:
      is_event: 1
```

### Available Condition Operators

See [Configuration/config.example.full.yaml](Configuration/config.example.full.yaml) for detailed examples.

| Operator | Example | Description |
|----------|---------|-------------|
| implicit | `field: value` | Equals |
| `eq` | `field: {eq: value}` | Equals (explicit) |
| `in` | `field: [a, b, c]` | Value in array |
| `contains` | `field: {contains: "text"}` | Substring match |
| `starts` | `field: {starts: "prefix"}` | Starts with |
| `ends` | `field: {ends: "suffix"}` | Ends with |
| `gt`, `gte` | `field: {gte: 50}` | Greater than (or equal) |
| `lt`, `lte` | `field: {lt: 100}` | Less than (or equal) |
| `between` | `field: {between: [10, 50]}` | Range |
| `isset` | `field: {isset: true}` | Field exists |
| `not` | `field: {not: {eq: value}}` | Negation |

## Usage

### ViewHelper

Generate short URLs in Fluid templates:

```html
<html xmlns:sn="http://typo3.org/ns/CPSIT/ShortNr/ViewHelpers"
      data-namespace-typo3-fluid="true">

<!-- By config name and UID -->
<sn:shortUrl name="news" uid="123" output="result" absolute="1">
    <f:if condition="{result.uri}">
        <a href="{result.uri}">{result.uri}</a>
    </f:if>
</sn:shortUrl>

<!-- From current page context (no name/uid = environment demand) -->
<sn:shortUrl output="shortNr" absolute="1">
    {shortNr.uri}
</sn:shortUrl>

<!-- From domain object -->
<sn:shortUrl object="{newsItem}" output="result" absolute="1">
    {result.uri}
</sn:shortUrl>
```

**ViewHelper Arguments:**

| Argument | Type | Description |
|----------|------|-------------|
| `output` | string | Variable name for result (required) |
| `languageUid` | int | Target language |
| `absolute` | bool | Generate absolute URL |

The ViewHelper supports three demand modes (mutually exclusive):

| Mode | Arguments | Description |
|------|-----------|-------------|
| Config + UID | `name` + `uid` | Explicit config name and record UID |
| Object | `object` | Domain object (extracts config + UID automatically) |
| Environment | *(none)* | Uses current page/plugin context |

### Service (PHP)

**Encoding (generate short URL):**

```php
use CPSIT\ShortNr\Service\EncoderService;
use CPSIT\ShortNr\Service\Url\Demand\Encode\ConfigNameEncoderDemand;
use CPSIT\ShortNr\Service\Url\Demand\Encode\ObjectEncoderDemand;
use CPSIT\ShortNr\Service\Url\Demand\Encode\EnvironmentEncoderDemand;

// Inject EncoderService via constructor
public function __construct(private readonly EncoderService $encoderService) {}

// 1. Config + UID: Explicit config name and record UID
$demand = (new ConfigNameEncoderDemand('news', 123))
    ->setRequest($request)      // Required for site context
    ->setLanguageId(0)          // Target language (null = from request)
    ->setAbsolute(true);        // Full URL vs relative path
$uri = $this->encoderService->encode($demand);

// 2. Object: From domain object (AbstractEntity)
$demand = (new ObjectEncoderDemand($newsEntity))
    ->setRequest($request)
    ->setAbsolute(true);
$uri = $this->encoderService->encode($demand);

// 3. Environment: From current request context
$demand = (new EnvironmentEncoderDemand(
    $request->getQueryParams(),
    $request->getAttribute('frontend.page.information')?->getPageRecord() ?? [],
    $request->getAttribute('routing'),
    $request->getAttribute('extbase')
))
    ->setRequest($request)
    ->setAbsolute(true);
$uri = $this->encoderService->encode($demand);
```

**Decoding (resolve short URL):**

```php
use CPSIT\ShortNr\Service\DecoderService;
use CPSIT\ShortNr\Service\Url\Demand\Decode\DecoderDemand;

// Inject DecoderService via constructor
public function __construct(private readonly DecoderService $decoderService) {}

// Direct: From short URL string
$demand = new DecoderDemand('NEWS123');
$fullUri = $this->decoderService->decode($demand);

// From request: Extracts short URL from request path
$demand = $this->decoderService->getDecoderDemandFromRequest($request);
$fullUri = $demand ? $this->decoderService->decode($demand) : null;
```

## How It Works

1. **Middleware** intercepts GET requests and checks for short URL patterns
2. **DecoderService** matches request path against configured patterns
3. On match: 301 redirect to resolved full URL
4. On no match: request continues to TYPO3 routing

**Encoding** compiles record data into short URL using configured pattern.
**Decoding** extracts UID from short URL, queries database with conditions, generates full URL.

## Caching

- Compiled patterns cached to filesystem
- Encoded/decoded URLs cached with 1 week TTL
- Cache cleared on `cache:all` or `cache:pages` flush
- Cache tags: `all`, `uri`, `encode`, `decode`

## NotFound Handling

Configure fallback behavior when a short URL cannot be resolved:

```yaml
shortNr:
  _default:
    notFound: 2           # Page UID
    # or
    notFound: "/404"      # URI string
    # or
    notFound: ""          # Disable (continue to TYPO3 routing)
```

## Extension Setup

The extension registers:
- PSR-15 middleware (early in stack, before site resolution)
- TYPO3 cache configuration (`cps_shortnr`)
- DataHandler hook for cache invalidation
