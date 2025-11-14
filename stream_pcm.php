<?php
header("Content-Type: application/json; charset=utf-8");

// ===== Helper =====
function json_error($msg) {
    echo json_encode(["error" => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

// ===== Input =====
if (!isset($_GET['song']) || trim($_GET['song']) === '') {
    json_error("missing_song");
}

$song   = trim($_GET['song']);
$artist = isset($_GET['artist']) ? trim($_GET['artist']) : '';

// Ghép keyword: tên bài + tên ca sĩ (nếu có)
$keyword = $song;
if ($artist !== '') {
    $keyword .= ' ' . $artist;
}

// client_id SoundCloud của bạn
$client_id = "xwYTVSni6n4FghaI0c4uJ8T9c4pyJ3rh";

// Chuẩn hóa cho URL
$keyword_q = urlencode($keyword);

// ===== 1) Thử SoundCloud trước =====
$search_url = "https://api-v2.soundcloud.com/search/tracks?q={$keyword_q}&client_id={$client_id}&limit=1";

$search_json = @file_get_contents($search_url);
if ($search_json !== false) {
    $search = json_decode($search_json, true);

    if (isset($search["collection"][0])) {
        $track = $search["collection"][0];
        $track_id = $track["id"];
        $title_sc = $track["title"];
        $artist_sc = $track["user"]["username"];

        // Lấy media để tìm transcodings
        $info_url = "https://api-v2.soundcloud.com/tracks/{$track_id}?client_id={$client_id}";
        $info_json = @file_get_contents($info_url);

        if ($info_json !== false) {
            $info = json_decode($info_json, true);

            if (isset($info["media"]["transcodings"])) {
                $mp3_progressive = "";
                foreach ($info["media"]["transcodings"] as $t) {
                    if (
                        isset($t["format"]["protocol"]) &&
                        $t["format"]["protocol"] === "progressive"
                    ) {
                        $mp3_progressive = $t["url"];
                        break;
                    }
                }

                if ($mp3_progressive !== "") {
                    // Gọi transcoding để lấy link cf-media.sndcdn.com
                    $trans_url = $mp3_progressive . "?client_id={$client_id}";
                    $trans_json = @file_get_contents($trans_url);

                    if ($trans_json !== false) {
                        $trans = json_decode($trans_json, true);
                        if (isset($trans["url"])) {
                            $final_mp3 = $trans["url"]; // link mp3 real (cf-media.sndcdn.com)

                            echo json_encode([
                                "title"      => $title_sc,
                                "artist"     => $artist_sc,
                                "audio_url"  => $final_mp3,
                                "lyric_url"  => "",
                                "language"   => "vietnamese"
                            ], JSON_UNESCAPED_UNICODE);
                            exit;
                        }
                    }
                }
            }
        }
    }
}

// ===== 2) Fallback sang YouTube (dùng HTML + ytdlp worker) =====
// Tìm trên YouTube bằng HTML, không cần API key
$yt_query = urlencode($keyword . " audio");
$yt_search_url = "https://www.youtube.com/results?search_query={$yt_query}";

$yt_html = @file_get_contents($yt_search_url);
if ($yt_html === false) {
    json_error("youtube_failed");
}

// Rất đơn giản: bắt videoId đầu tiên dạng "watch?v=XXXXXXXXXXX"
if (preg_match('/\/watch\?v=([a-zA-Z0-9_-]{11})/', $yt_html, $m)) {
    $video_id = $m[1];

    // Dùng worker ytdlp để convert sang mp3
    // Có thể dùng các endpoint kiểu: https://api.ytdlp.workers.dev/mp3?id=VIDEO_ID
    // (Nếu sau này worker này chết, bạn chỉ cần đổi domain, format giữ nguyên)
    $audio_url = "https://api.ytdlp.workers.dev/mp3?id=" . $video_id;

    echo json_encode([
        "title"      => $song,
        "artist"     => ($artist !== '' ? $artist : "YouTube"),
        "audio_url"  => $audio_url,
        "lyric_url"  => "",
        "language"   => "vietnamese"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Không tìm thấy ở đâu
json_error("not_found_anywhere");
