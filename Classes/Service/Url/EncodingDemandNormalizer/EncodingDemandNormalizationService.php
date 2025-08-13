<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\EncodingDemandNormalizer;

use CPSIT\ShortNr\Config\DTO\ConfigInterface;
use CPSIT\ShortNr\Exception\ShortNrDemandNormalizationException;
use CPSIT\ShortNr\Service\Url\Demand\EncoderDemandInterface;
use CPSIT\ShortNr\Service\Url\EncodingDemandNormalizer\Normalizer\DTO\EncoderDemandNormalizationResult;
use CPSIT\ShortNr\Service\Url\EncodingDemandNormalizer\Normalizer\EncodingDemandNormalizerInterface;
use CPSIT\ShortNr\Traits\SortPriorityIterableTrait;

class EncodingDemandNormalizationService
{
    use SortPriorityIterableTrait;

    /**
     * @var array<EncodingDemandNormalizerInterface>
     */
    private readonly array $normalizers;

    /**
     * @param iterable<EncodingDemandNormalizerInterface> $normalizers
     */
    public function __construct(
        iterable $normalizers
    )
    {
        $this->normalizers = $this->sortIteratableByPrioity($normalizers);
    }

    /**
     * @param EncoderDemandInterface $demand
     * @param ConfigInterface $config
     * @return EncoderDemandNormalizationResult
     * @throws ShortNrDemandNormalizationException
     */
    public function normalize(EncoderDemandInterface $demand, ConfigInterface $config): EncoderDemandNormalizationResult
    {
        foreach ($this->normalizers as $normalizer) {
            if ($normalizer->supports($demand, $config)) {
                $result = $normalizer->normalize($demand, $config);
                if($result instanceof EncoderDemandNormalizationResult) {
                    return $result;
                }
            }
        }

        throw new ShortNrDemandNormalizationException("No normalizer supports: " . $demand::class);
    }
}
