<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Enums;

use StringBackedEnum;

enum ConfigEnum: string
{
    // default names
    case ENTRYPOINT = 'shortNr';
    case DEFAULT_CONFIG = '_default';
    case Type = 'type';


    // not found handling
    case NotFound = 'notFound';

    // regex
    case Regex = 'regex';
    case Prefix = 'prefix';

    // database fields
    case Table = 'table';

    // condition
    case Condition = 'condition';

    // language handling
    case LanguageParentField = 'languageParentField';
    case LanguageField = 'languageField';
    case IdentifierField = 'identifierField';

    // condition operators
    case ConditionContains = 'contains';
    case ConditionNot = 'not';
    case ConditionEqual = 'eq';
    case ConditionGreaterThanEqual = 'gte';
    case ConditionGreaterThan = 'gt';
    case ConditionLessThan = 'lt';
    case ConditionLessThanEqual = 'lte';
    case ConditionStringEnds = 'ends';
    case ConditionStingStarts = 'starts';
    case ConditionIsset = 'isset';
    case ConditionRegexMatch = 'match';
    case ConditionBetween = 'between';
}
