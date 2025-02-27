<?php

namespace MailPoet\Segments\DynamicSegments\Exceptions;

use MailPoet\InvalidStateException;

class InvalidFilterException extends InvalidStateException {
  const MISSING_TYPE = 1;
  const INVALID_TYPE = 2;
  const MISSING_ROLE = 3;
  const MISSING_ACTION = 4;
  const MISSING_NEWSLETTER_ID = 5;
  const MISSING_CATEGORY_ID = 6;
  const MISSING_PRODUCT_ID = 7;
  const INVALID_EMAIL_ACTION = 8;
  const MISSING_VALUE = 9;
  const MISSING_NUMBER_OF_ORDERS_FIELDS = 10;
  const MISSING_TOTAL_SPENT_FIELDS = 11;
  const INVALID_DATE_VALUE = 12;
  const MISSING_COUNTRY = 13;
  const MISSING_FILTER = 14;
  const MISSING_OPERATOR = 15;
};
