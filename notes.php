<?php
/* =============================================================
   note 最新記事フィード（ラッコサーバー / PHP）
   - note の RSS をサーバー側で取得・解析し JSON で返す
   - index.html の JS から同一オリジンで fetch（CORS不要）
   - 30分ファイルキャッシュ付き（note へのアクセス過多を防止）
   ============================================================= */

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: public, max-age=1800');

$RSS   = 'https://note.com/witty_okapi1304/rss';
$COUNT = 3;
$cacheFile = sys_get_temp_dir() . '/llf_note_feed.json';

// キャッシュが新しければそれを返す
if (is_readable($cacheFile) && (time() - filemtime($cacheFile) < 1800)) {
    echo file_get_contents($cacheFile);
    exit;
}

// RSS 取得（file_get_contents → 失敗時 cURL）
$xml = false;
$ctx = stream_context_create(array('http' => array('timeout' => 8, 'header' => "User-Agent: Mozilla/5.0\r\n")));
$xml = @file_get_contents($RSS, false, $ctx);
if ($xml === false && function_exists('curl_init')) {
    $ch = curl_init($RSS);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => 'Mozilla/5.0',
    ));
    $xml = curl_exec($ch);
    curl_close($ch);
}

$out = array();
if ($xml !== false && $xml !== '') {
    $sx = @simplexml_load_string($xml);
    if ($sx && isset($sx->channel->item)) {
        $mediaNs = 'http://search.yahoo.com/mrss/';
        $i = 0;
        foreach ($sx->channel->item as $item) {
            if ($i++ >= $COUNT) break;
            $desc  = (string)$item->description;
            $thumb = '';
            $m = $item->children($mediaNs);
            if (isset($m->thumbnail)) {
                $attr = $m->thumbnail->attributes();
                $thumb = isset($attr['url']) ? (string)$attr['url'] : trim((string)$m->thumbnail);
            }
            if ($thumb === '' && preg_match('/<img[^>]+src=["\']([^"\']+)/i', $desc, $mm)) {
                $thumb = $mm[1];
            }
            $excerpt = trim(preg_replace('/\s+/', ' ', strip_tags($desc)));
            $excerpt = mb_substr($excerpt, 0, 90, 'UTF-8');
            $ts = strtotime((string)$item->pubDate);
            $out[] = array(
                'title'     => (string)$item->title,
                'link'      => (string)$item->link,
                'thumbnail' => $thumb,
                'date'      => $ts ? date('Y.m.d', $ts) : '',
                'excerpt'   => $excerpt,
            );
        }
    }
}

$json = json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if (!empty($out)) {
    @file_put_contents($cacheFile, $json);
}
echo $json;
