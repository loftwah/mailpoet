<?php
namespace MailPoet\Import;

use MailPoet\Models\CustomField;
use MailPoet\Models\Segment;
use MailPoet\Util\Helpers;

class BootstrapMenu {
  
  function __construct() {
    $this->subscriberFields = $this->getSubscriberFields();
    $this->subscriberCustomFields = $this->getSubscriberCustomFields();
    $this->segments = $this->getSegments();
  }
  
  function getSubscriberFields() {
    return array(
      'email' => __('Email'),
      'first_name' => __('First name'),
      'last_name' => __('Last name'),
      'status' => __('Status')
      /*    'confirmed_ip' => __('IP address')
            'confirmed_at' => __('Subscription date')*/
    );
  }
  
  function getSegments() {
    return array_map(function ($segment) {
      return array(
        'id' => $segment['id'],
        'name' => $segment['name'],
        'subscriberCount' => $segment['subscribers']
      );
    }, Segment::filter('filterWithSubscriberCount')
         ->findArray());
  }
  
  function getSubscriberCustomFields() {
    return CustomField::findArray();
  }
  
  function formatSubscriberFields($subscriberFields) {
    return array_map(function ($fieldId, $fieldName) {
      return array(
        'id' => $fieldId,
        'name' => $fieldName,
        'type' => ($fieldId === 'confirmed_at') ? 'date' : null,
        'custom' => false
      );
    }, array_keys($subscriberFields), $subscriberFields);
  }
  
  function formatSubscriberCustomFields($subscriberCustomFields) {
    return array_map(function ($field) {
      return array(
        'id' => $field['id'],
        'name' => $field['name'],
        'type' => $field['type'],
        'custom' => true
      );
    }, $subscriberCustomFields);
  }
  
  function formatFieldsForSelect2(
    $subscriberFields,
    $subscriberCustomFields) {
    $select2Fields = array(
      array(
        'name' => __('Actions'),
        'children' => array(
          array(
            'id' => 'ignore',
            'name' => __('Ignore column...'),
          ),
          array(
            'id' => 'create',
            'name' => __('Create new column...')
          ),
        )
      ),
      array(
        'name' => __('System columns'),
        'children' => $this->formatSubscriberFields($subscriberFields)
      )
    );
    if($this->subscriberCustomFields) {
      array_push($select2Fields, array(
        'name' => __('User columns'),
        'children' => $this->formatSubscriberCustomFields(
          $subscriberCustomFields
        )
      ));
    }
    return $select2Fields;
  }
  
  function bootstrap() {
    $data['segments'] = $this->segments;
    $data['subscriberFields'] = array_merge(
      $this->formatSubscriberFields($this->subscriberFields),
      $this->formatSubscriberCustomFields($this->subscriberCustomFields)
    );
    $data['subscriberFieldsSelect2'] = $this->formatFieldsForSelect2(
      $this->subscriberFields,
      $this->subscriberCustomFields
    );
    $data = array_map('json_encode', $data);
    $data['maxPostSizeBytes'] = Helpers::getMaxPostSize('bytes');
    $data['maxPostSize'] = Helpers::getMaxPostSize();
    return $data;
  }
}