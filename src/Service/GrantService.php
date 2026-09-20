<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Service;

use Forumify\Core\Entity\Notification;
use Forumify\Core\Entity\Role;
use Forumify\Core\Entity\User;
use Forumify\Core\Notification\NotificationService;
use Forumify\Core\Repository\UserRepository;
use Forumify\Ecommerce\Entity\Grant;
use Forumify\Ecommerce\Entity\Order;
use Forumify\Ecommerce\Entity\Subscription;
use Forumify\Ecommerce\Repository\GrantRepository;
use Forumify\Forum\Entity\Badge;
use Forumify\Forum\Notification\NewBadgeNotificationType;

class GrantService
{
    public function __construct(
        private readonly GrantRepository $grantRepository,
        private readonly UserRepository $userRepository,
        private readonly NotificationService $notificationService,
    ) {
    }

    public function grant(Order $order, ?Subscription $subscription): void
    {
        $user = $order->user;
        $product = $order->product;
        if ($user === null || $product === null) {
            return;
        }

        $grants = [];
        $newBadges = [];
        foreach ($product->grantedRoles as $role) {
            $grants[] = Grant::role($user, $role, $order, $subscription, $this->isPreExisting($user, $role));
            if (!$user->getRoleEntities()->contains($role)) {
                $user->getRoleEntities()->add($role);
            }
        }

        foreach ($product->grantedBadges as $badge) {
            $grants[] = Grant::badge($user, $badge, $order, $subscription, $this->isPreExisting($user, $badge));
            if ($user->getBadges()->contains($badge)) {
                continue;
            }

            $user->getBadges()->add($badge);
            $newBadges[] = $badge;
        }

        if ($grants === []) {
            return;
        }

        $this->grantRepository->saveAll($grants);
        $this->userRepository->save($user);

        foreach ($newBadges as $badge) {
            $this->notificationService->sendNotification(
                new Notification(NewBadgeNotificationType::TYPE, $user, ['badge' => $badge]),
            );
        }
    }

    public function revoke(Subscription $subscription): void
    {
        $grants = $this->grantRepository->findBySubscription($subscription);
        if ($grants === []) {
            return;
        }

        $this->grantRepository->removeAll($grants);

        $users = [];
        foreach ($grants as $grant) {
            $granted = $grant->role ?? $grant->badge;
            if ($granted === null || $grant->preExisting) {
                continue;
            }

            if ($this->grantRepository->findFirst($grant->user, $granted) !== null) {
                continue;
            }

            $collection = $granted instanceof Role ? $grant->user->getRoleEntities() : $grant->user->getBadges();
            $collection->removeElement($granted);
            $users[$grant->user->getId()] = $grant->user;
        }

        if ($users !== []) {
            $this->userRepository->saveAll(array_values($users));
        }
    }

    private function isPreExisting(User $user, Role|Badge $granted): bool
    {
        $first = $this->grantRepository->findFirst($user, $granted);
        if ($first !== null) {
            return $first->preExisting;
        }

        $owned = $granted instanceof Role ? $user->getRoleEntities() : $user->getBadges();

        return $owned->contains($granted);
    }
}
