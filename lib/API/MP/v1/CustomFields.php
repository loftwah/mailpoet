<?php declare(strict_types = 1);

namespace MailPoet\API\MP\v1;

use MailPoet\CustomFields\ApiDataSanitizer;
use MailPoet\CustomFields\CustomFieldsRepository;
use MailPoet\Models\CustomField;

class CustomFields {
  /** @var ApiDataSanitizer */
  private $customFieldsDataSanitizer;

  /** @var CustomFieldsRepository */
  private $customFieldsRepository;

  public function __construct(
    ApiDataSanitizer $customFieldsDataSanitizer,
    CustomFieldsRepository $customFieldsRepository
  ) {
    $this->customFieldsDataSanitizer = $customFieldsDataSanitizer;
    $this->customFieldsRepository = $customFieldsRepository;
  }

  public function getSubscriberFields(): array {
    $data = [
      [
        'id' => 'email',
        'name' => __('Email', 'mailpoet'),
        'type' => 'text',
        'params' => [
          'required' => '1',
        ],
      ],
      [
        'id' => 'first_name',
        'name' => __('First name', 'mailpoet'),
        'type' => 'text',
        'params' => [
          'required' => '',
        ],
      ],
      [
        'id' => 'last_name',
        'name' => __('Last name', 'mailpoet'),
        'type' => 'text',
        'params' => [
          'required' => '',
        ],
      ],
    ];

    $customFields = $this->customFieldsRepository->findAll();
    foreach ($customFields as $customField) {
      $result = [
        'id' => 'cf_' . $customField->getId(),
        'name' => $customField->getName(),
        'type' => $customField->getType(),
        'params' => $customField->getParams(),
      ];
      $data[] = $result;
    }

    return $data;
  }

  public function addSubscriberField(array $data = []): array {
    $customField = CustomField::createOrUpdate($this->customFieldsDataSanitizer->sanitize($data));
    $errors = $customField->getErrors();
    if (!empty($errors)) {
      throw new APIException('Failed to save a new subscriber field ' . join(', ', $errors), APIException::FAILED_TO_SAVE_SUBSCRIBER_FIELD);
    }
    $customField = CustomField::findOne($customField->id);
    if (!$customField instanceof CustomField) {
      throw new APIException('Failed to create a new subscriber field', APIException::FAILED_TO_SAVE_SUBSCRIBER_FIELD);
    }
    return $customField->asArray();
  }
}