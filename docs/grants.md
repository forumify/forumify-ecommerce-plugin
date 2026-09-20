# Role and badge grants

A product can grant forumify roles and badges when it is bought, and take them away when a subscription ends.

This is **separate from the fulfillment type and stacks with any of them**: a hoodie can be shipped *and*
grant a "Supporter" role.

## Product settings

Two many-to-many relations on `Product`: `grantedRoles` and `grantedBadges`. System roles can't be granted.

## The Grant entity (`ecommerce_grant`)

One row per thing granted, recording what was granted and why:

- the user
- the role or badge, **snapshotted on the row**, so editing the product later never changes existing grants
- the order, and the subscription if there is one
- `preExisting` — whether the user already had it when this grant was made

## Granting

`GrantSubscriber` listens on **`OrderPaidEvent`** — when the money is in, not when a shipment is completed.
`OrderPaidEvent` carries the subscription it started, so the listener can link its rows without a lookup.

A granted badge also sends the platform's "new badge" notification, the same way the automation action does.

## Revoking

On `SubscriptionEndedEvent`, the subscription's grant rows are removed. The role or badge itself is only
taken away when:

1. **no other grant row for it is left**, and
2. **the user didn't already have it** when the first of those grants was made (`preExisting`)

So two subscriptions that both grant "VIP" don't cancel each other out, and a role an admin gave by hand
survives.

> **Decision: track grants as rows rather than recomputing from orders.** The answer to "should this role be
> removed?" depends on history the order alone doesn't hold — what the user already had, and which other
> live grants exist. Rows make it a query instead of a reconstruction.

**A refund revokes the order's grants.** `OrderRefundedEvent` → `GrantService::revokeForOrder()`, with the
same two rules as above. That is the only thing that takes a one-time purchase's grants back; see
[self-service.md](self-service.md).

## Code

- `src/Entity/Grant.php`, `src/Repository/GrantRepository.php`
- `src/Service/GrantService.php`, `src/EventSubscriber/GrantSubscriber.php`
- Tests: `GrantTest` (a subscription grants a role; ending it revokes the role), `GrantServiceTest`
  (two overlapping grants; a pre-existing role survives)

> The grant tests use `KernelTestCase` with factories — mocking the collections was more code than the
> service itself.
