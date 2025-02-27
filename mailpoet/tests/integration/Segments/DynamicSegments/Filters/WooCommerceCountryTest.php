<?php

namespace MailPoet\Segments\DynamicSegments\Filters;

use Helper\Database;
use MailPoet\Entities\DynamicSegmentFilterData;
use MailPoet\Entities\DynamicSegmentFilterEntity;
use MailPoet\Entities\SegmentEntity;
use MailPoet\Entities\SubscriberEntity;
use MailPoet\Subscribers\SubscribersRepository;
use MailPoetVendor\Doctrine\DBAL\ForwardCompatibility\DriverStatement;
use MailPoetVendor\Doctrine\DBAL\Query\QueryBuilder;

class WooCommerceCountryTest extends \MailPoetTest {

  /** @var WooCommerceCountry */
  private $wooCommerceCountry;

  /** @var SubscribersRepository */
  private $subscribersRepository;

  public function _before(): void {
    Database::createLookUpTables();
    $this->wooCommerceCountry = $this->diContainer->get(WooCommerceCountry::class);
    $this->subscribersRepository = $this->diContainer->get(SubscribersRepository::class);

    $this->cleanup();

    $userId1 = $this->tester->createWordPressUser('customer1@example.com', 'customer');
    $userId2 = $this->tester->createWordPressUser('customer2@example.com', 'customer');
    $userId3 = $this->tester->createWordPressUser('customer3@example.com', 'customer');
    $userId4 = $this->tester->createWordPressUser('customer4@example.com', 'customer');

    $this->createCustomerLookupData(['user_id' => $userId1, 'email' => 'customer1@example.com', 'country' => 'CZ']);
    $this->createCustomerLookupData(['user_id' => $userId2, 'email' => 'customer2@example.com', 'country' => 'US']);
    $this->createCustomerLookupData(['user_id' => $userId3, 'email' => 'customer3@example.com', 'country' => 'US']);
    $this->createCustomerLookupData(['user_id' => $userId4, 'email' => 'customer4@example.com', 'country' => 'ES']);

  }

  public function testItAppliesFilter(): void {
    $segmentFilter = $this->getSegmentFilter('CZ');
    $queryBuilder = $this->wooCommerceCountry->apply($this->getQueryBuilder(), $segmentFilter);
    $statement = $queryBuilder->execute();
    assert($statement instanceof DriverStatement);
    $result = $statement->fetchAll();
    expect(count($result))->equals(1);
    $subscriber1 = $this->subscribersRepository->findOneById($result[0]['inner_subscriber_id']);
    $this->assertInstanceOf(SubscriberEntity::class, $subscriber1);
    expect($subscriber1)->isInstanceOf(SubscriberEntity::class);
    expect($subscriber1->getEmail())->equals('customer1@example.com');
  }

  public function testItAppliesFilterAny(): void {
    $segmentFilter = $this->getSegmentFilter(['CZ','US']);
    $queryBuilder = $this->wooCommerceCountry->apply($this->getQueryBuilder(), $segmentFilter);
    $statement = $queryBuilder->execute();
    assert($statement instanceof DriverStatement);
    $result = $statement->fetchAll();
    expect(count($result))->equals(3);
    $subscriber1 = $this->subscribersRepository->findOneById($result[0]['inner_subscriber_id']);
    assert($subscriber1 instanceof SubscriberEntity);
    $this->assertInstanceOf(SubscriberEntity::class, $subscriber1);
    expect($subscriber1->getEmail())->equals('customer1@example.com');
    $subscriber2 = $this->subscribersRepository->findOneById($result[1]['inner_subscriber_id']);
    $this->assertInstanceOf(SubscriberEntity::class, $subscriber2);
    expect($subscriber2)->isInstanceOf(SubscriberEntity::class);
    expect($subscriber2->getEmail())->equals('customer2@example.com');
    $subscriber3 = $this->subscribersRepository->findOneById($result[2]['inner_subscriber_id']);
    assert($subscriber3 instanceof SubscriberEntity);
    $this->assertInstanceOf(SubscriberEntity::class, $subscriber3);
    expect($subscriber3->getEmail())->equals('customer3@example.com');
  }

