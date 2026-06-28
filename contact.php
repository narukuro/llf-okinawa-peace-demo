<?php
/* =============================================================
   お問い合わせフォーム 受信スクリプト（ラッコサーバー / PHP）
   - index.html の <form action="contact.php"> から POST を受け取りメール送信
   - 送信後は index.html?sent=1#contact（失敗時 sent=0）へ戻る
   -------------------------------------------------------------
   ▼ 設置前に1か所だけ設定してください
     $TO … 受信したいメールアドレス（ドメイン取得後のアドレス推奨）
   ============================================================= */

$TO   = 'info@example.com';      // ← TODO: 受信用メールアドレスに変更
$SITE = '平和学習 沖縄';
$PAGE = 'index.html';            // 戻り先ページ（index.php にした場合は変更）

mb_language('Japanese');
mb_internal_encoding('UTF-8');

function go_back($ok) {
    global $PAGE;
    header('Location: ' . $PAGE . '?sent=' . ($ok ? '1' : '0') . '#contact');
    exit;
}

// POST 以外はトップへ
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { go_back(true); }

// 簡易スパム対策（ハニーポット：人間は入力しない隠しフィールド）
if (!empty($_POST['website'])) { go_back(true); }

$name    = trim($_POST['name']    ?? '');
$email   = trim($_POST['email']   ?? '');
$message = trim($_POST['message'] ?? '');

// バリデーション
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
$headers  = 'From: ' . mb_encode_mimeheader($SITE) . ' <' . $TO . ">\r\n";
$headers .= 'Reply-To: ' . $clean($email) . "\r\n";

$sent = mb_send_mail($TO, $subject, $body, $headers);

go_back($sent);
