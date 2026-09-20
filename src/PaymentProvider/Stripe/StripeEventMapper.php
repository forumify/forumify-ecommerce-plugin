<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\PaymentProvider\Stripe;

use DateTimeImmutable;
use Forumify\Ecommerce\PaymentProvider\Event\CheckoutCancelled;
use Forumify\Ecommerce\PaymentProvider\Event\CheckoutCompleted;
use Forumify\Ecommerce\PaymentProvider\Event\CheckoutFailed;
use Forumify\Ecommerce\PaymentProvider\Event\PaymentEvent;
use Forumify\Ecommerce\PaymentProvider\Event\PaymentRefunded;
use Forumify\Ecommerce\PaymentProvider\Event\SubscriptionEnded;
use Forumify\Ecommerce\PaymentProvider\Event\SubscriptionPaymentFailed;
use Forumify\Ecommerce\PaymentProvider\Event\SubscriptionRenewalStarted;
use Forumify\Ecommerce\PaymentProvider\Event\SubscriptionRenewed;
use Forumify\Ecommerce\PaymentProvider\Event\SubscriptionUpdated;
use Stripe\Charge;
use Stripe\Checkout\Session;
use Stripe\Dispute;
use Stripe\Event;
use Stripe\Invoice;
use Stripe\StripeClient;
use Stripe\StripeObject;
use Stripe\Subscription;
use UnexpectedValueException;

class StripeEventMapper
{
    public const ORDER_REFERENCE_KEY = 'ecommerce_order_reference';

    /**
     * @return list<PaymentEvent>
     */
    public function fromEvent(StripeClient $client, Event $event): array
    {
        $object = $event->data->object ?? null;

        return match (true) {
            $object instanceof Session => $this->fromSessionEvent($client, $event->type, $object),
            $object instanceof Invoice => $this->fromInvoiceEvent($client, $event->type, $object),
            $object instanceof Subscription => $this->fromSubscriptionEvent($event->type, $object),
            $object instanceof Charge => $this->fromChargeEvent($client, $event->type, $object),
            $object instanceof Dispute => $this->fromDisputeEvent($client, $event->type, $object),
            default => [],
        };
    }

    /**
     * @return list<PaymentEvent>
     */
    public function fromSession(StripeClient $client, Session $session): array
    {
        $reference = $session->client_reference_id;
        if ($reference === null) {
            return [];
        }

        if ($session->status === Session::STATUS_EXPIRED) {
            return [new CheckoutCancelled($reference)];
        }

        $unpaid = $session->payment_status === Session::PAYMENT_STATUS_UNPAID;
        if ($session->status !== Session::STATUS_COMPLETE || $unpaid) {
            return [];
        }

        $subscriptionId = self::id($session['subscription'] ?? null);
        $periodEnd = $subscriptionId === null
            ? null
            : self::subscriptionPeriodEnd($client->subscriptions->retrieve($subscriptionId));

        return [new CheckoutCompleted($reference, self::id($session['customer'] ?? null), $subscriptionId, $periodEnd)];
    }

    /**
     * @return list<PaymentEvent>
     */
    public function fromSubscription(Subscription $subscription): array
    {
        if ($subscription->status === Subscription::STATUS_CANCELED) {
            return [new SubscriptionEnded($subscription->id)];
        }

        $cancelling = $subscription->cancel_at_period_end || ($subscription['cancel_at'] ?? null) !== null;

        return [new SubscriptionUpdated($subscription->id, $cancelling)];
    }

    /**
     * @return list<PaymentEvent>
     */
    private function fromSessionEvent(StripeClient $client, string $type, Session $session): array
    {
        return match ($type) {
            'checkout.session.completed',
            'checkout.session.async_payment_succeeded',
            'checkout.session.expired' => $this->fromSession($client, $session),
            'checkout.session.async_payment_failed' => $session->client_reference_id === null
                ? []
                : [new CheckoutFailed($session->client_reference_id)],
            default => [],
        };
    }

    /**
     * @return list<PaymentEvent>
     */
    private function fromInvoiceEvent(StripeClient $client, string $type, Invoice $invoice): array
    {
        $subscriptionId = self::id(
            $invoice['parent']['subscription_details']['subscription'] ?? $invoice['subscription'] ?? null,
        );
        if ($subscriptionId === null) {
            return [];
        }

        $renewal = $invoice->billing_reason === Invoice::BILLING_REASON_SUBSCRIPTION_CYCLE;

        return match ($type) {
            'invoice.created' => $renewal ? [new SubscriptionRenewalStarted($subscriptionId, $invoice->id)] : [],
            'invoice.paid' => $renewal
                ? [new SubscriptionRenewed(
                    $subscriptionId,
                    $invoice->id,
                    $this->invoicePeriodEnd($client, $invoice, $subscriptionId),
                )]
                : [],
            'invoice.payment_failed' => [
                new SubscriptionPaymentFailed($subscriptionId, $renewal ? $invoice->id : null),
            ],
            default => [],
        };
    }

