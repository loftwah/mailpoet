<?php declare(strict_types = 1);

namespace MailPoet\Subscribers;

use MailPoet\Entities\SegmentEntity;
use MailPoet\Entities\SubscriberEntity;
use MailPoet\Listing\ListingDefinition;
use MailPoet\Listing\ListingRepository;
use MailPoet\Segments\DynamicSegments\FilterHandler;
use MailPoet\Segments\SegmentSubscribersRepository;
use MailPoet\Util\Helpers;
use MailPoet\WP\Functions as WPFunctions;
use MailPoetVendor\Doctrine\DBAL\Driver\Statement;
use MailPoetVendor\Doctrine\DBAL\Query\QueryBuilder as DBALQueryBuilder;
use MailPoetVendor\Doctrine\ORM\EntityManager;
use MailPoetVendor\Doctrine\ORM\Query\Expr\Join;
use MailPoetVendor\Doctrine\ORM\QueryBuilder;

class SubscriberListingRepository extends ListingRepository {
  public const FILTER_WITHOUT_LIST = 'without-list';

  const DEFAULT_SORT_BY = 'createdAt';

  private static $supportedStatuses = [
    SubscriberEntity::STATUS_SUBSCRIBED,
    SubscriberEntity::STATUS_UNSUBSCRIBED,
    SubscriberEntity::STATUS_INACTIVE,
    SubscriberEntity::STATUS_BOUNCED,
    SubscriberEntity::STATUS_UNCONFIRMED,
  ];

  /** @var FilterHandler */
  private $dynamicSegmentsFilter;

  /** @var EntityManager */
  private $entityManager;

  /** @var SegmentSubscribersRepository */
  private $segmentSubscribersRepository;

  /** @var SubscribersCountsController */
  private $subscribersCountsController;

  public function __construct(
    EntityManager $entityManager,
    FilterHandler $dynamicSegmentsFilter,
    SegmentSubscribersRepository $segmentSubscribersRepository,
    SubscribersCountsController $subscribersCountsController
  ) {
    parent::__construct($entityManager);
    $this->dynamicSegmentsFilter = $dynamicSegmentsFilter;
    $this->entityManager = $entityManager;
    $this->segmentSubscribersRepository = $segmentSubscribersRepository;
    $this->subscribersCountsController = $subscribersCountsController;
  }

  public function getData(ListingDefinition $definition): array {
    $dynamicSegment = $this->getDynamicSegmentFromFilters($definition);
    if ($dynamicSegment === null) {
      return parent::getData($definition);
    }
    return $this->getDataForDynamicSegment($definition, $dynamicSegment);
  }

  public function getCount(ListingDefinition $definition): int {
    $dynamicSegment = $this->getDynamicSegmentFromFilters($definition);
    if ($dynamicSegment === null) {
      return parent::getCount($definition);
    }
    $subscribersTable = $this->entityManager->getClassMetadata(SubscriberEntity::class)->getTableName();
    $subscribersIdsQuery = $this->entityManager
      ->getConnection()
      ->createQueryBuilder()
      ->select("count(DISTINCT $subscribersTable.id)")
      ->from($subscribersTable);
    $subscribersIdsQuery = $this->applyConstraintsForDynamicSegment($subscribersIdsQuery, $definition, $dynamicSegment);
    return (int)$subscribersIdsQuery->execute()->fetchColumn();
  }

  public function getActionableIds(ListingDefinition $definition): array {
    $ids = $definition->getSelection();
    if (!empty($ids)) {
      return $ids;
    }
    $dynamicSegment = $this->getDynamicSegmentFromFilters($definition);
    if ($dynamicSegment === null) {
      return parent::getActionableIds($definition);
    }
    $subscribersTable = $this->entityManager->getClassMetadata(SubscriberEntity::class)->getTableName();
    $subscribersIdsQuery = $this->entityManager
      ->getConnection()
      ->createQueryBuilder()
      ->select("DISTINCT $subscribersTable.id")
      ->from($subscribersTable);
    $subscribersIdsQuery = $this->applyConstraintsForDynamicSegment($subscribersIdsQuery, $definition, $dynamicSegment);
    $idsStatement = $subscribersIdsQuery->execute();
    $result = $idsStatement->fetchAll();
    return array_column($result, 'id');
  }

  protected function applySelectClause(QueryBuilder $queryBuilder) {
    $queryBuilder->select("PARTIAL s.{id,email,firstName,lastName,status,createdAt,countConfirmations,wpUserId,isWoocommerceUser,engagementScore}");
  }

  protected function applyFromClause(QueryBuilder $queryBuilder) {
    $queryBuilder->from(SubscriberEntity::class, 's');
  }

  protected function applyGroup(QueryBuilder $queryBuilder, string $group) {
    // include/exclude deleted
    if ($group === 'trash') {
      $queryBuilder->andWhere('s.deletedAt IS NOT NULL');
    } else {
      $queryBuilder->andWhere('s.deletedAt IS NULL');
    }

    if (!in_array($group, self::$supportedStatuses)) {
      return;
    }

    $queryBuilder
      ->andWhere('s.status = :status')
      ->setParameter('status', $group);
  }

