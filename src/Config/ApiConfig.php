<?php

declare(strict_types=1);

namespace LaCale\Config;

/**
 * Configuration et constantes de l'API La Cale
 */
final class ApiConfig
{
    // URL
    public const string DEFAULT_BASE_URL = 'https://la-cale.space';

    // Endpoints
    public const string ENDPOINT_SEARCH = '/api/external';
    
    public const string ENDPOINT_METADATA = '/api/external/meta';
    
    public const string ENDPOINT_UPLOAD = '/api/external/upload';
    
    public const string ENDPOINT_DOWNLOAD = '/api/torrents/download';

    // Limites selon la documentation
    public const int MAX_QUERY_LENGTH = 200;
    
    public const int MAX_CATEGORY_LENGTH = 64;
    
    public const int MAX_RESULTS = 50;
    
    public const int UPLOAD_RATE_LIMIT_PER_MINUTE = 30;

    // Timeouts
    public const int DEFAULT_TIMEOUT = 30;
    
    public const int UPLOAD_TIMEOUT = 60;

    // Cache
    public const int CACHE_DURATION_SECONDS = 30;

    // Types TMDB
    public const string TMDB_TYPE_MOVIE = 'MOVIE';
    
    public const string TMDB_TYPE_TV = 'TV';

    // Codes HTTP attendus
    public const int HTTP_OK = 200;
    
    public const int HTTP_CREATED = 201;
    
    public const int HTTP_UNAUTHORIZED = 401;
    
    public const int HTTP_NOT_FOUND = 404;
    
    public const int HTTP_CONFLICT = 409;
    
    public const int HTTP_UNPROCESSABLE = 422;
    
    public const int HTTP_TOO_MANY_REQUESTS = 429;
    
    public const int HTTP_SERVER_ERROR = 500;

    // Headers
    public const string USER_AGENT = 'LaCale-PHP-SDK/2.0';
    
    public const string HEADER_RETRY_AFTER = 'Retry-After';
}
