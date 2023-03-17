<?php


use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\CssSelector\CssSelectorConverter;

require_once __DIR__.'/../../bootstrap.php';

$load = [
    'torrents' => true,//true,
    'shows' => false,//true,
    'packs' => false,//true,
];
$sitePrefix = 'https://eztv.re';
$converter = new CssSelectorConverter();
$client = new Client();
if (file_exists('eztv.json')) {
    $data = json_decode(file_get_contents('eztv.json'),true);
} else {
    $data = [
        'shows' => [],
        'torrents' => [],
        'packs' => [],
    ];
}
if ($load['torrents'] == true) {
    echo 'Loading Torrents:';
    for ($limit = 100, $pages = 0, $page = 1, $end = false, $end != false; $page++) {
        echo "{$page}, ";
      $json = json_decode(file_get_contents('https://eztv.re/api/get-torrents?limit=100&page='.$page),true);
      $pages = ceil($json['torrents_count'] / $limit);
      $end = $page >= $pages;
      echo "Adding ".count($json['torrents'])." Torrents";
      foreach ($json['torrents'] as $torrent) {
          //echo "Adding Torrent ".json_encode($torrent)."\n";
          $data['torrents'][$torrent['id']] = $torrent;
      }
    }
    echo 'done, found '.count($data['torrents']).' tv series'.PHP_EOL;
    file_put_contents('eztv.json', json_encode($data, getJsonOpts()));
}
if ($load['shows'] == true) {
    echo 'Loading Shows:';
    $crawler = $client->request('GET', $sitePrefix.'/showlist/');
    $rows = $crawler->filter('.forum_header_border tr');
    echo "Adding ".$rows->count()." Shows";
    for ($idx = 4, $maxIdx = $rows->count(); $idx < $maxIdx; $idx++) {
        $row = $rows->eq($idx);
        $show = [
            'href' => $row->filter('td a')->attr('href'),
            'name' => $row->filter('td a')->text(),
            'status' => $row->filter('td font')->attr('class'),
            'rating' => $row->filter('td b')->text(),
            'votes' => $row->filter('td span')->text()
        ];
        echo "Adding Show ".json_encode($show)."\n";
        $data['shows'][$show['href']] = $show;
    }
    echo 'done, found '.count($data['series']).' tv series'.PHP_EOL;
    file_put_contents('eztv.json', json_encode($data, getJsonOpts()));
}
if ($load['packs'] == true) {
    echo 'Loading Packs:';
    for ($page = 1, $end = false; $end != false; $page++) {
        $crawler = $client->request('GET', $sitePrefix.'/cat/tv-packs-1/page_'.$page);
        $end = $crawler->filter("#header_holder table")->eq(4)->filter('td')->eq(1)->html() == '';
        $rows = $crawler->filter("#header_holder table")->eq(5)->filter('tr');
        echo "Adding ".$rows->count()." Packs";
        for ($idx = 2, $maxIdx = $rows->count(); $idx < $maxIdx; $idx++) {
            $row = $rows->eq($idx);
            $pack = [
                'href' => $row->filter('td')->eq(0)->filter('a')->attr('href'),
                'ep_id' => $row->filter('td')->eq(1)->filter('a')->attr('href'),
                'name' => $row->filter('td')->eq(1)->filter('a')->text(),
                'magnet' => $row->filter('td')->eq(2)->filter('a')->eq(0)->attr('href'),
                'torrent' => $row->filter('td')->eq(2)->filter('a')->eq(1)->attr('href'),
                'size' => $row->filter('td')->eq(3)->text(),
                'seeds' => $row->filter('td')->eq(5)->text() == '-' ? 0 : $row->filter('td')->eq(5)->text(),
            ];
            echo "Adding Pack ".json_encode($pack)."\n";
            $data['packs'][$pack['href']] = $pack;
        }
    }
    echo 'done, found '.count($data['packs']).' tv packs'.PHP_EOL;
    file_put_contents('eztv.json', json_encode($data, getJsonOpts()));
}
