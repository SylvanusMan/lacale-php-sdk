<?php

declare(strict_types=1);

namespace LaCale\Exception;

/**
 * Exception levée lors du dépassement de la limite de requêtes (429)
 * Rate limiting par IP et par passkey
 */
class RateLimitException extends LaCaleException
{
    private ?int $retryAfter = null;

    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }

    public function setRetryAfter(?int $retryAfter): self
    {
        $this->retryAfter = $retryAfter;
        return $this;
    }
}
