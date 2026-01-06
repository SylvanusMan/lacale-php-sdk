# La Cale API SDK (PHP)

![PHP Version](https://img.shields.io/badge/php-%5E8.4-blue)
![License](https://img.shields.io/badge/license-MIT-green)
![Version](https://img.shields.io/badge/version-2.0-orange)

Une librairie PHP **moderne, typée et robuste** pour interagir avec l'API de **La Cale** (la-cale.space). Ce SDK facilite l'intégration des fonctionnalités de recherche, de récupération de métadonnées et d'upload automatique dans vos projets PHP.

## ✨ Nouveautés v2.0

* ⚡ **Symfony HttpClient** : Remplacement de Guzzle par Symfony HttpClient (plus léger et performant)
* 🎯 **DTOs typés** : Objets de transfert de données immutables avec méthodes utilitaires
* 🛡️ **Exceptions spécifiques** : Hiérarchie d'exceptions pour une gestion d'erreur précise
* ✅ **Validation stricte** : Validation complète des paramètres selon la documentation API
* 📐 **Architecture moderne** : `declare(strict_types=1)`, classes finales, readonly properties
* 🔒 **Sécurité renforcée** : Gestion du rate limiting avec Retry-After header
* 📊 **Méthodes utilitaires** : Recherche dans les métadonnées, formatage de taille, etc.

## 🚀 Fonctionnalités

* **Recherche avancée** : Filtrage par termes et multi-catégories (compatible Prowlarr, Jackett)
* **Métadonnées** : Récupération des arbres de catégories, tags et groupes de tags
* **Upload** : Envoi simplifié de fichiers `.torrent` avec validation complète
* **Gestion d'erreurs** : Exceptions typées pour chaque cas d'erreur HTTP
* **Cache serveur** : Respect du cache serveur (~30s sur les métadonnées)
* **Rate limiting** : Gestion automatique du header Retry-After (429)

## 📋 Prérequis

- PHP 8.4 ou supérieur
- Composer
- Extensions PHP : `mbstring`, `json`
- Une clé API (passkey) valide de La Cale

## 📦 Installation

Installez la librairie via [Composer](https://getcomposer.org/) :

```bash
composer require sylvanusman/lacale-php-sdk
```

## 🔧 Configuration

### Initialisation du client

```php
<?php

declare(strict_types=1);

require 'vendor/autoload.php';

use LaCale\Client;
use LaCale\Exception\LaCaleException;

// Créez une instance du client avec votre passkey
$client = new Client('votre_passkey_ici');

// Configuration avancée
$client = new Client(
    passkey: 'votre_passkey_ici',
    baseUrl: 'https://la-cale.space', // Optionnel
    timeout: 30  // Timeout en secondes (optionnel)
);

// Avec un HttpClient personnalisé (pour tests ou configuration avancée)
use Symfony\Component\HttpClient\HttpClient;

$httpClient = HttpClient::create(['verify_peer' => false]);
$client = new Client(
    passkey: 'votre_passkey_ici',
    httpClient: $httpClient
);
```

## 📚 Utilisation

### 1. Recherche de torrents

Recherchez des torrents par terme et/ou catégories (retourne des objets `TorrentResult` typés) :

```php
use LaCale\Exception\ValidationException;
use LaCale\Exception\NetworkException;

try {
    // Recherche simple
    $results = $client->search('Matrix');

    // Recherche avec catégories (slugs)
    $results = $client->search('Matrix', ['films', 'series']);

    // Recherche sans terme (toutes les catégories spécifiées)
    $results = $client->search(null, ['films']);

    // Les résultats sont des objets TorrentResult
    foreach ($results as $torrent) {
        echo "Titre: " . $torrent->title . "\n";
        echo "InfoHash: " . $torrent->infoHash . "\n";
        echo "Taille: " . $torrent->getFormattedSize() . "\n"; // Ex: "2.5 GB"
        echo "Seeders: " . $torrent->seeders . "\n";
        echo "Date: " . $torrent->pubDate->format('Y-m-d H:i:s') . "\n";
        echo "Catégorie: " . $torrent->category . "\n";
        echo "Lien: " . $torrent->link . "\n";
        echo "---\n";
    }
} catch (ValidationException $e) {
    // Paramètres invalides (query trop longue, etc.)
    echo "Validation: " . $e->getMessage();
} catch (NetworkException $e) {
    // Problème réseau
    echo "Réseau: " . $e->getMessage();
} catch (LaCaleException $e) {
    // Autre erreur
    echo "Erreur: " . $e->getMessage();
}
```

### 2. Récupération des métadonnées

Obtenez la liste des catégories, tags et groupes de tags disponibles (retourne un objet `Metadata` typé) :

```php
try {
    $metadata = $client->getMetadata();

    // Catégories avec hiérarchie
    echo "=== CATÉGORIES ===\n";
    foreach ($metadata->categories as $category) {
        echo "ID: {$category->id} | Slug: {$category->slug} | Nom: {$category->name}\n";

        // Sous-catégories
        foreach ($category->children as $child) {
            echo "  ↳ {$child->id} | {$child->slug} | {$child->name}\n";
        }
    }

    // Groupes de tags
    echo "\n=== GROUPES DE TAGS ===\n";
    foreach ($metadata->tagGroups as $group) {
        echo "Groupe: {$group->name} (ordre: {$group->order})\n";
        foreach ($group->tags as $tag) {
            echo "  - {$tag->id}: {$tag->name} ({$tag->slug})\n";
        }
    }

    // Tags non groupés
    echo "\n=== TAGS NON GROUPÉS ===\n";
    foreach ($metadata->ungroupedTags as $tag) {
        echo "{$tag->id}: {$tag->name}\n";
    }

    // Méthodes utilitaires
    $filmsCategory = $metadata->findCategoryBySlug('films');
    if ($filmsCategory) {
        echo "\nCatégorie 'films': ID = {$filmsCategory->id}\n";
    }

    $tag1080p = $metadata->findTagBySlug('1080p');
    if ($tag1080p) {
        echo "Tag '1080p': ID = {$tag1080p->id}\n";
    }

} catch (LaCaleException $e) {
    echo "Erreur: " . $e->getMessage();
}
```

### 3. Upload d'un torrent

Uploadez un fichier `.torrent` avec métadonnées (retourne un objet `UploadResponse`) :

```php
use LaCale\Config\ApiConfig;
use LaCale\Exception\RateLimitException;
use LaCale\Exception\ConflictException;

try {
    // Upload simple
    $response = $client->upload(
        title: 'Matrix Reloaded 2003 FRENCH BluRay 1080p',
        categoryId: 'cat_films', // ID obtenu via getMetadata()
        torrentFilePath: '/path/to/torrent.torrent',
        tagIds: ['tag_1080p', 'tag_french', 'tag_bluray']
    );

    // Upload avec toutes les options
    $response = $client->upload(
        title: 'Matrix Reloaded 2003 FRENCH BluRay 1080p',
        categoryId: 'cat_films',
        torrentFilePath: '/path/to/torrent.torrent',
        tagIds: ['tag_1080p', 'tag_french'],
        options: [
            'description' => 'Description détaillée du torrent',
            'tmdbId' => '604',
            'tmdbType' => ApiConfig::TMDB_TYPE_MOVIE, // ou ApiConfig::TMDB_TYPE_TV
            'coverUrl' => 'https://example.com/cover.jpg',
            'nfoFilePath' => '/path/to/info.nfo'
        ]
    );

    // L'objet UploadResponse contient les informations
    if ($response->success) {
        echo "✓ Upload réussi !\n";
        echo "ID: {$response->id}\n";
        echo "Slug: {$response->slug}\n";
        echo "Lien: {$response->link}\n";
    }

} catch (ValidationException $e) {
    // Données invalides (fichier introuvable, URL invalide, etc.)
    echo "Validation: " . $e->getMessage();
    if ($errors = $e->getErrors()) {
        print_r($errors);
    }
} catch (RateLimitException $e) {
    // Rate limit atteint (30/minute)
    echo "Rate limit: " . $e->getMessage();
    if ($retryAfter = $e->getRetryAfter()) {
        echo "Réessayer dans {$retryAfter} secondes\n";
    }
} catch (ConflictException $e) {
    // Torrent déjà existant
    echo "Conflit: " . $e->getMessage();
} catch (LaCaleException $e) {
    echo "Erreur: " . $e->getMessage();
}
```

### 4. Génération de lien de téléchargement

Générez l'URL de téléchargement d'un torrent :

```php
$infoHash = 'abc123def456...';
$downloadUrl = $client->getDownloadLink($infoHash);

echo "Télécharger le torrent: " . $downloadUrl;
// Résultat: https://la-cale.space/api/torrents/download/abc123def456...?passkey=votre_passkey
```

## 🔍 Structure de réponse

### Réponse de recherche

```php
[
    [
        'title' => 'Nom du torrent',
        'info_hash' => 'hash_du_torrent',
        'size' => 1234567890, // Taille en octets
        'seeders' => 10,
        'leechers' => 2,
        'download_url' => 'https://...',
        'category' => 'films',
        'tags' => ['1080p', 'FRENCH']
    ],
    // ...
]
```

### Réponse de métadonnées

```php
[
    'categories' => [
        ['id' => '1', 'slug' => 'films', 'name' => 'Films'],
        // ...
    ],
    'tags' => [
        ['id' => '5', 'name' => '1080p'],
        // ...
    ],
    'tag_groups' => [
        ['name' => 'Qualité', 'tags' => [...]],
        // ...
    ]
]
```

### Réponse d'upload

```php
[
    'success' => true,
    'id' => 123,
    'slug' => 'matrix-reloaded-2003',
    'link' => 'https://la-cale.space/torrents/123-matrix-reloaded-2003'
]
```

## ⚠️ Gestion des erreurs

Le SDK v2.0 utilise une **hiérarchie d'exceptions** pour une gestion précise des erreurs :

```php
use LaCale\Exception\{
    LaCaleException,
    AuthenticationException,
    ValidationException,
    RateLimitException,
    ConflictException,
    NotFoundException,
    ServerException,
    NetworkException
};

try {
    $results = $client->search('test');

} catch (AuthenticationException $e) {
    // 401: Passkey invalide
    echo "Authentification: " . $e->getMessage();

} catch (ValidationException $e) {
    // 422: Données invalides
    echo "Validation: " . $e->getMessage();
    // Récupération des erreurs détaillées
    $errors = $e->getErrors();

} catch (RateLimitException $e) {
    // 429: Limite de requêtes atteinte
    echo "Rate limit: " . $e->getMessage();
    // Récupération du délai d'attente
    if ($retryAfter = $e->getRetryAfter()) {
        echo "Réessayer dans {$retryAfter} secondes";
    }

} catch (ConflictException $e) {
    // 409: Conflit (torrent existant, etc.)
    echo "Conflit: " . $e->getMessage();

} catch (NotFoundException $e) {
    // 404: Ressource non trouvée
    echo "Non trouvé: " . $e->getMessage();

} catch (ServerException $e) {
    // 5xx: Erreur serveur
    echo "Serveur: " . $e->getMessage();

} catch (NetworkException $e) {
    // Erreur réseau (timeout, connexion impossible)
    echo "Réseau: " . $e->getMessage();

} catch (LaCaleException $e) {
    // Toutes les autres erreurs
    echo "Erreur: " . $e->getMessage();
    echo "Code HTTP: " . $e->getCode();
}
```

### Hiérarchie des exceptions

```
LaCaleException (classe de base)
├── AuthenticationException (401)
├── NotFoundException (404)
├── ConflictException (409)
├── ValidationException (422)
├── RateLimitException (429)
├── ServerException (5xx)
└── NetworkException (réseau)
```

### Codes d'erreur HTTP

| Code | Exception | Description |
|------|-----------|-------------|
| 401  | `AuthenticationException` | Passkey invalide ou manquante |
| 404  | `NotFoundException` | Ressource non trouvée |
| 409  | `ConflictException` | Conflit (torrent déjà existant) |
| 422  | `ValidationException` | Données invalides ou malformées |
| 429  | `RateLimitException` | Limite de requêtes atteinte (30/min pour upload) |
| 500+ | `ServerException` | Erreur serveur |
| N/A  | `NetworkException` | Timeout, connexion impossible |

## 🛠️ Exemples avancés

### Workflow complet d'upload avec métadonnées

```php
<?php

declare(strict_types=1);

require 'vendor/autoload.php';

use LaCale\Client;
use LaCale\Config\ApiConfig;
use LaCale\Exception\LaCaleException;

$client = new Client('votre_passkey');

try {
    // 1. Récupérer les métadonnées (cache serveur ~30s)
    $metadata = $client->getMetadata();

    // 2. Trouver la catégorie "Films" avec la méthode utilitaire
    $filmsCategory = $metadata->findCategoryBySlug('films');
    if (!$filmsCategory) {
        throw new \RuntimeException('Catégorie "films" non trouvée');
    }

    // 3. Trouver les tags souhaités
    $tagIds = [];
    $desiredTags = ['1080p', 'french', 'bluray'];

    foreach ($desiredTags as $tagSlug) {
        $tag = $metadata->findTagBySlug($tagSlug);
        if ($tag) {
            $tagIds[] = $tag->id;
        }
    }

    echo "Catégorie: {$filmsCategory->name} (ID: {$filmsCategory->id})\n";
    echo "Tags trouvés: " . count($tagIds) . "\n";

    // 4. Uploader le torrent avec validation complète
    $response = $client->upload(
        title: 'Mon Film 2024 FRENCH BluRay 1080p',
        categoryId: $filmsCategory->id,
        torrentFilePath: '/path/to/film.torrent',
        tagIds: $tagIds,
        options: [
            'description' => 'Un excellent film de 2024 !',
            'tmdbId' => '12345',
            'tmdbType' => ApiConfig::TMDB_TYPE_MOVIE,
            'coverUrl' => 'https://image.tmdb.org/t/p/original/poster.jpg'
        ]
    );

    if ($response->success) {
        echo "✓ Torrent uploadé avec succès !\n";
        echo "  ID: {$response->id}\n";
        echo "  Slug: {$response->slug}\n";
        echo "  URL: {$response->link}\n";
    }

} catch (LaCaleException $e) {
    echo "✗ Erreur: " . $e->getMessage() . " (Code: " . $e->getCode() . ")\n";
}
```

### Gestion du rate limiting avec retry

```php
use LaCale\Exception\RateLimitException;

function uploadWithRetry(Client $client, ...$params): void
{
    $maxRetries = 3;
    $attempt = 0;

    while ($attempt < $maxRetries) {
        try {
            $response = $client->upload(...$params);
            echo "✓ Upload réussi: {$response->link}\n";
            return;

        } catch (RateLimitException $e) {
            $attempt++;
            $retryAfter = $e->getRetryAfter() ?? 60;

            if ($attempt >= $maxRetries) {
                throw $e;
            }

            echo "⏳ Rate limit atteint. Attente de {$retryAfter}s... (tentative {$attempt}/{$maxRetries})\n";
            sleep($retryAfter);
        }
    }
}

// Utilisation
try {
    uploadWithRetry(
        $client,
        title: 'Mon Film',
        categoryId: 'cat_films',
        torrentFilePath: '/path/to/file.torrent',
        tagIds: ['tag_1080p']
    );
} catch (LaCaleException $e) {
    echo "✗ Échec après {$maxRetries} tentatives: {$e->getMessage()}\n";
}
```

### Conversion des résultats en tableau

```php
// Les DTOs readonly peuvent être convertis en tableaux
$results = $client->search('Matrix');

foreach ($results as $torrent) {
    $array = $torrent->toArray();
    // ['title' => '...', 'size' => 123, 'pubDate' => '2025-01-01T00:00:00+00:00', ...]

    // Utilisation avec json_encode
    echo json_encode($array, JSON_PRETTY_PRINT);
}
```

## 📐 Architecture du SDK

```
src/
├── Client.php                 # Client principal de l'API
├── Config/
│   └── ApiConfig.php         # Constantes et configuration
├── DTO/                      # Data Transfer Objects (readonly)
│   ├── Category.php          # Catégorie avec hiérarchie
│   ├── Tag.php               # Tag simple
│   ├── TagGroup.php          # Groupe de tags
│   ├── Metadata.php          # Métadonnées complètes (avec méthodes utilitaires)
│   ├── TorrentResult.php     # Résultat de recherche (avec formatage)
│   └── UploadResponse.php    # Réponse d'upload
└── Exception/                # Hiérarchie d'exceptions
    ├── LaCaleException.php           # Exception de base
    ├── AuthenticationException.php   # 401
    ├── NotFoundException.php         # 404
    ├── ConflictException.php         # 409
    ├── ValidationException.php       # 422 (avec détails)
    ├── RateLimitException.php        # 429 (avec Retry-After)
    ├── ServerException.php           # 5xx
    └── NetworkException.php          # Réseau
```

## 🔒 Bonnes pratiques

### Sécurité de la passkey

```php
// ❌ Ne JAMAIS hardcoder la passkey
$client = new Client('ma_passkey_en_dur');

// ✅ Utiliser des variables d'environnement
$client = new Client($_ENV['LACALE_PASSKEY']);

// ✅ Ou un fichier de configuration sécurisé
$config = parse_ini_file('/secure/path/config.ini');
$client = new Client($config['lacale_passkey']);
```

### Validation avant upload

```php
// Valider les fichiers avant l'upload
$torrentPath = '/path/to/file.torrent';

if (!file_exists($torrentPath)) {
    throw new \RuntimeException("Fichier introuvable");
}

if (filesize($torrentPath) === 0) {
    throw new \RuntimeException("Fichier vide");
}

// Vérifier l'extension
if (pathinfo($torrentPath, PATHINFO_EXTENSION) !== 'torrent') {
    throw new \RuntimeException("Extension invalide");
}
```

### Rate limiting proactif

```php
use LaCale\Config\ApiConfig;

// Respecter la limite de 30 uploads/minute
$uploadCount = 0;
$startTime = time();

foreach ($torrents as $torrent) {
    if ($uploadCount >= ApiConfig::UPLOAD_RATE_LIMIT_PER_MINUTE) {
        $elapsed = time() - $startTime;
        if ($elapsed < 60) {
            sleep(60 - $elapsed);
        }
        $uploadCount = 0;
        $startTime = time();
    }

    try {
        $client->upload(...);
        $uploadCount++;
    } catch (RateLimitException $e) {
        // Gestion de l'exception
    }
}
```

## 🧪 Tests

```bash
# Installation des dépendances de développement
composer install --dev

# Analyse statique avec PHPStan
composer phpstan

# Tests unitaires (à venir)
composer test
```

## 📝 Contribution

Les contributions sont les bienvenues ! Pour contribuer :

1. Forkez le projet
2. Créez une branche (`git checkout -b feature/amelioration`)
3. Committez vos changements (`git commit -m 'Ajout d'une fonctionnalité'`)
4. Pushez vers la branche (`git push origin feature/amelioration`)
5. Ouvrez une Pull Request

## 📄 Licence

Ce projet est sous licence MIT. Voir le fichier `LICENSE` pour plus de détails.

## 🔗 Liens utiles

- [La Cale](https://la-cale.space)
- [Documentation API La Cale](https://la-cale.space/api/docs)
- [Symfony HttpClient](https://symfony.com/doc/current/http_client.html)
- [PHP 8.4 Release Notes](https://www.php.net/releases/8.4/)

## 👤 Auteur

**Sylvanus**
- Email: sylvanusproduction@email.com

## 🙏 Remerciements

Merci à l'équipe de **La Cale** pour la mise à disposition de leur API.

---

## 📝 Changelog

### v2.0.0 (2025-01)

**Breaking Changes:**
- Migration de Guzzle vers Symfony HttpClient
- Retour de DTOs typés au lieu de tableaux bruts
- Namespace des exceptions déplacé vers `LaCale\Exception\`
- Classe `Client` déclarée `final`

**Nouveautés:**
- DTOs immutables (readonly) avec méthodes utilitaires
- Hiérarchie d'exceptions spécifiques pour chaque erreur HTTP
- Validation stricte des paramètres selon la documentation API
- Support du header `Retry-After` pour le rate limiting
- Méthodes de recherche dans les métadonnées (`findCategoryBySlug`, `findTagBySlug`, etc.)
- Architecture moderne avec `declare(strict_types=1)`
- Configuration centralisée avec `ApiConfig`

**Améliorations:**
- Meilleure gestion des erreurs réseau
- Validation des URLs et fichiers avant envoi
- Documentation complète avec exemples
- Code coverage et PHPStan ready

### v1.0.0 (2024)
- Version initiale avec Guzzle

---

**Note** : Ce SDK n'est pas officiel et est maintenu par la communauté. Pour toute question relative à l'API elle-même, veuillez contacter directement l'équipe de La Cale.

**Important** : Le torrent doit contenir le source flag `lacale` pour être accepté par l'API