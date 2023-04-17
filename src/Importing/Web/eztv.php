<?php
/*

* Documentation Links

      [The BrowserKit Component (Symfony Docs)](https://symfony.com/doc/current/components/browser_kit.html)
      [The CssSelector Component (Symfony Docs)](https://symfony.com/doc/current/components/css_selector.html)
      [The DomCrawler Component (Symfony Docs)](https://symfony.com/doc/current/components/dom_crawler.html)
      [HTTP Client (Symfony Docs)](https://symfony.com/doc/current/http_client.html)

* URLs to support

      /shows/$id/$seo/
      /showlist/
      /api/get-torrents?limit=100&page=$page
      /cat/tv-packs-1/page_$page

* TODO

      /shows/ still has 2 items left
          parsing the episodes
          parsing the per-episode tvmaze lniks form the torrent list
      /ep/$id/$seo/
          screenshot from right side
          episode / ep ssummary
          episode air date
          season #
          episode #
      Store the records in mysql
      Write a Scraper for TVMaze

*/

use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\BrowserKit\CookieJar;
use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpClient\CachingHttpClient;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpKernel\HttpCache\Store;

require_once __DIR__.'/../../bootstrap.php';
require_once __DIR__.'/FakeCacheHeaderClient.php';

/** @var \Workerman\MySQL\Connection */
global $db;
/** @var \mysqli */
global $mysqlLinkId;
/** @var \Symfony\Component\BrowserKit\HttpBrowser */
global $client;

