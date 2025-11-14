<?php
header("Content-Type: application/json; charset=utf-8");

function json_error($msg){
    echo json_encode(["error"=>$msg], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_GET['song']) || trim($_GET['song']) === '') {
    json_error("missing_song");
}

$song   = trim($_GET['song']);
$artist = isset($_GET['artist']) ? trim($_GET['artist']) : '';
$keyword = $song . ' ' . $artist;

// SoundCloud Client ID
$client_id = "xwYTVSni6n4FghaI0c4uJ8T9c4pyJ3rh";

function get_url($url){
    $h = curl_init();
    curl_setopt($h, CURLOPT_URL, $url);
    curl_setopt($h, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($h, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($h, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($h, CURLOPT_USERAGENT, "Mozilla/5.0");
    $out = curl_exec($h);
    curl_close($h);
    return $out;
}

/* ---------- 1) TRY SOUNDCLOUD FIRST ---------- */
$search_url = "https://api-v2.soundcloud.com/search/tracks?q=" . urlencode($keyword) . "&client_id=$client_id&limit=1";

$sc_json = get_url($search_url);
if ($sc_json !== false){
    $sc = json_decode($sc_json, true);

    if (isset($sc["collection"][0])) {
        $track = $sc["collection"][0];
        $track_id = $track['id'];

        $info_url = "https://api-v2.soundcloud.com/tracks/$track_id?client_id=$client_id";
        $info_json = get_url($info_url);

        if ($info_json !== false) {
            $info = json_decode($info_json, true);

            if (isset($info['media']['transcodings'])) {
                foreach ($info['media']['transcodings'] as $t) {
                    if ($t['format']['protocol'] === 'progressive') {
                        $trans_url = $t['url'] . "?client_id=$client_id";
                        $trans_json = get_url($trans_url);

                        if ($trans_json !== false) {
                            $trans = json_decode($trans_json, true);
                            if (isset($trans['url'])) {
                                echo json_encode([
                                    "title"=>$track["title"],
                                    "artist"=>$track["user"]["username"],
                                    "audio_url"=>$trans["url"],
                                    "lyric_url"=>"",
                                    "language"=>"vietnamese"
                                ], JSON_UNESCAPED_UNICODE);
                                exit;
                            }
                        }
                    }
                }
            }
        }
    }
}

/* ---------- 2) YOUTUBE FALLBACK ---------- */

$yt_html = get_url("https://www.youtube.com/results?search_query=".urlencode($keyword));
if (!$yt_html) json_error("youtube_failed");

if (!preg_match('/\/watch\?v=([a-zA-Z0-9_-]{11})/', $yt_html, $m)){
    json_error("not_found");
}

$video_id = $m[1];
$mp3_url = "https://api.ytdlp.workers.dev/mp3?id=".$video_id;

echo json_encode([
    "title" => $song,
    "artist" => ($artist ?: "YouTube"),
    "audio_url" => $mp3_url,
    "lyric_url" => "",
    "language" => "vietnamese"
], JSON_UNESCAPED_UNICODE);
?>
