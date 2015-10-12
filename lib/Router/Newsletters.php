<?php
namespace MailPoet\Router;

use MailPoet\Listing;
use MailPoet\Mailer\API\MailPoet;
use MailPoet\Models\Newsletter;
use MailPoet\Models\Segment;
use MailPoet\Models\Setting;
use MailPoet\Models\Subscriber;
use MailPoet\Models\NewsletterTemplate;
use MailPoet\Newsletter\Renderer\Renderer;

if(!defined('ABSPATH')) exit;

class Newsletters {
  function __construct() {
  }

  function get($data = array()) {
    $id = (isset($data['id'])) ? (int) $data['id'] : 0;
    $newsletter = Newsletter::findOne($id);
    if($newsletter === false) {
      wp_send_json(false);
    } else {
      wp_send_json($newsletter->asArray());
    }
  }

  function getAll() {
    $collection = Newsletter::findArray();
    wp_send_json($collection);
  }

  function save($data = array()) {
    $result = Newsletter::createOrUpdate($data);
    if($result !== true) {
      wp_send_json($result);
    } else {
      wp_send_json(true);
    }
  }

  function delete($id) {
    $newsletter = Newsletter::findOne($id);
    if($newsletter !== false) {
      $result = $newsletter->delete();
    } else {
      $result = false;
    }
    wp_send_json($result);
  }

  function send($data = array()) {
    $newsletter = Newsletter::findOne($data['id'])->asArray();

    if(empty($data['segments'])) {
      return wp_send_json(array(
        'errors' => array(
            __("You need to select a list.")
          )
      ));
    }

    $segments = Segment::whereIdIn($data['segments'])->findMany();
    $subscribers = array();
    foreach($segments as $segment) {
      $segment_subscribers = $segment->subscribers()->findMany();
      foreach($segment_subscribers as $segment_subscriber) {
        $subscribers[$segment_subscriber->email] = $segment_subscriber
          ->asArray();
      }
    }

    if(empty($subscribers)) {
      return wp_send_json(array(
        'errors' => array(
            __("No subscribers found.")
          )
      ));
    }

    // TO REMOVE once we add the columns from/reply_to
    $newsletter = array_merge($newsletter, $data['newsletter']);
    // END - TO REMOVE

    $renderer = new Renderer(json_decode($newsletter['body'], true));
    $newsletter['body']['html'] = $renderer->renderAll();
    $newsletter['body']['text'] = '';

    $subscribers = Subscriber::find_array();
    $fromEmail = Setting::where('name', 'from_address')->findOne()->value;
    $fromName = Setting::where('name', 'from_name')->findOne()->value;
    $apiKey = Setting::where('name', 'api_key')->findOne()->value;
    $mailer = new MailPoet($apiKey, $fromEmail, $fromName);
	
    foreach ($subscribers as $subscriber) {
      $result = $mailer->send(
        $newsletter,
        sprintf('%s %s <%s>', $subscriber['first_name'], $subscriber['last_name'], $subscriber['email'])
      );
      if ($result !== true) wp_send_json(false);
    }
	
    wp_send_json(true);
  }

  function render($data = array()) {
    if(!isset($data['body'])) {
      wp_send_json(false);
    }
    $renderer = new Renderer(json_decode($data['body'], true));
    wp_send_json(array('rendered_body' => $renderer->renderAll()));
  }

  function listing($data = array()) {
    $listing = new Listing\Handler(
      \Model::factory('\MailPoet\Models\Newsletter'),
      $data
    );
    wp_send_json($listing->get());
  }

  function bulk_action($data = array()) {
    $bulk_action = new Listing\BulkAction(
      '\MailPoet\Models\Newsletter',
      $data
    );
    wp_send_json($bulk_action->apply());
  }

  function create($data = array()) {
    $newsletter = Newsletter::create();
    $newsletter->type = $data['type'];
    $newsletter->body = '{}';

    // try to load template data
    $template_id = (!empty($data['template']) ? (int)$data['template'] : 0);
    $template = NewsletterTemplate::findOne($template_id);
    if($template !== false) {
      $newsletter->body = $template->body;
    }

    $result = $newsletter->save();
    if($result !== true) {
      wp_send_json($newsletter->getValidationErrors());
    } else {
      wp_send_json(array(
        'url' => admin_url(
          'admin.php?page=mailpoet-newsletter-editor&id='.$newsletter->id()
        )
      ));
    }
  }
}
