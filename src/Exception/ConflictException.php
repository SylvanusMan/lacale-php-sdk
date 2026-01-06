<?php

declare(strict_types=1);

namespace LaCale\Exception;

/**
 * Exception levée lors de conflits (409)
 * Par exemple: torrent déjà existant
 */
class ConflictException extends LaCaleException
{
}
