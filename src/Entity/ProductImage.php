<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Entity;

use Doctrine\ORM\Mapping as ORM;
use Forumify\Core\Entity\AuditableEntityInterface;
use Forumify\Core\Entity\IdentifiableEntityTrait;
use Forumify\Core\Entity\SortableEntityInterface;
use Forumify\Core\Entity\SortableEntityTrait;
use Forumify\Core\Entity\TimestampableEntityTrait;
use Forumify\Ecommerce\Repository\ProductImageRepository;

#[ORM\Entity(repositoryClass: ProductImageRepository::class)]
#[ORM\Table('ecommerce_product_image')]
class ProductImage implements SortableEntityInterface, AuditableEntityInterface
{
    use IdentifiableEntityTrait;
    use SortableEntityTrait;
    use TimestampableEntityTrait;

    #[ORM\ManyToOne(inversedBy: 'images')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public private(set) Product $product;

    #[ORM\Column(length: 255)]
    public private(set) string $image;

    public function __construct(Product $product, string $image)
    {
        $this->product = $product;
        $this->image = $image;
    }

    public function getIdentifierForAudit(): string
    {
        return (string)$this->getId();
    }

    public function getNameForAudit(): string
    {
        return "{$this->product->title}: {$this->image}";
    }
}
