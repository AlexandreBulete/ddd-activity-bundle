<?php

declare(strict_types=1);

namespace AlexandreBulete\DddActivityBundle\Infrastructure\Sylius\Admin\Menu;

use AlexandreBulete\DddSyliusBundle\Admin\Menu\MenuContributorInterface;
use AlexandreBulete\DddSymfonyBundle\Messenger\Authorization\PermissionCheckerInterface;
use AlexandreBulete\DddSymfonyBundle\Messenger\Tracing\ActorResolverInterface;
use Knp\Menu\ItemInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Registered only when `activity.admin.enabled` is true. Shown to whoever may
 * read the journal — to everyone when nothing checks permissions.
 *
 * Sits under a shared "System" section (get-or-create, so it works whether or
 * not another system contributor created it first), next to the outbox.
 */
#[AutoconfigureTag('app.menu_contributor', ['priority' => 10])]
final readonly class ActivityMenuContributor implements MenuContributorInterface
{
    public function __construct(
        private ActorResolverInterface $actors,
        private ?PermissionCheckerInterface $checker = null,
    ) {}

    public function contribute(ItemInterface $menu): void
    {
        $actor = $this->actors->resolve();
        if ($this->checker !== null && ($actor === null || !$this->checker->isGranted($actor, 'activity.find_activity_entries'))) {
            return;
        }

        $system = $menu->getChild('system') ?? $menu
            ->addChild('system')
            ->setLabel('activity.menu.system')
            ->setLabelAttribute('icon', 'tabler:settings');

        $system
            ->addChild('activity', ['route' => 'activity_admin_activity_entry_index'])
            ->setLabel('activity.menu.journal')
            ->setLabelAttribute('icon', 'tabler:history');
    }
}
