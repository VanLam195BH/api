<?php
header("Content-Type: application/json");

if (!isset($_GET['song']) || trim($_GET['song']) == "") {
    echo json_encode(["error" => "missing_song"]);
    exit;
}

$song = urlencode($_GET['song']);

// ===== 1) TÌM TRÊN PIPED =====
$search_url = "https://piped.video/api/v1/search?q=$song";
$search_json = @file_get_contents($search_url);
if ($search_json) {
    $search = json_decode($search_json, true);
    if (isset($search[0]["url"])) {
        // Lấy ID video
        $video_url = $search[0]["url"];  // "/watch?v=xxxx"
        parse_str(parse_url($video_url, PHP_URL_QUERY), $q);
        $video_id = $q["v"];

        // Lấy stream
        $stream_url = "https://piped.video/api/v1/streams/$video_id";
        $stream_json = @file_get_contents($stream_url);
        if ($stream_json) {
            $stream = json_decode($stream_json, true);

            if (isset($stream["audioStreams"][0]["url"])) {
                echo json_encode([
                    "title" => $search[0]["title"],
                    "artist" => "YouTube",
                    "audio_url" => $stream["audioStreams"][0]["url"],
                    "language" => "vietnamese"
                ]);
                exit;
            }
        }
    }
}

// ===== 2) FALLBACK SOUNDCLOUD =====

$client_id = "xwYTVSni6n4FghaI0c4uJ8T9c4pyJ3rh";
$search_sc = "https://api-v2.soundcloud.com/search/tracks?q=$song&client_id=$client_id&limit=1";

$sc_json = @file_get_contents($search_sc);
$sc_data = json_decode($sc_json, true);

if (isset($sc_data["collection"][0])) {
    $track = $sc_data["collection"][0];
    $title = $track["title"];
    $artist = $track["user"]["username"];
    $track_id = $track["id"];

    $info_url = "https://api-v2.soundcloud.com/tracks/$track_id?client_id=$client_id";
    $info_json = @file_get_contents($info_url);
    $info = json_decode($info_json, true);

    foreach ($info["media"]["transcodings"] as $t) {
        if ($t["format"]["protocol"] == "progressive") {
            $trans = json_decode(file_get_contents($t["url"] . "?client_id=$client_id"), true);

            echo json_encode([
                "title" => $title,
                "artist" => $artist,
                "audio_url" => $trans["url"],
                "language" => "vietnamese"
            ]);
            exit;
        }
    }
}

echo json_encode(["error" => "not_found"]);
