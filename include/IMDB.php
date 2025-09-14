<?php

class IMDB {
    protected string $imdbId;
    protected ?string $html = null;
    protected ?DOMDocument $dom = null;
    protected ?DOMXPath $xpath = null;
    protected array $data = [];

    public function __construct(string $imdbIdOrUrl) {
        if (preg_match('#https?://www\.imdb\.com/title/(tt\d+)/#i', $imdbIdOrUrl, $matches)) {
            $this->imdbId = $matches[1];
        } elseif (preg_match('/^tt\d+$/', $imdbIdOrUrl)) {
            $this->imdbId = $imdbIdOrUrl;
        } else {
            throw new InvalidArgumentException("Invalid IMDb ID or URL format");
        }
    }


	
	
	
	
	protected function fetchPage(): void {
    $url = "https://www.imdb.com/title/{$this->imdbId}/";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_ENCODING => 'gzip',
        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.5',
            'Connection: keep-alive',
            'Upgrade-Insecure-Requests: 1',
        ],
    ]);
    $this->html = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if (!$this->html || $err) {
            throw new Exception("Failed to fetch IMDb page: {$err}");
        }
}
	
	
	
	
	
	
	
	
	

    protected function parseHtml(): void {
        libxml_use_internal_errors(true);
        $this->dom = new DOMDocument();
        $this->dom->loadHTML($this->html);
        $this->xpath = new DOMXPath($this->dom);
        libxml_clear_errors();
    }

    protected function xpathText(string $query): ?string {
        $nodes = $this->xpath->query($query);
        return $nodes->length > 0 ? trim($nodes->item(0)->textContent) : null;
    }

    protected function xpathTexts(string $query): array {
        $nodes = $this->xpath->query($query);
        $results = [];
        foreach ($nodes as $node) {
            $results[] = trim($node->textContent);
        }
        return $results;
    }

    protected function parseJsonLD(): ?array {
        $jsonLdNodes = $this->xpath->query('//script[@type="application/ld+json"]');
        if ($jsonLdNodes->length == 0) return null;
        
        $jsonText = $jsonLdNodes->item(0)->textContent;
        return json_decode($jsonText, true) ?: null;
    }

    protected function convertISO8601Duration(string $duration): string {
        preg_match('/PT(?:(\d+)H)?(?:(\d+)M)?/', $duration, $matches);
        $hours = $matches[1] ?? 0;
        $minutes = $matches[2] ?? 0;
        
        $parts = [];
        if ($hours > 0) $parts[] = $hours . 'h';
        if ($minutes > 0) $parts[] = $minutes . 'm';
        
        return $parts ? implode(' ', $parts) : $duration;
    }

    
	
	protected function extractCast(): array {
        $cast = [];
        $castNodes = $this->xpath->query('//div[@data-testid="title-cast-item"]');
        
        foreach ($castNodes as $node) {
            $actorNode = $this->xpath->query('.//a[@data-testid="title-cast-item__actor"]', $node);
            $characterNode = $this->xpath->query('.//a[@data-testid="cast-item-characters-link"]/span', $node);
            $photoNode = $this->xpath->query('.//img[@data-testid="title-cast-item__avatar"]', $node);
            
            if ($actorNode->length > 0) {
                $actor = trim($actorNode->item(0)->textContent);
                $character = $characterNode->length > 0 ? trim($characterNode->item(0)->textContent) : null;
                
                // Extract actor ID
                $actorUrl = $actorNode->item(0)->getAttribute('href');
                preg_match('/\/name\/(nm\d+)\//', $actorUrl, $matches);
                $actorId = $matches[1] ?? null;
                
                // Extract actor photo
                $actorPhoto = $photoNode->length > 0 ? $photoNode->item(0)->getAttribute('src') : null;
                
                $cast[] = [
                    'actor' => $actor,
                    'actor_id' => $actorId,
                    'character' => $character,
                    'photo' => $actorPhoto,
                    'url' => $actorId ? "https://www.imdb.com/name/{$actorId}/" : null
                ];
            }
        }
        
        return $cast;
    }

   
   
    protected function extractFullCredits(): array {
        $credits = [];
        
        // Directors
        $directors = [];
        $directorNodes = $this->xpath->query('//li[contains(@class, "ipc-metadata-list__item") and .//span[text()="Director"] or .//span[text()="Directors"]]//a');
        foreach ($directorNodes as $node) {
            $director = trim($node->textContent);
            $directorUrl = $node->getAttribute('href');
            preg_match('/\/name\/(nm\d+)\//', $directorUrl, $matches);
            $directorId = $matches[1] ?? null;
            
            $directors[] = [
                'name' => $director,
                'id' => $directorId
            ];
        }
        $credits['directors'] = $directors;
        
        // Writers
        $writers = [];
        $writerNodes = $this->xpath->query('//li[contains(@class, "ipc-metadata-list__item") and .//span[text()="Writer"] or .//span[text()="Writers"]]//a');
        foreach ($writerNodes as $node) {
            $writer = trim($node->textContent);
            $writerUrl = $node->getAttribute('href');
            preg_match('/\/name\/(nm\d+)\//', $writerUrl, $matches);
            $writerId = $matches[1] ?? null;
            
            $writers[] = [
                'name' => $writer,
                'id' => $writerId
            ];
        }
        $credits['writers'] = $writers;
        
        return $credits;
    }
	
	
	

