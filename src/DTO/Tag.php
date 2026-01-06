<?php

declare(strict_types=1);

namespace LaCale\DTO;

/**
 * DTO représentant un tag
 */
readonly class Tag
{
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
    ) {
    }

    /**
     * Crée une instance depuis un tableau de données API
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? '',
            name: $data['name'] ?? '',
            slug: $data['slug'] ?? '',
        );
    }
}
