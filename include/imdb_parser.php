<?php
declare(strict_types=1);

// ── Извлечение IMDb ID ────────────────────────────────────────────────────────
if (!preg_match('#https?://www\.imdb\.com/title/(tt\d+)/#i', $t_link, $_id_)) {
    exit('Invalid IMDb URL');
}
$imdbId = $_id_[1];

include_once INC_PATH . '/IMDB.php';

// ── Хелперы ───────────────────────────────────────────────────────────────────

/** Безопасный htmlspecialchars */
function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

/** Строка из массива через join или '' */
function implode_field(array $data, string $key, string $glue = ', '): string
{
    return !empty($data[$key]) ? implode($glue, (array)$data[$key]) : '';
}

/** Строка-поле из массива с fallback */
function str_field(array $data, string ...$keys): string
{
    foreach ($keys as $key) {
        if (!empty($data[$key])) return (string)$data[$key];
    }
    return '';
}

/** Условный HTML-блок — возвращает '' если $value пустой */
function when(string $value, string $html): string
{
    return $value !== '' ? $html : '';
}

/** Условный section-title + блок */
function section(string $title, string ...$blocks): string
{
    $content = implode('', $blocks);
    if ($content === '') return '';
    return "<div class='section-title'>{$title}</div>{$content}";
}

/** Одна info-строка */
function info_row(string $icon, string $label, string $value): string
{
    if ($value === '') return '';
    return "<div><i class='fa {$icon}'></i> <strong>{$label}:</strong> {$value}</div>";
}

// ── Парсинг runtime ───────────────────────────────────────────────────────────
function parse_runtime(string $raw): ?int
{
    if (preg_match('/(\d+)\s*h(?:ou)?r?s?\s*(\d+)?\s*m(?:in)?s?/i', $raw, $m)) {
        return (int)$m[1] * 60 + (int)($m[2] ?? 0);
    }
    if (preg_match('/(\d+)\s*m(?:in)?s?/i', $raw, $m)) {
        return (int)$m[1];
    }
    return null;
}

