<?php
/*
Torrent
"1930781": {
"hash": "82bf89cbe3d452492de7f92e6130ff6291ff04e4",
"filename": "Ex.on.the.Beach.US.S06E06.HDTV.x264-CRiMSON[eztv.re].mkv",
"imdb_id": "8285922",
"season": 6,
"episode": 6,
"seeds": 0,
"peers": 0,
"name": "Ex on the Beach US S06E06 HDTV x264-CRiMSON EZTV",
"seo": "ex-on-the-beach-us-s06e06-hdtv-x264-crimson",
"released": 1679027060,
"size": 270371477,
"image": "//ezimg.ch/thumbs/ex-on-the-beach-us-s06e06-hdtv-x264-crimson-large.jpg",
"magnet": "magnet:?xt=urn:btih:82bf89cbe3d452492de7f92e6130ff6291ff04e4&dn=Ex.on.the.Beach.US.S06E06.HDTV.x264-CRiMSON%5Beztv%5D&tr=udp://tracker.opentrackr.org:1337/announce&tr=udp://9.rarbg.me:2970/announce&tr=udp://p4p.arenabg.com:1337/announce&tr=udp://tracker.torrent.eu.org:451/announce&tr=udp://tracker.dler.org:6969/announce&tr=udp://open.stealth.si:80/announce&tr=udp://ipv4.tracker.harry.lu:80/announce&tr=https://opentracker.i2p.rocks:443/announce",
"torrent": "https://zoink.ch/torrent/Ex.on.the.Beach.US.S06E06.HDTV.x264-CRiMSON[eztv.re].mkv.torrent"
},
Pack
"507114": {
"name": "Class of 07 S01 WEBRip x264-ION10",
"seo": "class-of-07",
"size": 2576980378,
"seeds": 86,
"torrent_id": 1930659,
"magnet": "magnet:?xt=urn:btih:366c52c0f19356de38229069496170f4ef493539&dn=Class.of.07.S01.WEBRip.x264-ION10%5Beztv.re%5D%5Beztv%5D&tr=udp%3A%2F%2Ftracker.coppersurfer.tk%3A80&tr=udp%3A%2F%2Fglotorrents.pw%3A6969%2Fannounce&tr=udp%3A%2F%2Ftracker.opentrackr.org%3A1337%2Fannounce&tr=udp%3A%2F%2Fexodus.desync.com%3A6969",
"torrent": "https://zoink.ch/torrent/Class.of.07.S01.WEBRip.x264-ION10[eztv.re].torrent"
},
*/
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\CssSelector\CssSelectorConverter;

require_once __DIR__.'/../../../vendor/autoload.php';

