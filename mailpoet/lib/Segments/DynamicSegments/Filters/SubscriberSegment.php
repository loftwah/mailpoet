<?php

namespace MailPoet\Segments\DynamicSegments\Filters;

use MailPoet\Entities\DynamicSegmentFilterData;
use MailPoet\Entities\DynamicSegmentFilterEntity;
use MailPoet\Entities\SubscriberEntity;
use MailPoet\Entities\SubscriberSegmentEntity;
use MailPoet\Util\Security;
use MailPoetVendor\Doctrine\DBAL\Connection;
use MailPoetVendor\Doctrine\DBAL\Query\QueryBuilder;
use MailPoetVendor\Doctrine\ORM\EntityManager;

class SubscriberSegment implements Filter {
  const TYPE = 'subscribedToList';

  /** @var EntityManager */
  private $entityManager;

  public function __construct(
    EntityManager $entityManager
  ) {
    $this->entityManager = $entityManager;
  }

  public function apply(QueryBuilder $queryBuilder, DynamicSegmentFilterEntity $filter): QueryBuilder {
    $filterData = $filter->getFilterData();
    $segments = $filterData->getParam('segments');
    $operator = $filterData->getParam('operator');
    $parameterSuffix = $filter->getId() ?: Security::generateRandomString();
    $statusSubscribedParam = 'subscribed' . $parameterSuffix;
    $segmentsParam = 'segments' . $parameterSuffix;

    $subscribersTable = $this->entityManager->getClassMetadata(SubscriberEntity::class)->getTableName();
    $subscriberSegmentTable = $this->entityManager->getClassMetadata(SubscriberSegmentEntity::class)->getTableName();

    $queryBuilder->leftJoin(
      $subscribersTable,
      $subscriberSegmentTable,
      'subscriber_segments',
      "$subscribersTable.id = subscriber_segments.subscriber_id"
        . ' AND subscriber_segments.status = :' . $statusSubscribedParam
        . ' AND subscriber_segments.segment_id IN (:' . $segmentsParam . ')'
    );

    $queryBuilder->setParameter($statusSubscribedParam, SubscriberEntity::STATUS_SUBSCRIBED);
    $queryBuilder->setParameter($segmentsParam, $segments, Connection::PARAM_INT_ARRAY);

    if ($operator === DynamicSegmentFilterData::OPERATOR_NONE) {
      $queryBuilder->andWhere('subscriber_segments.id IS NULL');
    } else {
      $queryBuilder->andWhere('subscriber_segments.id IS NOT NULL');
    }

    if ($operator === DynamicSegmentFilterData::OPERATOR_ALL) {
      $queryBuilder->groupBy('subscriber_id');
      $queryBuilder->having('COUNT(1) = ' . count($segments));
    }

    return $queryBuilder;
  }
}