// ── Основная логика ───────────────────────────────────────────────────────────
try {
    $data = (new IMDB($t_link))->parse();

    $Title      = str_field($data, 'title');
    $Year       = str_field($data, 'year');
    $Rated      = str_field($data, 'content_rating');
    $Released   = str_field($data, 'release_date');
    $RuntimeRaw = str_field($data, 'runtime');
    $RuntimeMin = parse_runtime($RuntimeRaw);
    $Plot       = str_field($data, 'plot');
    $Tagline    = str_field($data, 'tagline');
    $Language   = implode_field($data, 'languages');
    $Country    = implode_field($data, 'countries');
    $Production = implode_field($data, 'production_companies');
    $Genre      = implode_field($data, 'genres');
    $Tags       = implode_field($data, 'tags');
    $Official   = str_field($data, 'official_site');
    $FilmLoc    = str_field($data, 'filming_locations');
    $AlsoKnown  = !empty($data['also_known_as']) ? implode('<br>', $data['also_known_as']) : '';

    $imdbRating = str_field($data, 'rating');
    $imdbVotes  = str_field($data, 'rating_count');
    $IMDBUrl    = "https://www.imdb.com/title/{$imdbId}/";

    // Творческая группа
    $Director = !empty($data['credits']['directors'])
        ? implode(', ', array_column($data['credits']['directors'], 'name'))
        : '';
    $Writer = !empty($data['credits']['writers'])
        ? implode(', ', array_column($data['credits']['writers'], 'name'))
        : '';

    $cast    = $data['cast'] ?? [];
    $Actors  = !empty($cast)
        ? implode(', ', array_column(array_slice($cast, 0, 7), 'actor'))
          . (count($cast) > 7 ? ', ...' : '')
        : '';

    $FullCast = '';
    foreach (array_slice($cast, 0, 10) as $a) {
        $FullCast .= '<strong>' . h($a['actor'] ?? '') . '</strong> as ' . h($a['character'] ?? '') . '<br>';
    }
    if (count($cast) > 10) {
        $FullCast .= '... and ' . (count($cast) - 10) . ' more';
    }

    // Награды
    $Awards = '';
    if (isset($data['awards']) && is_array($data['awards'])) {
        $wins = $data['awards']['total_wins'] ?? 0;
        $noms = $data['awards']['total_nominations'] ?? 0;
        $Awards = "Won {$wins} awards &amp; {$noms} nominations";
        if (!empty($data['awards']['oscars'])) {
            $ow = $data['awards']['oscars']['wins'] ?? 0;
            $on = $data['awards']['oscars']['nominations'] ?? 0;
            $Awards .= "<br>Oscars: {$ow} wins, {$on} nominations";
        }
    } else {
        $Awards = str_field($data, 'awards');
    }

    // Финансы
    $box        = $data['box_office'] ?? [];
    $Budget     = str_field($box, 'budget');
    $BoxOffice  = str_field($box, 'gross_worldwide');
    $Opening    = str_field($box, 'opening_weekend');
    $GrossUS    = str_field($box, 'gross_us');

    // Техспеки
    $tech        = $data['technical_specs'] ?? [];
    $Color       = str_field($tech, 'Color', 'color');
    $SoundMix    = str_field($tech, 'Sound Mix', 'Sound mix', 'sound_mix');
    $AspectRatio = str_field($tech, 'Aspect Ratio', 'Aspect ratio', 'aspect_ratio');
    $NegFmt      = str_field($tech, 'Negative Format', 'negative_format');

    // Storyline
    $story    = $data['storyline'] ?? [];
    $Synopsis = str_field($story, 'synopsis');
    $Cert     = str_field($story, 'Certificate', 'certificate');

    // Медиа
    $media      = $data['media'] ?? [];
    $PhotosCnt  = str_field($media, 'photos_count');
    $VideosCnt  = str_field($media, 'videos_count');
    $TrailerUrl = str_field($media, 'trailer_url');

    // Постер
    $Poster = preg_replace('#\._V1_.*?\.(jpg|png|jpeg)$#i', '.$1', str_field($data, 'poster'));
    $ss     = $Poster;

    // ── HTML ──────────────────────────────────────────────────────────────────
    $ratingHtml = when($imdbRating,
        "<div><i class='fa fa-star' style='color:#f5c518'></i> <strong>IMDb Rating:</strong>
         <span class='badge-extra'>{$imdbRating}/10"
         . when($imdbVotes, ' (' . number_format((int)$imdbVotes) . ' votes)') .
        "</span></div>"
    );

    $runtimeHtml = when($RuntimeRaw,
        "<div><i class='fa fa-clock-o'></i> <strong>Runtime:</strong> {$RuntimeRaw}"
        . ($RuntimeMin !== null ? " ({$RuntimeMin} min)" : '') .
        "</div>"
    );

    $t_link = "
<style>
.block-titled{border:1px solid #ddd;padding:15px;margin-bottom:20px;border-radius:4px;background:#fff;font-family:Arial,sans-serif}
.movie-poster{float:left;margin-right:15px;width:200px}
.movie-title{margin-top:0;color:#337ab7;font-size:22px}
.movie-details{overflow:hidden}
.badge-extra{background:#f5f5f5;padding:3px 6px;border-radius:3px;margin-right:5px;display:inline-block}
.clearfix{clear:both}
.movie-info div{margin-bottom:8px;line-height:1.5}
.movie-plot{font-style:italic;color:#555;margin-bottom:15px}
.section-title{font-weight:bold;margin:15px 0 5px;color:#444;border-bottom:1px solid #eee;padding-bottom:3px}
</style>

<div class='block-titled'>
    <div class='movie-poster'>
        <a href='" . h($IMDBUrl) . "' target='_blank' title='" . h($Title) . "'>
            <img src='" . h($ss) . "' class='rounded' alt='" . h($Title) . "' width='200'>
        </a>
        " . when($PhotosCnt, "<div class='text-center small'>Photos: {$PhotosCnt}</div>") . "
        " . when($VideosCnt, "<div class='text-center small'>Videos: {$VideosCnt}</div>") . "
    </div>

    <h3 class='movie-title'>
        <a href='" . h($IMDBUrl) . "' target='_blank'>" . h($Title) . " (" . h($Year) . ")</a>
        " . when($Rated, "<span class='badge-extra'>" . h($Rated) . "</span>") . "
        " . when($Cert,  "<span class='badge-extra'>" . h($Cert)  . "</span>") . "
    </h3>

    <div class='movie-details'>
        " . when($Tagline,  "<p class='movie-plot'><em>" . nl2br(h($Tagline)) . "</em></p>") . "
        " . when($Plot,     "<p class='movie-plot'>" . nl2br(h($Plot)) . "</p>") . "
        " . when($Synopsis, "<p class='movie-plot'><strong>Synopsis:</strong> " . nl2br(h($Synopsis)) . "</p>") . "

        <div class='movie-info'>
            {$ratingHtml}
            " . info_row('fa-calendar', 'Released', $Released) . "
            {$runtimeHtml}
            " . info_row('fa-film',       'Genres',    $Genre) . "
            " . info_row('fa-tags',       'Tags',      $Tags) . "
            " . info_row('fa-flag',       'Countries', $Country) . "
            " . info_row('fa-comment-o',  'Languages', $Language) . "
            " . when($AlsoKnown, "<div><i class='fa fa-language'></i> <strong>Also Known As:</strong><br>{$AlsoKnown}</div>") . "
            " . info_row('fa-briefcase',  'Production',         $Production) . "
            " . info_row('fa-map-marker', 'Filming Locations',  $FilmLoc) . "

            " . section('Creative Team',
                info_row('fa-user',   'Director(s)', $Director),
                info_row('fa-pencil', 'Writer(s)',   $Writer),
                info_row('fa-users',  'Main Cast',   $Actors),
                when($FullCast, "<div><i class='fa fa-users'></i> <strong>Full Cast:</strong><br>{$FullCast}</div>")
            ) . "

            " . section('Awards &amp; Recognition',
                when($Awards, "<div><i class='fa fa-trophy'></i> <strong>Awards:</strong> " . nl2br(h($Awards)) . "</div>")
            ) . "

            " . section('Financial Information',
                info_row('fa-money',      'Budget',          $Budget),
                info_row('fa-line-chart', 'Opening Weekend', $Opening),
                info_row('fa-dollar',     'Gross US',        $GrossUS),
                info_row('fa-ticket',     'Worldwide Gross', $BoxOffice)
            ) . "

            " . info_row('fa-globe',        'Official Site', $Official ? "<a href='" . h($Official) . "' target='_blank'>" . h($Official) . "</a>" : '') . "
            " . when($TrailerUrl, "<div><i class='fa fa-youtube-play'></i> <strong>Trailer:</strong> <a href='" . h($TrailerUrl) . "' target='_blank'>Watch on IMDb</a></div>") . "

            " . section('Technical Specifications',
                info_row('fa-paint-brush', 'Color',          $Color),
                info_row('fa-volume-up',   'Sound Mix',      $SoundMix),
                info_row('fa-expand',      'Aspect Ratio',   $AspectRatio),
                info_row('fa-camera',      'Negative Format',$NegFmt)
            ) . "
        </div>
    </div>
    <div class='clearfix'></div>
</div>";

} catch (Exception $e) {
    $t_link = "<div class='alert alert-danger'>Error fetching IMDb data: " . h($e->getMessage()) . "</div>";
    error_log('IMDb Parser Error: ' . $e->getMessage());
}