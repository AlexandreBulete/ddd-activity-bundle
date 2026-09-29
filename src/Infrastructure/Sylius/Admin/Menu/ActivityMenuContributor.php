<?php

declare(strict_types=1);

namespace AlexandreBulete\DddActivityBundle\Infrastructure\Sylius\Admin\Menu;

use AlexandreBulete\DddSyliusBundle\Admin\Menu\MenuContributorInterface;
use Knp\Menu\ItemInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Registered only when `activity.admin.enabled` is true.
 */
#[AutoconfigureTag('app.menu_contributor', ['priority' => 10])]
final readonly class ActivityMenuContributor implements MenuContributorInterface
{
    public function contribute(ItemInterface $menu): void
    {
        $menu
            ->addChild('activity', ['route' => 'activity_admin_activity_entry_index'])
            ->setLabel('activity.menu.journal')
            ->setLabelAttribute('icon', 'tabler:history');
    }
}
