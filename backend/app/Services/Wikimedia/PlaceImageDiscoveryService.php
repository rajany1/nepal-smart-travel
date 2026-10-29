<?php

namespace App\Services\Wikimedia;

use App\Models\Place;
use App\Services\PlaceImageMatcher;
use App\Services\PlaceImageService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Orchestrates Wikimedia Commons image discovery for one place.
 *
 * Strategies are tried strongest-first (explicit Commons tag / Wikidata,
 * then Wikipedia, exact-name search, geosearch). Every candidate is scored by
 * PlaceImageMatcher before it is downloaded; a wrong photo is worse than no
 * photo. Accepted files are mirrored to the public disk so the API never
 * hotlinks upload.wikimedia.org.
 */
class PlaceImageDiscoveryService
{
    private const DOWNLOAD_MIME_EXT = [
        'image/jpeg' => 'jpg',
        'image/jpg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    private const MAX_TITLES_PER_QUERY = 50;

    private ?string $wikidataQid = null;

    private ?array $wikidataEntity = null;

    public function __construct(
        private WikimediaClient $client,
        private PlaceImageMatcher $matcher,
        private PlaceImageService $images,
    ) {}

    /**
     * Discover and persist images for one place.
     *
     * @return array{inspected:int,stored:int,duplicates:int,download_failures:int}
     */
    public function discover(Place $place): array
    {
        $place->loadMissing('category');
        $this->wikidataQid = null;
        $this->wikidataEntity = null;

        $min = (int) config('images.min_per_place', 3);
        $max = (int) config('images.max_per_place', 10);
        $existing = $this->images->approvedCount($place->id);

        if ($existing >= $min) {
            return ['inspected' => 0, 'stored' => 0, 'duplicates' => 0, 'download_failures' => 0];
        }

        $this->images->markRunning($place);

        $accepted = [];
        $seen = [];
        $inspected = 0;
        $downloadFailures = 0;
        $rateLimited = null;
        $error = null;

        try {
            foreach ((array) config('images.strategies', []) as $strategy) {
                if ($existing + count($accepted) >= $max) {
                    break;
                }

                $candidates = $this->candidatesFor($place, (string) $strategy);
                $scored = [];

                foreach ($candidates as $candidate) {
                    $key = (string) ($candidate['source_id'] ?? $candidate['sha1'] ?? $candidate['url'] ?? '');
                    if ($key === '' || isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;
                    $inspected++;

                    $result = $this->matcher->evaluate($place, $candidate);
                    if (! ($result['accepted'] ?? false)) {
                        Log::debug('Place image candidate rejected', [
                            'place_id' => $place->id,
                            'title' => $candidate['title'] ?? null,
                            'via' => $candidate['via'] ?? null,
                            'reason' => $result['reasons'][0] ?? 'unknown',
                        ]);

                        continue;
                    }

                    $scored[] = ['candidate' => $candidate, 'score' => (float) $result['score']];
                }

                // Best photo first: it becomes the primary gallery image.
                usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

                foreach ($scored as $row) {
                    if ($existing + count($accepted) >= $max
                        || $existing + count($accepted) >= $min) {
                        break 2;
                    }

                    $localPath = $this->download($place, $row['candidate']);
                    if ($localPath === null) {
                        $downloadFailures++;

                        continue;
                    }

                    $row['candidate']['url'] = $localPath;
                    $row['candidate']['thumbnail_url'] = $localPath;
                    $accepted[] = $row;
                }
            }
        } catch (RateLimitExceededException $e) {
            $rateLimited = $e;
        } catch (Throwable $e) {
            $error = $e;
        }

        $batch = $this->images->storeBatch($place, $accepted);

        $total = $existing + $batch['stored'];
        if ($error !== null) {
            $this->images->finalize($place, $batch['stored'], $error->getMessage());
            throw $error;
        }

        if ($rateLimited !== null && $total < $min) {
            $this->images->finalize($place, $batch['stored'], $rateLimited->getMessage());
            throw $rateLimited;
        }

        if ($accepted === [] && $downloadFailures > 0) {
            $this->images->finalize($place, 0, 'all accepted candidates failed to download');
        } else {
            $this->images->finalize($place, $batch['stored'], null);
        }

        Log::info('Place image discovery finished', [
            'place_id' => $place->id,
            'inspected' => $inspected,
            'stored' => $batch['stored'],
            'duplicates' => $batch['duplicates'],
            'download_failures' => $downloadFailures,
        ]);

        return [
            'inspected' => $inspected,
            'stored' => $batch['stored'],
            'duplicates' => $batch['duplicates'],
            'download_failures' => $downloadFailures,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function candidatesFor(Place $place, string $strategy): array
    {
        return match ($strategy) {
            'commons_tag' => $this->fromCommonsTag($place),
            'wikidata_id' => $this->fromWikidata($place),
            'wikipedia' => $this->fromWikipedia($place),
            'text_search' => $this->fromTextSearch($place),
            'geosearch' => $this->fromGeosearch($place),
            default => [],
        };
    }

    /** Strategy 1: the explicit OSM `wikimedia_commons` tag. */
    private function fromCommonsTag(Place $place): array
    {
        $tag = trim((string) $place->commons_category);
        if ($tag === '') {
            return [];
        }

        if (preg_match('#^https?://commons\.wikimedia\.org/wiki/(.+)$#i', $tag, $match) === 1) {
            $tag = urldecode($match[1]);
        }
        $tag = trim(str_replace('_', ' ', $tag));

        if (stripos($tag, 'File:') === 0) {
            return $this->fetchImageInfo([$tag], 'commons_tag');
        }

        if (stripos($tag, 'Category:') !== 0) {
            $tag = 'Category:'.$tag;
        }

        $payload = $this->client->api('commons', [
            'action' => 'query',
            'list' => 'categorymembers',
            'cmtitle' => $tag,
            'cmtype' => 'file',
            'cmlimit' => self::MAX_TITLES_PER_QUERY,
        ]);

        $titles = [];
        foreach (($payload['query']['categorymembers'] ?? []) as $member) {
            $title = (string) ($member['title'] ?? '');
            if (stripos($title, 'File:') === 0) {
                $titles[] = $title;
            }
        }

        return $this->fetchImageInfo($titles, 'commons_tag');
    }

    /** Strategy 2: Wikidata P18/P373 and sitelinks. */
    private function fromWikidata(Place $place): array
    {
        $qid = trim((string) $place->wikidata_id);
        if ($qid === '' || preg_match('/^Q\d+$/i', $qid) !== 1) {
            return [];
        }

        $entity = $this->wikidataEntity($qid);
        if ($entity === null) {
            return [];
        }

        $candidates = [];
        $fileTitles = [];

        $p18 = $entity['claims']['P18'] ?? [];
        foreach ((array) $p18 as $claim) {
            $filename = $claim['mainsnak']['datavalue']['value'] ?? null;
            if (is_string($filename) && trim($filename) !== '') {
                $fileTitles[] = 'File:'.trim($filename);
            }
        }

        if ($fileTitles !== []) {
            $candidates = array_merge($candidates, $this->fetchImageInfo($fileTitles, 'wikidata_id'));
        }

        $category = null;
        foreach ((array) ($entity['claims']['P373'] ?? []) as $claim) {
            $value = $claim['mainsnak']['datavalue']['value'] ?? null;
            if (is_string($value) && trim($value) !== '') {
                $category = trim($value);
                break;
            }
        }

        if ($category !== null) {
            $payload = $this->client->api('commons', [
                'action' => 'query',
                'list' => 'categorymembers',
                'cmtitle' => 'Category:'.$category,
                'cmtype' => 'file',
                'cmlimit' => self::MAX_TITLES_PER_QUERY,
            ]);

            $titles = [];
            foreach (($payload['query']['categorymembers'] ?? []) as $member) {
                $title = (string) ($member['title'] ?? '');
                if (stripos($title, 'File:') === 0) {
                    $titles[] = $title;
                }
            }
            $candidates = array_merge($candidates, $this->fetchImageInfo($titles, 'commons_tag'));
        }

        if (trim((string) $place->wikipedia_title) === '') {
            $sitelink = $entity['sitelinks']['enwiki']['title']
                ?? $entity['sitelinks']['newiki']['title']
                ?? null;

            if (is_string($sitelink) && $sitelink !== '') {
                $endpoint = isset($entity['sitelinks']['enwiki']['title']) ? 'wikipedia' : 'wikipedia_ne';
                $candidates = array_merge($candidates, $this->pageImageCandidates($sitelink, $endpoint, 'wikipedia'));
            }
        }

        return $candidates;
    }

    /** Strategy 3: the OSM `wikipedia` tag. */
    private function fromWikipedia(Place $place): array
    {
        $raw = trim((string) $place->wikipedia_title);
        if ($raw === '') {
            return [];
        }

        if (preg_match('#^https?://([a-z-]+)\.wikipedia\.org/wiki/(.+)$#i', $raw, $match) === 1) {
            $lang = strtolower($match[1]);
            $title = urldecode($match[2]);
        } elseif (preg_match('/^([a-z-]{2,5}):(.+)$/i', $raw, $match) === 1) {
            $lang = strtolower($match[1]);
            $title = $match[2];
        } else {
            $lang = 'en';
            $title = $raw;
        }

        $endpoint = match ($lang) {
            'en' => 'wikipedia',
            'ne' => 'wikipedia_ne',
            default => null,
        };

        if ($endpoint === null || trim($title) === '') {
            return [];
        }

        return $this->pageImageCandidates(trim(str_replace('_', ' ', $title)), $endpoint, 'wikipedia');
    }

    /** Strategy 4: exact name + district text search on Commons. */
    private function fromTextSearch(Place $place): array
    {
        if ($this->isGenericName($place->name)) {
            return [];
        }

        $search = trim((string) $place->name);
        $district = trim((string) $place->district);
        if ($district !== '') {
            $search .= ' '.$district;
        }
        $search .= ' filetype:bitmap';

        $payload = $this->client->api('commons', [
            'action' => 'query',
            'list' => 'search',
            'srsearch' => $search,
            'srnamespace' => 6,
            'srlimit' => 20,
        ]);

        $titles = [];
        foreach (($payload['query']['search'] ?? []) as $hit) {
            $title = (string) ($hit['title'] ?? '');
            if (stripos($title, 'File:') === 0) {
                $titles[] = $title;
            }
        }

        return $this->fetchImageInfo($titles, 'text_search');
    }

    /** Strategy 5: geotagged Commons files around the place. */
    private function fromGeosearch(Place $place): array
    {
        if ($this->isGenericName($place->name)) {
            return [];
        }

        $lat = (float) $place->latitude;
        $lng = (float) $place->longitude;
        if ($lat === 0.0 && $lng === 0.0) {
            return [];
        }

        $radiusKm = (float) config('images.geo_radius_km.'.$this->distanceGroup($place), config('images.geo_radius_km.default', 10));
        $radiusMeters = (int) min(10000, max(100, $radiusKm * 1000));

        $payload = $this->client->api('commons', [
            'action' => 'query',
            'list' => 'geosearch',
            'gscoord' => $lat.'|'.$lng,
            'gsradius' => $radiusMeters,
            'gsnamespace' => 6,
            'gslimit' => 50,
        ]);

        $titles = [];
        $photoCoords = [];
        foreach (($payload['query']['geosearch'] ?? []) as $hit) {
            $title = (string) ($hit['title'] ?? '');
            if (stripos($title, 'File:') !== 0) {
                continue;
            }
            $titles[] = $title;
            $photoCoords[preg_replace('/^File:/i', '', $title)] = [
                'lat' => isset($hit['lat']) ? (float) $hit['lat'] : null,
                'lng' => isset($hit['lon']) ? (float) $hit['lon'] : null,
            ];
        }

        $candidates = $this->fetchImageInfo($titles, 'geosearch');

        foreach ($candidates as &$candidate) {
            $coords = $photoCoords[$candidate['title'] ?? ''] ?? null;
            if ($coords === null) {
                continue;
            }
            if ($candidate['photo_lat'] === null) {
                $candidate['photo_lat'] = $coords['lat'];
            }
            if ($candidate['photo_lng'] === null) {
                $candidate['photo_lng'] = $coords['lng'];
            }
        }
        unset($candidate);

        return $candidates;
    }

    /** Fetch Wikipedia's lead image and resolve its Commons file page. */
    private function pageImageCandidates(string $title, string $endpoint, string $via): array
    {
        $payload = $this->client->api($endpoint, [
            'action' => 'query',
            'prop' => 'pageimages',
            'piprop' => 'original|pageimage',
            'titles' => $title,
            'redirects' => 1,
        ]);

        $fileTitles = [];
        foreach (($payload['query']['pages'] ?? []) as $page) {
            $pageImage = (string) ($page['pageimage'] ?? '');
            if ($pageImage !== '') {
                $fileTitles[] = stripos($pageImage, 'File:') === 0 ? $pageImage : 'File:'.$pageImage;
            }
        }

        if ($fileTitles === []) {
            return [];
        }

        $candidates = $this->fetchImageInfo($fileTitles, $via, 'commons');
        if ($candidates !== []) {
            return $candidates;
        }

        return $this->fetchImageInfo($fileTitles, $via, $endpoint);
    }

    /**
     * Resolve file titles into normalised candidates via `prop=imageinfo`.
     *
     * @param  string[]  $titles
     * @return array<int, array<string, mixed>>
     */
    private function fetchImageInfo(array $titles, string $via, string $endpoint = 'commons'): array
    {
        $titles = array_values(array_unique(array_filter($titles, fn ($t) => is_string($t) && trim($t) !== '')));
        if ($titles === []) {
            return [];
        }

        $titles = array_slice($titles, 0, self::MAX_TITLES_PER_QUERY);
        $payload = $this->client->api($endpoint, [
            'action' => 'query',
            'titles' => implode('|', $titles),
            'prop' => 'imageinfo|categories',
            'iiprop' => 'url|size|mime|extmetadata|sha1',
            'iiurlwidth' => (int) config('images.thumb_width', 1200),
            'cllimit' => 'max',
        ]);

        if ($payload === null && $endpoint !== 'commons') {
            $payload = $this->client->api('commons', [
                'action' => 'query',
                'titles' => implode('|', $titles),
                'prop' => 'imageinfo|categories',
                'iiprop' => 'url|size|mime|extmetadata|sha1',
                'iiurlwidth' => (int) config('images.thumb_width', 1200),
                'cllimit' => 'max',
            ]);
            $endpoint = 'commons';
        }

        $candidates = [];
        foreach (($payload['query']['pages'] ?? []) as $page) {
            $info = $page['imageinfo'][0] ?? null;
            if (! is_array($info)) {
                continue;
            }

            $pageTitle = (string) ($page['title'] ?? '');
            $meta = is_array($info['extmetadata'] ?? null) ? $info['extmetadata'] : [];

            $url = (string) ($info['thumburl'] ?? $info['url'] ?? '');
            if ($url === '') {
                continue;
            }

            $license = $this->metaText($meta, 'LicenseShortName')
                ?: $this->metaText($meta, 'UsageTerms')
                ?: $this->metaText($meta, 'License');

            $author = $this->metaText($meta, 'Artist') ?: $this->metaText($meta, 'Credit');
            $attribution = $this->metaText($meta, 'Attribution')
                ?: $this->metaText($meta, 'Credit')
                ?: trim($author.($license !== '' ? ' / '.$license : '').' (Wikimedia Commons)');

            $categories = [];
            foreach (($page['categories'] ?? []) as $category) {
                $categoryTitle = (string) ($category['title'] ?? '');
                if ($categoryTitle !== '') {
                    $categories[] = $categoryTitle;
                }
            }

            $pageId = (string) ($page['pageid'] ?? sha1($pageTitle));
            $sourceBase = $endpoint === 'wikipedia_ne'
                ? 'https://ne.wikipedia.org'
                : ($endpoint === 'wikipedia' ? 'https://en.wikipedia.org' : 'https://commons.wikimedia.org');

            $candidates[] = [
                'url' => $url,
                'thumbnail_url' => $url,
                'original_url' => (string) ($info['url'] ?? ''),
                'width' => (int) ($info['width'] ?? 0),
                'height' => (int) ($info['height'] ?? 0),
                'mime' => (string) ($info['mime'] ?? ''),
                'sha1' => (string) ($info['sha1'] ?? ''),
                'title' => preg_replace('/^File:/i', '', $pageTitle),
                'object_name' => $this->metaText($meta, 'ObjectName'),
                'description' => $this->metaText($meta, 'ImageDescription'),
                'categories' => $categories,
                'license' => $license,
                'license_url' => $this->metaText($meta, 'LicenseUrl'),
                'author' => $author,
                'attribution' => $attribution,
                'source_id' => ($endpoint === 'commons' ? 'commons:' : 'wiki:').$pageId,
                'source_page_url' => $sourceBase.'/?curid='.rawurlencode($pageId),
                'photo_lat' => $this->metaCoordinate($meta, 'GPSLatitude'),
                'photo_lng' => $this->metaCoordinate($meta, 'GPSLongitude'),
                'via' => $via,
            ];
        }

        return $candidates;
    }

    /**
     * Download an accepted candidate to the public disk and return its
     * relative path. Only official Wikimedia media hosts are ever fetched.
     */
    private function download(Place $place, array $candidate): ?string
    {
        $url = (string) ($candidate['url'] ?? '');
        if (preg_match('#^https://(?:upload|thumb)\.wikimedia\.org/#i', $url) !== 1) {
            return null;
        }

        $mime = strtolower((string) ($candidate['mime'] ?? ''));
        $extension = self::DOWNLOAD_MIME_EXT[$mime] ?? null;
        if ($extension === null) {
            return null;
        }

        $hash = (string) ($candidate['sha1'] ?? '');
        if (preg_match('/^[a-f0-9]{40}$/i', $hash) !== 1) {
            $hash = sha1($url);
        }

        $diskName = (string) config('images.download.disk', 'public');
        $basePath = trim((string) config('images.download.path', 'places'), '/');
        $path = $basePath.'/'.$place->id.'/wm-'.strtolower($hash).'.'.$extension;

        $disk = Storage::disk($diskName);
        if ($disk->exists($path) && (int) $disk->size($path) > 0) {
            return $path;
        }

        $maxBytes = (int) config('images.download.max_bytes', 12 * 1024 * 1024);

        try {
            $response = Http::withHeaders([
                'User-Agent' => (string) config('images.user_agent'),
                'Accept' => 'image/*',
            ])
                ->timeout((int) config('images.timeout', 20))
                ->get($url);
        } catch (Throwable $e) {
            Log::warning('Place image download failed', [
                'place_id' => $place->id,
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('Place image download returned HTTP error', [
                'place_id' => $place->id,
                'url' => $url,
                'status' => $response->status(),
            ]);

            return null;
        }

        $contentType = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));
        if (! isset(self::DOWNLOAD_MIME_EXT[$contentType]) || $contentType !== $mime) {
            Log::warning('Place image download content-type mismatch', [
                'place_id' => $place->id,
                'expected' => $mime,
                'received' => $contentType,
            ]);

            return null;
        }

        $body = $response->body();
        if ($body === '' || strlen($body) > $maxBytes) {
            return null;
        }

        if (function_exists('getimagesizefromstring')) {
            $dimensions = @getimagesizefromstring($body);
            if ($dimensions === false
                || $dimensions[0] < (int) config('images.min_width', 400)
                || $dimensions[1] < (int) config('images.min_height', 300)) {
                return null;
            }
        }

        if (! $disk->put($path, $body)) {
            return null;
        }

        return $path;
    }

    private function wikidataEntity(string $qid): ?array
    {
        if ($this->wikidataQid === $qid) {
            return $this->wikidataEntity;
        }

        $payload = $this->client->api('wikidata', [
            'action' => 'wbgetentities',
            'ids' => $qid,
            'props' => 'claims|sitelinks',
            'sitefilter' => 'enwiki|newiki|commonswiki',
        ]);

        $this->wikidataQid = $qid;
        $this->wikidataEntity = $payload['entities'][$qid] ?? null;

        return $this->wikidataEntity;
    }

    private function metaText(array $meta, string $key): string
    {
        $value = $meta[$key]['value'] ?? null;
        if (! is_scalar($value)) {
            return '';
        }

        $text = html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = (string) preg_replace('/\s+/u', ' ', $text);

        return trim($text);
    }

    private function metaCoordinate(array $meta, string $key): ?float
    {
        $value = $this->metaText($meta, $key);
        if ($value === '' || ! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    private function isGenericName(?string $name): bool
    {
        $normalised = mb_strtolower(trim((string) $name));
        if ($normalised === '') {
            return true;
        }

        return in_array($normalised, array_map('mb_strtolower', (array) config('images.generic_names', [])), true);
    }

    private function distanceGroup(Place $place): string
    {
        $category = mb_strtolower((string) ($place->category?->name ?? ''));

        return in_array($category, ['nature', 'viewpoint', 'trekking', 'peak'], true)
            ? 'nature'
            : 'default';
    }
}
