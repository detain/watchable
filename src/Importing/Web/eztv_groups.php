<?php

use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\CssSelector\CssSelectorConverter;

require_once __DIR__.'/../../../vendor/autoload.php';

/**
* limiting:
*
* status
* rating
* date
* classification
* genre
*/

$sitePrefix = 'http://eztv.re';
$converter = new CssSelectorConverter();
$client = new Goutte\Client();
$jsonFile = 'eztv_shows.json';
if (!file_exists($jsonFile)) {
    echo "missing json data";
    exit;
}
echo "Loading data...";
$data = json_decode(file_get_contents($jsonFile),true);
echo "Loaded\n";
$limitTypes = [
    'counts' => ['status', 'classification', 'genre'],
    'ranges' => [
        //'start_date', 'rating', 'torrents', 'runtime'
    ]
];
$limits = [];
echo "Generating groups...";
foreach ($data['shows'] as $id => $show) {
    echo "{$id} ";
    foreach ($limitTypes['ranges'] as $fieldName) {
        if (!isset($limits[$fieldName]))
            $limits[$fieldName] = ['min' => false, 'max' => false];
        if (!isset($show[$fieldName]))
            continue;
        $fieldValue = is_array($show[$fieldName]) ? count($show[$fieldName]) : $show[$fieldName];
        if ($fieldName == 'start_date' && !is_null($fieldValue))
            $fieldValue = floatval(substr($fieldValue, 0, 4));
        if ($limits[$fieldName]['min'] === false || $limits[$fieldName]['min'] > $fieldValue)
            $limits[$fieldName]['min'] = $fieldValue;
        if ($limits[$fieldName]['max'] === false || $limits[$fieldName]['max'] < $fieldValue)
            $limits[$fieldName]['max'] = $fieldValue;
    }
    foreach ($limitTypes['counts'] as $fieldName) {
        if (!isset($limits[$fieldName]))
            $limits[$fieldName] = [];
        if (!isset($show[$fieldName]) || in_array($show[$fieldName], [null,'', 'All']))
            $show[$fieldName] = 'Unknown';
        if (!is_array($show[$fieldName]))
            $show[$fieldName] = [$show[$fieldName]];
        foreach ($show[$fieldName] as $fieldValue) {
            if (!isset($limits[$fieldName][$fieldValue]))
                $limits[$fieldName][$fieldValue] = 0;
            $limits[$fieldName][$fieldValue]++;
        }
    }
}
echo "done\n";
file_put_contents('eztv_show_limits.json', json_encode($limits, JSON_PRETTY_PRINT |  JSON_UNESCAPED_UNICODE |  JSON_UNESCAPED_SLASHES));