<?php
//$torUrls = [];
$torUrls = explode("\n", trim(file_get_contents('tors.txt')));
$moviesGlob = glob('/storage/movies/*');
file_put_contents('glob.txt', implode("\n", $moviesGlob));
//exit;
//$moviesGlob = json_decode(file_get_contents('glob.txt'),true);
$moviesCount = count($moviesGlob);
foreach ($moviesGlob as $idx => $dirFile) {
	$ext = strtolower(substr($dirFile, strrpos($dirFile, '.') + 1));
	if (count($torUrls) > $idx) {
		echo "Skipping ".basename($dirFile, '.'.$ext).PHP_EOL;
		continue;
	}
	if (in_array($ext, ['mp4', 'avi', 'mkv', 'mpg'])) {
		if (preg_match('/^(?P<name>.*).(?P<year>[1-2][0-9][0-9][0-9]).*[^0-9](?P<res>[0-9][0-9]*p)/uU', basename($dirFile, '.'.$ext), $matches)) {
			$name = str_replace(['.'], [' '], $matches['name']);
			$year = $matches['year'];
			$res = $matches['res'];
			$url = 'https://yts.lt/api/v2/list_movies.json?quality='.$res.'&query_term='.urlencode($name); 
			echo "[{$idx}/{$moviesCount}] [{$year}] {$name} {$res} @ {$url}\n";
			$response = trim(`curl -s -k "{$url}"`);
			$data = json_decode($response, true);
			if (!is_array($data) || !is_array($data['data'])) {
				echo "Couldnt JSON Decode the response {$response}.. Sleeping then continuing\n";
				sleep(5);
				continue;
				//die("Couldnt JSON Decode the response {$response}\n");
			}
			if ($data['data']['movie_count'] == 0) {
				echo "[{$idx}/{$moviesCount}] No results found , skipping to next file\n";
				continue;
			}
			if (is_array($data['data']) && is_array($data['data']['movies']) && count($data['data']['movies']) > 0) {
				$movieData = $data['data']['movies'][0];
				$bestTorrent = false;
				$bestQual = false;
				$bestType = false;
				foreach ($movieData['torrents'] as $torrentIdx => $torrentData) {
					if ($bestQual === false || intval(str_replace('p', '', $torrentData['quality'])) > intval($bestQual) || $bestQual === false || (intval(str_replace('p', '', $torrentData['quality'])) >= intval($bestQual) and stripos($torrentData['type'], 'b') !== false)) {
						$bestQual = intval(str_replace('p', '', $torrentData['quality']));
						$bestTorrent = $torrentIdx;
						$bestType = $torrentData['type'];
					}
				}
				if ($bestQual !== false) {
					$torrentData = $movieData['torrents'][$bestTorrent];
					$torUrl = $torrentData['url'];
					echo "[{$idx}/{$moviesCount}] Picked Quality {$bestQual} Type {$bestType} Year {$movieData['year']} Title {$movieData['title']} Tor {$torUrl}\n";
					$torUrls[] = $torUrl;
					file_put_contents('tors.txt', implode("\n", $torUrls));
				}
			}
		}
	}
	sleep(1);
}
print_r($torUrls);
