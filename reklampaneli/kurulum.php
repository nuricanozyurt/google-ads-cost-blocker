<?php
require_once 'guvenlik.php';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ads Kalkanı | Kurulum</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f6f9; }
        .code-box { background: #1e1e1e; color: #00ff00; padding: 15px; border-radius: 8px; font-family: monospace; font-size: 0.9rem; overflow-x: auto; }
        .nav-link { font-weight: bold; color: #495057; }
        .nav-tabs .nav-link.active { color: #0d6efd; border-bottom: 3px solid #0d6efd; background: transparent; border-top: 0; border-left: 0; border-right: 0; }
    </style>
</head>
<body>

<!-- ÜST MENÜ (NAVBAR) -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php">🛡️ Ads Kalkanı</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto fw-bold">
                <li class="nav-item"><a class="nav-link text-light" href="index.php">📊 Dashboard</a></li>
                <li class="nav-item"><a class="nav-link active text-white" href="kurulum.php">🔌 Kurulum & Entegrasyon</a></li>
                <li class="nav-item"><a class="nav-link text-light" href="ayarlar.php">⚙️ Ayarlar</a></li>
            </ul>
        </div>
    </div>
</nav>

<div class="container mt-5 mb-5">
    <div class="card border-0 shadow-sm" style="border-radius: 15px; overflow: hidden;">
        <div class="card-header bg-primary text-white p-4 border-0">
            <h4 class="mb-0 fw-bold">🚀 Sisteminizi Web Sitenize Bağlayın</h4>
            <p class="mb-0 small opacity-75 mt-1">Sadece tek satırlık bir kod ile bot kalkanını saniyeler içinde aktif edin.</p>
        </div>
        <div class="card-body p-4">
            
            <ul class="nav nav-tabs mb-4" id="kurulumTabs" role="tablist">
                <li class="nav-item" role="presentation"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#wordpress">WordPress Kurulumu</button></li>
                <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#php">Özel PHP Site</button></li>
                <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#googleads">Google Ads Ayarı</button></li>
            </ul>

            <div class="tab-content" id="myTabContent">
                <!-- WordPress Sekmesi -->
                <div class="tab-pane fade show active" id="wordpress">
                    <h6 class="fw-bold text-dark">Adım 1: Tema Dosyanızı Açın</h6>
                    <p class="text-muted small">WordPress admin panelinize girin. <strong>Görünüm > Tema Dosya Düzenleyici</strong> sekmesinden <code>header.php</code> (Tema Üst Bölümü) dosyasını seçin.</p>
                    
                    <h6 class="fw-bold text-dark mt-4">Adım 2: Kodu En Üste Yapıştırın</h6>
                    <p class="text-muted small">Aşağıdaki PHP kodunu kopyalayın ve <code>header.php</code> dosyasının <strong>en üstüne</strong> (<code>&lt;!DOCTYPE html&gt;</code> etiketinden bile önceye) yapıştırıp kaydedin.</p>
                    <div class="code-box mb-3">&lt;?php require_once 'yakalayici.php'; ?&gt;</div>
                    <div class="alert alert-warning small fw-bold">Not: `yakalayici.php` ve `db.php` dosyalarınızın WordPress ana dizininde (public_html) yüklü olduğundan emin olun.</div>
                </div>

                <!-- PHP Sekmesi -->
                <div class="tab-pane fade" id="php">
                    <h6 class="fw-bold text-dark">PHP Yazılımlar İçin Entegrasyon</h6>
                    <p class="text-muted small">Web sitenizin ana dizininde bulunan <code>index.php</code>, <code>header.php</code> veya trafiği ilk karşılayan ana dosyanızın en üst satırına (boşluk bırakmadan) şu kodu ekleyin:</p>
                    <div class="code-box mb-3">&lt;?php require_once 'yakalayici.php'; ?&gt;</div>
                    <p class="text-muted small mt-2">Bu kod, siteniz yüklenmeden önce ziyaretçinin IP'sini tarayacak ve bot ise siteyi açmadan engelleyecektir.</p>
                </div>

                <!-- Google Ads Sekmesi -->
                <div class="tab-pane fade" id="googleads">
                    <h6 class="fw-bold text-dark">Google Ads URL İzleme Şablonu (Zorunlu)</h6>
                    <p class="text-muted small">Sistemin reklam tıklamalarını yakalayabilmesi ve kelime analizi yapabilmesi için Google Ads panelinizde <strong>Ayarlar > Hesap Ayarları > İzleme</strong> bölümüne gidip şu şablonu yapıştırın:</p>
                    <div class="code-box mb-3">{lpurl}?gclid={gclid}&keyword={keyword}</div>
                    <p class="text-muted small mt-2">Bu ayar sayesinde sistemimiz, sitenize gelen kişinin hangi kelimeyi aratarak geldiğini anında veritabanına yazar.</p>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>