  public function testItAppliesFilterNone() {
    $segmentFilter = $this->getSegmentFilter(['CZ','US'], DynamicSegmentFilterData::OPERATOR_NONE);
    $queryBuilder = $this->wooCommerceCountry->apply($this->getQueryBuilder(), $segmentFilter);
    $statement = $queryBuilder->execute();
    assert($statement instanceof DriverStatement);
    $result = $statement->fetchAll();
    expect(count($result))->equals(1);
    $subscriber1 = $this->subscribersRepository->findOneById($result[0]['inner_subscriber_id']);
    $this->assertInstanceOf(SubscriberEntity::class, $subscriber1);
    expect($subscriber1->getEmail())->equals('customer4@example.com');
  }

  private function getQueryBuilder(): QueryBuilder {
    $subscribersTable = $this->entityManager->getClassMetadata(SubscriberEntity::class)->getTableName();
    return $this->entityManager
      ->getConnection()
      ->createQueryBuilder()
      ->select("$subscribersTable.id as inner_subscriber_id")
      ->from($subscribersTable);
  }

  /**
   * @param string[]|string $country
   * @param string $operator
   * @return DynamicSegmentFilterEntity
   */
  private function getSegmentFilter($country, $operator = null): DynamicSegmentFilterEntity {
    $filterData = [
      'country_code' => $country,
    ];
    if ($operator) {
      $filterData['operator'] = $operator;
    }
    $data = new DynamicSegmentFilterData(
      DynamicSegmentFilterData::TYPE_WOOCOMMERCE,
      WooCommerceCountry::ACTION_CUSTOMER_COUNTRY,
      $filterData
    );
    $segment = new SegmentEntity('Dynamic Segment', SegmentEntity::TYPE_DYNAMIC, 'description');
    $this->entityManager->persist($segment);
    $dynamicSegmentFilter = new DynamicSegmentFilterEntity($segment, $data);
    $this->entityManager->persist($dynamicSegmentFilter);
    $segment->addDynamicFilter($dynamicSegmentFilter);
    return $dynamicSegmentFilter;
  }

  private function createCustomerLookupData(array $data) {
    global $wpdb;
    $connection = $this->entityManager->getConnection();
    $customerLookupTable = $wpdb->prefix . 'wc_customer_lookup';
    $connection->executeQuery("
      INSERT INTO {$customerLookupTable} (user_id, first_name, last_name, email, country)
        VALUES (
            {$data['user_id']},
            '',
            '',
            '{$data['email']}',
            '{$data['country']}'
        )
    ");
    $id = $connection->lastInsertId();
    $orderId = (int)$id + 1;
    $orderLookupTable = $wpdb->prefix . 'wc_order_stats';
    $connection->executeQuery("
      INSERT INTO {$orderLookupTable} (order_id, status, customer_id)
        VALUES (
            {$orderId},
            'wc-completed',
            {$id}
        )
    ");
  }

  private function cleanUpLookUpTables(): void {
    global $wpdb;
    $connection = $this->entityManager->getConnection();
    $lookupTable = $wpdb->prefix . 'wc_customer_lookup';
    $orderLookupTable = $wpdb->prefix . 'wc_order_stats';
    $connection->executeStatement("TRUNCATE $lookupTable");
    $connection->executeStatement("TRUNCATE $orderLookupTable");
  }

  public function _after(): void {
    $this->cleanUp();
  }

  private function cleanup(): void {
    $this->truncateEntity(SegmentEntity::class);
    $this->truncateEntity(SubscriberEntity::class);

    $emails = ['customer1@example.com', 'customer2@example.com', 'customer3@example.com', 'customer4@example.com'];
    foreach ($emails as $email) {
      $this->tester->deleteWordPressUser($email);
    }
    $this->cleanUpLookUpTables();
  }
}
