<?php

declare(strict_types=1);

namespace LaCale\DTO;

/**
 * DTO représentant une catégorie ou sous-catégorie
 */
readonly class Category
{
    /**
     * @param Category[] $children
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
        public ?string $icon = null,
        public ?string $parentId = null,
        public array $children = [],
    ) {
    }

    /**
     * Crée une instance depuis un tableau de données API
     */
    public static function fromArray(array $data): self
    {
        $children = [];
        if (isset($data['children']) && is_array($data['children'])) {
            foreach ($data['children'] as $child) {
                $children[] = self::fromArray($child);
            }
        }

        return new self(
            id: $data['id'] ?? '',
            name: $data['name'] ?? '',
            slug: $data['slug'] ?? '',
            icon: $data['icon'] ?? null,
            parentId: $data['parentId'] ?? null,
            children: $children,
        );
    }

    /**
     * Vérifie si la catégorie a des enfants
     */
    public function hasChildren(): bool
    {
        return $this->children !== [];
    }

    /**
     * Récupère toutes les catégories (incluant les enfants) sous forme de liste plate
     * @return Category[]
     */
    public function flatten(): array
    {
        $result = [$this];
        foreach ($this->children as $child) {
            $result = array_merge($result, $child->flatten());
        }
        
        return $result;
    }
}
