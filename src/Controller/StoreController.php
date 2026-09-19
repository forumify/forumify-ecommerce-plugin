<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Controller;

use Forumify\Ecommerce\Repository\ProductCategoryRepository;
use Forumify\Ecommerce\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

class StoreController extends AbstractController
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly ProductCategoryRepository $categoryRepository,
    ) {
    }

    #[Route('', 'store')]
    public function index(#[MapQueryParameter] ?string $category = null): Response
    {
        return $this->render('@ForumifyEcommercePlugin/frontend/store/index.html.twig', [
            'category' => $category !== null ? $this->categoryRepository->findOneBy(['slug' => $category]) : null,
        ]);
    }

    #[Route('/{slug}', 'product')]
    public function product(string $slug): Response
    {
        $product = $this->productRepository->findPublishedBySlug($slug);
        if ($product === null) {
            throw $this->createNotFoundException();
        }

        return $this->render('@ForumifyEcommercePlugin/frontend/store/product.html.twig', [
            'product' => $product,
        ]);
    }
}
