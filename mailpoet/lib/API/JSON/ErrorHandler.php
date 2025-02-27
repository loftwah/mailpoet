<?php declare(strict_types = 1);

namespace MailPoet\API\JSON;

use MailPoet\Exception;
use MailPoet\HttpAwareException;
use MailPoet\WP\Functions as WPFunctions;

class ErrorHandler {
  /** @var string[] */
  private $defaultErrors;

  public function __construct(
    WPFunctions $wp
  ) {
    $this->defaultErrors = [
      Error::UNKNOWN => $wp->__('An unknown error occurred.', 'mailpoet'),
    ];
  }

  public function convertToResponse(\Throwable $e): ErrorResponse {
    if ($e instanceof Exception) {
      $errors = $e->getErrors() ?: $this->defaultErrors;
      $statusCode = $e instanceof HttpAwareException ? $e->getHttpStatusCode() : Response::STATUS_UNKNOWN;
      return new ErrorResponse($errors, [], $statusCode);
    }
    return new ErrorResponse($this->defaultErrors, [], Response::STATUS_UNKNOWN);
  }
}
