<?php

// Проверяем и извлекаем IMDb ID
$regex = "#https?://www\.imdb\.com/title/(tt\d+)/#i";
if (!preg_match($regex, $t_link, $_id_)) {
    exit("Invalid IMDb URL");
}
$imdbId = $_id_[1];

include_once(INC_PATH . '/IMDB.php');

try {
    $imdbObj = new IMDB($t_link);
    $data = $imdbObj->parse();

    // Основная информация
    $Title      = $data['title'] ?? '';
    $Year       = $data['year'] ?? '';
    $Rated      = $data['content_rating'] ?? '';
    $Released   = $data['release_date'] ?? '';
    $RuntimeRaw = $data['runtime'] ?? '';
    $RuntimeMinutes = null;
    if (preg_match('/(\d+)\s*h(?:ou)?r?s?\s*(\d+)?\s*m(?:in)?s?/i', $RuntimeRaw, $matches)) {
        $hours = (int)$matches[1];
        $minutes = isset($matches[2]) ? (int)$matches[2] : 0;
        $RuntimeMinutes = $hours * 60 + $minutes;
    } elseif (preg_match('/(\d+)\s*m(?:in)?s?/i', $RuntimeRaw, $matches)) {
        $RuntimeMinutes = (int)$matches[1];
    }

    // Классификация и теги
    $Tags = !empty($data['tags']) ? implode(', ', $data['tags']) : '';
    $Genre = !empty($data['genres']) ? implode(', ', $data['genres']) : '';

    // Творческая группа
    $Director = !empty($data['credits']['directors']) ? 
               implode(', ', array_column($data['credits']['directors'], 'name')) : '';
    $Writer = !empty($data['credits']['writers']) ? 
             implode(', ', array_column($data['credits']['writers'], 'name')) : '';
    $Actors = !empty($data['cast']) ? 
             implode(', ', array_column(array_slice($data['cast'], 0, 7), 'actor')) . 
             (count($data['cast']) > 7 ? ', ...' : '') : '';
			 
			 
			 
			 
    
    // Полный список актеров с персонажами
    $FullCast = '';
    if (!empty($data['cast'])) {
        foreach (array_slice($data['cast'], 0, 10) as $actor) {
            $FullCast .= "<strong>{$actor['actor']}</strong> as {$actor['character']}<br>";
        }
        if (count($data['cast']) > 10) {
            $FullCast .= "... and " . (count($data['cast']) - 10) . " more";
        }
    }

    // Дополнительная информация
    $Plot = $data['plot'] ?? '';
    $Language = !empty($data['languages']) ? implode(', ', $data['languages']) : '';
    $Country = !empty($data['countries']) ? implode(', ', $data['countries']) : '';
    $Awards = is_array($data['awards']) ? 
             "Won {$data['awards']['total_wins']} awards & {$data['awards']['total_nominations']} nominations" . 
             (!empty($data['awards']['oscars']) ? "<br>Oscars: {$data['awards']['oscars']['wins']} wins, {$data['awards']['oscars']['nominations']} nominations" : '') : 
             ($data['awards'] ?? '');
    $Poster = $data['poster'] ?? '';
    $imdbRating = $data['rating'] ?? '';
    $imdbVotes = $data['rating_count'] ?? '';
    $Production = !empty($data['production_companies']) ? implode(', ', $data['production_companies']) : '';
    $IMDBUrl = "https://www.imdb.com/title/{$imdbId}/";
    $Tagline = $data['tagline'] ?? '';

    // Технические характеристики
    $Color = $data['technical_specs']['Color'] ?? '';
    $SoundMix = $data['technical_specs']['Sound Mix'] ?? $data['technical_specs']['Sound mix'] ?? '';
    $AspectRatio = $data['technical_specs']['Aspect Ratio'] ?? $data['technical_specs']['Aspect ratio'] ?? '';
    $NegativeFormat = $data['technical_specs']['Negative Format'] ?? '';

    // Финансовые показатели
    $Budget = $data['box_office']['budget'] ?? '';
    $BoxOffice = $data['box_office']['gross_worldwide'] ?? '';
    $OpeningWeekend = $data['box_office']['opening_weekend'] ?? '';
    $GrossUS = $data['box_office']['gross_us'] ?? '';
    $Official = $data['official_site'] ?? '';

    // Storyline информация
    $Synopsis = $data['storyline']['synopsis'] ?? '';
    $Certificate = $data['storyline']['Certificate'] ?? '';
    $FilmingLocations = $data['filming_locations'] ?? '';
    $AlsoKnownAs = !empty($data['also_known_as']) ? implode('<br>', $data['also_known_as']) : '';

    // Медиа информация
    $PhotosCount = $data['media']['photos_count'] ?? '';
    $VideosCount = $data['media']['videos_count'] ?? '';
    $TrailerUrl = $data['media']['trailer_url'] ?? '';

    // Обработка постера
    $Poster = preg_replace('#\._V1_.*?\.(jpg|png|jpeg)$#i', '.$1', $Poster);
    $ss = $Poster;

    // --- Avistaz-style block ---
    $t_link = "
   
    <style>
        .block-titled { 
            border: 1px solid #ddd; 
            padding: 15px; 
            margin-bottom: 20px; 
            border-radius: 4px; 
            background: #fff;
            font-family: Arial, sans-serif;
        }
        .movie-poster { 
            float: left; 
            margin-right: 15px; 
            width: 200px;
        }
        .movie-title { 
            margin-top: 0; 
            color: #337ab7; 
            font-size: 22px;
        }
        .movie-details { 
            overflow: hidden;
        }
        .badge-extra { 
            background-color: #f5f5f5; 
            padding: 3px 6px; 
            border-radius: 3px; 
            margin-right: 5px;
            display: inline-block;
        }
        .clearfix { 
            clear: both;
        }
        .img-responsive { 
            max-width: 100%; 
            height: auto;
            border-radius: 3px;
        }
        .movie-info div { 
            margin-bottom: 8px;
            line-height: 1.5;
        }
        .movie-plot {
            font-style: italic;
            color: #555;
            margin-bottom: 15px;
        }
        .tech-specs {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px dashed #ddd;
        }
        .section-title {
            font-weight: bold;
            margin: 15px 0 5px 0;
            color: #444;
            border-bottom: 1px solid #eee;
            padding-bottom: 3px;
        }
        .actor-character {
            color: #666;
            font-size: 0.9em;
        }
    </style>

    
        <div class='movie-poster pull-left'>
            <a href='" . htmlspecialchars($IMDBUrl, ENT_QUOTES) . "' target='_blank' title='" . htmlspecialchars($Title, ENT_QUOTES) . "'>
                <img src='" . htmlspecialchars($ss, ENT_QUOTES) . "' class='rounded' alt='" . htmlspecialchars($Title, ENT_QUOTES) . "' width='200'>
            </a>
            " . ($PhotosCount ? "<div class='text-center small'>Photos: {$PhotosCount}</div>" : "") . "
            " . ($VideosCount ? "<div class='text-center small'>Videos: {$VideosCount}</div>" : "") . "
        </div>

        <h3 class='movie-title'>
            <a href='" . htmlspecialchars($IMDBUrl, ENT_QUOTES) . "' target='_blank' title='" . htmlspecialchars($Title, ENT_QUOTES) . "'>" . htmlspecialchars($Title) . " (" . htmlspecialchars($Year) . ")</a>
            " . ($Rated ? "<span class='badge-extra'>" . htmlspecialchars($Rated) . "</span>" : "") . "
            " . ($Certificate ? "<span class='badge-extra'>" . htmlspecialchars($Certificate) . "</span>" : "") . "
        </h3>

        <div class='movie-details'>
            " . ($Tagline ? "<p class='movie-plot'><em>" . nl2br(htmlspecialchars($Tagline)) . "</em></p>" : "") . "
            <p class='movie-plot'>" . nl2br(htmlspecialchars($Plot)) . "</p>
            " . ($Synopsis ? "<p class='movie-plot'><strong>Synopsis:</strong> " . nl2br(htmlspecialchars($Synopsis)) . "</p>" : "") . "

            <div class='movie-info'>
                " . ($imdbRating ? "
                <div>
                    <i class='fa fa-star' style='color: #f5c518;'></i> <strong>IMDb Rating:</strong> 
                    <span class='badge-extra'>
                        {$imdbRating}/10" . ($imdbVotes ? " (" . number_format($imdbVotes) . " votes)" : "") . "
                    </span>
                </div>" : "") . "

                " . ($Released ? "
                <div>
                    <i class='fa fa-calendar'></i> <strong>Released:</strong> {$Released}
                </div>" : "") . "

                " . ($RuntimeRaw ? "
                <div>
                    <i class='fa fa-clock-o'></i> <strong>Runtime:</strong> {$RuntimeRaw}" . ($RuntimeMinutes ? " ({$RuntimeMinutes} min)" : "") . "
                </div>" : "") . "

                " . ($Genre ? "
                <div>
                    <i class='fa fa-film'></i> <strong>Genres:</strong> {$Genre}
                </div>" : "") . "

                " . (!empty($Tags) ? "
                <div>
                    <i class='fa fa-tags'></i> <strong>Tags:</strong> {$Tags}
                </div>" : "") . "

                " . ($Country ? "
                <div>
                    <i class='fa fa-flag'></i> <strong>Countries:</strong> {$Country}
                </div>" : "") . "

                " . ($Language ? "
                <div>
                    <i class='fa fa-comment-o'></i> <strong>Languages:</strong> {$Language}
                </div>" : "") . "

                " . ($AlsoKnownAs ? "
                <div>
                    <i class='fa fa-language'></i> <strong>Also Known As:</strong><br>{$AlsoKnownAs}
                </div>" : "") . "

                " . ($Production ? "
                <div>
                    <i class='fa fa-briefcase'></i> <strong>Production:</strong> {$Production}
                </div>" : "") . "

                " . ($FilmingLocations ? "
                <div>
                    <i class='fa fa-map-marker'></i> <strong>Filming Locations:</strong> {$FilmingLocations}
                </div>" : "") . "

                <div class='section-title'>Creative Team</div>

                " . ($Director ? "
                <div>
                    <i class='fa fa-user'></i> <strong>Director(s):</strong> {$Director}
                </div>" : "") . "

                " . ($Writer ? "
                <div>
                    <i class='fa fa-pencil'></i> <strong>Writer(s):</strong> {$Writer}
                </div>" : "") . "

                " . ($Actors ? "
                <div>
                    <i class='fa fa-users'></i> <strong>Main Cast:</strong> {$Actors}
                </div>" : "") . "
				
                " . (!empty($FullCast) ? "
                <div>
                    <i class='fa fa-users'></i> <strong>Full Cast:</strong><br>{$FullCast}
                </div>" : "") . "

                <div class='section-title'>Awards & Recognition</div>

                " . ($Awards ? "
                <div>
                    <i class='fa fa-trophy'></i> <strong>Awards:</strong> " . nl2br(htmlspecialchars($Awards)) . "
                </div>" : "") . "

                <div class='section-title'>Financial Information</div>

                " . ($Budget ? "
                <div>
                    <i class='fa fa-money'></i> <strong>Budget:</strong> {$Budget}
                </div>" : "") . "

                " . ($OpeningWeekend ? "
                <div>
                    <i class='fa fa-line-chart'></i> <strong>Opening Weekend:</strong> {$OpeningWeekend}
                </div>" : "") . "

                " . ($GrossUS ? "
                <div>
                    <i class='fa fa-dollar'></i> <strong>Gross US:</strong> {$GrossUS}
                </div>" : "") . "

                " . ($BoxOffice ? "
                <div>
                    <i class='fa fa-ticket'></i> <strong>Worldwide Gross:</strong> {$BoxOffice}
                </div>" : "") . "

                " . ($Official ? "
                <div>
                    <i class='fa fa-globe'></i> <strong>Official Site:</strong> <a href='" . htmlspecialchars($Official, ENT_QUOTES) . "' target='_blank'>" . htmlspecialchars($Official, ENT_QUOTES) . "</a>
                </div>" : "") . "

                " . ($TrailerUrl ? "
                <div>
                    <i class='fa fa-youtube-play'></i> <strong>Trailer:</strong> <a href='" . htmlspecialchars($TrailerUrl, ENT_QUOTES) . "' target='_blank'>Watch on IMDb</a>
                </div>" : "") . "

                <div class='section-title'>Technical Specifications</div>

                " . ($Color ? "
                <div>
                    <i class='fa fa-paint-brush'></i> <strong>Color:</strong> {$Color}
                </div>" : "") . "

                " . ($SoundMix ? "
                <div>
                    <i class='fa fa-volume-up'></i> <strong>Sound Mix:</strong> {$SoundMix}
                </div>" : "") . "

                " . ($AspectRatio ? "
                <div>
                    <i class='fa fa-expand'></i> <strong>Aspect Ratio:</strong> {$AspectRatio}
                </div>" : "") . "

                " . ($NegativeFormat ? "
                <div>
                    <i class='fa fa-camera'></i> <strong>Negative Format:</strong> {$NegativeFormat}
                </div>" : "") . "
            </div>
        </div>
        <div class='clearfix'></div>
    
    ";

} catch (Exception $e) {
    $t_link = "<div class='alert alert-error'>Error fetching IMDb data: " . htmlspecialchars($e->getMessage()) . "</div>";
    error_log("IMDb Parser Error: " . $e->getMessage());
}

?>