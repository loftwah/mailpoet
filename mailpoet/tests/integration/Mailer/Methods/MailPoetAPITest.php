<?php

namespace MailPoet\Test\Mailer\Methods;

use Codeception\Stub\Expected;
use Codeception\Util\Stub;
use MailPoet\Config\ServicesChecker;
use MailPoet\Mailer\MailerError;
use MailPoet\Mailer\Methods\Common\BlacklistCheck;
use MailPoet\Mailer\Methods\ErrorMappers\MailPoetMapper;
use MailPoet\Mailer\Methods\MailPoet;
use MailPoet\Services\AuthorizedEmailsController;
use MailPoet\Services\Bridge\API;

class MailPoetAPITest extends \MailPoetTest {
  public $metaInfo;
  public $newsletter;
  public $subscriber;
  /** @var MailPoet */
  public $mailer;
  public $replyTo;
  public $sender;
  public $settings;

  public function _before() {
    parent::_before();
    $this->settings = [
      'method' => 'MailPoet',
      'api_key' => getenv('WP_TEST_MAILER_MAILPOET_API') ?
        getenv('WP_TEST_MAILER_MAILPOET_API') :
        '1234567890',
    ];
    $this->sender = [
      'from_name' => 'Sender',
      'from_email' => 'staff@mailpoet.com',
      'from_name_email' => 'Sender <staff@mailpoet.com>',
    ];
    $this->replyTo = [
      'reply_to_name' => 'Reply To',
      'reply_to_email' => 'reply-to@mailpoet.com',
      'reply_to_name_email' => 'Reply To <reply-to@mailpoet.com>',
    ];
    $this->mailer = new MailPoet(
      $this->settings['api_key'],
      $this->sender,
      $this->replyTo,
      new MailPoetMapper(),
      $this->makeEmpty(AuthorizedEmailsController::class)
    );
    $this->subscriber = 'Recipient <mailpoet-phoenix-test@mailinator.com>';
    $this->newsletter = [
      'subject' => 'testing MailPoet … © & ěščřžýáíéůėę€żąß∂ 😊👨‍👩‍👧‍👧', // try some special chars
      'body' => [
        'html' => 'HTML body',
        'text' => 'TEXT body',
      ],
    ];
    $this->metaInfo = [
      'email_type' => 'sending_test',
      'subscriber_status' => 'unknown',
      'subscriber_source' => 'administrator',
    ];
  }

  public function testItCanGenerateBodyForSingleMessage() {
    $body = $this->mailer->getBody($this->newsletter, $this->subscriber);
    $subscriber = $this->mailer->processSubscriber($this->subscriber);
    expect($body[0]['to']['address'])->equals($subscriber['email']);
    expect($body[0]['to']['name'])->equals($subscriber['name']);
    expect($body[0]['from']['address'])->equals($this->sender['from_email']);
    expect($body[0]['from']['name'])->equals($this->sender['from_name']);
    expect($body[0]['reply_to']['address'])->equals($this->replyTo['reply_to_email']);
    expect($body[0]['reply_to']['name'])->equals($this->replyTo['reply_to_name']);
    expect($body[0]['subject'])->equals($this->newsletter['subject']);
    expect($body[0]['html'])->equals($this->newsletter['body']['html']);
    expect($body[0]['text'])->equals($this->newsletter['body']['text']);
  }

  public function testItRemovesReplyToNameIfEmpty() {
    $replyTo = [
      'reply_to_email' => 'reply-to@mailpoet.com',
      'reply_to_name_email' => '<reply-to@mailpoet.com>',
    ];
    $mailer = new MailPoet(
      $this->settings['api_key'],
      $this->sender,
      $replyTo,
      new MailPoetMapper(),
      $this->makeEmpty(AuthorizedEmailsController::class)
    );
    $body = $mailer->getBody($this->newsletter, $this->subscriber);
    expect($body[0]['reply_to'])->equals([
      'address' => 'reply-to@mailpoet.com',
    ]);
  }

  public function testItCanGenerateBodyForMultipleMessages() {
    $newsletters = array_fill(0, 10, $this->newsletter);
    $subscribers = array_fill(0, 10, $this->subscriber);
    $body = $this->mailer->getBody($newsletters, $subscribers);
    expect(count($body))->equals(10);
    $subscriber = $this->mailer->processSubscriber($this->subscriber);
    expect($body[0]['to']['address'])->equals($subscriber['email']);
    expect($body[0]['to']['name'])->equals($subscriber['name']);
    expect($body[0]['from']['address'])->equals($this->sender['from_email']);
    expect($body[0]['from']['name'])->equals($this->sender['from_name']);
    expect($body[0]['reply_to']['address'])->equals($this->replyTo['reply_to_email']);
    expect($body[0]['reply_to']['name'])->equals($this->replyTo['reply_to_name']);
    expect($body[0]['subject'])->equals($this->newsletter['subject']);
    expect($body[0]['html'])->equals($this->newsletter['body']['html']);
    expect($body[0]['text'])->equals($this->newsletter['body']['text']);
  }

