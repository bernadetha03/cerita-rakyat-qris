<?php
require dirname(__DIR__).'/app/bootstrap.php'; require dirname(__DIR__).'/app/views.php';
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH) ?: '/'; $method=$_SERVER['REQUEST_METHOD'];

if($path==='/health'){ header('Content-Type: application/json'); echo json_encode(['ok'=>true,'time'=>date(DATE_ATOM)]); exit; }

if($path==='/' && $method==='GET'){
  $books=db()->query("SELECT * FROM books WHERE is_active=1 ORDER BY id DESC")->fetchAll(); header_html('Beranda'); ?>
  <section class="hero mb-5"><h1 class="display-5 fw-bold">Cerita Rakyat Nusantara</h1><p class="lead mb-0">Temukan cerita rakyat, beli dengan QRIS, lalu baca buku digital langsung tanpa membuat akun.</p></section>
  <h2 class="mb-4">Koleksi Buku</h2><div class="row g-4"><?php foreach($books as $b):?><div class="col-md-4"><div class="card book-card"><img class="cover" src="<?=e($b['cover_url'])?>" alt=""><div class="card-body d-flex flex-column"><h5><?=e($b['title'])?></h5><p class="text-secondary flex-grow-1"><?=e($b['synopsis'])?></p><div class="price mb-3"><?=money($b['price'])?></div><a class="btn btn-dark" href="<?=url('/book/'.$b['slug'])?>">Lihat & Beli</a></div></div></div><?php endforeach;?></div>
<?php footer_html(); exit; }

if(preg_match('#^/book/([a-z0-9-]+)$#',$path,$m) && $method==='GET'){
  $st=db()->prepare('SELECT * FROM books WHERE slug=? AND is_active=1');$st->execute([$m[1]]);$b=$st->fetch(); if(!$b){http_response_code(404);exit('Buku tidak ditemukan');}
  header_html($b['title']);?><div class="row g-5 align-items-start"><div class="col-md-5"><img class="img-fluid rounded-4 shadow" src="<?=e($b['cover_url'])?>"></div><div class="col-md-7"><span class="badge text-bg-secondary mb-2">Buku Digital</span><h1><?=e($b['title'])?></h1><p class="lead text-secondary"><?=e($b['synopsis'])?></p><div class="display-6 fw-bold my-4"><?=money($b['price'])?></div><form method="post" action="<?=url('/buy')?>"><input type="hidden" name="_csrf" value="<?=csrf()?>"><input type="hidden" name="book_id" value="<?=$b['id']?>"><button class="btn btn-success btn-lg">Beli dengan QRIS</button></form><p class="small text-secondary mt-3">Setelah pembayaran berhasil, ko akan menerima kode transaksi dan link baca khusus.</p></div></div><?php footer_html();exit;
}

if($path==='/buy' && $method==='POST'){
 csrf_check(); $id=(int)($_POST['book_id']??0); $st=db()->prepare('SELECT * FROM books WHERE id=? AND is_active=1');$st->execute([$id]);$b=$st->fetch();if(!$b)exit('Buku tidak ditemukan');
 $order='CR-'.date('YmdHis').'-'.strtoupper(substr(bin2hex(random_bytes(4)),0,6));$access=random_token();
 $st=db()->prepare("INSERT INTO transactions(order_id,book_id,amount,payment_method,payment_status,access_token,created_at) VALUES(?,?,?,'QRIS','pending',?,NOW())");$st->execute([$order,$id,$b['price'],$access]);
 $trx=['order_id'=>$order,'amount'=>$b['price']];$pay=midtrans_charge($trx,$b); if(!$pay['ok']){db()->prepare("UPDATE transactions SET payment_status='error',gateway_response=? WHERE order_id=?")->execute([json_encode($pay['raw']),$order]);exit('Gagal membuat QRIS: '.e($pay['error']));}
 db()->prepare('UPDATE transactions SET qr_url=?, gateway_response=? WHERE order_id=?')->execute([$pay['qr_url'],json_encode($pay['raw']),$order]); redirect('/payment/'.$order);
}

