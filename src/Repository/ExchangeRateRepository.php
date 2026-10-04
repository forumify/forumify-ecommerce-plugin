<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Repository;

use Forumify\Core\Repository\AbstractRepository;
use Forumify\Ecommerce\Entity\ExchangeRate;

/**
 * @extends AbstractRepository<ExchangeRate>
 */
class ExchangeRateRepository extends AbstractRepository
{
    public static function getEntityClass(): string
    {
        return ExchangeRate::class;
    }

    /**
     * @return array<string, string> currency => units per US dollar
     */
    public function findAllRates(): array
    {
        /** @var list<array{currency: string, rate: string}> $rows */
        $rows = $this->createQueryBuilder('r')
            ->select('r.currency AS currency', 'r.rate AS rate')
            ->getQuery()
            ->getArrayResult();

        $rates = [];
        foreach ($rows as $row) {
            $rates[$row['currency']] = $row['rate'];
        }

        return $rates;
    }
}