  public function testItCanAddExtraParametersToSingleMessage() {
    $extraParams = [
      'unsubscribe_url' => 'http://example.com',
      'meta' => $this->metaInfo,
    ];
    $body = $this->mailer->getBody($this->newsletter, $this->subscriber, $extraParams);
    expect($body[0]['list_unsubscribe'])->equals($extraParams['unsubscribe_url']);
    expect($body[0]['meta'])->equals($extraParams['meta']);
  }

  public function testItCanAddExtraParametersToMultipleMessages() {
    $newsletters = array_fill(0, 10, $this->newsletter);
    $subscribers = array_fill(0, 10, $this->subscriber);
    $extraParams = [
      'unsubscribe_url' => array_fill(0, 10, 'http://example.com'),
      'meta' => array_fill(0, 10, $this->metaInfo),
    ];

    $body = $this->mailer->getBody($newsletters, $subscribers, $extraParams);
    expect(count($body))->equals(10);
    expect($body[0]['list_unsubscribe'])->equals($extraParams['unsubscribe_url'][0]);
    expect($body[9]['list_unsubscribe'])->equals($extraParams['unsubscribe_url'][9]);
    expect($body[0]['meta'])->equals($extraParams['meta'][0]);
    expect($body[9]['meta'])->equals($extraParams['meta'][9]);
  }

  public function testItCanProcessSubscriber() {
    expect($this->mailer->processSubscriber('test@test.com'))
      ->equals(
        [
          'email' => 'test@test.com',
          'name' => '',
        ]);
    expect($this->mailer->processSubscriber('First <test@test.com>'))
      ->equals(
        [
          'email' => 'test@test.com',
          'name' => 'First',
        ]);
    expect($this->mailer->processSubscriber('First Last <test@test.com>'))
      ->equals(
        [
          'email' => 'test@test.com',
          'name' => 'First Last',
        ]);
  }

  public function testItWillNotSendIfApiKeyIsMarkedInvalid() {
    if (getenv('WP_TEST_MAILER_ENABLE_SENDING') !== 'true') $this->markTestSkipped();
    $this->mailer->servicesChecker = Stub::make(
      new ServicesChecker(),
      ['isMailPoetAPIKeyValid' => false],
      $this
    );
    $result = $this->mailer->send(
      $this->newsletter,
      $this->subscriber
    );
    expect($result['response'])->false();
  }

  public function testItCannotSendWithoutProperApiKey() {
    if (getenv('WP_TEST_MAILER_ENABLE_SENDING') !== 'true') $this->markTestSkipped();
    $this->mailer->api->setKey('someapi');
    $result = $this->mailer->send(
      $this->newsletter,
      $this->subscriber
    );
    expect($result['response'])->false();
  }

  public function testItCanSend() {
    if (getenv('WP_TEST_MAILER_ENABLE_SENDING') !== 'true') $this->markTestSkipped();
    $result = $this->mailer->send(
      $this->newsletter,
      $this->subscriber
    );
    expect($result['response'])->true();
  }

  public function testFormatConnectionError() {
    $this->mailer->api = Stub::makeEmpty(
      'MailPoet\Services\Bridge\API',
      ['sendMessages' => [
        'status' => API::SENDING_STATUS_CONNECTION_ERROR,
        'message' => 'connection error',
      ]],
      $this
    );
    $result = $this->mailer->send($this->newsletter, $this->subscriber);
    expect($result['response'])->false();
    expect($result['error'])->isInstanceOf(MailerError::class);
    expect($result['error']->getOperation())->equals(MailerError::OPERATION_CONNECT);
  }

  public function testFormatErrorNotArray() {
    $this->mailer->api = Stub::makeEmpty(
      'MailPoet\Services\Bridge\API',
      ['sendMessages' => [
        'code' => API::RESPONSE_CODE_NOT_ARRAY,
        'status' => API::SENDING_STATUS_SEND_ERROR,
        'message' => 'error not array',
      ]],
      $this
    );
    $result = $this->mailer->send($this->newsletter, $this->subscriber);
    expect($result['response'])->false();
    expect($result['error'])->isInstanceOf(MailerError::class);
    expect($result['error']->getOperation())->equals(MailerError::OPERATION_SEND);
  }

