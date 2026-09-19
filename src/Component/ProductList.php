<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Component;

use Doctrine\ORM\QueryBuilder;
use Forumify\Core\Component\List\AbstractDoctrineList;
use Forumify\Ecommerce\Entity\Product;
use Forumify\Ecommerce\Entity\ProductCategory;
use Forumify\Ecommerce\Entity\ProductStatus;
use Forumify\Ecommerce\Repository\ProductCategoryRepository;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\Metadata\UrlMapping;

/**
 * @extends AbstractDoctrineList<Product>
 */
#[AsLiveComponent('Ecommerce\\ProductList', '@ForumifyEcommercePlugin/frontend/components/product_list.html.twig')]
class ProductList extends AbstractDoctrineList
{
    #[LiveProp(writable: true, url: true)]
    public ?string $category = null;

    #[LiveProp(writable: true, onUpdated: 'onQueryUpdated', url: new UrlMapping(as: 'q'))]
    public ?string $query = null;

    /** @var list<ProductCategory>|null */
    private ?array $categories = null;

    public function __construct(private readonly ProductCategoryRepository $categoryRepository)
    {
    }

    #[LiveAction]
    public function filter(#[LiveArg] string $category = ''): void
    {
        $this->category = $category !== '' ? $category : null;
        $this->page = 1;
    }

    #[LiveAction]
    public function clear(): void
    {
        $this->category = null;
        $this->query = null;
        $this->page = 1;
    }

    public function onQueryUpdated(): void
    {
        $this->query = trim($this->query ?? '') !== '' ? $this->query : null;
        $this->page = 1;
    }

    /**
     * @return list<ProductCategory>
     */
    public function getCategories(): array
    {
        return $this->categories ??= $this->categoryRepository->findWithPublishedProducts();
    }

    public function getSelectedCategory(): ?ProductCategory
    {
        foreach ($this->getCategories() as $category) {
            if ($category->getSlug() === $this->category) {
                return $category;
            }
        }

        return null;
    }

    protected function getEntityClass(): string
    {
        return Product::class;
    }

    protected function getQuery(): QueryBuilder
    {
        $qb = parent::getQuery()
            ->andWhere('e.status = :published')
            ->setParameter('published', ProductStatus::Published);

        if ($this->category !== null) {
            $qb->innerJoin('e.categories', 'c')
                ->andWhere('c.slug = :category')
                ->setParameter('category', $this->category);
        }

        $query = trim($this->query ?? '');
        if ($query !== '') {
            $qb->andWhere('e.title LIKE :query OR e.shortDescription LIKE :query')
                ->setParameter('query', '%' . addcslashes($query, '%_\\') . '%');
        }

        return $qb;
    }

    /**
     * @return array<Product>
     */
    protected function getData(): array
    {
        $products = parent::getData();
        if ($products !== []) {
            $this->repository->createQueryBuilder('p')
                ->select('p', 't', 'pr')
                ->leftJoin('p.tiers', 't')
                ->leftJoin('t.prices', 'pr')
                ->where('p IN (:products)')
                ->setParameter('products', $products)
                ->getQuery()
                ->getResult();
        }

        return $products;
    }
}
