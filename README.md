# forumify Ecommerce

Bring e-commerce to your forumify powered community!

Sell one-off products and recurring subscriptions, take payments through Stripe, and grant roles and badges
to the people who buy them, all from your forumify admin panel.

- **Catalog**: categories, products, tiers and prices, with one-off and recurring billing.
- **Storefront**: a `/store` catalog and product pages, plus CMS widgets (product cards, pricing tables,
  buy buttons) to build your own landing pages.
- **Checkout and orders**: Stripe checkout, an order history for buyers and admins, invoices and refunds.
- **Subscriptions**: automatic renewals, failed payment handling and cancellation by buyers or admins.
- **Fulfillment**: instant delivery, or a queue of orders for an admin to hand over or ship.
- **Roles and badges**: granted on purchase, revoked when a subscription ends or an order is refunded.
- **Notifications**: emails to buyers and admins as orders and subscriptions change.

## Documentation

- **[User manual](https://docs.forumify.net/plugins/ecommerce-plugin/introduction)**: installing the plugin,
  setting up the store, connecting Stripe and running it day to day.
  Start with [Getting Started](https://docs.forumify.net/plugins/ecommerce-plugin/getting-started).
- **[Developer docs](docs/README.md)**: how each feature works inside, its extension points, and the
  decisions behind it.

## Installation

### Marketplace

If your instance is connected to the marketplace, simply purchase one of the subscription tiers and the plugin
will appear in **Admin -> Plugins**.

### Manual

[!] Please note this plugin is not free, so manual installation does not lift any revenue limits. You must
purchase a subscription from the forumify.net marketplace.

```bash
composer require forumify/forumify-ecommerce-plugin
php bin/console forumify:plugins:refresh
php bin/console forumify:plugins:activate forumify/forumify-ecommerce-plugin
php bin/console doctrine:migrations:migrate
```

The plugin can also be activated from **Admin → Plugins**. Once it is active, an **Ecommerce** section appears
in the admin panel. Requires PHP 8.4 and forumify 1.3 or newer.

Taking payments needs a Stripe account, a site reachable over HTTPS for webhooks, and forumify's background
workers (the message consumer and the scheduler) running.
See the [user manual](https://docs.forumify.net/plugins/ecommerce-plugin/getting-started) for the full setup.

## Development

```bash
make tests          # recreate the test database, then run phpunit
make run-tests      # just phpunit, when the schema hasn't changed
make quality        # phpcs, phpstan and the assets linter
make quality-fix    # fix what phpcs can fix automatically
```

Code conventions and the platform building blocks the plugin reuses are in [AGENTS.md](AGENTS.md). Read the
relevant feature doc in [docs/](docs/README.md) before changing a feature, and update it in the same change.

## License

[OSL-3.0](LICENSE.md)
