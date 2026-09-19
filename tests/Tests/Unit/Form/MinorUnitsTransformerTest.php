<?php

declare(strict_types=1);

namespace PluginTests\Tests\Unit\Form;

use Forumify\Ecommerce\Form\DataTransformer\MinorUnitsTransformer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Exception\TransformationFailedException;

class MinorUnitsTransformerTest extends TestCase
{
    public function testConvertsBetweenDecimalsAndMinorUnits(): void
    {
        $cents = new MinorUnitsTransformer(2);
        self::assertSame('19.99', $cents->transform(1999));
        self::assertSame(1999, $cents->reverseTransform('19.99'));
        self::assertSame(0, $cents->reverseTransform('0'));
        self::assertNull($cents->reverseTransform(''));

        $yen = new MinorUnitsTransformer(0);
        self::assertSame('500', $yen->transform(500));
        self::assertSame(500, $yen->reverseTransform('500'));

        $this->expectException(TransformationFailedException::class);
        $yen->reverseTransform('500.5');
    }
}
