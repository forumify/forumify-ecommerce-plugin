<?php

declare(strict_types=1);

namespace PluginTests\Tests\Application;

use Doctrine\Common\Collections\ArrayCollection;
use Forumify\Core\Entity\Notification;
use Forumify\Core\Entity\User;
use Forumify\Core\Repository\NotificationRepository;
use Forumify\Ecommerce\Entity\Order;
use Forumify\Ecommerce\Entity\OrderStatus;
use Forumify\Ecommerce\Fulfillment\Type\ManualFulfillmentType;
use Forumify\Ecommerce\Fulfillment\Type\PhysicalFulfillmentType;
use Forumify\Ecommerce\Notification\OrderCompletedNotificationType;
use Forumify\Ecommerce\Notification\OrderPaidNotificationType;
use Forumify\Ecommerce\Notification\OrderToFulfillNotificationType;
use Forumify\Ecommerce\Repository\OrderRepository;
use Forumify\Ecommerce\Repository\ProductRepository;
use Forumify\Testing\Factories\Core\RoleFactory;
use Forumify\Testing\Factories\Core\UserFactory;
use PluginTests\Tests\Factories\PaymentProviderConfigFactory;
use PluginTests\Tests\Factories\ProductFactory;
use PluginTests\Tests\Factories\ProductPriceFactory;
use PluginTests\Tests\Factories\ProductTierFactory;
use Twig\Environment;

class FulfillmentTest extends EcommerceWebTestCase
{
    public function testPhysicalOrderIsShippedByAnAdmin(): void
    {
        PaymentProviderConfigFactory::createOne();
        $product = ProductFactory::createOne([
            'title' => 'Hoodie',
            'fulfillmentType' => PhysicalFulfillmentType::KEY,
            'fulfillmentRole' => RoleFactory::createOne(),
        ]);
        $price = ProductPriceFactory::createOne(['tier' => ProductTierFactory::createOne(['product' => $product]), 'amountMinor' => 4500]);
        $customer = UserFactory::createOne();
        $this->client->loginUser($customer);

        $this->client->request('GET', "/store/checkout/{$price->getId()}");
        $this->client->submitForm('Pay $45.00');
        self::assertSelectorTextContains('.ecommerce-checkout-fulfillment', 'This value should not be blank.');
        self::assertNull($this->findOrder());

        $address = [
            'fulfillment[name]' => 'Jane Doe',
            'fulfillment[line1]' => 'Main Street 1',
            'fulfillment[postalCode]' => '1000',
            'fulfillment[city]' => 'Brussels',
            'fulfillment[country]' => 'BE',
        ];
        $this->client->submitForm('Pay $45.00', $address);
        $this->client->submitForm('Pay successfully');

        $order = $this->findOrder();
        self::assertSame(OrderStatus::Paid, $order?->status);
        self::assertSame('Jane Doe', $order->fulfillmentData['name']);
        self::assertSelectorTextContains('.ecommerce-order-status', 'Payment received');
        self::assertSelectorTextContains('.ecommerce-fulfillment-details', 'Main Street 1');
        self::assertSelectorTextContains('.ecommerce-fulfillment-details', 'Belgium');

        $this->client->request('GET', "/store/checkout/{$price->getId()}");
        self::assertInputValueSame('fulfillment[name]', 'Jane Doe');

        $this->client->loginUser($this->admin);
        $this->client->request('GET', '/admin/ecommerce/orders');
        self::assertSelectorTextContains('.table-wrapper', $order->reference);
        self::assertSelectorTextContains('nav, aside', 'Orders (1)');

        $this->client->request('GET', "/admin/ecommerce/orders/{$order->getId()}");
        self::assertSelectorTextContains('.ecommerce-fulfillment-details', 'Jane Doe');
        $this->client->submitForm('Mark as completed', ['order_complete[note]' => 'Tracking: https://track.test/1']);
        self::assertSelectorTextContains('body', 'The order was marked as completed');
        self::assertSelectorNotExists('form[name="order_complete"]');

        $order = $this->findOrder();
        self::assertSame(OrderStatus::Completed, $order?->status);
        self::assertSame($this->admin->getId(), $order->completedBy?->getId());

        $this->client->loginUser($customer);
        $this->client->request('GET', "/store/order/{$order->reference}");
        self::assertSelectorTextContains('.ecommerce-order-status', 'Your order is complete');
        self::assertSelectorTextContains('.ecommerce-fulfillment-details', 'Main Street 1');
        self::assertSelectorTextContains('.ecommerce-fulfillment-details', 'Tracking: https://track.test/1');
    }

