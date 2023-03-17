<?php


use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\CssSelector\CssSelectorConverter;

require_once __DIR__.'/../../bootstrap.php';

$sitePrefix = 'https://www1.gogoanime.bid';
$converter = new CssSelectorConverter();
$client = new Client();
if (file_exists('gogoanime.json')) {
    $data = json_decode(file_get_contents('gogoanime.json'),true);
} else {
    $data = [
        'series' => [],
    ];
    echo 'Loading Page:';
    for ($page = 1, $maxPages = 88; $page <= $maxPages; $page++) {
        echo ' '.$page;
        $crawler = $client->request('GET', $sitePrefix.'/anime-list.html?page='.$page);
        $listing = $crawler->filter('.anime_list_body .listing li a');
        for ($list = 0, $maxList = $listing->count(); $list < $maxList; $list++) {
            $data['series'][basename($listing->eq($list)->attr('href'))] = [
                'name' => $listing->eq($list)->text(),
            ];
        }
    }
    echo 'done, found '.count($data['series']).' anime series'.PHP_EOL;
    file_put_contents('gogoanime.json', json_encode($data, getJsonOpts()));
}
$updated = 0;
foreach ($data['series'] as $seo => $series) {
    echo '['.$updated.'] Loading Page:'.$seo;
    $crawler = $client->request('GET', $sitePrefix.'/category/'.$seo);
    $listing = $crawler->filter('#episode_page li a');
    $series['eps'] = 0;
    for ($list = 0, $maxList = $listing->count(); $list < $maxList; $list++) {
        $endEp = $listing->eq($list)->attr('ep_end');
        if ($endEp > $series['eps'])
            $series['eps'] = $endEp;
    }
    $crawler = $crawler->filter('.anime_info_body_bg');
    $series['img'] = $crawler->filter('img')->attr('src');
    $types = $crawler->filter('p.type');
    for ($list = 0, $maxList = $types->count(); $list < $maxList; $list++) {
        $origKey = $types->eq($list)->filter('span')->text();
        $key = strtolower(str_replace([':', ' '], ['', '-'], $origKey));
        if ($key == 'genre') {
            $value = [];
            $links = $types->eq($list)->filter('a');
            for ($link = 0, $maxLinks = $links->count(); $link < $maxLinks; $link++) {
                $value[] = $links->eq($link)->attr('title');
            }
        } elseif ($key == 'other-name') {
            $value = explode(', ', trim(substr($types->eq($list)->text(), strlen($origKey))));
        } else {
            $value = trim(substr($types->eq($list)->text(), strlen($origKey)));
        }
        $series[$key] = $value;
    }
    $data['series'][$seo] = $series;
    if ($updated % 100 == 0) {
        echo ' storing';
        file_put_contents('gogoanime.json', json_encode($data, getJsonOpts()));

    }
    $updated++;
    echo PHP_EOL;
}
echo ' done'.PHP_EOL;
file_put_contents('gogoanime.json', json_encode($data, getJsonOpts()));
