<?php

class IMDB {
    protected string $imdbId;
    protected array $data = [];

    // Вставь сюда свой TMDB API ключ
    private const TMDB_API_KEY = 'e83184074867f8e290402cc1dd71f0d5';
    private const TMDB_BASE    = 'https://api.themoviedb.org/3';
    private const TMDB_IMG     = 'https://image.tmdb.org/t/p/w500';

    public function __construct(string $imdbIdOrUrl) {
        if (preg_match('#https?://www\.imdb\.com/title/(tt\d+)/#i', $imdbIdOrUrl, $matches)) {
            $this->imdbId = $matches[1];
        } elseif (preg_match('/^tt\d+$/', $imdbIdOrUrl)) {
            $this->imdbId = $imdbIdOrUrl;
        } else {
            throw new InvalidArgumentException("Invalid IMDb ID or URL format");
        }
    }

    // ── HTTP запрос ──────────────────────────────────────────

    protected function fetchJson(string $url): ?array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ]);
        $response = curl_exec($ch);
        $err      = curl_error($ch);
        

        if (!$response || $err) {
            throw new Exception("TMDB API request failed: {$err}");
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("TMDB API JSON decode error: " . json_last_error_msg());
        }

        if (isset($decoded['status_code']) && $decoded['status_code'] !== 1) {
            throw new Exception("TMDB API error: " . ($decoded['status_message'] ?? 'Unknown'));
        }

        return $decoded;
    }

    // ── Найти TMDB ID по IMDb ID ─────────────────────────────

    protected function findTmdbId(): array {
        $url  = self::TMDB_BASE . "/find/{$this->imdbId}?external_source=imdb_id&api_key=" . self::TMDB_API_KEY;
        $data = $this->fetchJson($url);

        // Сначала ищем фильм
        if (!empty($data['movie_results'])) {
            return ['type' => 'movie', 'id' => $data['movie_results'][0]['id']];
        }

        // Потом сериал
        if (!empty($data['tv_results'])) {
            return ['type' => 'tv', 'id' => $data['tv_results'][0]['id']];
        }

        throw new Exception("Title not found on TMDB for IMDb ID: {$this->imdbId}");
    }

    // ── Получить детали фильма/сериала ───────────────────────

    protected function fetchDetails(string $type, int $tmdbId): array {
        $url = self::TMDB_BASE . "/{$type}/{$tmdbId}?api_key=" . self::TMDB_API_KEY
             . "&append_to_response=credits,keywords,release_dates,videos,external_ids";
        return $this->fetchJson($url) ?? [];
    }

    // ── Форматирование runtime ────────────────────────────────

    protected function formatRuntime(?int $minutes): string {
        if (!$minutes) return '';
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        $parts = [];
        if ($h > 0) $parts[] = $h . 'h';
        if ($m > 0) $parts[] = $m . 'm';
        return implode(' ', $parts);
    }

    // ── Основной парсер ──────────────────────────────────────

    public function parse(): array {
        $found    = $this->findTmdbId();
        $type     = $found['type'];
        $tmdbId   = $found['id'];
        $details  = $this->fetchDetails($type, $tmdbId);

        // Название
        $title = $type === 'movie'
            ? ($details['title'] ?? $details['original_title'] ?? '')
            : ($details['name']  ?? $details['original_name']  ?? '');

        // Год
        $dateField = $type === 'movie' ? 'release_date' : 'first_air_date';
        $releaseDate = $details[$dateField] ?? '';
        $year = $releaseDate ? substr($releaseDate, 0, 4) : '';

        // Runtime
        $runtimeMin = $type === 'movie'
            ? ($details['runtime'] ?? null)
            : ($details['episode_run_time'][0] ?? null);
        $runtimeFormatted = $this->formatRuntime($runtimeMin);

        // Жанры
        $genres = array_column($details['genres'] ?? [], 'name');

        // Постер
        $poster = !empty($details['poster_path'])
            ? self::TMDB_IMG . $details['poster_path']
            : '';

        // Рейтинг
        $rating      = $details['vote_average'] ?? null;
        $ratingCount = $details['vote_count']   ?? null;

        // Страны
        $countries = $type === 'movie'
            ? array_column($details['production_countries'] ?? [], 'name')
            : array_column($details['origin_country'] ?? [], '');
        if ($type === 'tv' && !empty($details['origin_country'])) {
            $countries = $details['origin_country'];
        }

        // Языки
        $languages = array_column($details['spoken_languages'] ?? [], 'english_name');

        // Продакшн компании
        $productionCompanies = array_column($details['production_companies'] ?? [], 'name');

        // Ключевые слова / теги
        $tags = array_column($details['keywords']['keywords'] ?? $details['keywords']['results'] ?? [], 'name');

        // Актёры и режиссёры
        $cast      = [];
        $directors = [];
        $writers   = [];

        foreach ($details['credits']['cast'] ?? [] as $member) {
            $cast[] = [
                'actor'     => $member['name']      ?? '',
                'actor_id'  => $member['id']        ?? null,
                'character' => $member['character'] ?? '',
                'photo'     => !empty($member['profile_path'])
                    ? 'https://image.tmdb.org/t/p/w185' . $member['profile_path']
                    : null,
                'url' => !empty($member['id'])
                    ? "https://www.themoviedb.org/person/{$member['id']}"
                    : null,
            ];
        }

        foreach ($details['credits']['crew'] ?? [] as $member) {
            if (($member['job'] ?? '') === 'Director') {
                $directors[] = ['name' => $member['name'], 'id' => $member['id'] ?? null];
            }
            if (in_array($member['job'] ?? '', ['Writer', 'Screenplay', 'Story', 'Author'])) {
                $writers[] = ['name' => $member['name'], 'id' => $member['id'] ?? null];
            }
        }

        // Трейлер
        $trailerUrl = '';
        foreach ($details['videos']['results'] ?? [] as $video) {
            if ($video['type'] === 'Trailer' && $video['site'] === 'YouTube') {
                $trailerUrl = 'https://www.youtube.com/watch?v=' . $video['key'];
                break;
            }
        }

        // Рейтинг контента (US)
        $contentRating = '';
        foreach ($details['release_dates']['results'] ?? [] as $country) {
            if ($country['iso_3166_1'] === 'US') {
                foreach ($country['release_dates'] ?? [] as $rd) {
                    if (!empty($rd['certification'])) {
                        $contentRating = $rd['certification'];
                        break 2;
                    }
                }
            }
        }

        $this->data = [
            'id'                  => $this->imdbId,
            'tmdb_id'             => $tmdbId,
            'type'                => $type,
            'url'                 => "https://www.imdb.com/title/{$this->imdbId}/",
            'tmdb_url'            => "https://www.themoviedb.org/{$type}/{$tmdbId}",
            'title'               => $title,
            'original_title'      => $details['original_title'] ?? $details['original_name'] ?? '',
            'year'                => $year,
            'release_date'        => $releaseDate,
            'rating'              => $rating ? number_format((float)$rating, 1) : null,
            'rating_count'        => $ratingCount,
            'genres'              => $genres,
            'runtime'             => $runtimeFormatted,
            'runtime_minutes'     => $runtimeMin,
            'plot'                => $details['overview'] ?? '',
            'poster'              => $poster,
            'content_rating'      => $contentRating,
            'languages'           => $languages,
            'countries'           => $countries,
            'production_companies'=> $productionCompanies,
            'tags'                => $tags,
            'tagline'             => $details['tagline'] ?? '',
            'cast'                => $cast,
            'credits'             => [
                'directors' => $directors,
                'writers'   => $writers,
            ],
            'awards'              => '',
            'box_office'          => [
                'budget'         => !empty($details['budget'])
                    ? '$' . number_format((int)$details['budget'])
                    : '',
                'gross_worldwide' => !empty($details['revenue'])
                    ? '$' . number_format((int)$details['revenue'])
                    : '',
                'gross_us'        => '',
                'opening_weekend' => '',
            ],
            'storyline'           => [
                'synopsis'    => $details['overview'] ?? '',
                'certificate' => $contentRating,
            ],
            'media' => [
                'trailer_url'  => $trailerUrl,
                'photos_count' => null,
                'videos_count' => count($details['videos']['results'] ?? []) ?: null,
            ],
            'official_site'       => $details['homepage'] ?? '',
            'tagline'             => $details['tagline'] ?? '',
            'also_known_as'       => [],
            'filming_locations'   => '',
            'technical_specs'     => [],
        ];

        return $this->data;
    }
}