    public function testManualOrderNotifiesFulfillersAndBuyer(): void
    {
        PaymentProviderConfigFactory::createOne();
        $fulfillers = RoleFactory::createOne(['title' => 'Game masters']);
        $fulfiller = UserFactory::createOne(['roleEntities' => new ArrayCollection([$fulfillers])]);
        $product = ProductFactory::createOne([
            'title' => 'Diamond pack',
            'fulfillmentType' => ManualFulfillmentType::KEY,
            'fulfillmentSettings' => ['inputLabel' => 'Minecraft username'],
            'fulfillmentRole' => $fulfillers,
        ]);
        $price = ProductPriceFactory::createOne(['tier' => ProductTierFactory::createOne(['product' => $product]), 'amountMinor' => 500]);
        $customer = UserFactory::createOne();
        $this->client->loginUser($customer);

        $this->client->request('GET', "/store/checkout/{$price->getId()}");
        self::assertSelectorTextContains('label[for="fulfillment_value"]', 'Minecraft username');
        $this->client->submitForm('Pay $5.00', ['fulfillment[value]' => 'Notch']);
        $this->client->submitForm('Pay successfully');
        self::assertSelectorTextContains('.ecommerce-fulfillment-details', 'Minecraft username: Notch');

        $order = $this->findOrder();
        self::assertNotNull($order);
        self::assertSame([OrderPaidNotificationType::TYPE], $this->notificationTypes($customer));
        self::assertSame([OrderToFulfillNotificationType::TYPE], $this->notificationTypes($fulfiller));
        self::assertStringContainsString('<strong>Minecraft username:</strong> Notch', $this->renderEmail($fulfiller));

        $this->client->loginUser($this->admin);
        $this->client->request('GET', "/admin/ecommerce/orders/{$order->getId()}");
        $this->client->submitForm('Mark as completed');

        self::assertSame(
            [OrderPaidNotificationType::TYPE, OrderCompletedNotificationType::TYPE],
            $this->notificationTypes($customer),
        );
        self::assertStringContainsString('is complete', $this->renderEmail($customer));
    }

    public function testAdminConfiguresManualFulfillment(): void
    {
        $product = ProductFactory::createOne();
        $role = RoleFactory::createOne(['title' => 'Game masters']);

        $this->client->request('GET', "/admin/ecommerce/products/{$product->getId()}/edit");
        $this->client->submitForm('Save', [
            'product[fulfillmentType]' => ManualFulfillmentType::KEY,
            'product[fulfillmentSettings][manual][inputLabel]' => 'Minecraft username',
        ]);
        self::assertSelectorTextContains('body', 'Choose the role that fulfills the orders of this product.');

        $this->client->submitForm('Save', [
            'product[fulfillmentType]' => ManualFulfillmentType::KEY,
            'product[fulfillmentSettings][manual][inputLabel]' => 'Minecraft username',
            'product[fulfillmentRole]' => (string)$role->getId(),
        ]);

        $saved = self::getContainer()->get(ProductRepository::class)->find($product->getId());
        self::assertSame(ManualFulfillmentType::KEY, $saved?->fulfillmentType);
        self::assertSame(['inputLabel' => 'Minecraft username'], $saved->fulfillmentSettings);
        self::assertSame($role->getId(), $saved->fulfillmentRole?->getId());

        $this->client->request('GET', "/admin/ecommerce/products/{$product->getId()}/edit");
        $this->client->submitForm('Save', ['product[fulfillmentType]' => PhysicalFulfillmentType::KEY]);
        $saved = self::getContainer()->get(ProductRepository::class)->find($product->getId());
        self::assertSame([], $saved?->fulfillmentSettings);
    }

    private function findOrder(): ?Order
    {
        return self::getContainer()->get(OrderRepository::class)->findOneBy([], ['id' => 'DESC']);
    }

    /**
     * @return list<string>
     */
    private function notificationTypes(User $user): array
    {
        return array_map(
            static fn (Notification $notification) => $notification->getType(),
            self::getContainer()->get(NotificationRepository::class)->findBy(['recipient' => $user], ['id' => 'ASC']),
        );
    }

    private function renderEmail(User $user): string
    {
        $notification = self::getContainer()->get(NotificationRepository::class)->findOneBy(['recipient' => $user], ['id' => 'DESC']);
        self::assertInstanceOf(Notification::class, $notification);
        $types = [
            OrderPaidNotificationType::TYPE => OrderPaidNotificationType::class,
            OrderCompletedNotificationType::TYPE => OrderCompletedNotificationType::class,
            OrderToFulfillNotificationType::TYPE => OrderToFulfillNotificationType::class,
        ];
        $type = self::getContainer()->get($types[$notification->getType()]);

        return self::getContainer()->get(Environment::class)->render($type->getEmailTemplate($notification), [
            'notification' => $notification,
            'this' => $type,
            ...($notification->getDeserializedContext() ?? []),
        ]);
    }
}
