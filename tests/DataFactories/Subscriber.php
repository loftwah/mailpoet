<?php

namespace MailPoet\Test\DataFactories;

use MailPoet\DI\ContainerWrapper;
use MailPoet\Entities\SegmentEntity;
use MailPoet\Entities\SubscriberEntity;
use MailPoet\Entities\SubscriberSegmentEntity;
use MailPoetVendor\Doctrine\ORM\EntityManager;

class Subscriber {

  /** @var array */
  private $data;

  /** @var SegmentEntity[] */
  private $segments;

  public function __construct() {
    $this->data = [
      'email' => bin2hex(random_bytes(7)) . '@example.com', // phpcs:ignore
      'status' => 'subscribed',
    ];
    $this->segments = [];
  }

  /**
   * @param string $firstName
   * @return $this
   */
  public function withFirstName($firstName) {
    $this->data['first_name'] = $firstName;
    return $this;
  }

  /**
   * @param string $lastName
   * @return $this
   */
  public function withLastName($lastName) {
    $this->data['last_name'] = $lastName;
    return $this;
  }

  /**
   * @param string $email
   * @return $this
   */
  public function withEmail($email) {
    $this->data['email'] = $email;
    return $this;
  }

  /**
   * @param string $status
   * @return $this
   */
  public function withStatus($status) {
    $this->data['status'] = $status;
    return $this;
  }

  /**
   * @param int $count
   * @return $this
   */
  public function withCountConfirmations($count) {
    $this->data['count_confirmations'] = $count;
    return $this;
  }

  /**
   * @param SegmentEntity[] $segments
   * @return $this
   */
  public function withSegments(array $segments) {
    $this->segments = array_merge($this->segments, $segments);
    return $this;
  }

  /**
   * @throws \Exception
   */
  public function create(): SubscriberEntity {
    $entityManager = ContainerWrapper::getInstance()->get(EntityManager::class);
    $subscriber = new SubscriberEntity();
    $subscriber->setStatus($this->data['status']);
    $subscriber->setEmail($this->data['email']);
    if (isset($this->data['count_confirmations'])) $subscriber->setConfirmationsCount($this->data['count_confirmations']);
    if (isset($this->data['last_name'])) $subscriber->setLastName($this->data['last_name']);
    if (isset($this->data['first_name'])) $subscriber->setFirstName($this->data['first_name']);
    $entityManager->persist($subscriber);

    foreach ($this->segments as $segment) {
      $subscriberSegment = new SubscriberSegmentEntity($segment, $subscriber, 'subscribed');
      $entityManager->persist($subscriberSegment);
    }

    $entityManager->flush();
    return $subscriber;
  }
}
