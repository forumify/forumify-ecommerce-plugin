# forumify E-commerce Plugin

`forumify/forumify-ecommerce-plugin`, a forumify plugin (a Symfony bundle) that adds e-commerce to a forumify community.
It is not a standalone app: the only kernel is `Forumify\Testing\ForumifyTestKernel`, with `tests/` as its project dir.

## Commands

```bash
make quality        # phpcs + phpstan (level 8, src/ only)
make quality-fix    # phpcbf
make tests          # recreate the test DB (forumify:plugins:test-setup), then run phpunit
make run-tests      # wipes tests/var (the settings cache outlives DB rollbacks), then phpunit. Rebuild the DB only when the schema changes.
make migration      # doctrine:migrations:diff into namespace ForumifyEcommercePluginMigrations

vendor/bin/phpunit --testsuite "Unit Tests"     # or "Application Tests"
vendor/bin/phpunit --filter testSomething
cd tests && php bin/console <cmd> --env=test    # console for the test kernel
```

The tests need MySQL 8.4 at `root:root@127.0.0.1:3306/plugin_test` (from `../../docker-compose.yml`) and mailcatcher on port 1025.
`dama/doctrine-test-bundle` wraps each test in a transaction that it rolls back.

## How plugins plug in

- **Plugin class** `src/ForumifyEcommercePlugin.php`: `getPluginMetadata()` sets the name, author, description, homepage and an optional `settingsRoute`. `getPermissions()` returns a nested array.
  Permissions become `<slug of metadata name>.<path>`. The name "Ecommerce" gives `ecommerce.admin.x.view`. Renaming the plugin would change every permission string.
  Roles are granted permissions in Admin → Roles. Super-admins pass every check.
- **Auto-registered** by `AbstractForumifyBundle`: Doctrine attribute mapping for `src/Entity`, the `migrations/` dir (namespace read from the first migration file), API Platform resources anywhere in `src/`, plus `config/services.yaml` and `config/packages/*.yaml`.
  No vendor paths are needed. Shipping `config/packages/doctrine.yaml` etc. turns that automatic registration off.
- **Routes** `config/routes.yaml` (currently commented out). Admin controllers go in `src/Admin/Controller` with prefix `admin/` and name prefix `forumify_admin_`, or a plugin prefix like milhq's `admin/milhq/` + `milhq_admin_`.
  Frontend controllers go in `src/Controller`. Routes only load while the plugin is active.
- **Services**: everything in `src/` is autowired and autoconfigured, except `Entity/`, `DependencyInjection/` and `DataFixture/`.
- **Templates** live in `templates/` and are referenced as `@ForumifyEcommercePlugin/...`. Frontend pages extend `@Forumify/frontend/base.html.twig` (blocks `title_page`, `body`, `stylesheets`).
  Admin pages extend `@Forumify/admin/base.html.twig`. `@Forumify/form/simple_form_page.html.twig` renders a quick form page (pass `admin: true` for the admin layout).
- **Translations** go in `translations/messages+intl-icu.en.yaml`, keyed under a plugin prefix. Validation messages go there too: the platform sets the validator's `translation_domain` to `messages`, so a `validators` file is ignored.
- **Assets**: `public/` is published as `bundles/forumifyecommerceplugin/`. Stimulus controllers are hand-written in `assets/dist/*.js` and registered in `assets/package.json` under `symfony.controllers`. There is no bundler.

## Platform building blocks to reuse

- **Entities**: use the traits `IdentifiableEntityTrait`, `TimestampableEntityTrait`, `SluggableEntityTrait`, `SortableEntityTrait` (+ `SortableEntityInterface`) and `BlameableEntityTrait`.
  Implement `AccessControlledEntityInterface` (`getACLPermissions()`, `getACLParameters()`) for per-entity ACL. Doctrine attributes go on properties. Prefix table names with the plugin name.
