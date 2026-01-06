<?php

declare(strict_types=1);

namespace LaCale\Exception;

/**
 * Exception levée lors d'erreurs de validation (422)
 * Données invalides ou malformées
 */
class ValidationException extends LaCaleException
{
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null, private readonly array $errors = [])
    {
        parent::__construct($message, $code, $previous);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
