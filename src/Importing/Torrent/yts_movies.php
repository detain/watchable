<?php
$badGenres = ['Documentary','Music','Film-Noir','News','Reality-TV','Talk-Show','Game-Show'];
$movies = json_decode(file_get_contents('movies.json'),true);
$yts = json_decode(file_get_contents('yts.json'),true);
$localImdbIds = [];
$matches = [];;
$torrents = [];;
$minSeeds = 3;
$minRating = 6.0;
$minQuality = 1080;
$filtered = [];
$cmds = ['#!/bin/bash'];
foreach ($movies as $idx => $movie) {
    $imdbId = strtolower(trim($movie['ImdbId']));
	$localImdbIds[$imdbId] = $idx;
}
foreach ($yts as $ytsId => $movie) {
    $imdbId = strtolower(trim($movie['imdb_code']));
    $alreadyHave = false;
    if (array_key_exists($imdbId, $localImdbIds)) { // found imdb code match
        $matches[] = $ytsId;
        $alreadyHave = true;
        //echo "[".(count($matches)+1)."] Found Match {$movie['title']}\n";
        $movies[$localImdbIds[$imdbId]]['YtsId'] = $ytsId;
    } else {
        //echo "Missing Match {$movie['title']}\n";
    }
    $yts[$ytsId]['have'] = $alreadyHave;
    if ($alreadyHave === false && $movie['language'] == 'en' && $movie['rating'] >= $minRating && (!array_key_exists('genres', $movie) || count(array_intersect($movie['genres'], $badGenres)) == 0)) {
        $bestTor = false;
        foreach ($movie['torrents'] as $torIdx => $torrent) {
            if (intval($torrent['quality']) == $minQuality && $torrent['seeds'] >= $minSeeds) {
                if ($bestTor === false || $torrent['type'] == 'bluray') {
                    $bestTor = $torrent;
                }
                $filtered[$ytsId] = $movie;
            }
        }
        if ($bestTor !== false) {
            $torrents[$bestTor['url']] = $movie['title_long'];
            $cmds[] = 'wget '.escapeshellarg($bestTor['url']).' -O '.escapeshellarg($movie['title_long'].'.torrent').';';
        }
    }
}
foreach ($movies as $idx => $movie) {
    if (!array_key_exists('YtsId', $movie))
        $missing[] = $movie['Path'].'/'.$movie['RelativePath'];
}
echo count($matches).'/'.count($movies)." local movies matched to yts\n";
echo count($missing).'/'.count($movies)." local movies missing matchups to yts\n";
echo count($filtered)." yts movies passed filters\n";
file_put_contents('torrents.sh', implode("\n", $cmds));
file_put_contents('filtered.json', json_encode($filtered, JSON_PRETTY_PRINT |  JSON_UNESCAPED_UNICODE |  JSON_UNESCAPED_SLASHES));
file_put_contents('missing.json', json_encode($missing, JSON_PRETTY_PRINT |  JSON_UNESCAPED_UNICODE |  JSON_UNESCAPED_SLASHES));
file_put_contents('yts.json', json_encode($yts, JSON_PRETTY_PRINT |  JSON_UNESCAPED_UNICODE |  JSON_UNESCAPED_SLASHES));
file_put_contents('movies.json', json_encode($movies, JSON_PRETTY_PRINT |  JSON_UNESCAPED_UNICODE |  JSON_UNESCAPED_SLASHES));
/* movies.json  {
    "Id": 1,
    "Path": "/storage/'Pimpernel' Smith (1941)",
    "RelativePath": "'Pimpernel'.Smith.1941.1080p.BluRay.x264-[YTS.AG].mp4",
    "TmdbId": 43788,
    "ImdbId": "tt0034027",
    "Title": "'Pimpernel' Smith",
    "OriginalTitle": "'Pimpernel' Smith",
    "Year": 1941    }, */
/* yts.json     "47883": {
    "id": 47883,
    "url": "https://yts.mx/movies/jurassic-punk-2022",
    "imdb_code": "tt15095920",
    "title": "Jurassic Punk",
    "title_english": "Jurassic Punk",
    "title_long": "Jurassic Punk (2022)",
    "slug": "jurassic-punk-2022",
    "year": 2022,
    "rating": 8.4,
    "runtime": 81,
    "genres": [
      "Documentary"
    ],
    "summary": "Steve 'Spaz' Williams is a pioneer in computer animation. His digital dinosaurs of JURASSIC PARK transformed Hollywood in 1993, but an appetite for anarchy and reckless disregard for authority may have cost him the recognition he deserved.",
    "description_full": "Steve 'Spaz' Williams is a pioneer in computer animation. His digital dinosaurs of JURASSIC PARK transformed Hollywood in 1993, but an appetite for anarchy and reckless disregard for authority may have cost him the recognition he deserved.",
    "synopsis": "Steve 'Spaz' Williams is a pioneer in computer animation. His digital dinosaurs of JURASSIC PARK transformed Hollywood in 1993, but an appetite for anarchy and reckless disregard for authority may have cost him the recognition he deserved.",
    "yt_trailer_code": "XCU-bA1lp5c",
    "language": "en",
    "mpa_rating": "",
    "background_image": "https://yts.mx/assets/images/movies/jurassic_punk_2022/background.jpg",
    "background_image_original": "https://yts.mx/assets/images/movies/jurassic_punk_2022/background.jpg",
    "small_cover_image": "https://yts.mx/assets/images/movies/jurassic_punk_2022/small-cover.jpg",
    "medium_cover_image": "https://yts.mx/assets/images/movies/jurassic_punk_2022/medium-cover.jpg",
    "large_cover_image": "https://yts.mx/assets/images/movies/jurassic_punk_2022/large-cover.jpg",
    "state": "ok",
    "torrents": [
      {
        "url": "https://yts.mx/torrent/download/5543869C1EEC948F33C88504EC142606BFCA9D97",
        "hash": "5543869C1EEC948F33C88504EC142606BFCA9D97",
        "quality": "1080p",
        "type": "web",
        "seeds": 0,
        "peers": 0,
        "size": "1.49 GB",
        "size_bytes": 1599875318,
        "date_uploaded": "2022-12-29 22:29:47",
        "date_uploaded_unix": 1672349387
      }
    ],
    "date_uploaded": "2022-12-29 21:35:20",
    "date_uploaded_unix": 1672346120
  }, */


