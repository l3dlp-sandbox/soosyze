<?php

declare(strict_types=1);

namespace SoosyzeCore\Template\Hook;

use Psr\Container\ContainerInterface;
use SoosyzeCore\QueryBuilder\Services\Query;

class User implements \SoosyzeCore\User\UserInterface
{
    public function hookUserPermissionModule(array &$permissions): void
    {
        $permissions[ 'Template' ][ 'template.admin' ] = 'Use the admin theme';
    }

    public function hookInstallUser(ContainerInterface $ci): void
    {
        /** @phpstan-var Query $query */
        $query = $ci->get(Query::class);
        $query->insertInto('role_permission', [ 'role_id', 'permission_id' ])
            ->values([ 3, 'template.admin' ])
            ->execute();
    }
}
