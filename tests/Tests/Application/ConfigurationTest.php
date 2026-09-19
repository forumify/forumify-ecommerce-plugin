<?php

declare(strict_types=1);

namespace PluginTests\Tests\Application;

use Forumify\Ecommerce\Service\StoreSettings;

class ConfigurationTest extends EcommerceWebTestCase
{
    public function testAdminChangesStoreCurrency(): void
    {
        $crawler = $this->client->request('GET', '/admin/ecommerce/configuration');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href$="/admin/ecommerce/configuration"]');
        $currencySelect = $crawler->filter('#configuration_ecommerce__currency');
        self::assertSame('USD', $currencySelect->filter('option[selected]')->attr('value'));
        self::assertEqualsCanonicalizing(
            StoreSettings::SUPPORTED_CURRENCIES,
            $currencySelect->filter('option')->each(static fn ($option) => $option->attr('value')),
        );

        $this->client->submitForm('Save', [
            'configuration[ecommerce__currency]' => 'EUR',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'The ecommerce configuration was saved.');
        self::assertSame('EUR', self::getContainer()->get(StoreSettings::class)->getCurrency());
    }
}
