.PHONY: quality
quality:
	@./vendor/bin/phpcs -s -p
	@./vendor/bin/phpstan

.PHONY: quality-fix
quality-fix:
	@./vendor/bin/phpcbf

.PHONY: tests
tests:
	make setup-tests
	make run-tests

.PHONY: setup-tests
setup-tests:
	@rm -rf tests/var
	@cd tests && php bin/console forumify:plugins:test-setup --env=test

.PHONY: run-tests
run-tests:
	@rm -rf tests/var
	@./vendor/bin/phpunit

# Generates a migration for your own entities only, forumify's own migrations live in
# their own namespace and are left alone.
.PHONY: migration
migration:
	@cd tests && php bin/console doctrine:migrations:diff --namespace=ForumifyEcommercePluginMigrations --env=test
