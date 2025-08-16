<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Validation;

final class ValidationPipelineFactory
{
    public function create(): ValidationPipeline
    {
        $pipeline = new ValidationPipeline();
        
        // Add validators in order of execution
        $pipeline->addValidator(new TreeContextValidator());
        $pipeline->addValidator(new GreedyValidator());
        
        return $pipeline;
    }
}