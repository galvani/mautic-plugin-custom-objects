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

namespace MauticPlugin\CustomObjectsBundle\EventListener;

use Mautic\CoreBundle\Event\TokenReplacementEvent;
use Mautic\EmailBundle\EmailEvents;
use Mautic\EmailBundle\Entity\Email;
use Mautic\EmailBundle\Event\EmailSendEvent;
use Mautic\LeadBundle\Entity\Lead;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use MauticPlugin\CustomObjectsBundle\Provider\ConfigProvider;
use MauticPlugin\CustomObjectsBundle\Model\CustomObjectModel;
use MauticPlugin\CustomObjectsBundle\Model\CustomItemModel;
use Mautic\EmailBundle\EventListener\MatchFilterForLeadTrait;
use Mautic\CoreBundle\Helper\BuilderTokenHelper;
use Mautic\CoreBundle\Event\BuilderEvent;
use MauticPlugin\CustomObjectsBundle\Entity\CustomObject;
use MauticPlugin\CustomObjectsBundle\Entity\CustomField;
use MauticPlugin\CustomObjectsBundle\Exception\NotFoundException;
use MauticPlugin\CustomObjectsBundle\DTO\TableConfig;
use MauticPlugin\CustomObjectsBundle\Entity\CustomItem;
use Mautic\UserBundle\Entity\User;
use Mautic\UserBundle\Model\UserModel;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use MauticPlugin\CustomObjectsBundle\CustomItemEvents;
use MauticPlugin\CustomObjectsBundle\Event\CustomItemListQueryEvent;
use Mautic\LeadBundle\Entity\LeadList;
use Doctrine\ORM\Query\Expr;
use MauticPlugin\CustomObjectsBundle\Provider\CustomFieldTypeProvider;
use Mautic\LeadBundle\Segment\RandomParameterName;
use Mautic\LeadBundle\Segment\ContactSegmentFilterFactory;
use Mautic\LeadBundle\Segment\ContactSegmentFilter;

/**
 * Handles Custom Object token replacements with the correct value in emails.
 */
class TokenSubscriber implements EventSubscriberInterface
{
    use MatchFilterForLeadTrait;

    private const TOKEN = '{custom-object=(.*?)}';

    /**
     * @var ConfigProvider
     */
    private $configProvider;

    /**
     * @var CustomFieldTypeProvider
     */
    private $customFieldTypeProvider;

    /**
     * @var ContactSegmentFilterFactory
     */
    private $contactSegmentFilterFactory;

    /**
     * @var RandomParameterName
     */
    private $randomParameterNameService;

    /**
     * @var CustomObjectModel
     */
    private $customObjectModel;

    /**
     * @var CustomItemModel
     */
    private $customItemModel;

    /**
     * @var TokenStorageInterface
     */
    private $tokenStorage;

    /**
     * @var UserModel
     */
    private $userModel;