protected function extractTechnicalSpecs(): array {
    $specs = [];
    
    // Основные технические характеристики
    $techSpecsNodes = $this->xpath->query('//div[@data-testid="techspecs-section"]//li');
    
    foreach ($techSpecsNodes as $node) {
        $label = $this->xpath->query('.//span[@class="ipc-metadata-list-item__label"]', $node);
        $value = $this->xpath->query('.//div[contains(@class, "ipc-metadata-list-item__content-container")]', $node);
        
        if ($label->length > 0 && $value->length > 0) {
            $specName = trim($label->item(0)->textContent);
            $specValue = trim($value->item(0)->textContent);
            
            // Нормализация названий характеристик
            $specName = str_replace(':', '', $specName);
            $specs[$specName] = $specValue;
        }
    }
    
    // Дополнительные технические данные из других разделов
    $aspectRatio = $this->xpathText('//li[contains(@data-testid, "aspect-ratio")]//div');
    if ($aspectRatio) {
        $specs['Aspect Ratio'] = $aspectRatio;
    }
    
    $soundMix = $this->xpathText('//li[contains(@data-testid, "sound-mix")]//div');
    if ($soundMix) {
        $specs['Sound Mix'] = $soundMix;
    }
    
    $color = $this->xpathText('//li[contains(@data-testid, "color")]//div');
    if ($color) {
        $specs['Color'] = $color;
    }
    
    $negativeFormat = $this->xpathText('//li[contains(@data-testid, "negative-format")]//div');
    if ($negativeFormat) {
        $specs['Negative Format'] = $negativeFormat;
    }
    
    // Обработка Runtime из JSON-LD как запасной вариант
    if (empty($specs) && isset($this->data['runtime'])) {
        $specs['Runtime'] = $this->data['runtime'];
    }
    
    return $specs;
}











