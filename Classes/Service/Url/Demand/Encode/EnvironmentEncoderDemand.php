<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Demand\Encode;

use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Extbase\Mvc\ExtbaseRequestParameters;

class EnvironmentEncoderDemand extends EncoderDemand
{

    /**
     * @param array $queryParams
     * @param array $pageRecord
     * @param PageArguments|null $pageRoutingArguments
     * @param ExtbaseRequestParameters|null $extbaseRequestParameters
     */
    public function __construct(
        private readonly array $queryParams,
        private readonly array $pageRecord,
        private readonly ?PageArguments $pageRoutingArguments,
        private readonly ?ExtbaseRequestParameters $extbaseRequestParameters
    )
    {}

    /**
     * @return array
     */
    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    /**
     * @return array
     */
    public function getPageRecord(): array
    {
        return $this->pageRecord;
    }

    /**
     * @return PageArguments|null
     */
    public function getPageRoutingArguments(): ?PageArguments
    {
        return $this->pageRoutingArguments;
    }

    /**
     * @return ExtbaseRequestParameters|null
     */
    public function getExtbaseRequestParameters(): ?ExtbaseRequestParameters
    {
        return $this->extbaseRequestParameters;
    }

    /**
     * @inheritDoc
     */
    public function getCacheKey(): ?string
    {
        $signature = null;
        if ($this->extbaseRequestParameters) {
            $signature = serialize($this->extbaseRequestParameters->getArguments()) . '-' . $this->extbaseRequestParameters->getPluginName() . '-' . $this->extbaseRequestParameters->getControllerExtensionName() . '-' . $this->extbaseRequestParameters->getControllerName() . '-' . $this->extbaseRequestParameters->getControllerActionName();
        } elseif ($this->pageRoutingArguments) {
            $signature = serialize($this->pageRoutingArguments->getArguments() + $this->pageRoutingArguments->getStaticArguments() + $this->pageRoutingArguments->getDynamicArguments() + $this->pageRoutingArguments->getRouteArguments() + $this->pageRoutingArguments->getQueryArguments());
        } elseif (!empty($this->queryParams)) {
            $signature = serialize($this->queryParams) . ($this->pageRecord['uid'] ?? '0');
        }

        if ($signature) {
            return md5($signature).'@'.$this->getLanguageId().'('. ($this->isAbsolute() ? 'ABS':'NO-ABS') .')';
        }

        return null;
    }
}
