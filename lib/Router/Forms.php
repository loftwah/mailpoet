<?php
namespace MailPoet\Router;
use \MailPoet\Models\Form;
use \MailPoet\Form\Renderer as FormRenderer;
use \MailPoet\Listing;
use \MailPoet\Form\Util;

if(!defined('ABSPATH')) exit;

class Forms {
  function __construct() {
  }

  function get($id = false) {
    $form = Form::findOne($id);
    if($form === false) {
      return false;
    } else {
      return $form->asArray();
    }
  }

  function listing($data = array()) {
    $listing = new Listing\Handler(
      '\MailPoet\Models\Form',
      $data
    );

    $listing_data = $listing->get();

    // fetch segments relations for each returned item
    foreach($listing_data['items'] as &$item) {
      // form's segments
      $form_settings = (
        (is_serialized($item['settings']))
        ? unserialize($item['settings'])
        : array()
      );

      $item['segments'] = (
        !empty($form_settings['segments'])
        ? $form_settings['segments']
        : array()
      );
    }

    return $listing_data;
  }

  function getAll() {
    return Form::findArray();
  }

  function create() {
    // create new form
    $form_data = array(
      'name' => __('New form'),
      'body' => array(
        array(
          'id' => 'email',
          'name' => __('Email'),
          'type' => 'text',
          'static' => true,
          'params' => array(
            'label' => __('Email'),
            'required' => true
          )
        ),
        array(
          'id' => 'submit',
          'name' => __('Submit'),
          'type' => 'submit',
          'static' => true,
          'params' => array(
            'label' => __('Subscribe!')
          )
        )
      ),
      'settings' => array(
        'on_success' => 'message',
        'success_message' => __('Check your inbox or spam folder now to confirm your subscription.'),
        'segments' => null,
        'segments_selected_by' => 'admin'
      )
    );

    return $this->save($form_data);
  }

  function save($data = array()) {
    $form = Form::createOrUpdate($data);

    if($form !== false && $form->id()) {
      return array(
        'result' => true,
        'form_id' => $form->id()
      );
    } else {
      return array(
        'result' => false,
        'errors' => $form->getValidationErrors()
      );
    }
  }

  function previewEditor($data = array()) {
    // html
    $html = FormRenderer::renderHTML($data);

    // convert shortcodes
    $html = do_shortcode($html);

    // styles
    $css = new Util\Styles(FormRenderer::getStyles($data));

    return array(
      'html' => $html,
      'css' => $css->render()
    );
  }

  function exportsEditor($id) {
    $exports = false;

    $form = Form::findOne($id);

    if($form !== false) {
      $exports = Util\Export::getAll($form->asArray());
    }

    return $exports;
  }

  function saveEditor($data = array()) {
    $form_id = (isset($data['id']) ? (int)$data['id'] : 0);
    $name = (isset($data['name']) ? $data['name'] : __('New form'));
    $body = (isset($data['body']) ? $data['body'] : array());
    $settings = (isset($data['settings']) ? $data['settings'] : array());
    $styles = (isset($data['styles']) ? $data['styles'] : '');

    if(empty($body) || empty($settings)) {
      // error
      return false;
    } else {
      // check if the form is used as a widget
      $is_widget = false;
      $widgets = get_option('widget_mailpoet_form');
      if(!empty($widgets)) {
        foreach($widgets as $widget) {
          if(isset($widget['form']) && (int)$widget['form'] === $form_id) {
            $is_widget = true;
            break;
          }
        }
      }

      // check if the user gets to pick his own lists
      // or if it's selected by the admin
      $has_segment_selection = false;

      foreach ($body as $i => $block) {
        if($block['type'] === 'segment') {
          $has_segment_selection = true;
          if(!empty($block['params']['values'])) {
            $list_selection = array_map(function($segment) {
              if(!empty($segment)) {
                return (int)$segment['id'];
              }
            }, $block['params']['values']);
          }
          break;
        }
      }

      // check list selection
      if($has_segment_selection === true) {
        $settings['segments_selected_by'] = 'user';
      } else {
        $settings['segments_selected_by'] = 'admin';
      }
    }

    $form = Form::createOrUpdate(array(
      'id' => $form_id,
      'name' => $name,
      'body' => $body,
      'settings' => $settings,
      'styles' => $styles
    ));

    return array(
      'result' => ($form !== false),
      'is_widget' => $is_widget
    );
  }

  function restore($id) {
    $result = false;

    $form = Form::findOne($id);
    if($form !== false) {
      $result = $form->restore();
    }

    return $result;
  }

  function trash($id) {
    $result = false;

    $form = Form::findOne($id);
    if($form !== false) {
      $result = $form->trash();
    }

    return $result;
  }

  function delete($id) {
    $result = false;

    $form = Form::findOne($id);
    if($form !== false) {
      $form->delete();
      $result = 1;
    }

    return $result;
  }

  function duplicate($id) {
    $result = false;

    $form = Form::findOne($id);
    if($form !== false) {
      $data = array(
        'name' => sprintf(__('Copy of %s'), $form->name)
      );
      $result = $form->duplicate($data)->asArray();
    }

    return $result;
  }

  function bulkAction($data = array()) {
    $bulk_action = new Listing\BulkAction(
      '\MailPoet\Models\Form',
      $data
    );

    return $bulk_action->apply();
  }
}