$client = new HttpBrowser(
    $cachingClient = new CachingHttpClient(
        $fakeClient = new FakeCacheHeaderClient(
            $httpClient = HttpClient::create(['timeout' => 900, 'verify_peer' => false]
        )),
        $cacheStore = new Store(__DIR__.'/../../../data/http_cache/eztv'), ['default_ttl' => 2678400]),
    null,
    $cookieJar = new CookieJar()
);
$load = [
    'torrents' => true,
    'packs' => true,
    'shows' => true,
    'show' => true,
];
$sitePrefix = 'http://eztv.re';
$jsonFile = 'eztv.json';
$jsonSmallFile = 'eztv_shows_small.json';
$dataSmall = [
    'shows' => []
];
if (file_exists($jsonFile)) {
    echo "Loading data...";
    $data = json_decode(file_get_contents($jsonFile),true);
    echo "Loaded\n";
} else {
    $data = [
        'shows' => [],
        'packs' => [],
        'torrents' => [],
    ];
}
if ($load['shows'] == true) {
    echo 'Loading Shows:';
    [$code, $contentType, $crawler] = apiCall($sitePrefix.'/showlist/');
    $rows = $crawler->filter('.forum_header_border tr');
    echo "Adding ".$rows->count()." Shows";
    for ($idx = 4, $maxIdx = $rows->count(); $idx < $maxIdx; $idx++) {
        $row = $rows->eq($idx);
        $td = $row->filter('td');
        $href = explode('/', $td->filter('a')->attr('href'));
        $id = intval($href[2]);
        $show = [
            'name' => $td->filter('a')->text(),
            'seo' => $href[3],
            'status' => $td->filter('font')->attr('class'),
            'rating' => floatval($td->filter('b')->text()),
            'votes' => intval(preg_replace('/[^0-9]/', '', $td->filter('span')->text()))
        ];
        echo "Adding Show {$id} ".json_encode($show)."\n";
        $data['shows'][$id] = $show;
    }
    echo 'done, found '.count($data['shows']).' tv series'.PHP_EOL;
    file_put_contents('eztv_shows.json', json_encode($data['shows'], JSON_PRETTY_PRINT |  JSON_UNESCAPED_UNICODE |  JSON_UNESCAPED_SLASHES));
    file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT |  JSON_UNESCAPED_UNICODE |  JSON_UNESCAPED_SLASHES));
}
if ($load['torrents'] == true) {
    echo 'Loading Torrents:';
    for ($limit = 100, $pages = 0, $page = 1, $end = false; $end == false; $page++) {
        echo "{$page}/{$pages}, ";
        //$json = json_decode(file_get_contents('http://eztv.re/api/get-torrents?limit=100&page='.$page),true);
        [$code, $contentType, $json] = apiCall('http://eztv.re/api/get-torrents?limit=100&page='.$page);
        //$json = json_decode(file_get_contents('torrents_page_'.$page.'.json'),true);
        if (!isset($json['torrents_count'])) {
            echo "No torrent_count, skipping page {$page}\n";
            continue;
        }
        $pages = ceil($json['torrents_count'] / $limit);
        $end = $page >= $pages;
        echo "Adding ".count($json['torrents'])." Torrents";
        foreach ($json['torrents'] as $torrent) {
            $id = $torrent['id'];
            //echo 'Old:'.json_encode($torrent)."\n";
            $torrent['name'] = $torrent['title'];
            $torrent['seo'] = basename($torrent['episode_url']);
            $torrent['released'] = $torrent['date_released_unix'];
            $torrent['size'] = intval($torrent['size_bytes']);
            $torrent['season'] = intval($torrent['season']);
            $torrent['episode'] = intval($torrent['episode']);
            $torrent['image'] = $torrent['large_screenshot'];
            $torrent['magnet'] = $torrent['magnet_url'];
            if (isset($torrent['torrent_url']))
                $torrent['torrent'] = $torrent['torrent_url'];
            unset($torrent['id']);
            unset($torrent['title']);
            unset($torrent['date_released_unix']);
            unset($torrent['episode_url']);
            unset($torrent['size_bytes']);
            unset($torrent['large_screenshot']);
            unset($torrent['small_screenshot']);
            unset($torrent['torrent_url']);
            unset($torrent['magnet_url']);
            //echo 'New:'."Adding Torrent {$id} ".json_encode($torrent, JSON_PRETTY_PRINT)."\n";
            $data['torrents'][$id] = $torrent;
        }
    }
    echo 'done, found '.count($data['torrents']).' tv series'.PHP_EOL;
    file_put_contents('eztv_torrents.json', json_encode($data['torrents'], JSON_PRETTY_PRINT |  JSON_UNESCAPED_UNICODE |  JSON_UNESCAPED_SLASHES));
    file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT |  JSON_UNESCAPED_UNICODE |  JSON_UNESCAPED_SLASHES));
}
if ($load['packs'] == true) {
    echo 'Loading Packs:';
    for ($page = 1, $end = false; $end == false; $page++) {
        [$code, $contentType, $crawler] = apiCall($sitePrefix.'/cat/tv-packs-1/page_'.$page);
        $end = $crawler->filter("#header_holder table")->eq(4)->filter('td')->eq(1)->html() == '';
        $rows = $crawler->filter("#header_holder table")->eq(5)->filter('tr');
        echo " [{$page}] Adding ".$rows->count()." Packs";
        for ($idx = 2, $maxIdx = $rows->count(); $idx < $maxIdx; $idx++) {
            $row = $rows->eq($idx);
            $td = $row->filter('td');
            //echo $td->eq(2)->html()."\n".$td->count()."\n".$td->eq(2)->filter('a')->count();
            if ($td->count() == 0)
                die('Empty TD Nodes');
            $href = explode('/', $td->eq(0)->filter('a')->attr('href'));
            $id = intval($href[2]);
            $sizeParts = explode(' ', $td->eq(3)->text());
            $pack = [
                'name' => preg_replace('/ \[eztv\]$/', '', $td->eq(1)->filter('a')->text()),
                'seo' => $href[3],
                'seeds' => $td->eq(5)->text() == '-' ? 0 : intval($td->eq(5)->text()),
                'torrent_id' => intval(explode('/', $td->eq(1)->filter('a')->attr('href'))[2]),
                'magnet' => $td->eq(2)->filter('a')->eq(0)->attr('href')
            ];
            if ($td->count() > 0) {
                $pack['size'] = ceil(floatval($sizeParts[0]) * ($sizeParts[1] == 'MB' ? 1048576 : 1073741824));
                if ($td->eq(2)->filter('a')->count() == 2)
                    $pack['torrent'] = $td->eq(2)->filter('a')->eq(1)->attr('href');
            }
            //echo "Adding Pack {$id} ".json_encode($pack, JSON_PRETTY_PRINT)."\n";
            $data['packs'][$id] = $pack;
        }
    }
    echo 'done, found '.count($data['packs']).' tv packs'.PHP_EOL;
    file_put_contents('eztv_packs.json', json_encode($data['packs'], JSON_PRETTY_PRINT |  JSON_UNESCAPED_UNICODE |  JSON_UNESCAPED_SLASHES));
    file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT |  JSON_UNESCAPED_UNICODE |  JSON_UNESCAPED_SLASHES));
}
if ($load['show'] == true) {
    /*
    "271007": {
    "name": "$50K Three Ways",
    "seo": "50k-three-ways",
    "status": "ended",
    "rating": 8.7,
    "votes": 24
    },*/
    $showCount = count($data['shows']);
    $showIdx = 0;
    $maxTries = 5;
    $tryDelay = 10;
    $skip = false;
    $totalRetries = 0;
    $totalFailed = 0;
    foreach ($data['shows'] as $id => $show) {
        $showIdx++;
        $tries = 0;
        if ($skip !== false && $skip > $showIdx) {
            echo "Skipping id {$id}: {$skipIdx}/{$skip} skipped\n";
            continue;
        }
        $url = $sitePrefix.'/shows/'.$id.'/'.$show['seo'].'/';
        [$code, $contentType, $crawler] = apiCall($url);
        if ($code != 200) {
            while ($code != 200 && $tries < $maxTries) {
                echo "URL {$url} got error code {$code}, retry {$tries}/{$maxTries} tries after a {$tryDelay}sec delay...";
                sleep($tryDelay);
                echo "woke up, retrying\n";
                $tries++;
                $totalRetries++;
                [$code, $contentType, $crawler] = apiCall($url);
            }
            if ($code != 200) {
                echo "Couldnt get URL {$url} after {$tries} attempts, skipping!\n";
                $totalFailed++;
                continue;
            }
        }
        if ($crawler->filter('.show_info_main_logo img')->count() > 0)
            $show['image'] = $sitePrefix.$crawler->filter('.show_info_main_logo img')->attr('src');
        if ($crawler->filter('.show_info_banner_logo')->count() > 0) {
            $show['description'] = trim($crawler->filter('.show_info_banner_logo')->text());
        }
        if ($crawler->filter('.show_info_rating_score a')->count() > 0)
            $show['imdb'] = $crawler->filter('.show_info_rating_score a')->attr('href');
        $show['torrents'] = [];
        $rows = $torrents = $crawler->filter('a.epinfo');
        $idxMax = $rows->count();
        if ($idxMax > 0)
            for ($idx = 0; $idx < $idxMax; $idx++)
                $show['torrents'][] = intval(explode('/', $rows->eq($idx)->attr('href'))[2]);
        $show['cast'] = [];
        $td = $crawler->filter('.show_info_tvnews_column > div > table > tr > td');
        if ($td->count() > 0) {
            $castHtml = explode('<br>', trim($td->html()));
            foreach ($castHtml as $cast) {
                if (preg_match('/name[^>]*>([^<]*).*as (.*)/u', $cast, $matches)) {
                    $show['cast'][$matches[1]] = $matches[2];
                }
            }
        }
        $parts = explode('<div class="showinfo_header">', $crawler->filter('.show_info_description > tr:nth-child(2) td:nth-child(1)')->html());
        array_shift($parts);
        if (count($parts) > 0) {
            $parts[0] = str_replace('<div style="width: 537px; height: 250px; overflow-y: auto;">', '', $parts[0]);
            foreach ($parts as $part) {
                if (preg_match_all('/<h3>([^<]*)<\/h3><\/div>(<br>.*)<br>/msu', $part, $matches)) {
                    $type = $matches[1][0];
                    $typeData = $matches[2][0];
                    // make sure this follows the expected format
                    if (strtolower(substr($type, 0, strlen($show['name']))) == strtolower($show['name'])) {
                        $type = substr($type, strlen($show['name'])+3);
                    }
                    $type = strtolower($type);
                    if ($type == "general information") {
                        $typeData = explode('<br>', $typeData);
                        foreach ($typeData as $typeRow) {
                            if (preg_match('/^([^:]*): (.*)$/ui', $typeRow, $matches)) {
                                $key = strtolower($matches[1]);
                                if ($key == 'series premiere') {
                                    $start = strtotime($matches[2]);
                                    $show['start_date'] = $start === false ? $matches[2] : date('Y-m-d', $start);
                                } elseif ($key == 'genre') {
                                    $show[$key] = explode(' | ', $matches[2]);
                                } elseif ($key == 'runtime') {
                                    $show[$key] = is_null($matches[2]) ? null : intval(explode(' ', $matches[2])[0]);
                                } else {
                                    $show[$key] = $matches[2];
                                }
                            }
                        }
                    } elseif (substr($type, -8) == 'episodes') {
                        if (!isset($show['episodes']))
                            $show['episodes'] = [];
                        $show['episodes'][$type] = $typeData;
                    } elseif (substr($type, 0, 10) != 'additional') {
                        $show[$type] = $typeData;
                    }
                }
            }
        }
        $links = $crawler->filter('.show_info_description > tr:nth-child(2) td:nth-child(1) a');
        $idxMax = $links->count();
        if ($idxMax > 0)
            for ($idx = 0; $idx < $idxMax; $idx++) {
                $link = $links->eq($idx)->attr('href');
                if (stripos($link, 'imdb') !== false)
                    $show['imdb'] = $link;
                elseif (stripos($link, 'tvmaze') !== false)
                    $show['tvmaze'] = $link;
        }
        echo "[{$showIdx}]/{$showCount}] Show {$id}\n ";//.json_encode($show, JSON_PRETTY_PRINT)."\n";
        $data['shows'][$id] = $show;
        unset($show['torrents']);
        unset($show['cast']);
        unset($show['episodes']);
        $dataSmall['shows'][$id] = $show;
    }
    echo 'done, found '.count($data['shows']).' shows'.PHP_EOL;
    echo "Total Retries '{$totalRetries}', Total Failed '{$totalFailed}'\n";
    file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT |  JSON_UNESCAPED_UNICODE |  JSON_UNESCAPED_SLASHES));
    file_put_contents('eztv_shows.json', json_encode($data['shows'], JSON_PRETTY_PRINT |  JSON_UNESCAPED_UNICODE |  JSON_UNESCAPED_SLASHES));
    file_put_contents($jsonSmallFile, json_encode($dataSmall, JSON_PRETTY_PRINT |  JSON_UNESCAPED_UNICODE |  JSON_UNESCAPED_SLASHES));
}