protected function extractAwards(): array {
    $awards = [];
    
    // Oscar wins/nominations
    $oscarsNode = $this->xpathText('//section[contains(@data-testid, "awards")]//span[contains(text(), "Oscar")]');
    if ($oscarsNode) {
        preg_match('/(\d+) wins? and (\d+) nominations?/', $oscarsNode, $matches);
        if (count($matches) > 0) {
            $awards['oscars'] = [
                'wins' => $matches[1] ?? 0,
                'nominations' => $matches[2] ?? 0,
                'text' => $oscarsNode
            ];
        }
    }
    
    // Total wins and nominations
    $winsNode = $this->xpathText('//section[contains(@data-testid, "awards")]//span[contains(text(), "wins")]');
    $nominationsNode = $this->xpathText('//section[contains(@data-testid, "awards")]//span[contains(text(), "nominations")]');
    
    if ($winsNode) {
        preg_match('/(\d+)/', $winsNode, $matches);
        $awards['total_wins'] = $matches[1] ?? 0;
    }
    
    if ($nominationsNode) {
        preg_match('/(\d+)/', $nominationsNode, $matches);
        $awards['total_nominations'] = $matches[1] ?? 0;
    }
    
    // Other awards - improved selector
    $otherAwards = [];
    $awardNodes = $this->xpath->query('//section[contains(@data-testid, "awards")]//li[contains(@class, "ipc-metadata-list__item")]');
    foreach ($awardNodes as $node) {
        $awardText = trim($node->textContent);
        if (!empty($awardText) && !str_contains($awardText, 'Oscar') && !str_contains($awardText, 'wins') && !str_contains($awardText, 'nominations')) {
            $otherAwards[] = $awardText;
        }
    }
    
    if (!empty($otherAwards)) {
        $awards['other_awards'] = $otherAwards;
    }
    
    return $awards;
}







   
   
 protected function extractTags(): array {
    $tags = [];
    
    // Пробуем разные варианты извлечения тегов
    $possibleTagQueries = [
        '//a[contains(@href, "/keyword/")]',  // Стандартные ключевые слова
        '//div[@data-testid="storyline-plot-keywords"]//a',
        '//div[contains(@class, "ipc-chip-list")]//span[contains(@class, "ipc-chip__text")]', // Современные "чипсы" IMDb
        '//div[@data-testid="genres"]//a'  // Жанры тоже считаем тегами
    ];
    
    foreach ($possibleTagQueries as $query) {
        $elements = $this->xpath->query($query);
        foreach ($elements as $element) {
            $tag = trim($element->textContent);
            if (!empty($tag) && !in_array($tag, $tags)) {
                $tags[] = $tag;
            }
        }
    }
    
    return $tags;
}
   
   
   
   
   
   

    protected function extractStoryline(): array {
        $storyline = [];
        
        // Plot summary
        $storyline['plot'] = $this->xpathText('//div[@data-testid="storyline-plot-summary"]//div[contains(@class, "ipc-html-content-inner-div")]');
        
        // Plot synopsis
        $storyline['synopsis'] = $this->xpathText('//div[@data-testid="storyline-plot-synopsis"]//div[contains(@class, "ipc-html-content-inner-div")]');
        
        // Storyline elements
        $elements = $this->xpath->query('//div[@data-testid="storyline-storyline-elements"]//li');
        foreach ($elements as $element) {
            $label = $this->xpath->query('.//span[contains(@class, "ipc-metadata-list-item__label")]', $element);
            $content = $this->xpath->query('.//div[contains(@class, "ipc-metadata-list-item__content-container")]', $element);
            
            if ($label->length > 0 && $content->length > 0) {
                $key = trim($label->item(0)->textContent);
                $value = trim($content->item(0)->textContent);
                $storyline[$key] = $value;
            }
        }
        
        return $storyline;
    }

    protected function extractBoxOffice(): array {
        $boxOffice = [];
        
        $budget = $this->xpathText('//li[@data-testid="title-boxoffice-budget"]//span[contains(@class, "ipc-metadata-list-item__list-content-item")]');
        if ($budget) {
            $boxOffice['budget'] = $budget;
        }
        
        $grossUS = $this->xpathText('//li[@data-testid="title-boxoffice-grossdomestic"]//span[contains(@class, "ipc-metadata-list-item__list-content-item")]');
        if ($grossUS) {
            $boxOffice['gross_us'] = $grossUS;
        }
        
        $grossWorldwide = $this->xpathText('//li[@data-testid="title-boxoffice-cumulativeworldwidegross"]//span[contains(@class, "ipc-metadata-list-item__list-content-item")]');
        if ($grossWorldwide) {
            $boxOffice['gross_worldwide'] = $grossWorldwide;
        }
        
        $openingWeekend = $this->xpathText('//li[@data-testid="title-boxoffice-openingweekenddomestic"]//span[contains(@class, "ipc-metadata-list-item__list-content-item")]');
        if ($openingWeekend) {
            $boxOffice['opening_weekend'] = $openingWeekend;
        }
        
        return $boxOffice;
    }

    protected function extractMedia(): array {
        $media = [];
        
        // Photos count
        $photosCount = $this->xpathText('//a[contains(@href, "/title/{$this->imdbId}/mediaindex")]');
        if ($photosCount) {
            preg_match('/\((\d+)\)/', $photosCount, $matches);
            $media['photos_count'] = $matches[1] ?? null;
        }
        
        // Videos count
        $videosCount = $this->xpathText('//a[contains(@href, "/title/{$this->imdbId}/videogallery")]');
        if ($videosCount) {
            preg_match('/\((\d+)\)/', $videosCount, $matches);
            $media['videos_count'] = $matches[1] ?? null;
        }
        
        // Poster URL
        $posterUrl = $this->xpathText('//img[@data-testid="hero-image__poster"]/@src');
        if ($posterUrl) {
            $media['poster_url'] = $posterUrl;
        }
        
        // Trailer
        $trailerNode = $this->xpath->query('//a[contains(@href, "/video/") and contains(@href, "playlist")]');
        if ($trailerNode->length > 0) {
            $media['trailer_url'] = 'https://www.imdb.com' . $trailerNode->item(0)->getAttribute('href');
        }
        
        return $media;
    }

    protected function extractCompanyCredits(): array {
        $companies = [];
        
        // Production companies
        $productionNodes = $this->xpath->query('//li[@data-testid="title-details-companies"]//a');
        foreach ($productionNodes as $node) {
            $company = trim($node->textContent);
            $companyUrl = $node->getAttribute('href');
            preg_match('/\/company\/(co\d+)\//', $companyUrl, $matches);
            $companyId = $matches[1] ?? null;
            
            $companies['production'][] = [
                'name' => $company,
                'id' => $companyId,
                'url' => $companyId ? "https://www.imdb.com/company/{$companyId}/" : null
            ];
        }
        
        // Distributors
        $distributorNodes = $this->xpath->query('//li[contains(@data-testid, "title-details-distributors")]//a');
        foreach ($distributorNodes as $node) {
            $distributor = trim($node->textContent);
            $distributorUrl = $node->getAttribute('href');
            preg_match('/\/company\/(co\d+)\//', $distributorUrl, $matches);
            $distributorId = $matches[1] ?? null;
            
            $companies['distributors'][] = [
                'name' => $distributor,
                'id' => $distributorId,
                'url' => $distributorId ? "https://www.imdb.com/company/{$distributorId}/" : null
            ];
        }
        
        // Special effects
        $effectsNodes = $this->xpath->query('//li[contains(@data-testid, "title-details-specialeffects")]//a');
        foreach ($effectsNodes as $node) {
            $effects = trim($node->textContent);
            $effectsUrl = $node->getAttribute('href');
            preg_match('/\/company\/(co\d+)\//', $effectsUrl, $matches);
            $effectsId = $matches[1] ?? null;
            
            $companies['special_effects'][] = [
                'name' => $effects,
                'id' => $effectsId,
                'url' => $effectsId ? "https://www.imdb.com/company/{$effectsId}/" : null
            ];
        }
        
        return $companies;
    }





