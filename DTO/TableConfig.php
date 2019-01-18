<?php

declare(strict_types=1);

/*
 * @copyright   2019 Mautic, Inc. All rights reserved
 * @author      Mautic, Inc.
 *
 * @link        https://mautic.com
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\CustomObjectsBundle\DTO;

use MauticPlugin\CustomObjectsBundle\DTO\TableFilterConfig;

class TableConfig
{
    /**
     * @var int
     */
    private $limit;

    /**
     * @var int
     */
    private $page;

    /**
     * @var string
     */
    private $orderBy;

    /**
     * @var string
     */
    private $orderDirection;

    /**
     * @var array
     */
    private $filters = [];

    /**
     * @param integer $limit
     * @param integer $page
     * @param string  $orderBy
     * @param string  $orderDirection
     */
    public function __construct(int $limit, int $page, string $orderBy, string $orderDirection = 'ASC')
    {
        $this->limit          = $limit;
        $this->page           = $page;
        $this->orderBy        = $orderBy;
        $this->orderDirection = $orderDirection;
    }

    /**
     * @return string
     */
    public function getOrderBy(): string
    {
        return $this->orderBy;
    }

    /**
     * @return string
     */
    public function getOrderDirection(): string
    {
        return $this->orderDirection;
    }

    /**
     * @return integer
     */
    public function getOffset(): int
    {
        $offset = ($this->page === 1) ? 0 : (($this->page - 1) * $this->limit);
        
        return $offset < 0 ? 0 : $offset;
    }

    /**
     * @param TableFilterConfig $tableFilterConfig
     */
    public function addFilter(TableFilterConfig $tableFilterConfig): void
    {
        if (!isset($this->filters[$tableFilterConfig->getTableAlias()])) {
            $this->filters[$tableFilterConfig->getTableAlias()] = [];
        }

        $this->filters[$tableFilterConfig->getTableAlias()][] = $tableFilterConfig;
    }

    /**
     * Checks if the filter value is not empty before adding the filter.
     * 
     * @param TableFilterConfig $tableFilterConfig
     */
    public function addFilterIfNotEmpty(TableFilterConfig $tableFilterConfig): void
    {
        if (!empty($tableFilterConfig->getValue())) {
            $this->addFilter($tableFilterConfig);
        }
    }

    /**
     * @return TableFilterConfig[]
     */
    public function getFilters(): array
    {
        return $this->filters;
    }
}