$dataSmall = [];
foreach ($data as $type => $typeData) {
    $dataSmall[$type] = [];
    for ($idx = 0, $idxMax = 10, $typeKeys = array_keys($typeData); $idx < $idxMax; $idx++) {
        $key = $typeKeys[$idx];
        $value = $typeData[$key];
        $dataSmall[$type][$key] = $value;
    }
}
file_put_contents('eztv_small.json', json_encode($dataSmall, JSON_PRETTY_PRINT |  JSON_UNESCAPED_UNICODE |  JSON_UNESCAPED_SLASHES));


function apiCall($url) {
    /**
    * @var \Workerman\MySQL\Connection
    */
    global $db;
    /**
    * @var \mysqli
    */
    global $mysqlLinkId;
    /**
    * @var \Symfony\Component\BrowserKit\HttpBrowser
    */
    global $client;
    //$key = str_replace(['://', '/'], ['_', '_'], $url);
    //$response = file_get_contents($url);
    $expireMonths = 1;
    /*
    $return = $db->query("SELECT url, code, type, updated, date_add(updated, interval {$expireMonths} month) < now() as expired, response FROM watchable.eztv_api_cache where url='".$mysqlLinkId->real_escape_string($url)."' and date_add(updated, INTERVAL {$expireMonths} MONTH) > now()");
    if (count($return) == 0 || $return[0]['expired'] == 1) {
    */
        $crawler = $client->request('GET', $url);
        $response = $client->getResponse();
        $code = $response->getStatusCode();
        $contentType = strtolower(explode(';', $response->getHeader('content-type'))[0]);
        $responseContent = cleanUtf8($response->getContent());
    /*
        //var_export(['code' => $code, 'type' => $contentType, 'response' => $responseContent, 'url' => $url]);
        if (count($return) == 0) {
            echo "Adding New Data for {$url}\n";
            $db->insert('eztv_api_cache')
                ->cols(['url', 'code', 'type', 'response'])
                ->set('updated', 'NOW()')
                ->bindValues([
                    'url' => $url,
                    'code' => $code,
                    'type' => $contentType,
                    'response' => $responseContent
                ])->query();
        } else {
            echo "Replacing Expired Data for {$url}\n";
            $db->update('eztv_api_cache')
                ->cols(['code', 'type', 'response'])
                ->set('updated', 'NOW()')
                ->where('url = :url')
                ->bindValues([
                    'code' => $code,
                    'type' => $contentType,
                    'response' => $responseContent,
                    'url' => $url
                ])->query();
        }

    } else {
        $code = $return[0]['code'];
        $contentType = $return[0]['type'];
        $responseContent = $return[0]['response'];
        echo "Using Cached Data for {$url} {$code} {$contentType}\n";
    }
    */
    switch ($contentType) {
        case 'application/json':
            $return = json_decode($responseContent, true);
            break;
        case 'text/html':
        default:
            //$crawler = new Crawler(null, $url);
            //$crawler->addContent($responseContent, $contentType);
            //$crawler = $client->createCrawlerFromContent($url, $responseContent, $contentType );
            $return = $crawler;
            break;
    }
    return [$code, $contentType, $return];
};