protected function extractUserReviews(): array {
    $reviews = [];
    
    // Fixed rating breakdown extraction
    $ratingBreakdown = [];
    $ratingNodes = $this->xpath->query('//div[contains(@class, "ipl-rating-star")]//span[contains(@class, "ipl-rating-star__total-votes")]');
    foreach ($ratingNodes as $node) {
        $ratingText = trim($node->textContent);
        preg_match('/(\d+\.?\d*)%/', $ratingText, $percentMatch);
        preg_match('/\(([\d,]+)\)/', $ratingText, $countMatch);
        
        if (count($percentMatch) && count($countMatch)) {
            $ratingBreakdown[] = [
                'rating' => $percentMatch[1],
                'count' => str_replace(',', '', $countMatch[1])
            ];
        }
    }
    
    if (!empty($ratingBreakdown)) {
        $reviews['rating_breakdown'] = $ratingBreakdown;
    }
    
    // Rest of the method remains the same...
    return $reviews;
}







    public function parse(): array {
        $this->fetchPage();
        $this->parseHtml();
		
		
        $json = $this->parseJsonLD();
        if (!$json) {
            throw new Exception("Failed to parse JSON-LD from IMDb page");
        }

        // Basic info from JSON-LD
        $this->data = [
            'id' => $this->imdbId,
            'url' => "https://www.imdb.com/title/{$this->imdbId}/",
            'title' => $json['name'] ?? null,
            'original_title' => $json['alternateName'] ?? null,
            'year' => isset($json['datePublished']) ? substr($json['datePublished'], 0, 4) : null,
            'release_date' => $json['datePublished'] ?? null,
            'rating' => $json['aggregateRating']['ratingValue'] ?? null,
            'rating_count' => $json['aggregateRating']['ratingCount'] ?? null,
            'genres' => is_array($json['genre'] ?? null) ? $json['genre'] : [$json['genre'] ?? ''],
            'runtime' => isset($json['duration']) ? $this->convertISO8601Duration($json['duration']) : null,
            'plot' => $json['description'] ?? null,
            'poster' => $json['image'] ?? null,
            'content_rating' => $json['contentRating'] ?? null,
            'languages' => $this->xpathTexts('//li[@data-testid="title-details-languages"]//a'),
            'countries' => $this->xpathTexts('//li[@data-testid="title-details-origin"]//a'),
            'production_companies' => $this->xpathTexts('//li[@data-testid="title-details-companies"]//a'),
            'tags' => $this->extractTags(),
			
            
   
        ];
		
		
		

        // Extended info
        $this->data += [
            'cast' => $this->extractCast(),
            'credits' => $this->extractFullCredits(),
            'technical_specs' => $this->extractTechnicalSpecs(),
            'awards' => $this->extractAwards(),
            'box_office' => $this->extractBoxOffice(),
            'storyline' => $this->extractStoryline(),
            'media' => $this->extractMedia(),
            'company_credits' => $this->extractCompanyCredits(),
            'user_reviews' => $this->extractUserReviews(),
            'official_site' => $this->xpathText('//a[contains(text(),"Official Site") or contains(text(),"Official website")]/@href'),
            'tagline' => $this->xpathText('//section[contains(@data-testid, "storyline-taglines")]//span[contains(@class, "ipc-metadata-list-item__list-content-item")]'),
            'also_known_as' => $this->xpathTexts('//li[@data-testid="title-details-akas"]//li'),
            'filming_locations' => $this->xpathText('//li[@data-testid="title-details-filminglocations"]//a'),
            'release_dates' => $this->xpathTexts('//li[@data-testid="title-details-releasedates"]//li'),
        ];

        return $this->data;
    }
}