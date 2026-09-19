<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\PaymentProvider\Fake;

use Symfony\Component\DependencyInjection\Attribute\WhenNot;
use Symfony\Component\Form\AbstractType;

/**
 * @extends AbstractType<array<string, mixed>>
 */
#[WhenNot(env: 'prod')]
class FakeSettingsType extends AbstractType
{
}
