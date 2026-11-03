<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Tests\Fixtures\Extensions\shortnr_test\Classes\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

/**
 * Extbase view of tx_shortnrtest_record: importers and frontend forms write records this way,
 * bypassing DataHandler.
 */
class Record extends AbstractEntity
{
    protected string $title = '';
    protected bool $hidden = false;

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function isHidden(): bool
    {
        return $this->hidden;
    }

    public function setHidden(bool $hidden): void
    {
        $this->hidden = $hidden;
    }
}
