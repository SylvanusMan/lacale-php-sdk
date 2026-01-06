<?php

declare(strict_types=1);

namespace LaCale\DTO;

/**
 * DTO représentant un groupe de tags
 */
readonly class TagGroup
{
    /**
     * @param Tag[] $tags
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
        public int $order,
        public array $tags,
    ) {
    }

    /**
     * Crée une instance depuis un tableau de données API
     */
    public static function fromArray(array $data): self
    {
        $tags = [];
        if (isset($data['tags']) && is_array($data['tags'])) {
            foreach ($data['tags'] as $tag) {
                $tags[] = Tag::fromArray($tag);
            }
        }

        return new self(
            id: $data['id'] ?? '',
            name: $data['name'] ?? '',
            slug: $data['slug'] ?? '',
            order: (int)($data['order'] ?? 0),
            tags: $tags,
        );
    }

    /**
     * Trouve un tag par son slug
     */
    public function findTagBySlug(string $slug): ?Tag
    {
        foreach ($this->tags as $tag) {
            if ($tag->slug === $slug) {
                return $tag;
            }
        }
        
        return null;
    }

    /**
     * Trouve un tag par son ID
     */
    public function findTagById(string $id): ?Tag
    {
        foreach ($this->tags as $tag) {
            if ($tag->id === $id) {
                return $tag;
            }
        }
        
        return null;
    }
}
