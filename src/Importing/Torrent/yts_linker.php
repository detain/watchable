<?php
$data = json_decode(file_get_contents('data.json'),true);
@mkdir(dirname('/storage.orig'), 0777, true);
@mkdir(dirname('/storage.tor'), 0777, true);
foreach ($data['links'] as $fileName => $torFile) {
    $hash = $data['found'][$fileName];
    echo "FileName:{$fileName}  TorFile:{$torFile}  Hash:{$hash}\n";
    @mkdir(dirname('/storage.orig/'.$torFile), 0777, true);
    link($fileName, '/storage.orig/'.$torFile);
    link('/home/detain/tor/'.$hash.'.torrent', '/storage.tor/'.$hash.'.torrent');
}