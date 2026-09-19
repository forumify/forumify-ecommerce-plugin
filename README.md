# Forumify Ecommerce

Bring e-commerce to your forumify powered community!

> Generated with the forumify plugin skeleton. See the
> [plugin guide](https://docs.forumify.net/guides/plugin) to get your bearings, then
> replace this section with something about your own plugin.

## Requirements

- PHP 8.4 or newer
- Composer
- A MySQL 8.4 database, for running the tests

## What's in here

This is a bare plugin: forumify recognises it, the tooling is set up, and nothing else is
assumed about what you're building.

```
src/
  ForumifyEcommercePlugin.php   Plugin class: name, author, permissions
config/
  services.yaml           Everything in src/ is registered as a service
  routes.yaml             Where your controllers' routes are mounted
migrations/               Database migrations
tests/                    Your test suite, see "Testing" below
```

Two things in `composer.json` make it a plugin:

```json
{
    "type": "forumify-plugin",
    "extra": {
        "forumify-plugin-class": "Forumify\\PluginSkeleton\\ForumifyEcommercePlugin"
    }
}
```

forumify discovers installed plugins by that package type, and boots the class named in
`extra`. Everything in `config/` is loaded for you.

## Where things go

Your plugin is a Symfony bundle, so most of this is ordinary Symfony.

| What you want to add | Where it goes |
| --- | --- |
| A page for your members | A controller in `src/Controller`, then uncomment the frontend block in `config/routes.yaml` |
| An admin page | A controller in `src/Admin/Controller`, then uncomment the admin block in `config/routes.yaml` |
| Database entities | `src/Entity`, with repositories in `src/Repository` extending `Forumify\Core\Repository\AbstractRepository` |
| Templates | `templates/`, referenced as `@ForumifyEcommercePlugin/frontend/your_template.html.twig` |
| Translations | `translations/messages+intl-icu.en.yaml` |
| Permissions | `getPermissions()` on your plugin class, checked as `<slugged-plugin-name>.admin.example.view` |
| Stimulus controllers | `assets/dist`, listed under `symfony.controllers` in an `assets/package.json` |

Entities in `src/Entity`, your `migrations/` directory and API Platform resources are
registered automatically, resolved from wherever the plugin is installed, so you never
write a vendor path yourself.

## Developing against a forumify instance

Point a local forumify instance at your working copy with a path repository, so your
changes are live without publishing anything:

```bash
cd /path/to/your/forumify-instance
composer config repositories.forumify-ecommerce path /path/to/forumify-ecommerce
composer require forumify/forumify-ecommerce-plugin:@dev
```

Then activate it:

```bash
php bin/console forumify:plugins:refresh
php bin/console forumify:plugins:activate forumify/forumify-ecommerce-plugin
php bin/console cache:clear
```

Your plugin also shows up under **Admin → Plugins**, where it can be activated from the
interface instead.

## Testing

The test suite boots a real forumify application, so it needs a database. The connection
string lives in `.env` and defaults to `mysql://root:root@127.0.0.1:3306/plugin_test`.

```bash
make tests          # prepare the database, then run phpunit
make run-tests      # just phpunit, when the database is already set up
```

- `tests/Tests/Unit` — no kernel, no database, fast.
- `tests/Tests/Application` — boots forumify and makes real requests.

The included `PluginBootTest` proves your plugin is registered and boots. forumify ships
the rest of the plumbing: the test kernel, the test configuration, and factories for its
own entities under `Forumify\Testing\Factories`.

```php
use Forumify\Testing\Factories\Core\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;

class YourControllerTest extends WebTestCase
{
    use Factories;

    public function testItLoads(): void
    {
        $client = static::createClient();
        UserFactory::createOne(['username' => 'tester']);

        $client->request('GET', '/your-page');

        self::assertResponseIsSuccessful();
    }
}
```

For API resources, extend `Forumify\Testing\Api\ApiTestCase` and use the CRUD traits,
which cover the standard operations for you.

## Code quality

```bash
make quality        # phpcs + phpstan
make quality-fix    # fix what phpcs can fix automatically
```

Both run in CI on every push and pull request.

## Migrations

After adding or changing an entity, generate a migration:

```bash
make migration
```

Review the generated file before committing it. Keep migrations in the namespace the
first one uses: forumify registers your migrations directory under the namespace the
files declare, and that name is stored in the database as part of the executed versions.
Renaming it later would make doctrine re-run migrations that have already been applied.

## Publishing

1. Push the repository to GitHub.
2. Submit it to [Packagist](https://packagist.org/packages/submit).
3. Tag a release. Users install it with
   `composer require forumify/forumify-ecommerce-plugin`.

## Documentation

- [Plugin guide](https://docs.forumify.net/guides/plugin)
- [Customization guides](https://docs.forumify.net/guides/customization/introduction)
- [Component framework](https://docs.forumify.net/guides/framework/components/box)
