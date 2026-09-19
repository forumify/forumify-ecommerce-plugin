<?php

declare(strict_types=1);

namespace PluginTests\Tests\Application;

use Doctrine\Common\Collections\ArrayCollection;
use Forumify\Core\Entity\User;
use Forumify\Testing\Factories\Core\RoleFactory;
use Forumify\Testing\Factories\Core\UserFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;

abstract class EcommerceWebTestCase extends WebTestCase
{
    use Factories;

    protected KernelBrowser $client;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $this->client->followRedirects();

        $this->admin = UserFactory::createOne([
            'roleEntities' => new ArrayCollection([RoleFactory::findOrCreate(['slug' => 'super-admin'])]),
        ]);
        $this->client->loginUser($this->admin);
    }
}