$load = [
    'torrents' => false,
    'shows' => false,
    'packs' => false,
    'show' => true,
];
$sitePrefix = 'https://eztv.re';
$converter = new CssSelectorConverter();
$client = new Goutte\Client();
if (file_exists('eztv.json')) {
    echo "Loading data...";
    $data = json_decode(file_get_contents('eztv.json'),true);
    echo "Loaded\n";
} else {
    $data = [
        'shows' => [],
        'torrents' => [],
        'packs' => [],
    ];
}
if ($load['torrents'] == true) {
    echo 'Loading Torrents:';
    for ($limit = 100, $pages = 0, $page = 1, $end = false; $end == false; $page++) {
        echo "{$page}/{$pages}, ";
        //$json = json_decode(file_get_contents('https://eztv.re/api/get-torrents?limit=100&page='.$page),true);
        $json = json_decode(file_get_contents('torrents_page_'.$page.'.json'),true);
        if (!isset($json['torrents_count']))
            continue;
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
    file_put_contents('eztv.json', json_encode($data, JSON_PRETTY_PRINT |  JSON_UNESCAPED_UNICODE |  JSON_UNESCAPED_SLASHES));
}
if ($load['shows'] == true) {
    echo 'Loading Shows:';
    $crawler = $client->request('GET', $sitePrefix.'/showlist/');
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
        //echo "Adding Show {$id} ".json_encode($show)."\n";
        $data['shows'][$id] = $show;
    }
    echo 'done, found '.count($data['shows']).' tv series'.PHP_EOL;
    file_put_contents('eztv.json', json_encode($data, JSON_PRETTY_PRINT |  JSON_UNESCAPED_UNICODE |  JSON_UNESCAPED_SLASHES));
}
if ($load['packs'] == true) {
    echo 'Loading Packs:';
    for ($page = 1, $end = false; $end == false; $page++) {
        $crawler = $client->request('GET', $sitePrefix.'/cat/tv-packs-1/page_'.$page);
        $end = $crawler->filter("#header_holder table")->eq(4)->filter('td')->eq(1)->html() == '';
        $rows = $crawler->filter("#header_holder table")->eq(5)->filter('tr');
        echo " [{$page}] Adding ".$rows->count()." Packs";
        for ($idx = 2, $maxIdx = $rows->count(); $idx < $maxIdx; $idx++) {
            $row = $rows->eq($idx);
            $td = $row->filter('td');
            //echo $td->eq(2)->html()."\n".$td->count()."\n".$td->eq(2)->filter('a')->count();
            $href = explode('/', $td->eq(0)->filter('a')->attr('href'));
            $id = intval($href[2]);
            $sizeParts = explode(' ', $td->eq(3)->text());
            $pack = [
                'name' => preg_replace('/ \[eztv\]$/', '', $td->eq(1)->filter('a')->text()),
                'seo' => $href[3],
                'size' => ceil(floatval($sizeParts[0]) * ($sizeParts[1] == 'MB' ? 1048576 : 1073741824)),
                'seeds' => $td->eq(5)->text() == '-' ? 0 : intval($td->eq(5)->text()),
                'torrent_id' => intval(explode('/', $td->eq(1)->filter('a')->attr('href'))[2]),
                'magnet' => $td->eq(2)->filter('a')->eq(0)->attr('href')
            ];
            if ($td->eq(2)->filter('a')->count() == 2)
                $pack['torrent'] = $td->eq(2)->filter('a')->eq(1)->attr('href');
            //echo "Adding Pack {$id} ".json_encode($pack, JSON_PRETTY_PRINT)."\n";
            $data['packs'][$id] = $pack;
        }
    }
    echo 'done, found '.count($data['packs']).' tv packs'.PHP_EOL;
    file_put_contents('eztv.json', json_encode($data, JSON_PRETTY_PRINT |  JSON_UNESCAPED_UNICODE |  JSON_UNESCAPED_SLASHES));
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
    foreach ($data['shows'] as $id => $show) {
        $url = $sitePrefix.'/shows/'.$id.'/'.$show['seo'].'/';
        $crawler = $client->request('GET', $url);
        $show['image'] = $sitePrefix.$crawler->filter('.show_info_main_logo img')->attr('src');
        $show['description'] = $crawler->filter('.show_info_banner_logo')->text();
        $show['imdb'] = $crawler->filter('.show_info_rating_score a')->attr('href');
        $show['torrents'] = [];
        $rows = $torrents = $crawler->filter('a.epinfo');
        $idxMax = $rows->count();
        if ($idxMax > 0)
            for ($idx = 0; $idx < $idxMax; $idx++)
                $show['torrents'][] = intval(explode('/', $rows->eq($idx)->attr('href'))[2]);
        $show['cast'] = [];
        preg_match_all('/<div itemprop="actor" itemscope itemtype="http:\/\/schema.org\/Person" style="display: inline;"><span itemprop="name">(?P<actor>[^<]*)<\/span><\/div> \.* as (?P<plays>[^<]*)<br \/>/msuU',
            $crawler->filter('.show_info_tvnews_column > div > table > tr > td')->html(), $matches);
        foreach ($matches['actor'] as $idx => $actor)
            $show['cast'][$actor] = $matches['plays'][$idx];
        $show['other'] = [];
        $parts = explode('<div class="showinfo_header">', $crawler->filter('.show_info_description > tr:nth-child(2) td:nth-child(1)')->html());
        array_shift($parts);
        $parts[0] = str_replace('<div style="width: 537px; height: 250px; overflow-y: auto;">', '', $parts[0]);
        foreach ($parts as $part)
            if (preg_match_all('/<h3>([^<]*)<\/h3><\/div>(<br>.*)<br>/msu', $part, $matches))
                $show['other'][$matches[1][0]] = explode('<br>', $matches[2][0]);
        $links = $crawler->filter('.show_info_description > tr:nth-child(2) td:nth-child(1) a');
        $idxMax = $links->count();
        if ($idxMax > 0)
            for ($idx = 0; $idx < $idxMax; $idxMax++) {
                $link = $links->eq($idx)->attr('href');
                if (stripos($link, 'imdb') !== false)
                    $show['imdb'] = $link;
                elseif (stripos($link, 'tvmaze') !== false)
                    $show['tvmaze'] = $link;
            }
        echo "Show {$id} ".json_encode($show, JSON_PRETTY_PRINT)."\n";
        $data['shows'][$id] = $show;
    }
    echo 'done, found '.count($data['shows']).' shows'.PHP_EOL;
    file_put_contents('eztv.json', json_encode($data, JSON_PRETTY_PRINT |  JSON_UNESCAPED_UNICODE |  JSON_UNESCAPED_SLASHES));
}