<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Revenue;

use Brick\Money\Money;

/**
 * Monthly recurring revenue, in the store currency. The part at risk is included in the total: it belongs to
 * past due subscriptions, which still renew if the customer's payment goes through.
 */
class Mrr
{
    public function __construct(
        public readonly Money $total,
        public readonly Money $atRisk,
        public readonly int $subscriptions,
        public readonly int $pastDue,
    ) {
    }
}
