<?php

declare(strict_types=1);

/*
 * @copyright   2018 Mautic, Inc. All rights reserved
 * @author      Mautic, Inc.
 *
 * @link        https://mautic.com
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\CustomObjectsBundle\Repository;

use Doctrine\DBAL\Types\Type;
use Mautic\CoreBundle\Entity\CommonRepository;

class CustomObjectRepository extends CommonRepository
{
    /**
     * @param string   $alias
     * @param int|null $id
     *
     * @return bool
     */
    public function isAliasUnique(string $alias, int $id = null): bool
    {
        $q = $this->createQueryBuilder('e');

        $q->select('count(e.id) as alias_count');
        $q->where('e.alias = :alias');
        $q->setParameter('alias', $alias);

        if (null !== $id) {
            $q->andWhere($q->expr()->neq('e.id', ':ignoreId'));
            $q->setParameter('ignoreId', $id);
        }

        return (bool) $q->getQuery()->getSingleResult()['alias_count'];
    }
}