    /**
     * @return list<PaymentEvent>
     */
    private function fromSubscriptionEvent(string $type, Subscription $subscription): array
    {
        return match ($type) {
            'customer.subscription.updated' => $this->fromSubscription($subscription),
            'customer.subscription.deleted' => [new SubscriptionEnded($subscription->id)],
            default => [],
        };
    }

    /**
     * @return list<PaymentEvent>
     */
    private function fromChargeEvent(StripeClient $client, string $type, Charge $charge): array
    {
        if ($type !== 'charge.refunded') {
            return [];
        }

        return [$this->refunded($client, $charge, $charge->amount_refunded)];
    }

    /**
     * A lost dispute takes the money back just like a refund does. A won one changes nothing.
     *
     * @return list<PaymentEvent>
     */
    private function fromDisputeEvent(StripeClient $client, string $type, Dispute $dispute): array
    {
        if ($type !== 'charge.dispute.closed' || $dispute->status !== Dispute::STATUS_LOST) {
            return [];
        }

        $chargeId = self::id($dispute['charge'] ?? null);
        if ($chargeId === null) {
            return [];
        }

        return [$this->refunded($client, $client->charges->retrieve($chargeId), $dispute->amount)];
    }

    private function refunded(StripeClient $client, Charge $charge, ?int $amountMinor): PaymentRefunded
    {
        $invoiceId = self::id($charge['invoice'] ?? null);
        if ($invoiceId !== null) {
            return new PaymentRefunded(externalPaymentId: $invoiceId, amountMinor: $amountMinor);
        }

        return new PaymentRefunded(self::orderReference($client, $charge), amountMinor: $amountMinor);
    }

    private static function orderReference(StripeClient $client, Charge $charge): ?string
    {
        $reference = $charge->metadata[self::ORDER_REFERENCE_KEY] ?? null;
        if (is_string($reference)) {
            return $reference;
        }

        $paymentIntentId = self::id($charge['payment_intent'] ?? null);
        if ($paymentIntentId === null) {
            return null;
        }

        $reference = $client->paymentIntents->retrieve($paymentIntentId)->metadata[self::ORDER_REFERENCE_KEY] ?? null;

        return is_string($reference) ? $reference : null;
    }

    public static function invoicePaymentIntentId(Invoice $invoice): ?string
    {
        $payments = $invoice['payments']['data'] ?? [];
        foreach ($payments as $payment) {
            $paymentIntent = self::id($payment['payment']['payment_intent'] ?? null);
            if ($paymentIntent !== null) {
                return $paymentIntent;
            }
        }

        return self::id($invoice['payment_intent'] ?? null);
    }

    private function invoicePeriodEnd(StripeClient $client, Invoice $invoice, string $subscriptionId): DateTimeImmutable
    {
        $ends = [];
        foreach ($invoice['lines']['data'] ?? [] as $line) {
            $end = $line['period']['end'] ?? null;
            if (is_int($end)) {
                $ends[] = $end;
            }
        }

        if ($ends !== []) {
            return self::date(max($ends));
        }

        return self::subscriptionPeriodEnd($client->subscriptions->retrieve($subscriptionId))
            ?? throw new UnexpectedValueException("Stripe subscription {$subscriptionId} has no billing period.");
    }

    private static function subscriptionPeriodEnd(Subscription $subscription): ?DateTimeImmutable
    {
        $ends = [];
        foreach ($subscription['items']['data'] ?? [] as $item) {
            $end = $item['current_period_end'] ?? null;
            if (is_int($end)) {
                $ends[] = $end;
            }
        }

        $end = $ends === [] ? ($subscription['current_period_end'] ?? null) : max($ends);

        return is_int($end) ? self::date($end) : null;
    }

    public static function id(mixed $value): ?string
    {
        return match (true) {
            is_string($value) => $value,
            $value instanceof StripeObject && is_string($value->id) => $value->id,
            default => null,
        };
    }

    private static function date(int $timestamp): DateTimeImmutable
    {
        return new DateTimeImmutable("@{$timestamp}");
    }
}
