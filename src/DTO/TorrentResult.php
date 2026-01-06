<?php

declare(strict_types=1);

namespace LaCale\DTO;

use DateTimeImmutable;

/**
 * DTO représentant un résultat de recherche de torrent
 */
readonly class TorrentResult
{
    public function __construct(
        public string $title,
        public string $guid,
        public int $size,
        public DateTimeImmutable $pubDate,
        public string $link,
        public string $category,
        public int $seeders,
        public int $leechers,
        public string $infoHash,
    ) {
    }

    /**
     * Crée une instance depuis un tableau de données API
     */
    public static function fromArray(array $data): self
    {
        return new self(
            title: $data['title'] ?? '',
            guid: $data['guid'] ?? '',
            size: (int)($data['size'] ?? 0),
            pubDate: new DateTimeImmutable($data['pubDate'] ?? 'now'),
            link: $data['link'] ?? '',
            category: $data['category'] ?? '',
            seeders: (int)($data['seeders'] ?? 0),
            leechers: (int)($data['leechers'] ?? 0),
            infoHash: $data['infoHash'] ?? '',
        );
    }

    /**
     * Convertit en tableau associatif
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'guid' => $this->guid,
            'size' => $this->size,
            'pubDate' => $this->pubDate->format(DateTimeImmutable::ATOM),
            'link' => $this->link,
            'category' => $this->category,
            'seeders' => $this->seeders,
            'leechers' => $this->leechers,
            'infoHash' => $this->infoHash,
        ];
    }

    /**
     * Retourne la taille formatée en unités lisibles
     */
    public function getFormattedSize(): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $size = $this->size;
        $unitIndex = 0;

        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }

        return sprintf('%.2f %s', $size, $units[$unitIndex]);
    }
}