  public function testFormatErrorTooBig() {
    $this->mailer->api = Stub::makeEmpty(
      'MailPoet\Services\Bridge\API',
      ['sendMessages' => [
        'code' => API::RESPONSE_CODE_PAYLOAD_TOO_BIG,
        'status' => API::SENDING_STATUS_SEND_ERROR,
        'message' => 'error too big',
      ]],
      $this
    );
    $result = $this->mailer->send($this->newsletter, $this->subscriber);
    expect($result['response'])->false();
    expect($result['error'])->isInstanceOf(MailerError::class);
  }

  public function testFormatPayloadError() {
    $this->mailer->api = Stub::makeEmpty(
      'MailPoet\Services\Bridge\API',
      ['sendMessages' => [
        'code' => API::RESPONSE_CODE_PAYLOAD_ERROR,
        'status' => API::SENDING_STATUS_SEND_ERROR,
        'message' => 'Api Error',
      ]],
      $this
    );
    $result = $this->mailer->send([$this->newsletter, $this->newsletter], ['a@example.com', 'c d <b@example.com>']);
    expect($result['response'])->false();
    expect($result['error'])->isInstanceOf(MailerError::class);
    expect($result['error']->getOperation())->equals(MailerError::OPERATION_SEND);
  }

  public function testFormatPayloadErrorWithErrorMessage() {
    $this->mailer->api = Stub::makeEmpty(
      'MailPoet\Services\Bridge\API',
      ['sendMessages' => [
        'code' => API::RESPONSE_CODE_PAYLOAD_ERROR,
        'status' => API::SENDING_STATUS_SEND_ERROR,
        'message' => '[{"index":0,"errors":{"subject":"subject is missing"}},{"index":1,"errors":{"subject":"subject is missing"}}]',
      ]],
      $this
    );
    $result = $this->mailer->send([$this->newsletter, $this->newsletter], ['a@example.com', 'c d <b@example.com>']);
    expect($result['response'])->false();
    expect($result['error'])->isInstanceOf(MailerError::class);
    expect($result['error']->getOperation())->equals(MailerError::OPERATION_SEND);
  }

  public function testItCallsAuthorizedEmailsValidationOnRelatedError() {
    $mailer = new MailPoet(
      $this->settings['api_key'],
      $this->sender,
      $this->replyTo,
      new MailPoetMapper(),
      $this->makeEmpty(AuthorizedEmailsController::class, ['checkAuthorizedEmailAddresses' => Expected::once()])
    );
    $mailer->api = $this->makeEmpty(
      API::class,
      ['sendMessages' => [
        'code' => API::RESPONSE_CODE_CAN_NOT_SEND,
        'status' => API::SENDING_STATUS_SEND_ERROR,
        'message' => MailerError::MESSAGE_EMAIL_NOT_AUTHORIZED,
      ]]
    );
    $mailer->send([$this->newsletter], [$this->subscriber]);
  }

  public function testItChecksBlacklistBeforeSendingToASingleSubscriber() {
    $blacklistedSubscriber = 'blacklist_test@example.com';
    $blacklist = Stub::make(new BlacklistCheck(), ['isBlacklisted' => true], $this);
    $mailer = Stub::make(
      $this->mailer,
      [
        'blacklist' => $blacklist,
        'errorMapper' => new MailPoetMapper(),
        'servicesChecker' => Stub::make(
          new ServicesChecker(),
          ['isMailPoetAPIKeyValid' => true],
          $this
        ),
      ],
      $this
    );
    $result = $mailer->send(
      $this->newsletter,
      $blacklistedSubscriber
    );
    expect($result['response'])->false();
    expect($result['error'])->isInstanceOf(MailerError::class);
    expect($result['error']->getMessage())->stringContainsString('unknown error');
    expect($result['error']->getMessage())->stringContainsString('MailPoet has returned an unknown error.');
  }

  public function testItChecksBlacklistBeforeSendingToMultipleSubscribers() {
    $blacklistedSubscriber = 'blacklist_test@example.com';
    $blacklist = Stub::make(new BlacklistCheck(), ['isBlacklisted' => true], $this);
    $mailer = Stub::make(
      $this->mailer,
      [
        'blacklist' => $blacklist,
        'errorMapper' => new MailPoetMapper(),
        'servicesChecker' => Stub::make(
          new ServicesChecker(),
          ['isMailPoetAPIKeyValid' => true],
          $this
        ),
      ],
      $this
    );
    $result = $mailer->send(
      $this->newsletter,
      ['good@example.com', $blacklistedSubscriber, 'good2@example.com']
    );
    expect($result['response'])->false();
    expect($result['error'])->isInstanceOf(MailerError::class);
    expect($result['error']->getMessage())->stringContainsString('unknown error');
    expect($result['error']->getMessage())->stringContainsString('MailPoet has returned an unknown error.');
  }
}
