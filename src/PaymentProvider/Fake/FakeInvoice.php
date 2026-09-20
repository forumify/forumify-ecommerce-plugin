<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\PaymentProvider\Fake;

use Forumify\Ecommerce\Entity\Order;
use Forumify\Ecommerce\Money\MoneyFormatter;
use Symfony\Component\DependencyInjection\Attribute\WhenNot;

/**
 * A stand-in for the invoice a real provider issues: enough of a PDF to prove the download works.
 */
#[WhenNot(env: 'prod')]
class FakeInvoice
{
    public function __construct(private readonly MoneyFormatter $moneyFormatter)
    {
    }

    public function render(Order $order): string
    {
        return self::pdf([
            'Invoice ' . $order->reference,
            '',
            'Date: ' . ($order->paidAt ?? $order->getCreatedAt())->format('j M Y'),
            'Customer: ' . ($order->user?->getDisplayName() ?? 'Unknown'),
            '',
            $order->productTitle . ' - ' . $order->tierLabel,
            'Total: ' . $this->moneyFormatter->format($order->getAmount()),
            '',
            'Paid with the fake payment provider. This is not a real invoice.',
        ]);
    }

    /**
     * @param list<string> $lines
     */
    private static function pdf(array $lines): string
    {
        $content = "BT\n/F1 12 Tf\n60 780 Td\n16 TL\n";
        foreach ($lines as $line) {
            $content .= '(' . self::escape($line) . ") Tj\nT*\n";
        }
        $content .= 'ET';

        $objects = [
            '<</Type/Catalog/Pages 2 0 R>>',
            '<</Type/Pages/Kids[3 0 R]/Count 1>>',
            '<</Type/Page/Parent 2 0 R/MediaBox[0 0 595 842]'
                . '/Resources<</Font<</F1 4 0 R>>>>/Contents 5 0 R>>',
            '<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>',
            '<</Length ' . strlen($content) . ">>\nstream\n" . $content . "\nendstream",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1) . " 0 obj\n" . $object . "\nendobj\n";
        }

        $size = count($objects) + 1;
        $startXref = strlen($pdf);
        $pdf .= "xref\n0 {$size}\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= "trailer\n<</Size {$size}/Root 1 0 R>>\nstartxref\n{$startXref}\n%%EOF";

        return $pdf;
    }

    private static function escape(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }
}
