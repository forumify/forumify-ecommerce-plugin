<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\PaymentProvider;

enum PaymentProviderCapability
{
    /** refund() can send the money back. */
    case Refund;

    /** getInvoice() returns an invoice document for a paid order. */
    case Invoice;

    /** getHostedPaymentUrl() returns a page where the buyer can settle an unpaid order. */
    case HostedPayment;
}
