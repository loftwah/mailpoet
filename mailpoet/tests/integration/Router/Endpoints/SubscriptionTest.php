<?php

namespace MailPoet\Test\Router\Endpoints;

use Codeception\Stub;
use Codeception\Stub\Expected;
use MailPoet\Router\Endpoints\Subscription;
use MailPoet\Subscription\Captcha;
use MailPoet\Subscription\Pages;
use MailPoet\WP\Functions as WPFunctions;

class SubscriptionTest extends \MailPoetTest {
  public $data;

  /** @var WPFunctions */
  private $wp;

  /** @var Captcha */
  private $captcha;

  public function _before() {
    $this->data = [];
    $this->wp = WPFunctions::get();
    $this->captcha = $this->diContainer->get(Captcha::class);
  }

  public function testItDisplaysConfirmPage() {
    $pages = Stub::make(Pages::class, [
      'wp' => $this->wp,
      'confirm' => Expected::exactly(1),
    ], $this);
    $subscription = new Subscription($pages, $this->wp, $this->captcha);
    $subscription->confirm($this->data);
  }

  public function testItDisplaysManagePage() {
    $pages = Stub::make(Pages::class, [
      'wp' => new WPFunctions,
      'getManageLink' => Expected::exactly(1),
      'getManageContent' => Expected::exactly(1),
    ], $this);
    $subscription = new Subscription($pages, $this->wp, $this->captcha);
    $subscription->manage($this->data);
    do_shortcode('[mailpoet_manage]');
    do_shortcode('[mailpoet_manage_subscription]');
  }

  public function testItDisplaysUnsubscribePage() {
    $pages = Stub::make(Pages::class, [
      'wp' => new WPFunctions,
      'unsubscribe' => Expected::exactly(1),
    ], $this);
    $subscription = new Subscription($pages, $this->wp, $this->captcha);
    $subscription->unsubscribe($this->data);
  }
}