  protected function applySearch(QueryBuilder $queryBuilder, string $search) {
    $search = Helpers::escapeSearch($search);
    $queryBuilder
      ->andWhere('s.email LIKE :search or s.firstName LIKE :search or s.lastName LIKE :search')
      ->setParameter('search', "%$search%");
  }

  protected function applyFilters(QueryBuilder $queryBuilder, array $filters) {
    if (!isset($filters['segment'])) {
      return;
    }
    if ($filters['segment'] === self::FILTER_WITHOUT_LIST) {
      $this->segmentSubscribersRepository->addConstraintsForSubscribersWithoutSegment($queryBuilder);
      return;
    }
    $segment = $this->entityManager->find(SegmentEntity::class, (int)$filters['segment']);
    if (!$segment instanceof SegmentEntity) {
      return;
    }
    if ($segment->isStatic()) {
      $queryBuilder->join('s.subscriberSegments', 'ss', Join::WITH, 'ss.segment = :ssSegment')
        ->setParameter('ssSegment', $segment->getId());
      return;
    }
  }

  protected function applyParameters(QueryBuilder $queryBuilder, array $parameters) {
    // nothing to do here
  }

  protected function applySorting(QueryBuilder $queryBuilder, string $sortBy, string $sortOrder) {
    if (!$sortBy) {
      $sortBy = self::DEFAULT_SORT_BY;
    }
    $queryBuilder->addOrderBy("s.$sortBy", $sortOrder);
  }

  public function getGroups(ListingDefinition $definition): array {
    $queryBuilder = clone $this->queryBuilder;
    $this->applyFromClause($queryBuilder);

    // total count
    $countQueryBuilder = clone $queryBuilder;
    $countQueryBuilder->select('COUNT(s) AS subscribersCount');
    $countQueryBuilder->andWhere('s.deletedAt IS NULL');
    $totalCount = (int)$countQueryBuilder->getQuery()->getSingleScalarResult();

    // trashed count
    $trashedCountQueryBuilder = clone $queryBuilder;
    $trashedCountQueryBuilder->select('COUNT(s) AS subscribersCount');
    $trashedCountQueryBuilder->andWhere('s.deletedAt IS NOT NULL');
    $trashedCount = (int)$trashedCountQueryBuilder->getQuery()->getSingleScalarResult();

    // count-by-status query
    $queryBuilder->select('s.status, COUNT(s) AS subscribersCount');
    $queryBuilder->andWhere('s.deletedAt IS NULL');
    $queryBuilder->groupBy('s.status');

    $map = [];
    foreach ($queryBuilder->getQuery()->getResult() as $item) {
      $map[$item['status']] = (int)$item['subscribersCount'];
    }

    return [
      [
        'name' => 'all',
        'label' => WPFunctions::get()->__('All', 'mailpoet'),
        'count' => $totalCount,
      ],
      [
        'name' => SubscriberEntity::STATUS_SUBSCRIBED,
        'label' => WPFunctions::get()->__('Subscribed', 'mailpoet'),
        'count' => $map[SubscriberEntity::STATUS_SUBSCRIBED] ?? 0,
      ],
      [
        'name' => SubscriberEntity::STATUS_UNCONFIRMED,
        'label' => WPFunctions::get()->__('Unconfirmed', 'mailpoet'),
        'count' => $map[SubscriberEntity::STATUS_UNCONFIRMED] ?? 0,
      ],
      [
        'name' => SubscriberEntity::STATUS_UNSUBSCRIBED,
        'label' => WPFunctions::get()->__('Unsubscribed', 'mailpoet'),
        'count' => $map[SubscriberEntity::STATUS_UNSUBSCRIBED] ?? 0,
      ],
      [
        'name' => SubscriberEntity::STATUS_INACTIVE,
        'label' => WPFunctions::get()->__('Inactive', 'mailpoet'),
        'count' => $map[SubscriberEntity::STATUS_INACTIVE] ?? 0,
      ],
      [
        'name' => SubscriberEntity::STATUS_BOUNCED,
        'label' => WPFunctions::get()->__('Bounced', 'mailpoet'),
        'count' => $map[SubscriberEntity::STATUS_BOUNCED] ?? 0,
      ],
      [
        'name' => 'trash',
        'label' => WPFunctions::get()->__('Trash', 'mailpoet'),
        'count' => $trashedCount,
      ],
    ];
  }

