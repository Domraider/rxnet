<?php
namespace Rxnet\Data\FormatExtensions;


use League\JsonGuard\ErrorCode;
use League\JsonGuard\FormatExtension;
use League\JsonGuard\ValidationError;

class DomainFormatExtension implements FormatExtension
{
    /**
     * @param string $value The value to validate
     * @param string|null $pointer A pointer to the value
     * @return ValidationError|null
     */
    public function validate($value, $pointer = null)
    {
        if (stripos($value, ".") === false) {
            return new ValidationError('A domain must have a tld', ErrorCode::INVALID_FORMAT, $value, $pointer);
        }
        if (starts_with($value, "xn--")) {
            return new ValidationError('A domain must not be in idn format', ErrorCode::INVALID_FORMAT, $value, $pointer);
        }
        // TODO validate no subdomains
    }
}