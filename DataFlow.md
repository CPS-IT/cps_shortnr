# Dataflow

## Decoding


┌-----------------------------┐
│  HTTP Request               │
│  mysite.com/PAGE123-1       │
└----------┬------------------┘
           │
┌----------▼----------┐
│  DecoderMiddleware  │  (TYPO3 PSR-15)
│  • Is it a ShortNr? │
│  • 301 Redirect     │
│  • no-cache headers │
└----------┬----------┘
           │
┌----------▼----------┐
│  DecoderService     │
│  • normalize URI    │
│  • 24 h cache hit   │
│  • ask Processor    │
└----------┬----------┘
           │
┌----------▼----------┐
│  Processor          │
│  (Page | Plugin)    │
│  • regex match      │
│  • DB lookup        │
│  • build full URI   │
└----------┬----------┘
           │
┌----------▼----------┐
│  ProcessorResult    │
│  → full URI string  │
│  → validity flag    │
└----------┬----------┘
           │
┌----------▼----------┐
│  RedirectResponse   │
│  Location: /en/...  │
└---------------------┘


Decode an ShortNr URI segment into the real URI

### EntryPoint Middleware

* Request given to DecoderService
* * Check if Uri is Middleware
* Decode ShortNr via Request
* if URI was successfully decoded, create RedirectResponse (intercept Middleware) (SEO friendly)
* * 301 response code
* * 'Cache-Control' => 'no-cache, no-store, must-revalidate',
* * 'X-Robots-Tag' => 'noindex, nofollow'

### DecoderService

* decodeRequest - gets the URI from the Request
* decode -
* * decode entire URI segment,
* * normalize URI and trim by normalizing we use only the Last URI segment without any query or Framents or slashes
* * cache the decode result for 1 day (24h)
* decodeShortNr (private) - Decode the plain ShortNr
* return the decoded full URI

### decodeShortNr -> ConfigLoader (ConfigItem)

config shortNr decoding based on a given regex, every ConfigItem CAN (but not must) their own special regex but most use the default regex

* loads entire config
* calls conditionService->findAllMatchConfigCandidates() to get all matching results, (all configItem that match the given ShortNr) it return candidate DTO (ConfigMatchCandidate) obj.
* ConfigMatchCandidate are Per Regex and contain the ConfigItem Names that use that exact Regex.
* iterate through all ConfigItem Names from the Candidate and give the ConfigItem.
* Load the Processor that can handle this ConfigItem (each Processor has a type). A type is also in each ConfigItem to match. the first match wins
* Tell the Processor to decode the ShortNr with these objects:
* * ConfigMatchCandidate (contains the Regex / and the Group matches of this regex + the Original ShortNrString)
* * ConfigItem, contains the Config for that Config Item Name but also has access to the global config or special custom Config Entries.
* expect an ProcessorDecodeResult as answer or NULL
* * if ProcessorDecodeResult check if that ProcessorDecodeResult->isValid()
* * * if is Valid return the Full URI

### Processor

The Processor is Responsible to decode the ShortNr back into the real Working URI that will be later redirected to.
Each Processor has the knowledge on how to decode the ShortNr. for example how to decode a Page and How to decode a Record Detail like a News Detail.

Custom Processor can be easily added by simply create a new decoder and add the Interface ( CPSIT\ShortNr\Service\Url\Processor\ProcessorInterface )

there are 2 Default Processor

* PageProcessor
* PluginProcessor

#### PageProcessor

Page processor resolves Pages based the given ShortNr that contains the Prefix to identify itself as a page, the Page_UID and optional a Language Flag for multiLanguage

Multi-language is only Supported and Needed on the Overlay translation Strategy, the other Speperate Page Tree By Language did not need any Language Flags since the Page UID are unique by themselves.

* sanitize / normalize the PageUid,
* * on overlay Systems we normally only work with the Base UID and the Language flag.
* * we use a PageTree (that is serialized and cached) to fast find the page UID and can detect malformed Page Uids like Sublanguage Uid instead of Base UID
* * recover the correct Page UID for the given BaseUid

* load the Page Slug form the Database
* call shortNrRepository->resolveTable
* * Uses the complex Operator system to apply PreQuery (adds DB query Conditions)
* * after Execute The Query, filter with PostQueryConditions (complex Conditions like Regex or such)
* * * custom condition can be implemented by simply implement anywhere a new Class with the (CPSIT\ShortNr\Service\Url\Condition\Operators\QueryOperatorInterface) for QueryOperations or (CPSIT\ShortNr\Service\Url\Condition\Operators\ResultOperatorInterface) for PostQueryOperations, the DI system do the rest
* transform first Matching result into a PageData DTO that contains the Page UID, LanguageID, and Slug
* * the slug is raw and unusable and must be refined with the SiteLanguage BaseUri and the Site BaseUri where the given page is located in that order (Site_BaseUri / SiteLanguage_BaseUri / Slug)
* transforms the PageData into full Functional Uri using the PageTree again to find the RootPageId and based on the LanguageId from the PageData. the Typo3SiteResolver is used as an Abstraction Layer for it.
* Returns ProcessorDecodeResult DTO object that contains the full URI
