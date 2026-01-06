<?php

declare(strict_types=1);

namespace LaCale\DTO;

/**
 * DTO représentant les métadonnées complètes de l'API
 */
readonly class Metadata
{
    /**
     * @param Category[] $categories
     * @param TagGroup[] $tagGroups
     * @param Tag[] $ungroupedTags
     */
    public function __construct(
        public array $categories,
        public array $tagGroups,
        public array $ungroupedTags,
    ) {
    }

    /**
     * Crée une instance depuis un tableau de données API
     */
    public static function fromArray(array $data): self
    {
        $categories = [];
        if (isset($data['categories']) && is_array($data['categories'])) {
            foreach ($data['categories'] as $category) {
                $categories[] = Category::fromArray($category);
            }
        }

        $tagGroups = [];
        if (isset($data['tagGroups']) && is_array($data['tagGroups'])) {
            foreach ($data['tagGroups'] as $group) {
                $tagGroups[] = TagGroup::fromArray($group);
            }
        }

        $ungroupedTags = [];
        if (isset($data['ungroupedTags']) && is_array($data['ungroupedTags'])) {
            foreach ($data['ungroupedTags'] as $tag) {
                $ungroupedTags[] = Tag::fromArray($tag);
            }
        }

        return new self(
            categories: $categories,
            tagGroups: $tagGroups,
            ungroupedTags: $ungroupedTags,
        );
    }

    /**
     * Trouve une catégorie par son slug (recherche récursive)
     */
    public function findCategoryBySlug(string $slug): ?Category
    {
        foreach ($this->categories as $category) {
            if ($category->slug === $slug) {
                return $category;
            }
            
            // Recherche dans les enfants
            foreach ($category->flatten() as $flatCategory) {
                if ($flatCategory->slug === $slug) {
                    return $flatCategory;
                }
            }
        }
        
        return null;
    }

    /**
     * Trouve une catégorie par son ID
     */
    public function findCategoryById(string $id): ?Category
    {
        foreach ($this->categories as $category) {
            foreach ($category->flatten() as $flatCategory) {
                if ($flatCategory->id === $id) {
                    return $flatCategory;
                }
            }
        }
        
        return null;
    }

    /**
     * Trouve un tag par son slug (dans tous les groupes et tags non groupés)
     */
    public function findTagBySlug(string $slug): ?Tag
    {
        // Recherche dans les groupes
        foreach ($this->tagGroups as $tagGroup) {
            $tag = $tagGroup->findTagBySlug($slug);
            if ($tag !== null) {
                return $tag;
            }
        }

        // Recherche dans les tags non groupés
        foreach ($this->ungroupedTags as $ungroupedTag) {
            if ($ungroupedTag->slug === $slug) {
                return $ungroupedTag;
            }
        }

        return null;
    }

    /**
     * Récupère tous les tags (groupés et non groupés)
     * @return Tag[]
     */
    public function getAllTags(): array
    {
        $tags = [];
        foreach ($this->tagGroups as $tagGroup) {
            $tags = array_merge($tags, $tagGroup->tags);
        }
        
        return array_merge($tags, $this->ungroupedTags);
    }
}