if(preg_match('#^/payment/(CR-[A-Z0-9-]+)$#',$path,$m) && $method==='GET'){
 $st=db()->prepare('SELECT t.*,b.title FROM transactions t JOIN books b ON b.id=t.book_id WHERE t.order_id=?');$st->execute([$m[1]]);$t=$st->fetch();if(!$t)exit('Transaksi tidak ditemukan');
 if($t['payment_status']==='paid') redirect('/receipt/'.$t['order_id']); header_html('Pembayaran QRIS');?><div class="text-center"><h1>Scan QRIS</h1><p><?=e($t['title'])?> • <strong><?=money($t['amount'])?></strong></p><?php if($t['qr_url']):?><img class="qr shadow" src="<?=e($t['qr_url'])?>" alt="QRIS"><?php endif;?><p class="mt-3">Status: <span class="badge text-bg-warning">Menunggu pembayaran</span></p><div class="d-flex gap-2 justify-content-center no-print"><a class="btn btn-dark" href="<?=url('/check/'.$t['order_id'])?>">Cek Status Pembayaran</a><?php if(filter_var(envv('PAYMENT_SIMULATION','true'),FILTER_VALIDATE_BOOLEAN)):?><form method="post" action="<?=url('/simulate-paid')?>"><input type="hidden" name="_csrf" value="<?=csrf()?>"><input type="hidden" name="order_id" value="<?=e($t['order_id'])?>"><button class="btn btn-outline-success">Simulasikan Berhasil</button></form><?php endif;?></div><p class="small text-secondary mt-4">Order ID: <?=e($t['order_id'])?></p></div><?php footer_html();exit;
}

if(preg_match('#^/check/(CR-[A-Z0-9-]+)$#',$path,$m)){
 $st=db()->prepare('SELECT * FROM transactions WHERE order_id=?');$st->execute([$m[1]]);$t=$st->fetch();if(!$t)exit('Transaksi tidak ditemukan'); $s=midtrans_status($t['order_id']); if(is_success_status($s)){db()->prepare("UPDATE transactions SET payment_status='paid',paid_at=COALESCE(paid_at,NOW()),gateway_response=? WHERE order_id=?")->execute([json_encode($s),$t['order_id']]);} redirect('/payment/'.$t['order_id']);
}
if($path==='/simulate-paid' && $method==='POST' && filter_var(envv('PAYMENT_SIMULATION','true'),FILTER_VALIDATE_BOOLEAN)){
 csrf_check();$order=$_POST['order_id']??'';db()->prepare("UPDATE transactions SET payment_status='paid',paid_at=NOW() WHERE order_id=?")->execute([$order]);redirect('/receipt/'.$order);
}

if($path==='/midtrans/webhook' && $method==='POST'){
 $raw=file_get_contents('php://input');$n=json_decode($raw,true)?:[];$expected=hash('sha512',($n['order_id']??'').($n['status_code']??'').($n['gross_amount']??'').envv('MIDTRANS_SERVER_KEY',''));
 if(!hash_equals($expected,(string)($n['signature_key']??''))){http_response_code(401);echo 'invalid signature';exit;}
 $order=$n['order_id']??'';$status=is_success_status($n)?'paid':(($n['transaction_status']??'')==='expire'?'expired':'pending');
 db()->prepare("UPDATE transactions SET payment_status=?, paid_at=IF(?='paid',COALESCE(paid_at,NOW()),paid_at), gateway_response=? WHERE order_id=?")->execute([$status,$status,$raw,$order]);echo 'OK';exit;
}

if(preg_match('#^/receipt/(CR-[A-Z0-9-]+)$#',$path,$m)){
 $st=db()->prepare('SELECT t.*,b.title FROM transactions t JOIN books b ON b.id=t.book_id WHERE t.order_id=?');$st->execute([$m[1]]);$t=$st->fetch();if(!$t||$t['payment_status']!=='paid')redirect('/payment/'.$m[1]);header_html('Bukti Transaksi');?><div class="receipt"><div class="text-center mb-4"><h2>BUKTI TRANSAKSI</h2><span class="badge text-bg-success">PEMBAYARAN BERHASIL</span></div><table class="table"><tr><th>No. Transaksi</th><td><?=e($t['order_id'])?></td></tr><tr><th>Tanggal</th><td><?=date('d-m-Y',strtotime($t['paid_at']))?></td></tr><tr><th>Waktu/Jam</th><td><?=date('H:i:s',strtotime($t['paid_at']))?> WIT</td></tr><tr><th>Judul Buku</th><td><?=e($t['title'])?></td></tr><tr><th>Harga</th><td><?=money($t['amount'])?></td></tr><tr><th>Metode Pembayaran</th><td>QRIS</td></tr></table><div class="d-grid gap-2 no-print"><a class="btn btn-dark" href="<?=url('/read/'.$t['access_token'])?>">Baca Buku</a><button onclick="window.print()" class="btn btn-outline-secondary">Cetak / Simpan PDF</button></div><p class="small text-secondary mt-3">Simpan nomor transaksi atau link baca ini. Karena website tidak menggunakan akun, link baca adalah kunci akses buku.</p></div><?php footer_html();exit;
}