  public function getFilters(ListingDefinition $definition): array {
    $group = $definition->getGroup();

    $subscribersWithoutSegmentStats = $this->subscribersCountsController->getSubscribersWithoutSegmentStatisticsCount();
    $key = $group ?: 'all';
    $subscribersWithoutSegmentCount = $subscribersWithoutSegmentStats[$key];

    $subscribersWithoutSegmentLabel = sprintf(
      __('Subscribers without a list (%s)', 'mailpoet'),
      number_format((float)$subscribersWithoutSegmentCount)
    );

    $queryBuilder = clone $this->queryBuilder;
    $queryBuilder
      ->select('s')
      ->from(SegmentEntity::class, 's');
    if ($group === 'trash') {
      $queryBuilder->andWhere('s.deletedAt IS NOT NULL');
    } else {
      $queryBuilder->andWhere('s.deletedAt IS NULL');
    }

    // format segment list
    $allSubscribersList = [
      'label' => WPFunctions::get()->__('All Lists', 'mailpoet'),
      'value' => '',
    ];

    $withoutSegmentList = [
      'label' => $subscribersWithoutSegmentLabel,
      'value' => self::FILTER_WITHOUT_LIST,
    ];

    $segmentList = [];
    foreach ($queryBuilder->getQuery()->getResult() as $segment) {
      $key = $group ?: 'all';
      if ($segment->isStatic()) {
        $count = $this->subscribersCountsController->getSegmentGlobalStatusStatisticsCount($segment);
      } else {
        $count = $this->subscribersCountsController->getSegmentStatisticsCount($segment);
      }
      if (!$count[$key]) {
        continue;
      }

      $segmentList[] = [
        'label' => sprintf('%s (%s)', $segment->getName(), number_format((float)$count[$key])),
        'value' => $segment->getId(),
      ];
    }

    usort($segmentList, function($a, $b) {
      return strcasecmp($a['label'], $b['label']);
    });

    array_unshift($segmentList, $allSubscribersList, $withoutSegmentList);
    return ['segment' => $segmentList];
  }

  private function getDataForDynamicSegment(ListingDefinition $definition, SegmentEntity $segment) {
    $queryBuilder = clone $this->queryBuilder;
    $sortBy = Helpers::underscoreToCamelCase($definition->getSortBy()) ?: self::DEFAULT_SORT_BY;
    $this->applySelectClause($queryBuilder);
    $this->applyFromClause($queryBuilder);

    $subscribersTable = $this->entityManager->getClassMetadata(SubscriberEntity::class)->getTableName();
    $subscribersIdsQuery = $this->entityManager
      ->getConnection()
      ->createQueryBuilder()
      ->select("DISTINCT $subscribersTable.id")
      ->from($subscribersTable);
    $subscribersIdsQuery = $this->applyConstraintsForDynamicSegment($subscribersIdsQuery, $definition, $segment);
    $subscribersIdsQuery->orderBy("$subscribersTable." . Helpers::camelCaseToUnderscore($sortBy), $definition->getSortOrder());
    $subscribersIdsQuery->setFirstResult($definition->getOffset());
    $subscribersIdsQuery->setMaxResults($definition->getLimit());

    $idsStatement = $subscribersIdsQuery->execute();
    // This shouldn't happen because execute on select SQL always returns Statement, but PHPStan doesn't know that
    if (!$idsStatement instanceof Statement) {
      $queryBuilder->andWhere('0 = 1');
      return;
    }
    $result = $idsStatement->fetchAll();
    $ids = array_column($result, 'id');
    if (count($ids)) {
      $queryBuilder->andWhere('s.id IN (:subscriberIds)')
        ->setParameter('subscriberIds', $ids);
    } else {
      $queryBuilder->andWhere('0 = 1'); // Don't return any subscribers if no ids found
    }
    $this->applySorting($queryBuilder, $sortBy, $definition->getSortOrder());
    return $queryBuilder->getQuery()->getResult();
  }

  private function applyConstraintsForDynamicSegment(
    DBALQueryBuilder $subscribersQuery,
    ListingDefinition $definition,
    SegmentEntity $segment
  ) {
    // Apply dynamic segments filters
    $subscribersQuery = $this->dynamicSegmentsFilter->apply($subscribersQuery, $segment);
    // Apply group, search to fetch only necessary ids
    $subscribersTable = $this->entityManager->getClassMetadata(SubscriberEntity::class)->getTableName();
    if ($definition->getSearch()) {
      $search = Helpers::escapeSearch((string)$definition->getSearch());
      $subscribersQuery
        ->andWhere("$subscribersTable.email LIKE :search or $subscribersTable.first_name LIKE :search or $subscribersTable.last_name LIKE :search")
        ->setParameter('search', "%$search%");
    }
    if ($definition->getGroup()) {
      if ($definition->getGroup() === 'trash') {
        $subscribersQuery->andWhere("$subscribersTable.deleted_at IS NOT NULL");
      } else {
        $subscribersQuery->andWhere("$subscribersTable.deleted_at IS NULL");
      }
      if (in_array($definition->getGroup(), self::$supportedStatuses)) {
        $subscribersQuery
          ->andWhere("$subscribersTable.status = :status")
          ->setParameter('status', $definition->getGroup());
      }
    }
    return $subscribersQuery;
  }

  private function getDynamicSegmentFromFilters(ListingDefinition $definition): ?SegmentEntity {
    $filters = $definition->getFilters();
    if (!$filters || !isset($filters['segment'])) {
      return null;
    }
    if ($filters['segment'] === self::FILTER_WITHOUT_LIST) {
      return null;
    }
    $segment = $this->entityManager->find(SegmentEntity::class, (int)$filters['segment']);
    if (!$segment instanceof SegmentEntity) {
      return null;
    }
    return $segment->isStatic() ? null : $segment;
  }
}
