<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Form\DataTransformer;

use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * @implements DataTransformerInterface<int, string>
 */
class MinorUnitsTransformer implements DataTransformerInterface
{
    public function __construct(private readonly int $fractionDigits)
    {
    }

    public function transform(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return (string)BigDecimal::of($value)->withPointMovedLeft($this->fractionDigits);
    }

    public function reverseTransform(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return BigDecimal::of($value)
                ->withPointMovedRight($this->fractionDigits)
                ->toBigInteger()
                ->toInt();
        } catch (MathException $e) {
            throw new TransformationFailedException($e->getMessage(), previous: $e);
        }
    }
}