    /**
     * @param ConfigProvider    $configProvider
     * @param CustomFieldTypeProvider    $customFieldTypeProvider
     * @param ContactSegmentFilterFactory    $contactSegmentFilterFactory
     * @param RandomParameterName $randomParameterNameService
     * @param CustomObjectModel $customObjectModel
     * @param CustomItemModel   $customItemModel
     * @param TokenStorageInterface   $customItemModel
     * @param UserModel   $userModel
     */
    public function __construct(
        ConfigProvider $configProvider,
        CustomFieldTypeProvider $customFieldTypeProvider,
        ContactSegmentFilterFactory $contactSegmentFilterFactory,
        RandomParameterName $randomParameterNameService,
        CustomObjectModel $customObjectModel, 
        CustomItemModel $customItemModel//,
        // TokenStorageInterface $tokenStorage,
        // UserModel $userModel
    )
    {
        $this->configProvider    = $configProvider;
        $this->customFieldTypeProvider    = $customFieldTypeProvider;
        $this->contactSegmentFilterFactory    = $contactSegmentFilterFactory;
        $this->randomParameterNameService    = $randomParameterNameService;
        $this->customObjectModel = $customObjectModel;
        $this->customItemModel   = $customItemModel;
        // $this->tokenStorage      = $tokenStorage;
        // $this->userModel      = $userModel;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            EmailEvents::EMAIL_ON_BUILD    => ['onBuilderBuild', 0],
            EmailEvents::EMAIL_ON_SEND     => ['decodeTokens', 0],
            EmailEvents::EMAIL_ON_DISPLAY  => ['decodeTokens', 0],
            CustomItemEvents::ON_CUSTOM_ITEM_LIST_QUERY => 'onListQuery',
        ];
    }

    /**
     * @param BuilderEvent $event
     */
    public function onBuilderBuild(BuilderEvent $event)
    {
        if (!$this->configProvider->pluginIsEnabled()) {
            return;
        }

        if (!$event->tokensRequested(self::TOKEN)) {
            return;
        }

        $customObjects = $this->customObjectModel->fetchAllPublishedEntities();

        /** @var CustomObject $customObject */
        foreach ($customObjects as $customObject) {
            /** @var CustomField $customField */
            foreach ($customObject->getCustomFields() as $customField) {
                $token = "{custom-object={$customObject->getAlias()}:{$customField->getAlias()} | where=segment-filter | order=latest | limit=1 | default=}";
                $label = "{$customObject->getName()}: {$customField->getLabel()}";
                $event->addToken($token, $label);
            }
        }
    }

    /**
     * @param EmailSendEvent $event
     */
    public function decodeTokens(EmailSendEvent $event)
    {
        if (!$this->configProvider->pluginIsEnabled()) {
            return;
        }

        preg_match_all('/'.self::TOKEN.'/', $event->getContent(), $matches);

        if (!empty($matches[1])) {
            $contact = $event->getLead();
            $email = $event->getEmail();
            // $this->setActiveUser($this->userModel->getEntity($email->getCreatedBy())); // Do we care about CO permissions at this point?
            $segments = $email->getLists(); // take the where conditions from this.
            foreach ($matches[1] as $key => $tokenDataRaw) {
                $token = $matches[0][$key];
                $parts = $this->trimArrayElements(explode('|', $tokenDataRaw));

                if (empty($parts[0])) {
                    continue;
                }

                $aliases = $this->trimArrayElements(explode(':', $parts[0]));
                unset($parts[0]);

                if (2 !== count($aliases)) {
                    continue;
                }

                $customObjectAlias = $aliases[0];
                $customFieldAlias = $aliases[1];
                $orderBy = CustomItem::TABLE_ALIAS.'.dateAdded';
                $orderDir = 'DESC';
                $limit = 1;
                $defaultValue = '';
                $where = '';
                
                try {
                    $customObject = $this->customObjectModel->fetchEntityByAlias($customObjectAlias);
                } catch (NotFoundException $e) {
                    continue;
                }
                // custom-object=product:sku | where=segment-filter |order=latest|limit=1 | default=No thing
                foreach ($parts as $part) {
                    $options = $this->trimArrayElements(explode('=', $part));
                    
                    if (2 !== count($options)) {
                        continue;
                    }
                    
                    $keyword = $options[0];
                    $value = $options[1];
                    
                    if ('limit' === $keyword) {
                        $limit = (int) $value;
                    }

                    if ('order' === $keyword) {
                        // "latest" is the default value but more will come in the future.
                    }

                    if ('where' === $keyword) {
                        $where = $value;
                    }

                    if ('default' === $keyword) {
                        $defaultValue = $value;
                    }
                }

                if ('segment-filter' === $where) {
                    $segmentConditions = [];
                    if ('list' === $email->getEmailType()) {
                        /** @var LeadList $segment */
                        foreach ($email->getLists() as $segment) {
                            $segmentFilters = $this->contactSegmentFilterFactory->getSegmentFilters($segment);
                            /** @var ContactSegmentFilter $filter */
                            foreach ($segmentFilters as $filter) {
                                if ($filter->ob === 'custom_object') {// there is no way how to get the object.
                                    $segmentConditions[] = $filter;
                                }
                            }
                        }
                    }
                    // @todo implement also campaign emails.
                }
                
                $tableConfig = new TableConfig($limit, 1, $orderBy, $orderDir);
                $tableConfig->addParameter('customObjectId', $customObject->getId());
                $tableConfig->addParameter('filterEntityType', 'contact');
                $tableConfig->addParameter('filterEntityId', (int) $contact['id']);
                $tableConfig->addParameter('tokenWhere', $where);
                $tableConfig->addParameter('segmentConditions', $segmentConditions);
                $customItems = $this->customItemModel->getTableData($tableConfig);
                $fieldValues = [];

                /** @var CustomItem $customItem */
                foreach ($customItems as $customItem) {
                    $customItem = $this->customItemModel->populateCustomFields($customItem);
                    try {
                        $fieldValues[] = $customItem->findCustomFieldValueForFieldAlias($customFieldAlias)->getValue();
                    } catch (NotFoundException $e) {
                        // Custom field not found.
                    }
                }

                $result = empty($fieldValues) ? $defaultValue : implode(', ', $fieldValues);

                $event->addToken($token, $result);
            }
        }
    }

    /**
     * @param CustomItemListQueryEvent $event
     */
    public function onListQuery(CustomItemListQueryEvent $event): void
    {
        $tableConfig = $event->getTableConfig();
        $contactId = $tableConfig->getParameter('filterEntityId');
        $segmentConditions = $tableConfig->getParameter('segmentConditions');
        if ('contact' === $tableConfig->getParameter('filterEntityType') && $contactId && $segmentConditions && is_array($segmentConditions)) {
            $queryBuilder = $event->getQueryBuilder();

            foreach ($segmentConditions as $condition) {
                $customFieldType = $this->customFieldTypeProvider->getType($condition['type']);
                $fieldData = explode('_', $condition['field']);
                $customFieldId = (int) $fieldData[1];
                $randomHash = $this->randomParameterNameService->generateRandomParameterName();
                $tableAlias = "{$customFieldType->getTableAlias()}_{$randomHash}";
                $queryBuilder->leftJoin(
                    $customFieldType->getEntityClass(),
                    $tableAlias,
                    Expr\Join::WITH,
                    "CustomItem.id = {$tableAlias}.customItem AND {$tableAlias}.customField = {$customFieldId}");
                // @todo deal with AND and OR.
                // @todo deal with different operators.
                $queryBuilder->andWhere("{$tableAlias}.value = :{$randomHash}");
                $queryBuilder->setParameter($randomHash, $condition['filter']);
            }
            // dump($queryBuilder->getQuery()->getSQL());
            // dump($queryBuilder->getQuery()->getParameters());
        }

    }

    private function trimArrayElements(array $array): array
    {
        return array_map(function ($part) {
            return trim($part);
        }, $array);
    }

    private function setActiveUser(User $user): void
    {
        $token = $this->tokenStorage->getToken();
        // $user  = $token->getUser();

        // if (!$user->isAdmin() && empty($user->getActivePermissions())) {
        //     $activePermissions = $this->permissionRepository->getPermissionsByRole($user->getRole());

        //     $user->setActivePermissions($activePermissions);
        // }

        $token->setUser($user);

        $this->tokenStorage->setToken($token);
    }
}
