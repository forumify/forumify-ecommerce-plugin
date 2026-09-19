<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Admin\Controller;

use Forumify\Admin\Crud\AbstractCrudController;
use Forumify\Core\Form\UploadType;
use Forumify\Core\Service\UploadTransaction;
use Forumify\Ecommerce\Admin\Form\NewProductTierType;
use Forumify\Ecommerce\Admin\Form\ProductImagesType;
use Forumify\Ecommerce\Admin\Form\ProductPriceType;
use Forumify\Ecommerce\Admin\Form\ProductTierType;
use Forumify\Ecommerce\Admin\Form\ProductType;
use Forumify\Ecommerce\Entity\Product;
use Forumify\Ecommerce\Entity\ProductImage;
use Forumify\Ecommerce\Entity\ProductPrice;
use Forumify\Ecommerce\Entity\ProductTier;
use Forumify\Ecommerce\Repository\ProductImageRepository;
use Forumify\Ecommerce\Repository\ProductPriceRepository;
use Forumify\Ecommerce\Repository\ProductTierRepository;
use Forumify\Ecommerce\Service\PriceChangeService;
use Forumify\Ecommerce\Service\StoreSettings;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * @extends AbstractCrudController<Product>
 */
#[Route('/products', 'products')]
#[IsGranted('ecommerce.admin.products.view')]
class ProductController extends AbstractCrudController
{
    protected ?string $permissionView = 'ecommerce.admin.products.view';
    protected ?string $permissionCreate = 'ecommerce.admin.products.manage';
    protected ?string $permissionEdit = 'ecommerce.admin.products.manage';
    protected ?string $permissionDelete = 'ecommerce.admin.products.manage';

    protected string $formTemplate = '@ForumifyEcommercePlugin/admin/products/form.html.twig';

    public function __construct(
        private readonly ProductTierRepository $tierRepository,
        private readonly ProductPriceRepository $priceRepository,
        private readonly PriceChangeService $priceChangeService,
        private readonly StoreSettings $storeSettings,
        private readonly ProductImageRepository $imageRepository,
        private readonly UploadTransaction $uploadTransaction,
        #[Autowire(service: 'ecommerce_asset.storage')]
        private readonly FilesystemOperator $assetStorage,
    ) {
    }

    protected function getTranslationPrefix(): string
    {
        return 'ecommerce.' . parent::getTranslationPrefix();
    }

    protected function getEntityClass(): string
    {
        return Product::class;
    }

    protected function getTableName(): string
    {
        return 'Ecommerce\\ProductTable';
    }

    protected function getForm(?object $data): FormInterface
    {
        return $this->createForm(ProductType::class, $data);
    }

    protected function redirectAfterSave(mixed $entity, bool $isNew): Response
    {
        return $isNew
            ? $this->redirectToRoute('ecommerce_admin_products_edit', ['identifier' => $entity->getId()])
            : $this->redirectToRoute('ecommerce_admin_products_list');
    }

    #[Route('/{id}/tiers/create', '_tier_create')]
    #[IsGranted('ecommerce.admin.products.manage')]
    public function createTier(Request $request, Product $product): Response
    {
        $tier = new ProductTier($product);
        $price = new ProductPrice($tier, $this->storeSettings->getCurrency());
        $tier->prices->add($price);

        $form = $this->createForm(NewProductTierType::class, ['tier' => $tier, 'price' => $price]);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->render('@ForumifyEcommercePlugin/admin/products/tier_form.html.twig', [
                'form' => $form->createView(),
                'tier' => null,
                'product' => $product,
            ]);
        }

        $this->tierRepository->save($tier);

