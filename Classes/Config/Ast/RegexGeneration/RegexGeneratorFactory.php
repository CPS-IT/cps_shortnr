<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\RegexGeneration;

use CPSIT\ShortNr\Config\Ast\RegexGeneration\Strategies\GroupRegexStrategy;
use CPSIT\ShortNr\Config\Ast\RegexGeneration\Strategies\LiteralRegexStrategy;
use CPSIT\ShortNr\Config\Ast\RegexGeneration\Strategies\SequenceRegexStrategy;
use CPSIT\ShortNr\Config\Ast\RegexGeneration\Strategies\SubSequenceRegexStrategy;

final class RegexGeneratorFactory
{
    public function create(): RegexGenerator
    {
        $generator = new RegexGenerator();
        
        // Order matters - most specific strategies first
        $generator->addStrategy(new LiteralRegexStrategy());
        $generator->addStrategy(new GroupRegexStrategy());
        $generator->addStrategy(new SubSequenceRegexStrategy());
        $generator->addStrategy(new SequenceRegexStrategy());
        
        return $generator;
    }
}