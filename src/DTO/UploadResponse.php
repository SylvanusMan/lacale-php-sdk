<?php

declare(strict_types=1);

namespace LaCale\DTO;

/**
 * DTO représentant la réponse d'un upload de torrent
 */
readonly class UploadResponse
{
    public function __construct(
        public bool $success,
        public string $id,
        public string $slug,
        public string $link,
    ) {
    }

    /**
     * Crée une instance depuis un tableau de données API
     */
    public static function fromArray(array $data): self
    {
        return new self(
            success: (bool)($data['success'] ?? false),
            id: $data['id'] ?? '',
            slug: $data['slug'] ?? '',
            link: $data['link'] ?? '',
        );
    }

    /**
     * Convertit en tableau associatif
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'id' => $this->id,
            'slug' => $this->slug,
            'link' => $this->link,
        ];
    }
}