- **Repositories** extend `Forumify\Core\Repository\AbstractRepository<T>` with a static `getEntityClass()`. They provide `save/saveAll/remove/removeAll/flush` (which dispatch `EntityPostSaveEvent`/`EntityPostRemoveEvent`), `reorder()` and `addACLToQuery($qb, 'view', Entity::class, 'alias')`.
- **Admin CRUD**: extend `Forumify\Admin\Crud\AbstractCrudController<T>` with `#[Route('/products', 'products')]`. Implement `getEntityClass()`, `getTableName()` (the Live component name) and `getForm()`.
  Set `$permissionView/Create/Edit/Delete` and override `getTranslationPrefix()`. It gives list/create/edit/delete routes (`*_list`, `*_create`, `*_edit`, `*_delete`), `PreSaveCrudEvent`/`PostSaveCrudEvent`, and overridable templates.
  Translations expected: `<prefix>single`, `<prefix>plural`.
- **Tables**: extend `Forumify\Core\Component\Table\AbstractDoctrineTable` with `#[AsLiveComponent('Ecommerce\\ProductTable', '@Forumify/components/table/table.html.twig')]`.
  In `buildTable()` use `addColumn(name, [field, renderer, searchable, sortable, label, class])`, `renderAction(route, params, phosphorIcon)` and `addPositionColumn()`. Field joins can be at most 1 level deep (`category.name`).
- **Frontend lists**: `Forumify\Core\Component\List\AbstractDoctrineList` Live components.
- **Admin menu**: either implement `AdminMenuBuilderInterface::build(Menu $menu)` (milhq, top-level section), or decorate `SettingsMenuProviderInterface` (calendar, entry under Settings).
  Menu items take `['icon' => 'ph ph-…', 'permission' => '…']`. Icons are Phosphor.
- **Frontend menu**: menus are built by admins in the Menu Builder. A plugin can contribute a `MenuTypeInterface` (see milhq `src/MenuBuilder/MenuType`).
- **Settings**: `SettingRepository` `get/set/setBulk`, plus `toFormData('prefix')`/`handleFormData()` for a configuration form (milhq `ConfigurationController`). Twig has `setting('key')`.
- **Uploads**: `Forumify\Core\Form\UploadType` (`filesystem`, `asset_package`, `file_constraints`). It needs a flysystem storage in `config/packages/flysystem.yaml` and an asset package in `config/packages/framework.yaml` (see milhq).
- **Notifications**: extend `AbstractEmailNotificationType` (in-app + email), send via `NotificationService`. Mail goes through `Core\Service\Mailer`.
- **CMS widgets**: implement `Forumify\Cms\Widget\WidgetInterface` (auto-tagged) to make blocks page builders can drop in.
- **Automation**: triggers, conditions and actions are tagged services (`src/Automation/*`).
- **Scheduler/Messenger**: the platform scheduler and a Doctrine messenger transport are available.
- **Version gating**: `#[PluginVersion('forumify/forumify-ecommerce-plugin', 'premium')]` or `PluginVersionChecker::isVersionInstalled()`, in case of paid tiers.
- **Access checks**: `#[IsGranted('ecommerce.admin....')]`, `denyAccessUnlessGranted(VoterAttribute::ACL->value, ['entity' => $e, 'permission' => 'view'])`, and API Platform `security: 'is_granted("...")'` or `extraProperties: ['acl' => 'view']`.
- The platform has no payment, currency or order code, so all of that is ours to build.

## Conventions

- Properties: entities (and other data classes) use public typed properties, not getter/setter pairs. Keep a method only when it adds behavior.
  Use property hooks for guards (`set { ... }`) and `public private(set)` for state that only the class may change.
  Doctrine hydrates raw values, so set hooks don't run on load.
  A **computed value is a method** (`getGallery()`, `getAmount()`), never a get-only virtual property.
  These were hooks once and were all converted: a property that can never be assigned only pretends to be one — it reads as state while it is really behaviour, and it hides a per-read computation behind a field. Twig resolves `product.gallery` to `getGallery()` either way, so templates don't care.
  Set hooks stay, because those guard real state (`ProductPrice::$amountMinor` refuses writes once a price is locked).
  Declare entity properties in the class body, not through constructor promotion (promotion is fine for `private readonly` service dependencies). The platform traits (`getId()`, `getSlug()`, `getPosition()`) keep their methods.
  phpcs 3 can't parse hooks, so `PSR2.Classes.PropertyDeclaration.{Multiple,ScopeMissing}` are silenced in `phpcs.xml`.
