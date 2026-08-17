<?php
declare(strict_types=1);
session_start();

function envv(string $key, $default=null) {
    static $env = null;
    if ($env === null) {
        $env = $_ENV + $_SERVER;
        $file = dirname(__DIR__).'/.env';
        if (is_file($file)) {
            foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
                [$k,$v] = array_map('trim', explode('=', $line, 2));
                $v = trim($v, "\"'");
                $env[$k] = $v;
            }
        }
    }
    return $env[$key] ?? $default;
}

date_default_timezone_set((string) envv('APP_TIMEZONE','Asia/Jayapura'));

function db(): PDO {
    static $pdo;
    if (!$pdo) {
        $host = (string)envv('DB_HOST', envv('MYSQLHOST','127.0.0.1'));
        $port = (string)envv('DB_PORT', envv('MYSQLPORT','3306'));
        $name = (string)envv('DB_DATABASE', envv('MYSQLDATABASE','cerita_rakyat'));
        $user = (string)envv('DB_USERNAME', envv('MYSQLUSER','root'));
        $pass = (string)envv('DB_PASSWORD', envv('MYSQLPASSWORD',''));
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name);
        $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    }
    return $pdo;
}
function e(?string $s): string { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function app_url(): string {
    $configured = trim((string)envv('APP_URL',''));
    if ($configured !== '') return rtrim($configured,'/');
    $railway = trim((string)envv('RAILWAY_PUBLIC_DOMAIN',''));
    if ($railway !== '') return 'https://'.rtrim($railway,'/');
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8080';
    $https = (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    return ($https ? 'https://' : 'http://').$host;
}
function url(string $path=''): string { return app_url().'/'.ltrim($path,'/'); }
function money($n): string { return 'Rp'.number_format((float)$n,0,',','.'); }
function redirect(string $path): never { header('Location: '.(str_starts_with($path,'http') ? $path : url($path))); exit; }
function random_token(int $bytes=24): string { return bin2hex(random_bytes($bytes)); }
function csrf(): string { if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(24)); return $_SESSION['csrf']; }
function csrf_check(): void { if(!hash_equals($_SESSION['csrf'] ?? '', $_POST['_csrf'] ?? '')) { http_response_code(419); exit('CSRF token tidak valid.'); } }
function admin_ok(): bool { return !empty($_SESSION['admin']); }
function admin_guard(): void { if(!admin_ok()) redirect('/admin/login'); }

function midtrans_charge(array $trx, array $book): array {
    if (filter_var(envv('PAYMENT_SIMULATION','true'), FILTER_VALIDATE_BOOLEAN)) {
        return ['ok'=>true,'transaction_status'=>'pending','qr_url'=>url('/simulate-qr.php?order='.rawurlencode($trx['order_id'])),'raw'=>['simulation'=>true]];
    }
    $prod = filter_var(envv('MIDTRANS_IS_PRODUCTION','false'), FILTER_VALIDATE_BOOLEAN);
    $endpoint = $prod ? 'https://api.midtrans.com/v2/charge' : 'https://api.sandbox.midtrans.com/v2/charge';
    $payload = [
        'payment_type'=>'qris',
        'transaction_details'=>['order_id'=>$trx['order_id'],'gross_amount'=>(int)$trx['amount']],
        'item_details'=>[['id'=>(string)$book['id'],'price'=>(int)$trx['amount'],'quantity'=>1,'name'=>mb_substr($book['title'],0,50)]],
        'qris'=>['acquirer'=>'gopay']
    ];
    $ch=curl_init($endpoint);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>['Content-Type: application/json','Accept: application/json'],CURLOPT_USERPWD=>(string)envv('MIDTRANS_SERVER_KEY').':',CURLOPT_POSTFIELDS=>json_encode($payload),CURLOPT_TIMEOUT=>30]);
    $body=curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
    $json=json_decode((string)$body,true) ?: [];
    if($code<200 || $code>=300) return ['ok'=>false,'error'=>$json['status_message'] ?? $err ?: 'Gagal membuat transaksi QRIS','raw'=>$json];
    $qr=null; foreach(($json['actions']??[]) as $a){ if(in_array($a['name']??'',['generate-qr-code-v2','generate-qr-code'],true)){ $qr=$a['url']??null; if($qr) break; } }
    return ['ok'=>true,'transaction_status'=>$json['transaction_status']??'pending','qr_url'=>$qr,'raw'=>$json];
}

function midtrans_status(string $orderId): array {
    if (filter_var(envv('PAYMENT_SIMULATION','true'), FILTER_VALIDATE_BOOLEAN)) return ['transaction_status'=>'pending'];
    $prod=filter_var(envv('MIDTRANS_IS_PRODUCTION','false'), FILTER_VALIDATE_BOOLEAN);
    $endpoint=($prod?'https://api.midtrans.com':'https://api.sandbox.midtrans.com').'/v2/'.rawurlencode($orderId).'/status';
    $ch=curl_init($endpoint); curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPAUTH=>CURLAUTH_BASIC,CURLOPT_USERPWD=>(string)envv('MIDTRANS_SERVER_KEY').':',CURLOPT_TIMEOUT=>20]);
    $body=curl_exec($ch); curl_close($ch); return json_decode((string)$body,true) ?: [];
}
function is_success_status(array $n): bool { return in_array($n['transaction_status']??'', ['settlement','capture'], true) && (($n['fraud_status']??'accept')==='accept'); }
