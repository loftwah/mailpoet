<?php

namespace MailPoet\Test\Cron\Workers;

use Codeception\Stub;
use MailPoet\Cron\Workers\AuthorizedSendingEmailsCheck;
use MailPoet\Entities\ScheduledTaskEntity;
use MailPoet\Services\AuthorizedEmailsController;

class AuthorizedSendingEmailsCheckTest extends \MailPoetTest {
  public function _before() {
    parent::_before();
    $this->truncateEntity(ScheduledTaskEntity::class);
  }

  public function testItRunsCheckOnBridge() {
    $bridgeMock = $this->makeEmpty(AuthorizedEmailsController::class, ['checkAuthorizedEmailAddresses' => Stub\Expected::once()]);
    $worker = new AuthorizedSendingEmailsCheck($bridgeMock);
    $worker->processTaskStrategy(new ScheduledTaskEntity(), microtime(true));
  }
}
