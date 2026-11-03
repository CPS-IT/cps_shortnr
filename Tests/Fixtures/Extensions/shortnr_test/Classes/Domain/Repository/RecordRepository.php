<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Tests\Fixtures\Extensions\shortnr_test\Classes\Domain\Repository;

use CPSIT\ShortNr\Tests\Fixtures\Extensions\shortnr_test\Classes\Domain\Model\Record;
use TYPO3\CMS\Extbase\Persistence\Repository;

/**
 * @extends Repository<Record>
 */
class RecordRepository extends Repository
{
    public function findByUidIncludingHidden(int $uid): ?Record
    {
        $query = $this->createQuery();
        $query->getQuerySettings()->setIgnoreEnableFields(true)->setRespectStoragePage(false);

        return $query->matching($query->equals('uid', $uid))->execute()->getFirst();
    }
}