- Money: use `Brick\Money\Money` (never floats). Store it as an `int` property in minor units (e.g. `amountMinor`) plus one `currency` column per entity, and expose it through a `getAmount(): Money` built with `Money::ofMinor()`.
  Do combined calculations with `RationalMoney` and round once, at the result. Display it with the `ecommerce_money` Twig filter / `MoneyFormatter`.

- phpcs is PSR-12 plus Slevomat: `declare(strict_types=1)`, short arrays, early exit, no Yoda, no unused uses, `array<T>` phpdoc (not `T[]`), no comment after code, lines ≤ 120 characters, and cognitive complexity ≤ 33.
  Arrow functions are written `fn(...)`, without a space. A ternary that does not fit on one line breaks before `?` and `:`, with the condition alone on the first line.
  Attribute order is fixed: `PluginVersion`, `ORM\`, `Assert\`, `Groups`, `UniqueEntity`, `Route`, `AsLiveComponent`, `LiveAction`, `IsGranted`.
- PHPStan level 8 on `src/`. Entity "never written" property errors are ignored under `src/Entity/*`.
- Code should explain itself. Don't write comments that explain code; phpdoc type annotations are fine. (The skeleton's docblock on the plugin class was deliberately removed.)
- Naming: `{Action}Service`, `{Entity}Type` forms, `{Entity}Repository`, `{Name}Subscriber`, and form DTOs in `Form/DTO/`.
- Migrations: one per PR, hand-reviewed, with a real `getDescription()` and `down()`. Keep namespace `ForumifyEcommercePluginMigrations`.
- Add reasonable blocks to twig templates so admins and other plugins have logical places to hook in and add/replace sections.

## Testing approach

- Every feature gets **at least one happy-flow application test** plus **several unit tests**. Keep the suite small: a few concise tests that cover a lot, not one test per branch.
- Start unit tests as a plain `PHPUnit\Framework\TestCase` with mocks. `dg/bypass-finals` is enabled, so final classes can be mocked.
  When mocking gets painful, switch to `KernelTestCase` + `use Factories;` and create real data with zenstruck/foundry.
- Application tests: `WebTestCase` + `use Factories;`, `$client = static::createClient()`, `$client->loginUser($user)`, `$client->followRedirects()`, `submitForm('Save', [...])`, then assert against the repository.
  An admin user is `UserFactory::createOne(['roleEntities' => new ArrayCollection([RoleFactory::findOrCreate(['slug' => 'super-admin'])])])`.
  The platform's `UserTrait::createAdmin()` also works but is being phased out, so prefer factories. A shared base `WebTestCase` like milhq's `MilhqWebTestCase` is reasonable once there are several tests.
- Platform factories: `Forumify\Testing\Factories\{Core,Forum,OAuth}\*` (User, Role, ACL, Notification, Forum, Topic, Comment, Badge, OAuthClient, …).
  Our own factories go in `tests/Tests/Factories/` (auto-registered as services by `tests/config/services.yaml`).
- API resources: extend `Forumify\Testing\Api\ApiTestCase` with the CRUD traits in `Forumify\Testing\Api\Crud`.
- Test fixture files go in `tests/data/` (`TEST_DATA_DIR`).
- Suites are "Unit Tests" (`tests/Tests/Unit`) and "Application Tests" (`tests/Tests/Application`).

## Docs

`docs/` holds **developer feature docs**, one per feature, indexed by `docs/README.md` (which also has the architecture overview). Read the relevant one before working on a feature: each doc describes the domain model, the flow, the extension points, and the decisions behind them with their reasoning.

**Keep the docs up to date as part of the work, not afterwards.** A change to a feature updates its doc in the same PR:
- New or changed behaviour, entities, fields, events, services, routes or permissions → update the feature doc (and its "Code" section if files moved).
- A decision made or reversed along the way → record it in that doc as a `> **Decision:**` block, **with the reasoning**, so it can be revisited on purpose rather than by accident. This is the part that is lost if it isn't written down immediately.
- Something dropped, deferred or discovered to be missing → `roadmap.md`.
- A new feature gets its own doc plus a row in `docs/README.md` and in the table above.
