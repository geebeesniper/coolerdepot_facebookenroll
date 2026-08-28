<?php
namespace App\Services;

class ProviderValidationException extends \RuntimeException
{
    private string $field;

    public function __construct(string $field, string $message)
    {
        parent::__construct($message);
        $this->field = $field;
    }

    public function field(): string
    {
        return $this->field;
    }
}
