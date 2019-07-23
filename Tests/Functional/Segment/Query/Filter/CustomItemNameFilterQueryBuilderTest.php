<?php

declare(strict_types=1);

/*
 * @copyright   2019 Mautic Contributors. All rights reserved
 * @author      Mautic, Inc
 *
 * @link        https://mautic.com
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\CustomObjectsBundle\Tests\Functional\Segment\Query\Filter;

use Doctrine\ORM\EntityManager;
use Liip\FunctionalTestBundle\Test\WebTestCase;
use Mautic\LeadBundle\Segment\ContactSegmentFilter;
use Mautic\LeadBundle\Segment\Query\QueryBuilder;
use Mautic\LeadBundle\Segment\RandomParameterName;
use MauticPlugin\CustomObjectsBundle\Helper\QueryFilterHelper;
use MauticPlugin\CustomObjectsBundle\Provider\CustomFieldTypeProvider;
use MauticPlugin\CustomObjectsBundle\Segment\Query\Filter\CustomItemNameFilterQueryBuilder;
use MauticPlugin\CustomObjectsBundle\Tests\Functional\DataFixtures\Traits\FixtureObjectsTrait;
use MauticPlugin\CustomObjectsBundle\Repository\DbalQueryTrait;

class CustomItemNameFilterQueryBuilderTest extends WebTestCase
{
    use FixtureObjectsTrait;
    use DbalQueryTrait;

    /** @var EntityManager */
    private $entityManager;

    protected function setUp(): void
    {
        /** @var EntityManager */
        $entityManager       = $this->getContainer()->get('doctrine.orm.entity_manager');
        $this->entityManager = $entityManager;
        $fixturesDirectory   = $this->getFixturesDirectory();
        $objects             = $this->loadFixtureFiles([
            $fixturesDirectory.'/roles.yml',
            $fixturesDirectory.'/users.yml',
            $fixturesDirectory.'/leads.yml',
            $fixturesDirectory.'/custom_objects.yml',
            $fixturesDirectory.'/custom_fields.yml',
            $fixturesDirectory.'/custom_items.yml',
            $fixturesDirectory.'/custom_xref.yml',
            $fixturesDirectory.'/custom_values.yml',
        ], false, null, 'doctrine'); //,ORMPurger::PURGE_MODE_DELETE);

        $this->setFixtureObjects($objects);

        parent::setUp();
    }

    protected function tearDown(): void
    {
        foreach ($this->getFixturesInUnloadableOrder() as $entity) {
            $this->entityManager->remove($entity);
        }
        $this->entityManager->flush();
        parent::tearDown();
    }

    public function testApplyQuery(): void
    {
        /** @var CustomFieldTypeProvider $fieldTypeProvider */
        $fieldTypeProvider   = $this->getContainer()->get('custom_field.type.provider');
        $filterHelper        = new QueryFilterHelper($fieldTypeProvider);
        $queryBuilderService = new CustomItemNameFilterQueryBuilder(new RandomParameterName(), $filterHelper);
        $filterMock          = $this->createSegmentFilterMock('%emotion%', 'text', 'like', 'custom_object3');
        $queryBuilder        = $this->getLeadsQueryBuilder();

        $queryBuilderService->applyQuery($queryBuilder, $filterMock);

        $this->assertSame(2, $this->executeSelect($queryBuilder)->rowCount());
    }

    /**
     * @param mixed  $value
     * @param string $type
     * @param string $operator
     * @param string $fixtureField
     *
     * @return \PHPUnit_Framework_MockObject_MockObject
     *
     * @throws \MauticPlugin\CustomObjectsBundle\Tests\Functional\Exception\FixtureNotFoundException
     */
    private function createSegmentFilterMock($value, $type = 'text', $operator = 'eq', $fixtureField = 'custom_field1'): \PHPUnit_Framework_MockObject_MockObject
    {
        $filterMock = $this->getMockBuilder(ContactSegmentFilter::class)
            ->disableOriginalConstructor()
            ->getMock();

        $filterMock->method('getType')->willReturn($type);
        $filterMock->method('getOperator')->willReturn($operator);
        $filterMock->method('getField')->willReturn((string) $this->getFixtureById($fixtureField)->getId());
        $filterMock->method('getParameterValue')->willReturn($value);
        $filterMock->method('getParameterHolder')->willReturn((string) ':needle');

        return $filterMock;
    }

    private function getLeadsQueryBuilder(): QueryBuilder
    {
        $connection   = $this->entityManager->getConnection();
        $queryBuilder = new QueryBuilder($connection);

        $queryBuilder->select('l.*')->from(MAUTIC_TABLE_PREFIX.'leads', 'l');

        return $queryBuilder;
    }
}
