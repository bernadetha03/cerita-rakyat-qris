<?php
function header_html(string $title): void { ?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=e($title)?> - <?=e((string)envv('APP_NAME'))?></title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="stylesheet" href="<?=url('/assets/style.css')?>"></head><body>
<nav class="navbar navbar-expand-lg bg-dark navbar-dark"><div class="container"><a class="navbar-brand fw-bold" href="<?=url('/')?>">📚 Cerita Rakyat</a><div class="ms-auto"><a class="btn btn-outline-light btn-sm" href="<?=url('/cek-transaksi')?>">Cek Transaksi</a></div></div></nav><main class="container py-5">
<?php }
function footer_html(): void { ?></main><footer class="border-top py-4 text-center text-secondary small">Cerita Rakyat Nusantara • Pembayaran QRIS</footer></body></html><?php }
