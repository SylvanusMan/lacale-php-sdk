<?php

declare(strict_types=1);

namespace LaCale;

use LaCale\Config\ApiConfig;
use LaCale\DTO\Metadata;
use LaCale\DTO\TorrentResult;
use LaCale\DTO\UploadResponse;
use LaCale\Exception\AuthenticationException;
use LaCale\Exception\ConflictException;
use LaCale\Exception\LaCaleException;
use LaCale\Exception\NetworkException;
use LaCale\Exception\NotFoundException;
use LaCale\Exception\RateLimitException;
use LaCale\Exception\ServerException;
use LaCale\Exception\ValidationException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Client moderne pour l'API La Cale
 *
 * @package LaCale
 * @version 2.0
 */
final readonly class Client
{
    private HttpClientInterface $httpClient;

    /**
     * @param string $passkey Clé API de La Cale (récupérable sur votre profil)
     * @param string $baseUrl URL de base de l'API (par défaut: https://la-cale.space)
     * @param int $timeout Timeout par défaut en secondes
     * @param HttpClientInterface|null $httpClient Client HTTP personnalisé (optionnel)
     */
    public function __construct(
        private string $passkey,
        private string $baseUrl = ApiConfig::DEFAULT_BASE_URL,
        private int $timeout = ApiConfig::DEFAULT_TIMEOUT,
        ?HttpClientInterface $httpClient = null,
    ) {
        if ($this->passkey === '' || $this->passkey === '0') {
            throw new AuthenticationException('La passkey ne peut pas être vide');
        }

        $this->httpClient = $httpClient ?? HttpClient::create([
            'base_uri' => rtrim($this->baseUrl, '/'),
            'timeout' => $this->timeout,
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => ApiConfig::USER_AGENT,
            ],
        ]);
    }

    /**
     * Recherche de torrents (compatible JSON indexers comme Prowlarr, Jackett)
     *
     * @param string|null $query Terme de recherche (max 200 caractères)
     * @param string[] $categories Liste des slugs de catégories (ex: ['films', 'series'])
     * @return TorrentResult[] Liste des résultats (max 50 items)
     * @throws LaCaleException
     */
    public function search(?string $query = null, array $categories = []): array
    {
        // Validation des paramètres
        if ($query !== null && mb_strlen($query) > ApiConfig::MAX_QUERY_LENGTH) {
            throw new ValidationException(
                sprintf('Le terme de recherche ne peut pas dépasser %d caractères', ApiConfig::MAX_QUERY_LENGTH)
            );
        }

        foreach ($categories as $category) {
            if (mb_strlen($category) > ApiConfig::MAX_CATEGORY_LENGTH) {
                throw new ValidationException(
                    sprintf('Le slug de catégorie "%s" ne peut pas dépasser %d caractères', $category, ApiConfig::MAX_CATEGORY_LENGTH)
                );
            }
        }

        // Construction de la query string
        $queryParams = ['passkey' => $this->passkey];
        if ($query !== null && $query !== '') {
            $queryParams['q'] = $query;
        }

        $queryString = http_build_query($queryParams);

        // Ajout des catégories (répétition du paramètre cat)
        foreach ($categories as $category) {
            $queryString .= '&cat=' . urlencode($category);
        }

        try {
            $response = $this->httpClient->request('GET', ApiConfig::ENDPOINT_SEARCH . '?' . $queryString);
            $statusCode = $response->getStatusCode();

            $this->handleHttpErrors($statusCode, $response);

            $data = $response->toArray();

            if (!is_array($data)) {
                throw new LaCaleException('Réponse API invalide: format inattendu');
            }

            // Conversion en DTOs
            return array_map(
                TorrentResult::fromArray(...),
                $data
            );
        } catch (TransportExceptionInterface $e) {
            throw new NetworkException('Erreur réseau lors de la recherche: ' . $e->getMessage(), 0, $e);
        } catch (ExceptionInterface $e) {
            throw new LaCaleException('Erreur lors de la recherche: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Récupère les métadonnées complètes (catégories, tags, groupes)
     * Utile pour obtenir les IDs nécessaires à l'upload
     *
     * Cache serveur: ~30s
     *
     * @return Metadata Métadonnées complètes
     * @throws LaCaleException
     */
    public function getMetadata(): Metadata
    {
        try {
            $response = $this->httpClient->request('GET', ApiConfig::ENDPOINT_METADATA, [
                'query' => ['passkey' => $this->passkey],
            ]);

            $statusCode = $response->getStatusCode();
            $this->handleHttpErrors($statusCode, $response);

            $data = $response->toArray();

            return Metadata::fromArray($data);
        } catch (TransportExceptionInterface $e) {
            throw new NetworkException('Erreur réseau lors de la récupération des métadonnées: ' . $e->getMessage(), 0, $e);
        } catch (ExceptionInterface $e) {
            throw new LaCaleException('Erreur lors de la récupération des métadonnées: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Upload un fichier .torrent
     *
     * Rate limit: 30 uploads/minute
     * Important: Le torrent doit contenir le source flag "lacale"
     *
     * @param string $title Titre de la release (requis)
     * @param string $categoryId ID de la catégorie (obtenu via getMetadata())
     * @param string $torrentFilePath Chemin absolu vers le fichier .torrent
     * @param string[] $tagIds Liste des IDs de tags (obtenus via getMetadata())
     * @param array{
     *     description?: string,
     *     tmdbId?: string,
     *     tmdbType?: string,
     *     coverUrl?: string,
     *     nfoFilePath?: string
     * } $options Options supplémentaires
     * @return UploadResponse Réponse avec le lien du torrent créé
     * @throws LaCaleException
     */
    public function upload(
        string $title,
        string $categoryId,
        string $torrentFilePath,
        array $tagIds = [],
        array $options = []
    ): UploadResponse {
        // Validation des paramètres requis
        if (trim($title) === '') {
            throw new ValidationException('Le titre ne peut pas être vide');
        }

        if (trim($categoryId) === '') {
            throw new ValidationException('L\'ID de catégorie ne peut pas être vide');
        }

        if (!file_exists($torrentFilePath)) {
            throw new ValidationException('Le fichier torrent est introuvable: ' . $torrentFilePath);
        }

        if (!is_readable($torrentFilePath)) {
            throw new ValidationException('Le fichier torrent n\'est pas lisible: ' . $torrentFilePath);
        }

        // Validation du type TMDB si fourni
        if (isset($options['tmdbType'])) {
            $validTypes = [ApiConfig::TMDB_TYPE_MOVIE, ApiConfig::TMDB_TYPE_TV];
            if (!in_array($options['tmdbType'], $validTypes, true)) {
                throw new ValidationException(
                    sprintf('Le type TMDB doit être %s ou %s', ApiConfig::TMDB_TYPE_MOVIE, ApiConfig::TMDB_TYPE_TV)
                );
            }
        }

        // Validation de l'URL de couverture
        if (isset($options['coverUrl']) && !filter_var($options['coverUrl'], FILTER_VALIDATE_URL)) {
            throw new ValidationException("L'URL de couverture n'est pas valide");
        }

        // Validation du fichier NFO
        if (isset($options['nfoFilePath']) && !file_exists($options['nfoFilePath'])) {
            throw new ValidationException('Le fichier NFO est introuvable: ' . $options['nfoFilePath']);
        }

        // Construction du formulaire multipart
        $formFields = [
            'passkey' => $this->passkey,
            'title' => $title,
            'categoryId' => $categoryId,
            'file' => DataPart::fromPath($torrentFilePath),
        ];

        // Ajout des tags (champ répété)
        foreach ($tagIds as $index => $tagId) {
            $formFields[sprintf('tags[%s]', $index)] = $tagId;
        }

        // Champs optionnels
        $optionalFields = ['description', 'tmdbId', 'tmdbType', 'coverUrl'];
        foreach ($optionalFields as $optionalField) {
            if (isset($options[$optionalField]) && $options[$optionalField] !== '') {
                $formFields[$optionalField] = (string)$options[$optionalField];
            }
        }

        // Fichier NFO optionnel
        if (isset($options['nfoFilePath']) && file_exists($options['nfoFilePath'])) {
            $formFields['nfoFile'] = DataPart::fromPath($options['nfoFilePath']);
        }

        $formDataPart = new FormDataPart($formFields);

        try {
            $response = $this->httpClient->request('POST', ApiConfig::ENDPOINT_UPLOAD, [
                'headers' => $formDataPart->getPreparedHeaders()->toArray(),
                'body' => $formDataPart->bodyToIterable(),
                'timeout' => ApiConfig::UPLOAD_TIMEOUT,
            ]);

            $statusCode = $response->getStatusCode();
            $this->handleHttpErrors($statusCode, $response);

            $data = $response->toArray();

            return UploadResponse::fromArray($data);
        } catch (TransportExceptionInterface $e) {
            throw new NetworkException('Erreur réseau lors de l\'upload: ' . $e->getMessage(), 0, $e);
        } catch (ExceptionInterface $e) {
            throw new LaCaleException("Erreur lors de l'upload: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Génère l'URL de téléchargement d'un torrent (sans requête HTTP)
     *
     * @param string $infoHash Hash du torrent
     * @return string URL de téléchargement complète
     */
    public function getDownloadLink(string $infoHash): string
    {
        if (trim($infoHash) === '') {
            throw new ValidationException('L\'infoHash ne peut pas être vide');
        }

        return sprintf(
            '%s%s/%s?passkey=%s',
            rtrim($this->baseUrl, '/'),
            ApiConfig::ENDPOINT_DOWNLOAD,
            $infoHash,
            $this->passkey
        );
    }

    /**
     * Gère les erreurs HTTP selon les codes de statut
     *
     * @throws LaCaleException
     */
    private function handleHttpErrors(int $statusCode, mixed $response): void
    {
        if ($statusCode >= 200 && $statusCode < 300) {
            return; // Succès
        }

        $message = 'Erreur API';
        try {
            $data = $response->toArray(false);
            $message = $data['message'] ?? $data['error'] ?? $message;
            $errors = $data['errors'] ?? [];
        } catch (\Throwable) {
            $errors = [];
        }

        switch ($statusCode) {
            case ApiConfig::HTTP_UNAUTHORIZED:
                throw new AuthenticationException(
                    $message ?: 'Passkey invalide ou manquante',
                    $statusCode
                );

            case ApiConfig::HTTP_NOT_FOUND:
                throw new NotFoundException(
                    $message ?: 'Ressource non trouvée',
                    $statusCode
                );

            case ApiConfig::HTTP_CONFLICT:
                throw new ConflictException(
                    $message ?: 'Conflit - Le torrent existe peut-être déjà',
                    $statusCode
                );

            case ApiConfig::HTTP_UNPROCESSABLE:
                throw new ValidationException(
                    $message ?: 'Données invalides',
                    $statusCode,
                    null,
                    $errors
                );

            case ApiConfig::HTTP_TOO_MANY_REQUESTS:
                $rateLimitException = new RateLimitException(
                    $message ?: 'Limite de requêtes atteinte - Veuillez patienter',
                    $statusCode
                );

                // Récupération du Retry-After header si disponible
                try {
                    $retryAfter = $response->getHeaders()['retry-after'][0] ?? null;
                    if ($retryAfter !== null) {
                        $rateLimitException->setRetryAfter((int)$retryAfter);
                    }
                } catch (\Throwable) {
                    // Ignore si header non disponible
                }

                throw $rateLimitException;

            case ApiConfig::HTTP_SERVER_ERROR:
            default:
                if ($statusCode >= 500) {
                    throw new ServerException(
                        $message ?: 'Erreur serveur',
                        $statusCode
                    );
                }

                throw new LaCaleException($message, $statusCode);
        }
    }

    /**
     * Retourne la passkey utilisée (pour débogage uniquement)
     * ATTENTION: Ne jamais exposer cette valeur publiquement
     *
     * @internal
     */
    public function getPasskey(): string
    {
        return $this->passkey;
    }

    /**
     * Retourne l'URL de base configurée
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }
}