if(preg_match('#^/read/([a-f0-9]{48})$#',$path,$m)){
 $st=db()->prepare("SELECT b.*,t.order_id FROM transactions t JOIN books b ON b.id=t.book_id WHERE t.access_token=? AND t.payment_status='paid'");$st->execute([$m[1]]);$b=$st->fetch();if(!$b){http_response_code(403);exit('Akses tidak valid atau pembayaran belum berhasil.');}header_html($b['title']);?><article class="reader"><div class="text-center mb-5"><h1><?=e($b['title'])?></h1><div class="text-secondary">Buku digital • <?=e($b['order_id'])?></div></div><?=$b['content_html']?></article><?php footer_html();exit;
}

if($path==='/cek-transaksi' && $method==='GET'){header_html('Cek Transaksi');?><div class="mx-auto" style="max-width:520px"><h1>Cek Transaksi</h1><p>Masukkan nomor transaksi untuk membuka kembali bukti pembayaran.</p><form method="post"><input type="hidden" name="_csrf" value="<?=csrf()?>"><input class="form-control form-control-lg mb-3" name="order_id" placeholder="CR-2026..."><button class="btn btn-dark w-100">Cek</button></form></div><?php footer_html();exit;}
if($path==='/cek-transaksi' && $method==='POST'){csrf_check();$o=trim($_POST['order_id']??'');redirect('/receipt/'.$o);}

if($path==='/admin/login'){ if($method==='POST'){csrf_check();if(hash_equals((string)envv('ADMIN_PASSWORD',''),(string)($_POST['password']??''))){$_SESSION['admin']=true;redirect('/admin');}$err='Password salah.';} header_html('Admin Login');?><div class="mx-auto" style="max-width:420px"><h1>Admin</h1><?php if(isset($err)):?><div class="alert alert-danger"><?=$err?></div><?php endif;?><form method="post"><input type="hidden" name="_csrf" value="<?=csrf()?>"><input type="password" class="form-control mb-3" name="password" placeholder="Password admin"><button class="btn btn-dark w-100">Masuk</button></form></div><?php footer_html();exit;}
if($path==='/admin/logout'){session_destroy();redirect('/');}
if($path==='/admin'){admin_guard();$stats=db()->query("SELECT COUNT(*) trx, SUM(payment_status='paid') paid, COALESCE(SUM(CASE WHEN payment_status='paid' THEN amount ELSE 0 END),0) revenue FROM transactions")->fetch();$books=db()->query('SELECT * FROM books ORDER BY id DESC')->fetchAll();$trx=db()->query('SELECT t.*,b.title FROM transactions t JOIN books b ON b.id=t.book_id ORDER BY t.id DESC LIMIT 30')->fetchAll();header_html('Dashboard Admin');?><div class="d-flex justify-content-between"><h1>Dashboard Admin</h1><a href="<?=url('/admin/logout')?>">Keluar</a></div><div class="row g-3 my-3"><div class="col-md-4"><div class="card p-3"><b>Total Transaksi</b><div class="display-6"><?=$stats['trx']?></div></div></div><div class="col-md-4"><div class="card p-3"><b>Berhasil</b><div class="display-6"><?=$stats['paid']?></div></div></div><div class="col-md-4"><div class="card p-3"><b>Pendapatan</b><div class="h2"><?=money($stats['revenue'])?></div></div></div></div><h3 class="mt-5">Transaksi Terbaru</h3><div class="table-responsive"><table class="table table-striped"><tr><th>Order</th><th>Buku</th><th>Harga</th><th>Status</th><th>Waktu</th></tr><?php foreach($trx as $t):?><tr><td><?=e($t['order_id'])?></td><td><?=e($t['title'])?></td><td><?=money($t['amount'])?></td><td><?=e($t['payment_status'])?></td><td><?=e($t['created_at'])?></td></tr><?php endforeach;?></table></div><?php footer_html();exit;}

http_response_code(404);header_html('404');echo '<h1>404</h1><p>Halaman tidak ditemukan.</p>';footer_html();
