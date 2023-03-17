<?php
require_once __DIR__.'/../../../vendor/autoload.php';
include 'Torrent.php';
$yts = json_decode(file_get_contents('yts.json'),true);
$torrents = json_decode(file_get_contents('torrent_videos.json'),true);
$found = [];
$links = [];
$hashes = [];
foreach ($yts as $ytsId => $ytsData) {
    if (isset($ytsData['torrents'])) {
        foreach ($ytsData['torrents'] as $torData) {
            $hashes[$torData['hash']] = $ytsId;
        }
    }
}
foreach (explode("\n", trim(`ssh vaultd find /storage -type f`)) as $fileName) {
    if (preg_match('/\.(mpeg|mpg|flv|divx|ogm|m4v|avi|iso|mkv|mp4)$/i', $fileName)) {
        $fileSlug = strtolower(basename($fileName));
        if (array_key_exists($fileSlug, $torrents)) {
            $found[$fileName] = $torrents[$fileSlug];
        }
    }
}
echo "found ".count($found)." matches\n";
foreach ($found as $fileName => $hash) {
    $ytsId = $hashes[$hash];
    $ytsData = $yts[$ytsId];
    foreach ($ytsData['torrents'] as $torData) {
        if ($torData['hash'] == $hash)
            break;
    }
    $tor = new Torrent('tor/'.$hash);
    $torFiles = $tor->content();
    foreach ($torFiles as $torFile => $fileSize) {
        if (strtolower(basename($torFile)) == strtolower(basename($fileName))) {
            $links[$fileName] = $torFile;
        }
    }
}
file_put_contents('data.json', json_encode([
    'hashes' => $hashes,
    'found' => $found,
    'links' => $links,
], JSON_PRETTY_PRINT));
print_r($links);
echo "found ".count($links)." links\n";
exit;


$torrentPathInfos = [];
echo 'Mapping Torrent PathInfo Responses to Torrents';
foreach ($torrents as $torrentId => $torrentFiles) {
    foreach ($torrentFiles as $torrentFileFull => $torrentFileSize) {
        $torrentPathInfos[$torrentFileFull] = pathinfo($torrentFileFull);
    }
}
echo ' done'.PHP_EOL;
$yts = loadJson('yts');
$hashs = [];
echo 'Mapping Torrent hashes to YTS IDs';
foreach ($yts as $ytsId => $ytsData) {
    if (isset($ytsData['torrents']['items'])) {
        foreach ($ytsData['torrents']['items'] as $itemIdx => $itemData) {
            $hashs[$itemData['hash']] = $ytsId;
        }
    }
}
echo ' done'.PHP_EOL;
$files = loadJson('files');
$updates = 0;
foreach ($files as $fileFullName => $fileData) {
    if (!isset($fileData['torrent_id'])) {
        $pathInfo = pathinfo($fileFullName);
        echo $pathInfo['basename'].' ';
        $found = false;
        foreach ($torrents as $torrentId => $torrentFiles) {
            foreach ($torrentFiles as $torrentFileFull => $torrentFileSize) {
                $torrentPathInfo = $torrentPathInfos[$torrentFileFull];
                if ($torrentPathInfo['dirname'] == $pathInfo['filename']) {
                    $files[$fileFullName]['torrent_id'] = $torrentId;
                    $updates++;
                    $found = true;
                    echo '+D';
                } elseif ($torrentPathInfo['filename'] == $pathInfo['filename']) {
                    $files[$fileFullName]['torrent_id'] = $torrentId;
                    $updates++;
                    $found = true;
                    echo '+F';
                } elseif ($pathInfo['extension'] == 'srt') {
                    $fileName = preg_replace('/\]\.[a-zA-Z\.]+$/', ']', $pathInfo['basename']);
                    if ($torrentPathInfo['dirname'] == $fileName || $torrentPathInfo['filename'] == $fileName) {
                        $files[$fileFullName]['torrent_id'] = $torrentId;
                        $updates++;
                        $found = true;
                        echo '+S';
                    }
                }
            }
        }
        if ($found == false) {
            echo '-';
        }
        echo PHP_EOL;
    }
    if (isset($fileData['torrent_id']) && isset($hashs[$fileData['torrent_id']])) {
        $files[$fileFullName]['yts_id'] = $hashs[$fileData['torrent_id']];
        if (isset($yts[$files[$fileFullName]['yts_id']]['imdb_code'])) {
            $files[$fileFullName]['imdb_id'] = preg_replace('/^tt/', '', $yts[$files[$fileFullName]['yts_id']]['imdb_code']);
        }
    }
}
echo 'done'.PHP_EOL;
echo 'Generated '.$updates.' Updates'.PHP_EOL;
putJson('files', $files);