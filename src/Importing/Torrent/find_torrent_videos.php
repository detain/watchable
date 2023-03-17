<?php
include 'Torrent.php';
$files = [];
foreach (glob('tor/*') as $fileName) {
    echo '.';
	$tor = new Torrent($fileName);
	foreach ($tor->content() as $torFile => $fileSize)
		if (preg_match('/\.(mpeg|mpg|flv|divx|ogm|m4v|avi|iso|mkv|mp4)$/i', $torFile))
			$files[strtolower(basename($torFile))] = strtoupper($tor->hash_info());
    unset($tor);
}
echo "Found ".count($files)." Video Files\n";
file_put_contents('torrent_videos.json', json_encode($files, JSON_PRETTY_PRINT));
