<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Admin\Form;

use Forumify\Core\Form\UploadType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractType<array{images: list<string>}>
 */
class ProductImagesType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('images', UploadType::class, [
            'accept' => 'image/*',
            'asset_package' => 'ecommerce.asset',
            'file_constraints' => [new Assert\Image(maxSize: '4M')],
            'filesystem' => 'ecommerce_asset.storage',
            'help' => 'ecommerce.admin.gallery.images_help',
            'label' => 'ecommerce.admin.gallery.images',
            'multiple' => true,
        ]);
    }
}
