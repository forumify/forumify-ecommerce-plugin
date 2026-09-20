<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\PaymentProvider;

use LogicException;

/**
 * An invoice the provider issued: either a link to it, or its contents.
 */
class InvoiceDocument
{
    private function __construct(
        public readonly string $filename,
        public readonly ?string $url = null,
        public readonly ?string $contents = null,
        public readonly string $contentType = 'application/pdf',
    ) {
    }

    public static function url(string $url, string $filename): self
    {
        return new self($filename, url: $url);
    }

    public static function file(string $contents, string $filename, string $contentType = 'application/pdf'): self
    {
        return new self($filename, contents: $contents, contentType: $contentType);
    }

    public function getContents(): string
    {
        return $this->contents ?? throw new LogicException('This invoice is hosted by the payment provider.');
    }
}