$crawler = $client->request('GET', 'https://eztv.re/ep/1416440/doctors-s21e60-720p-hdtv-x264-norite/');
// Extract episode summary
$episodeSummary = $crawler->filter('.panel-heading')->text();
// Extract season and episode number
$seasonEpisodeNumber = $crawler->filter('.breadcrumb li:nth-child(4)')->text();
// Extract screenshots
$screenshots = $crawler->filter('.screenshot-thumbnail img')->each(function ($node) {
    return $node->attr('src');
});
// Extract episode title
$episodeTitle = $crawler->filter('.section-content .epinfo h1')->text();
// Extract show title
$showTitle = $crawler->filter('.section-content .epinfo h2 a')->text();
// Extract release date
$releaseDate = $crawler->filter('.section-content .epinfo span:contains("Released")')->siblings()->text();
// Extract magnet link
$magnetLink = $crawler->filter('.section-content .download-box a[href^="magnet:"]')->attr('href');


$crawler = $client->request('GET', 'https://eztv.re/shows/498355/canadas-ultimate-challenge/');
// Extract torrents
$torrents = $crawler->filter('#main > table > tbody > tr')->each(function ($row) {
    $cells = $row->filter('td');
    $torrent = [
        'title' => $cells->eq(1)->text(),
        'magnetLink' => $cells->eq(2)->filter('a[href^="magnet:"]')->attr('href'),
    ];
    return $torrent;
});
// Extract per-episode TVMaze links
$links = $crawler->filter('.episodes .epinfo a')->each(function ($node) {
    return $node->attr('href');
});
