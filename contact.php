<?php
/* =============================================================
   お問い合わせフォーム 受信スクリプト（ラッコサーバー / PHP）
   - index.html の <form action="contact.php"> から POST を受け取りメール送信
   - 送信後は index.html?sent=1#contact（失敗時 sent=0）へ戻る
   -------------------------------------------------------------
   ▼ 設置前に1か所だけ設定してください
     $TO … 受信したいメールアドレス（ドメイン取得後のアドレス推奨）
   ============================================================= */

$TO   = 'nulliro@gmail.com';        // ← 受信先メール（変更可）
$FROM = 'noreply@llfokinawa.com';   // 送信元（配信性のため独自ドメインのアドレス）
$SITE = '平和学習 沖縄';
$PAGE = 'index.html';               // 戻り先ページ

mb_language('Japanese');
mb_internal_encoding('UTF-8');

function go_back($ok) {
    global $PAGE;
    header('Location: ' . $PAGE . '?sent=' . ($ok ? '1' : '0') . '#contact');
    exit;
}

// POST 以外はトップへ
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { go_back(true); }

/* ===== スパム対策（適度）：該当時は「成功したフリ」で無視し、メールは送らない ===== */

// (1) JS実行の証明。多くのスパムbotはJSを動かさず、この値を送れない
if (($_POST['jsok'] ?? '') !== '1') { go_back(true); }

// (2) ハニーポット：人間には見えない隠し入力。埋まっていればbot
if (!empty($_POST['website'])) { go_back(true); }

// (3) 同一IPの連投制限（20秒に1回まで）。書き込めない環境では素通り＝本物は弾かない
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
if ($ip !== '') {
    $rl = sys_get_temp_dir() . '/llf_contact_' . md5($ip) . '.flag';
    $last = @filemtime($rl);
    if ($last !== false && (time() - $last) < 20) { go_back(true); }
    @touch($rl);
}

$name    = trim($_POST['name']    ?? '');
$email   = trim($_POST['email']   ?? '');
$message = trim($_POST['message'] ?? '');

// (4) 文字数・内容フィルタ（長すぎ／宣伝リンク多用／名前・メール内のURLはbot）
$tooLong  = mb_strlen($name) > 100 || mb_strlen($email) > 200 || mb_strlen($message) > 4000;
$linkNum  = preg_match_all('~https?://|www\.~i', $message);
$badField = preg_match('~https?://~i', $name . ' ' . $email);
if ($tooLong || $linkNum >= 2 || $badField) { go_back(true); }

// バリデーション（必須・メール形式）
$valid = ($name !== '') && ($message !== '') && filter_var($email, FILTER_VALIDATE_EMAIL);
if (!$valid) { go_back(false); }

// ヘッダーインジェクション対策（改行除去）
$clean = function ($s) { return str_replace(array("\r", "\n"), ' ', $s); };

$subject = '【' . $SITE . '】お問い合わせ（' . $clean($name) . ' 様）';

$body  = "ウェブサイトのお問い合わせフォームから送信がありました。\n\n";
$body .= "──────────────────\n";
$body .= "お名前：" . $name . "\n";
$body .= "メール：" . $email . "\n";
$body .= "──────────────────\n\n";
$body .= "【お問い合わせ内容】\n" . $message . "\n";

// From はドメイン側アドレス、Reply-To に送信者を入れて返信しやすく
$headers  = 'From: ' . mb_encode_mimeheader($SITE) . ' <' . $FROM . ">\r\n";
$headers .= 'Reply-To: ' . $clean($email) . "\r\n";

$sent = mb_send_mail($TO, $subject, $body, $headers);

go_back($sent);