        $this->addFlash('success', 'ecommerce.admin.tier.saved');
        return $this->redirectToProduct($product);
    }

    #[Route('/tiers/{id}/edit', '_tier_edit')]
    #[IsGranted('ecommerce.admin.products.manage')]
    public function editTier(Request $request, ProductTier $tier): Response
    {
        $form = $this->createForm(ProductTierType::class, $tier);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->render('@ForumifyEcommercePlugin/admin/products/tier_form.html.twig', [
                'form' => $form->createView(),
                'tier' => $tier,
                'product' => $tier->product,
            ]);
        }

        $this->tierRepository->save($tier);

        $this->addFlash('success', 'ecommerce.admin.tier.saved');
        return $this->redirectToProduct($tier->product);
    }

    #[Route('/tiers/{id}/delete', '_tier_delete')]
    #[IsGranted('ecommerce.admin.products.manage')]
    public function deleteTier(ProductTier $tier): Response
    {
        $product = $tier->product;
        if ($tier->hasLockedPrices()) {
            $this->addFlash('error', 'ecommerce.admin.tier.delete_locked');
            return $this->redirectToProduct($product);
        }

        $this->tierRepository->remove($tier);

        $this->addFlash('success', 'ecommerce.admin.tier.deleted');
        return $this->redirectToProduct($product);
    }

    #[Route('/{id}/images/create', '_image_create')]
    #[IsGranted('ecommerce.admin.products.manage')]
    public function createImages(Request $request, Product $product): Response
    {
        $form = $this->createForm(ProductImagesType::class);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->render('@Forumify/form/simple_form_page.html.twig', [
                'admin' => true,
                'cancelPath' => $this->generateUrl('ecommerce_admin_products_edit', ['identifier' => $product->getId()]) . '#tab-gallery',
                'form' => $form->createView(),
                'title' => 'ecommerce.admin.gallery.create',
                'titleArgs' => ['product' => $product->title],
            ]);
        }

        $images = array_map(
            static fn (string $path) => new ProductImage($product, $path),
            UploadType::toPaths($form->get('images')->getData()),
        );
        $this->imageRepository->saveAll($images);

        $this->addFlash('success', 'ecommerce.admin.gallery.saved');
        return $this->redirectToGallery($product);
    }

    #[Route('/images/{id}/delete', '_image_delete')]
    #[IsGranted('ecommerce.admin.products.manage')]
    public function deleteImage(ProductImage $image): Response
    {
        $this->uploadTransaction->stageDeletion($this->assetStorage, $image->image);
        $this->imageRepository->remove($image);

        $this->addFlash('success', 'ecommerce.admin.gallery.deleted');
        return $this->redirectToGallery($image->product);
    }

    #[Route('/tiers/{id}/prices/create', '_price_create')]
    #[IsGranted('ecommerce.admin.products.manage')]
    public function createPrice(Request $request, ProductTier $tier): Response
    {
        $price = new ProductPrice($tier, $this->storeSettings->getCurrency());

        return $this->handlePriceForm($request, $price, 'ecommerce.admin.price.create');
    }

    #[Route('/prices/{id}/edit', '_price_edit')]
    #[IsGranted('ecommerce.admin.products.manage')]
    public function editPrice(Request $request, ProductPrice $price): Response
    {
        return $this->handlePriceForm($request, $price, 'ecommerce.admin.price.edit');
    }

    #[Route('/prices/{id}/change', '_price_change')]
    #[IsGranted('ecommerce.admin.products.manage')]
    public function changePrice(Request $request, ProductPrice $price): Response
    {
        if (!$this->priceChangeService->canChange($price)) {
            $this->addFlash('error', 'ecommerce.admin.price.change_not_allowed');
            return $this->redirectToTier($price->tier);
        }

        $replacement = $this->priceChangeService->createReplacement($price);
        $form = $this->createForm(ProductPriceType::class, $replacement, ['show_active' => false]);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->renderPriceForm($form, $price->tier, 'ecommerce.admin.price.change', 'ecommerce.admin.price.change_help');
        }

        $this->priceChangeService->changePrice($price, $replacement);

        $this->addFlash('success', 'ecommerce.admin.price.changed');
        return $this->redirectToTier($price->tier);
    }

    #[Route('/prices/{id}/delete', '_price_delete')]
    #[IsGranted('ecommerce.admin.products.manage')]
    public function deletePrice(ProductPrice $price): Response
    {
        $tier = $price->tier;
        if ($price->locked) {
            $this->addFlash('error', 'ecommerce.admin.price.delete_locked');
            return $this->redirectToTier($tier);
        }
        if ($tier->prices->count() <= 1) {
            $this->addFlash('error', 'ecommerce.admin.price.delete_last');
            return $this->redirectToTier($tier);
        }

        $this->priceRepository->remove($price);

        $this->addFlash('success', 'ecommerce.admin.price.deleted');
        return $this->redirectToTier($tier);
    }

    private function handlePriceForm(Request $request, ProductPrice $price, string $title): Response
    {
        $form = $this->createForm(ProductPriceType::class, $price);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            $help = $price->locked ? 'ecommerce.admin.price.locked_help' : null;
            return $this->renderPriceForm($form, $price->tier, $title, $help);
        }

        $this->priceRepository->save($price);

        $this->addFlash('success', 'ecommerce.admin.price.saved');
        return $this->redirectToTier($price->tier);
    }

    /**
     * @param FormInterface<ProductPrice> $form
     */
    private function renderPriceForm(FormInterface $form, ProductTier $tier, string $title, ?string $help): Response
    {
        return $this->render('@ForumifyEcommercePlugin/admin/products/price_form.html.twig', [
            'form' => $form->createView(),
            'help' => $help,
            'tier' => $tier,
            'title' => $title,
        ]);
    }

    private function redirectToProduct(Product $product): Response
    {
        return $this->redirect($this->generateUrl('ecommerce_admin_products_edit', ['identifier' => $product->getId()]) . '#tab-tiers');
    }

    private function redirectToGallery(Product $product): Response
    {
        return $this->redirect($this->generateUrl('ecommerce_admin_products_edit', ['identifier' => $product->getId()]) . '#tab-gallery');
    }

    private function redirectToTier(ProductTier $tier): Response
    {
        return $this->redirect($this->generateUrl('ecommerce_admin_products_tier_edit', ['id' => $tier->getId()]) . '#tab-prices');
    }
}
