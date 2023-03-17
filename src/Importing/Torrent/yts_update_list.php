#!/usr/bin/env php
<?php
$url = 'https://yts.mx/api/v2/list_movies.json?sort=seeds&limit=50&with_rt_ratings=true&page=';
$page = 1;
$yts = [];
$end = false;
$movieCount = 0;
while ($end === false) {
	$json = json_decode(trim(`curl -s "{$url}{$page}"`),true);
	if (is_array($json) && $json['status'] == 'ok') {
		if ($page == 1) {
			$movieCount = $json['data']['movie_count'];
			$pages = ceil($movieCount / 50);
			echo "Movies: {$movieCount}, Pages: {$pages}  ";
		}
		echo "{$page} ";
		if ($page == $pages)
			$end = true;
		foreach ($json['data']['movies'] as $movie) {
			$yts[$movie['id']] = $movie;
		}
	} else {
		echo "there was an error on page {$page}\n";
		var_export($json);
		exit;
	}
	$page++;
}
echo "\n";
file_put_contents('yts.json', json_encode($yts, JSON_PRETTY_PRINT |  JSON_UNESCAPED_UNICODE |  JSON_UNESCAPED_SLASHES));
