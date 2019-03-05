<?php

declare(strict_types=1);

namespace MauticPlugin\CustomObjectsBundle\Tests\Helper;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\SchemaTool;
use Liip\FunctionalTestBundle\Test\WebTestCase;
use Mautic\LeadBundle\Segment\ContactSegmentFilterFactory;
use Mautic\LeadBundle\Segment\Query\QueryBuilder;
use MauticPlugin\CustomObjectsBundle\Helper\QueryFilterHelper;
use MauticPlugin\CustomObjectsBundle\Provider\CustomFieldTypeProvider;
use MauticPlugin\CustomObjectsBundle\Tests\DataFixtures\Traits\FixtureObjectsTrait;

class QueryFilterHelperTest extends WebTestCase
{
    use FixtureObjectsTrait;

    /** @var EntityManager */
    private $entityManager;

    /** @var ContactSegmentFilterFactory */
    private $filterFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $em       = $this->getContainer()->get('doctrine')->getManager();
        $metadata = $em->getMetadataFactory()->getAllMetadata();

        $schemaTool = new SchemaTool($em);
        $schemaTool->dropDatabase();
        if (!empty($metadata)) {
            $schemaTool->createSchema($metadata);
        }
        $this->postFixtureSetup();

        $pluginDirectory   = $this->getContainer()->get('kernel')->locateResource('@CustomObjectsBundle');
        $fixturesDirectory = $pluginDirectory.'/Tests/DataFixtures/ORM/Data';

        $objects = $this->loadFixtureFiles([
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

        $this->entityManager = $this->getContainer()->get('doctrine.orm.entity_manager');
        $this->filterFactory = $this->getContainer()->get('mautic.lead.model.lead_segment_filter_factory');
    }

    public function testGetCustomValueValueExpression(): void
    {
        /** @var CustomFieldTypeProvider $fieldTypeProvider */
        $fieldTypeProvider = $this->getContainer()->get('custom_field.type.provider');
        $filterHelper      = new QueryFilterHelper($fieldTypeProvider);

        $filters = [
            [
                'filter' => ['glue' => 'and', 'field' => 'cmf_'.$this->getFixtureById('custom_field1')->getId(), 'type' => 'custom_object', 'operator' => 'eq', 'value' => 'love'],
                'match'  => 'test_value.value = :test_value_value',
            ],
            [
                'filter' => ['glue' => 'and', 'field' => 'cmf_'.$this->getFixtureById('custom_field1')->getId(), 'type' => 'custom_object', 'operator' => 'like', 'value' => 'love'],
                'match'  => 'test_value.value LIKE :test_value_value',
            ],
            [
                'filter' => ['glue' => 'and', 'field' => 'cmf_'.$this->getFixtureById('custom_field1')->getId(), 'type' => 'custom_object', 'operator' => 'neq', 'value' => 'love'],
                'match'  => '(test_value.value = :test_value_value) OR (test_value.value IS NULL)',
            ],
        ];

        foreach ($filters as $filter) {
            $queryBuilder = new QueryBuilder($this->entityManager->getConnection());

            $filterHelper->addCustomFieldValueExpressionFromSegmentFilter(
                $queryBuilder, 'test', $this->filterFactory->factorSegmentFilter($filter['filter'])
            );

            $whereResponse = (string) $queryBuilder->getQueryPart('where');
            $this->assertSame($filter['match'], $whereResponse);
        }
    }
}
