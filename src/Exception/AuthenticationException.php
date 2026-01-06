<?php

declare(strict_types=1);

namespace LaCale\Exception;

/**
 * Exception levée lors d'erreurs d'authentification (401)
 * Passkey invalide ou manquante
 */
class AuthenticationException extends LaCaleException
{
}
