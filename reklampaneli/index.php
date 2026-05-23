<?php 
require_once 'guvenlik.php'; // DB ve Session buranın içinde
date_default_timezone_set('Europe/Istanbul'); 

// --- VERİTABANI ŞİŞME KORUMASI (30 Günden Eski Kayıtları Otomatik Siler) ---
try {
    $db->query("DELETE FROM reklam_tiklamalari WHERE tarih_saat < DATE_SUB(NOW(), INTERVAL 30 DAY)");
} catch(PDOException $e) {}

try {
    $db->query("ALTER TABLE reklam_tiklamalari ADD COLUMN fingerprint VARCHAR(100) DEFAULT ''");
    $db->query("ALTER TABLE reklam_tiklamalari ADD COLUMN sitede_kalis_suresi INT DEFAULT 0");
    $db->query("ALTER TABLE reklam_tiklamalari ADD COLUMN site_ici_tiklama INT DEFAULT 0");
} catch (PDOException $e) { }

$ayar_cek = $db->query("SELECT tiklama_siniri FROM ayarlar WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
$dinamik_sinir = $ayar_cek ? $ayar_cek['tiklama_siniri'] : 3;

$filtre = isset($_GET['filtre']) ? $_GET['filtre'] : '30';
$tur = isset($_GET['tur']) ? $_GET['tur'] : 'hepsi'; 
$sayfa = isset($_GET['sayfa']) && (int)$_GET['sayfa'] > 0 ? (int)$_GET['sayfa'] : 1;
$limit = 15; 
$offset = ($sayfa - 1) * $limit;

$kosullar = [];
if ($filtre != 'tumu') {
    $kosullar[] = "tarih_saat >= DATE_SUB(NOW(), INTERVAL " . intval($filtre) . " DAY)";
}
$where_sql = count($kosullar) > 0 ? "WHERE " . implode(" AND ", $kosullar) : "";

$filtre_metni = ['7' => 'Son 7 Gün', '14' => 'Son 14 Gün', '30' => 'Son 1 Ay', 'tumu' => 'Tüm Zamanlar'];

$toplam_tiklama = $db->query("SELECT COUNT(*) FROM reklam_tiklamalari $where_sql")->fetchColumn();
$toplam_engellenen = $db->query("SELECT COUNT(DISTINCT ip_adresi) FROM reklam_tiklamalari WHERE durum IN (1,2) " . ($where_sql ? " AND ".str_replace("WHERE ", "", $where_sql) : ""))->fetchColumn();

$kelime_sorgu_tum = $db->query("SELECT kelime, COUNT(*) as sayi FROM reklam_tiklamalari $where_sql GROUP BY kelime ORDER BY sayi DESC")->fetchAll(PDO::FETCH_ASSOC);
$cihaz_sorgu = $db->query("SELECT cihaz, COUNT(*) as sayi FROM reklam_tiklamalari $where_sql GROUP BY cihaz")->fetchAll(PDO::FETCH_ASSOC);
$saat_sorgu = $db->query("SELECT HOUR(tarih_saat) as saat, COUNT(*) as sayi FROM reklam_tiklamalari $where_sql GROUP BY HOUR(tarih_saat) ORDER BY sayi DESC LIMIT 2")->fetchAll(PDO::FETCH_ASSOC);

// GRAFİK İÇİN KELİME VERİLERİNİ JAVASCRIPT'E HAZIRLAMA
$grafik_kelimeler = [];
$grafik_sayilar = [];
$limit_grafik = 0;
foreach($kelime_sorgu_tum as $k) {
    if($limit_grafik >= 7) break; 
    $grafik_kelimeler[] = ($k['kelime'] != '') ? $k['kelime'] : 'Direkt/Bilinmiyor';
    $grafik_sayilar[] = $k['sayi'];
    $limit_grafik++;
}

$having_sql = "";
if ($tur == 'bot') {
    $having_sql = " HAVING durum_kontrol = 2";
} elseif ($tur == 'supheli') {
    $having_sql = " HAVING (durum_kontrol = 1 OR tiklama_sayisi > $dinamik_sinir) AND durum_kontrol != 2";
} elseif ($tur == 'temiz') {
    $having_sql = " HAVING tiklama_sayisi <= $dinamik_sinir AND durum_kontrol = 0";
}

$count_sql = "SELECT COUNT(*) FROM (SELECT ip_adresi, MAX(durum) as durum_kontrol, COUNT(*) as tiklama_sayisi FROM reklam_tiklamalari $where_sql GROUP BY ip_adresi $having_sql) as t";
$toplam_kayit = $db->query($count_sql)->fetchColumn();
$toplam_sayfa = ceil($toplam_kayit / $limit);

$sql = "SELECT ip_adresi, 
        COUNT(*) as tiklama_sayisi, 
        MAX(tarih_saat) as son_gorulme, 
        MIN(tarih_saat) as ilk_gorulme, 
        MAX(durum) as durum_kontrol, 
        MAX(sehir) as g_sehir, 
        MAX(operator) as g_operator, 
        MAX(cihaz) as g_cihaz, 
        MAX(kelime) as g_kelime,
        MAX(isletim_sistemi) as g_os,
        MAX(user_agent) as g_ua,
        MAX(fingerprint) as g_fingerprint,
        SUM(sitede_kalis_suresi) as toplam_sure,
        SUM(site_ici_tiklama) as toplam_sitetik
        FROM reklam_tiklamalari 
        $where_sql
        GROUP BY ip_adresi
        $having_sql
        ORDER BY tiklama_sayisi DESC
        LIMIT $limit OFFSET $offset";

$sorgu = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ads Kalkanı | Pro Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background-color: #f4f6f9; padding-bottom: 80px; }
        .stat-card { border-radius: 12px; border: none; transition: transform 0.2s, box-shadow 0.2s; cursor: default; }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 .5rem 1rem rgba(0,0,0,.10)!important; }
        .help-card { cursor: pointer; background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%); color: white; }
        .table-card { border-radius: 12px; border: none; overflow: hidden; }
        .bottom-refresh-bar { position: fixed; bottom: 0; left: 0; right: 0; background: white; z-index: 1000; border-top: 1px solid #e9ecef; }
        .filter-btn-group .btn { font-size: 0.85rem; font-weight: bold; }
        .table-hover>tbody>tr.table-dark:hover>* { background-color: #1a1e21 !important; color: white !important;}
        .table-hover>tbody>tr.table-success:hover>* { background-color: #c1e6cb !important;}
        .table-hover>tbody>tr.table-danger:hover>* { background-color: #f1b0b7 !important;}
        .search-box { border-radius: 20px; border: 1px solid #ddd; padding-left: 40px; }
        .search-icon { position: absolute; left: 15px; top: 10px; color: #aaa; }
        .analiz-card { border-radius: 12px; border: none; background: white; padding: 15px; height: 100%; box-shadow: 0 .125rem .25rem rgba(0,0,0,.075); transition: transform 0.2s, box-shadow 0.2s;}
        .analiz-card.clickable:hover { transform: translateY(-3px); box-shadow: 0 .5rem 1rem rgba(0,0,0,.10)!important; cursor: pointer; }
        .ip-link { text-decoration: none; border-bottom: 1px dashed; padding-bottom: 1px; transition: all 0.2s; }
        .ip-link:hover { color: #0d6efd !important; border-bottom: 1px solid #0d6efd; }
        .detail-row { border-bottom: 1px solid #eee; padding: 10px 0; display: flex; justify-content: space-between; align-items: center; }
        .detail-row:last-child { border-bottom: none; }
        .detail-label { font-size: 0.8rem; color: #6c757d; font-weight: 600; text-transform: uppercase; }
        .detail-value { font-size: 0.9rem; font-weight: bold; color: #212529; text-align: right; word-break: break-all; max-width: 60%; }
        .guide-box { background-color: #f8f9fa; border-left: 4px solid #0d6efd; padding: 12px; border-radius: 5px; margin-bottom: 15px; }
        .chart-container { position: relative; height: 260px; width: 100%; }
        .alert-pro { background-color: #fff3cd; border-left: 5px solid #ffc107; border-radius: 8px; color: #664d03; padding: 15px; font-size: 0.9rem; font-weight: 500; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php">🛡️ Ads Kalkanı</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto fw-bold">
                <li class="nav-item"><a class="nav-link active text-white" href="index.php">📊 Dashboard</a></li>
                <li class="nav-item"><a class="nav-link text-light" href="kurulum.php">🔌 Kurulum & Entegrasyon</a></li>
                <li class="nav-item"><a class="nav-link text-light" href="ayarlar.php">⚙️ Ayarlar</a></li>
                <!-- Müşterinin Çıkış Yapması İçin Buton -->
                <li class="nav-item ms-lg-3"><a class="btn btn-danger btn-sm mt-1 fw-bold shadow-sm" href="logout.php">Çıkış Yap</a></li>
            </ul>
        </div>
    </div>
</nav>

<div class="container mt-4">
    
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mb-4 gap-3">
        <h2 class="h4 text-dark fw-bold mb-0">🛡️ Ads Kalkanı Kontrol Paneli</h2>
        <div class="d-flex gap-2">
            <button class="btn btn-success btn-sm fw-bold shadow-sm px-3" data-bs-toggle="modal" data-bs-target="#iadeModal">💸 Ads İade Asistanı</button>
        </div>
    </div>

    <!-- İstatistik Kartları -->
    <div class="row mb-4">
        <div class="col-6 col-md-3 mb-3">
            <div class="card stat-card bg-white shadow-sm h-100 p-3 border-start border-success border-4">
                <div class="text-muted small fw-bold text-uppercase">Google Ads Durumu</div>
                <div class="mt-2 text-dark fw-bold small"><span class="text-success">●</span> Sistem Aktif</div>
            </div>
        </div>
        <div class="col-6 col-md-3 mb-3">
            <div class="card stat-card bg-white shadow-sm h-100 p-3 border-start border-primary border-4">
                <div class="text-muted small fw-bold text-uppercase">Gelen Tıklama</div>
                <h3 class="mt-2 mb-0 text-primary fw-bold"><?php echo $toplam_tiklama; ?> <small class="fs-6 text-muted">Tık</small></h3>
            </div>
        </div>
        <div class="col-6 col-md-3 mb-3">
            <div class="card stat-card bg-white shadow-sm h-100 p-3 border-start border-danger border-4">
                <div class="text-muted small fw-bold text-uppercase">Engellenen </div>
                <h3 class="mt-2 mb-0 text-danger fw-bold"><?php echo $toplam_engellenen; ?> <small class="fs-6 text-muted">IP</small></h3>
            </div>
        </div>
        <div class="col-6 col-md-3 mb-3">
            <div class="card stat-card help-card shadow-sm h-100 p-3 d-flex justify-content-center align-items-center" data-bs-toggle="modal" data-bs-target="#yardimModal">
                <div class="text-center fw-bold small text-uppercase">💡 Nasıl Kullanılır?</div>
            </div>
        </div>
    </div>

    <!-- KELİME GRAFİĞİ -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card analiz-card shadow-sm p-4">
                <!-- Başlık Düzeltildi -->
                <h6 class="fw-bold text-dark text-uppercase mb-3"><span class="me-2">📈</span> En Çok Aranan Kelimeler Grafiği</h6>
                <div class="chart-container">
                    <canvas id="kelimeGrafik"></canvas>
                </div>
                
                <div class="alert-pro mt-4">
                    <span class="fs-5 me-2">⚠️</span> 
                    <strong>Saldırı Bitmesi Beklenen Kelimeler:</strong> 
                    Not: Sistemimizin <em>"Saldırı / Botnet"</em> olarak tespit ettiği kelimeler için Ads panelinizden manuel durdurma işlemi yapmayın. Kalkanımız zararlı IP'leri otomatik blokladığı için saldırı bittiğinde kelimeleriniz temiz trafikle yayında kalmaya devam edecektir.
                </div>
            </div>
        </div>
    </div>

    <!-- ÖZET ANALİZ BÖLÜMÜ -->
    <div class="row mb-4">
        <div class="col-md-4 mb-3">
            <div class="analiz-card">
                <h6 class="fw-bold text-secondary mb-3 text-uppercase" style="font-size: 0.8rem;">📱 Cihaz Dağılımı</h6>
                <?php 
                if(count($cihaz_sorgu) > 0) {
                    foreach($cihaz_sorgu as $cihaz) {
                        $yuzde = ($cihaz['sayi'] / $toplam_tiklama) * 100;
                        echo '<div class="d-flex justify-content-between small fw-bold mt-2"><span>'.$cihaz['cihaz'].'</span><span>%'.round($yuzde).' ('.$cihaz['sayi'].')</span></div>';
                        echo '<div class="progress" style="height: 6px;"><div class="progress-bar bg-primary" style="width: '.$yuzde.'%"></div></div>';
                    }
                } else { echo '<small class="text-muted">Veri yok</small>'; }
                ?>
            </div>
        </div>
        
        <div class="col-md-4 mb-3">
            <div class="analiz-card clickable" data-bs-toggle="modal" data-bs-target="#kelimeModal">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold text-secondary mb-0 text-uppercase" style="font-size: 0.8rem;">🔑 Top Kelimeler</h6>
                    <span class="badge bg-light text-primary border" style="font-size: 0.6rem;">Tümünü Gör 🖱️</span>
                </div>
                <ul class="list-group list-group-flush small">
                    <?php 
                    if(count($kelime_sorgu_tum) > 0) {
                        $sayac = 0;
                        foreach($kelime_sorgu_tum as $kelime) {
                            if($sayac >= 3) break;
                            $k_ad = $kelime['kelime'] != '' ? $kelime['kelime'] : 'Bilinmiyor';
                            echo '<li class="list-group-item d-flex justify-content-between align-items-center px-0 py-1 border-0 bg-transparent">
                                    <span class="text-truncate fw-bold text-dark" style="max-width: 150px;">'.$k_ad.'</span>
                                    <span class="badge bg-light text-dark border rounded-pill">'.$kelime['sayi'].' tık</span>
                                  </li>';
                            $sayac++;
                        }
                    } else { echo '<small class="text-muted">Kelime verisi yok</small>'; }
                    ?>
                </ul>
            </div>
        </div>
        
        <div class="col-md-4 mb-3">
            <div class="analiz-card">
                <h6 class="fw-bold text-secondary mb-3 text-uppercase" style="font-size: 0.8rem;">⏰ En Yoğun Saatler</h6>
                <ul class="list-group list-group-flush small">
                    <?php 
                    if(count($saat_sorgu) > 0) {
                        foreach($saat_sorgu as $saat) {
                            $saat_format = str_pad($saat['saat'], 2, '0', STR_PAD_LEFT) . ':00';
                            echo '<li class="list-group-item d-flex justify-content-between align-items-center px-0 py-1 border-0">
                                    <strong class="text-dark">'.$saat_format.'</strong>
                                    <span class="text-muted">'.$saat['sayi'].' tıklama</span>
                                  </li>';
                        }
                    } else { echo '<small class="text-muted">Veri yok</small>'; }
                    ?>
                </ul>
            </div>
        </div>
    </div>

    <!-- FİLTRE VE ARAMA -->
    <div class="row mb-3 align-items-center">
        <div class="col-md-7 mb-2 text-nowrap overflow-auto pb-2">
            <div class="btn-group shadow-sm filter-btn-group">
                <a href="?filtre=<?php echo $filtre; ?>&tur=hepsi" class="btn btn-white border <?php echo $tur == 'hepsi' ? 'bg-secondary text-white' : 'bg-white'; ?>">Tümü</a>
                <a href="?filtre=<?php echo $filtre; ?>&tur=temiz" class="btn btn-white border <?php echo $tur == 'temiz' ? 'bg-success text-white' : 'bg-white text-success'; ?>">🟢 Kullanıcılar</a>
                <a href="?filtre=<?php echo $filtre; ?>&tur=supheli" class="btn btn-white border <?php echo $tur == 'supheli' ? 'bg-danger text-white' : 'bg-white text-danger'; ?>">🔴 Şüpheliler</a>
                <a href="?filtre=<?php echo $filtre; ?>&tur=bot" class="btn btn-white border <?php echo $tur == 'bot' ? 'bg-dark text-white' : 'bg-white text-dark'; ?>">⚫ Botlar</a>
            </div>
        </div>
        <div class="col-md-5 mb-2 position-relative">
            <span class="search-icon">🔍</span>
            <input type="text" id="tabloArama" class="form-control search-box shadow-sm" placeholder="IP veya Lokasyon Ara...">
        </div>
    </div>

    <form method="POST" id="topluIslemForm">
        <div class="card table-card shadow-sm mb-5">
            <div class="card-header bg-white border-bottom py-3 d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
                <h6 class="mb-0 fw-bold text-secondary text-nowrap">Trafik Analiz Raporu <span class="badge bg-light text-dark border ms-2">Toplam: <?php echo $toplam_kayit; ?> Kayıt</span></h6>
                
                <div class="d-flex align-items-center gap-2">
                    <button type="submit" id="akilliButon" class="btn btn-secondary btn-sm shadow-sm fw-bold px-3" disabled>Seçim Yapın</button>
                    <div class="dropdown">
                        <button class="btn btn-light btn-sm dropdown-toggle border fw-bold px-3 shadow-sm" type="button" id="dateFilterDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            📅 <?php echo $filtre_metni[$filtre]; ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0" aria-labelledby="dateFilterDropdown">
                            <li><a class="dropdown-item small <?php echo $filtre == '7' ? 'active' : ''; ?>" href="?filtre=7&tur=<?php echo $tur; ?>">Son 7 Gün</a></li>
                            <li><a class="dropdown-item small <?php echo $filtre == '14' ? 'active' : ''; ?>" href="?filtre=14&tur=<?php echo $tur; ?>">Son 14 Gün</a></li>
                            <li><a class="dropdown-item small <?php echo $filtre == '30' ? 'active' : ''; ?>" href="?filtre=30&tur=<?php echo $tur; ?>">Son 1 Ay</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item small <?php echo $filtre == 'tumu' ? 'active' : ''; ?>" href="?filtre=tumu&tur=<?php echo $tur; ?>">Tüm Zamanlar</a></li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 border-top-0" id="anaTablo">
                        <thead class="table-light text-muted small">
                            <tr>
                                <th class="ps-4" style="width: 40px;"><input class="form-check-input border-secondary" type="checkbox" id="tumunuSec" style="cursor:pointer;"></th>
                                <th>IP ADRESİ / CİHAZ</th>
                                <th>KELİME / AĞ</th>
                                <th class="text-center">TIKLAMA</th>
                                <th>SON GÖRÜLME</th>
                                <th class="text-end pe-4">SİSTEM DURUMU</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(count($sorgu) > 0): ?>
                                <?php foreach($sorgu as $row): 
                                    $satir_rengi = ""; $ana_yazi = "text-dark"; $alt_yazi = "text-muted";
                                    
                                    if ($row['durum_kontrol'] == 2) {
                                        $satir_rengi = "table-dark"; $ana_yazi = "text-light"; $alt_yazi = "text-light opacity-75";
                                    } elseif ($row['durum_kontrol'] == 1 || $row['tiklama_sayisi'] > $dinamik_sinir) {
                                        $satir_rengi = "table-danger";
                                    } else {
                                        $satir_rengi = "table-success";
                                    }
                                    
                                    $js_durum = ($row['durum_kontrol'] == 0) ? "0" : "1";
                                    
                                    $bot_skor = 0;
                                    if ($row['durum_kontrol'] == 2) { $bot_skor = 100; } 
                                    elseif ($row['durum_kontrol'] == 1) { $bot_skor = 90; } 
                                    else {
                                        if ($row['tiklama_sayisi'] > $dinamik_sinir) $bot_skor += 50;
                                        else $bot_skor += ($row['tiklama_sayisi'] * 10);
                                        if ($row['toplam_sure'] == 0) $bot_skor += 20;
                                        if (empty($row['g_fingerprint'])) $bot_skor += 10;
                                        if ($bot_skor > 85) $bot_skor = 85;
                                    }
                                    
                                    $ip_parcalar = explode('.', $row['ip_adresi']);
                                    $subnet_gosterim = (count($ip_parcalar) == 4) ? $ip_parcalar[0].'.'.$ip_parcalar[1].'.'.$ip_parcalar[2].'.*' : $row['ip_adresi'];
                                ?>
                                <tr class="<?php echo $satir_rengi; ?>">
                                    <td class="ps-4">
                                        <input class="form-check-input ip-secici border-secondary" type="checkbox" name="secilen_ipler[]" value="<?php echo $row['ip_adresi']; ?>" data-durum="<?php echo $js_durum; ?>" style="cursor:pointer;">
                                    </td>
                                    <td class="text-nowrap">
                                        <a href="#" class="ip-link <?php echo $ana_yazi; ?> fw-bold" 
                                           data-bs-toggle="modal" data-bs-target="#ipRontgenModal"
                                           data-ip="<?php echo $row['ip_adresi']; ?>"
                                           data-subnet="<?php echo $subnet_gosterim; ?>"
                                           data-sehir="<?php echo $row['g_sehir']; ?>"
                                           data-isp="<?php echo $row['g_operator']; ?>"
                                           data-ilk="<?php echo date('d.m.Y H:i', strtotime($row['ilk_gorulme'])); ?>"
                                           data-son="<?php echo date('d.m.Y H:i', strtotime($row['son_gorulme'])); ?>"
                                           data-tiklama="<?php echo $row['tiklama_sayisi']; ?> Defa"
                                           data-sure="<?php echo $row['toplam_sure'] > 0 ? $row['toplam_sure'].' Saniye' : '0 Saniye (Hemen Çıktı)'; ?>"
                                           data-sitetik="<?php echo $row['toplam_sitetik']; ?> Kez Tıkladı"
                                           data-cihaz="<?php echo isset($row['g_cihaz']) ? $row['g_cihaz'] : 'Bilinmiyor'; ?> / <?php echo isset($row['g_os']) ? $row['g_os'] : 'Bilinmiyor'; ?>"
                                           data-fingerprint="<?php echo !empty($row['g_fingerprint']) ? $row['g_fingerprint'] : 'Bulunamadı (Eski/Gizli Kayıt)'; ?>"
                                           data-skor="<?php echo $bot_skor; ?>"
                                           data-ua="<?php echo htmlspecialchars($row['g_ua']); ?>">
                                           🔍 <?php echo $row['ip_adresi']; ?>
                                        </a>
                                        <div class="<?php echo $alt_yazi; ?>" style="font-size: 0.75rem; margin-top: 2px;"><?php echo isset($row['g_cihaz']) ? $row['g_cihaz'] : 'Bilinmiyor'; ?></div>
                                    </td>
                                    <td>
                                        <div class="fw-bold <?php echo $ana_yazi; ?>" style="font-size: 0.9rem;">Kelime: <?php echo (isset($row['g_kelime']) && $row['g_kelime'] != '') ? $row['g_kelime'] : '-'; ?></div>
                                        <div class="<?php echo $alt_yazi; ?>" style="font-size: 0.75rem;"><?php echo $row['g_sehir']; ?> / <?php echo $row['g_operator']; ?></div>
                                    </td>
                                    <td class="text-center text-nowrap">
                                        <span class="badge <?php echo $row['durum_kontrol']==2 ? 'bg-light text-dark' : ($row['tiklama_sayisi']>$dinamik_sinir ? 'bg-danger' : 'bg-success'); ?> border border-light"><?php echo $row['tiklama_sayisi']; ?> kez</span>
                                    </td>
                                    <td class="text-nowrap"><small class="<?php echo $alt_yazi; ?> fw-bold"><?php echo date('d.m.Y H:i', strtotime($row['son_gorulme'])); ?></small></td>
                                    <td class="text-end pe-4 text-nowrap">
                                        <?php if($row['durum_kontrol'] == 2): ?>
                                            <span class="badge bg-danger px-3 py-2 shadow-sm border border-light" style="font-size: 0.7rem;">🤖 OTO KALKAN</span>
                                        <?php elseif($row['durum_kontrol'] == 1): ?>
                                            <div class="d-flex flex-column align-items-end">
                                                <span class="badge bg-dark mb-1 px-3" style="font-size: 0.7rem;">MANUEL ENGELLİ</span>
                                                <a href="islemler.php?islem=engelkaldir&ip=<?php echo $row['ip_adresi']; ?>" class="text-decoration-none fw-bold" style="font-size: 0.75rem; color: #dc3545;">Kilidi Aç</a>
                                            </div>
                                        <?php else: ?>
                                            <a href="islemler.php?islem=engelle&ip=<?php echo $row['ip_adresi']; ?>" class="btn btn-sm <?php echo $row['tiklama_sayisi']>$dinamik_sinir ? 'btn-dark' : 'btn-outline-dark'; ?> shadow-sm fw-bold" style="font-size: 0.75rem;"><?php echo $row['tiklama_sayisi']>$dinamik_sinir ? 'Şüpheliyi Engelle' : 'Manuel Engelle'; ?></a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="text-center py-5 text-muted fw-bold">Kayıt bulunamadı.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- SAYFALAMA -->
                <?php if($toplam_sayfa > 1): ?>
                <div class="d-flex justify-content-center p-3 border-top bg-light">
                    <nav aria-label="Sayfa Gezinme">
                        <ul class="pagination pagination-sm mb-0 shadow-sm">
                            <?php if($sayfa > 1): ?>
                                <li class="page-item"><a class="page-link text-dark fw-bold" href="?filtre=<?php echo $filtre; ?>&tur=<?php echo $tur; ?>&sayfa=<?php echo ($sayfa - 1); ?>">Önceki</a></li>
                            <?php endif; ?>
                            <?php for($i = 1; $i <= $toplam_sayfa; $i++): ?>
                                <li class="page-item <?php echo $i == $sayfa ? 'active' : ''; ?>">
                                    <a class="page-link <?php echo $i == $sayfa ? 'bg-primary border-primary text-white' : 'text-dark'; ?>" href="?filtre=<?php echo $filtre; ?>&tur=<?php echo $tur; ?>&sayfa=<?php echo $i; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            <?php if($sayfa < $toplam_sayfa): ?>
                                <li class="page-item"><a class="page-link text-dark fw-bold" href="?filtre=<?php echo $filtre; ?>&tur=<?php echo $tur; ?>&sayfa=<?php echo ($sayfa + 1); ?>">Sonraki</a></li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </form>
</div>

<!-- IP RÖNTGEN EKRANI MODALI -->
<div class="modal fade" id="ipRontgenModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px; overflow: hidden;">
            <div class="modal-header bg-dark text-white border-0 py-3">
                <h5 class="modal-title fw-bold">
                    <span class="me-2">🔎</span> IP Detay ve Davranış Analizi
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0 bg-light">
                <ul class="nav nav-tabs bg-white px-3 pt-3 border-bottom-0" id="rontgenTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active fw-bold border-0 text-dark" style="background: transparent;" id="ip-tab" data-bs-toggle="tab" data-bs-target="#tab-ip" type="button">🌐 IP Bilgisi</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-bold border-0 text-dark" style="background: transparent;" id="davranis-tab" data-bs-toggle="tab" data-bs-target="#tab-davranis" type="button">🕵️ Davranış ve Cihaz</button>
                    </li>
                </ul>
                <div class="tab-content bg-white p-4" id="rontgenTabContent">
                    <div class="tab-pane fade show active" id="tab-ip" role="tabpanel">
                        <div class="detail-row"><span class="detail-label">IP Adresi</span><span class="detail-value text-primary fs-5" id="r_ip">Yükleniyor...</span></div>
                        <div class="detail-row"><span class="detail-label">Şehir / Ülke</span><span class="detail-value" id="r_sehir">Yükleniyor...</span></div>
                        <div class="detail-row"><span class="detail-label">Servis Sağlayıcı (ISP)</span><span class="detail-value" id="r_isp">Yükleniyor...</span></div>
                        <div class="detail-row"><span class="detail-label">İlk Görülme Tarihi</span><span class="detail-value text-muted" id="r_ilk">Yükleniyor...</span></div>
                        <div class="detail-row"><span class="detail-label">Son Görülme Tarihi</span><span class="detail-value text-muted" id="r_son">Yükleniyor...</span></div>
                    </div>
                    <div class="tab-pane fade" id="tab-davranis" role="tabpanel">
                        <div class="mb-3">
                            <span class="detail-label d-block mb-1">Yapay Zeka Bot Skoru</span>
                            <div class="progress" style="height: 12px;">
                                <div class="progress-bar progress-bar-striped progress-bar-animated" id="r_skor_bar" style="width: 0%"></div>
                            </div>
                            <small class="text-muted fw-bold float-end mt-1" id="r_skor_yazi">%0 Şüpheli</small>
                        </div>
                        <hr class="mt-4 mb-2">
                        <div class="detail-row"><span class="detail-label">Toplam Tıklama (Ads)</span><span class="detail-value text-danger" id="r_tiklama">Yükleniyor...</span></div>
                        <div class="detail-row"><span class="detail-label">Sitede Geçirilen Süre</span><span class="detail-value text-success" id="r_sure">Yükleniyor...</span></div>
                        <div class="detail-row"><span class="detail-label">Site İçi Etkileşim</span><span class="detail-value" id="r_sitetik">Yükleniyor...</span></div>
                        <div class="detail-row"><span class="detail-label">Cihaz Türü</span><span class="detail-value" id="r_cihaz">Yükleniyor...</span></div>
                        <div class="detail-row"><span class="detail-label">Device Hash (Parmak İzi)</span><span class="detail-value text-muted font-monospace" style="font-size:0.75rem;" id="r_fp">Yükleniyor...</span></div>
                        <div class="detail-row border-0 pb-0 flex-column align-items-start mt-2">
                            <span class="detail-label mb-1">Kullanıcı Ajanı (User-Agent)</span>
                            <span class="detail-value text-muted w-100 text-start" style="font-size:0.7rem; max-width: 100%;" id="r_ua">Yükleniyor...</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light border-0 d-flex justify-content-between">
                <a href="#" id="modal_c_blok_btn" class="btn btn-danger fw-bold shadow-sm" onclick="return confirm('Bu IP\'nin bulunduğu tüm mahalleyi (C-Blok) engellemek istediğinize emin misiniz?');">🚧 C-Blok (Mahalle) Engelle</a>
                <button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">Kapat</button>
            </div>
        </div>
    </div>
</div>

<!-- GOOGLE ADS İADE ASİSTANI MODALI -->
<div class="modal fade" id="iadeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
            <div class="modal-header bg-success text-white border-0 py-3">
                <h5 class="modal-title fw-bold">
                    <span class="me-2">💸</span> Google Ads İade Asistanı
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <p class="text-muted small mb-4">Saldırı yapan botların ve şüpheli tıklamaların listesini Google'ın istediği formatta indirip, resmi itiraz formu üzerinden ödediğiniz parayı geri talep edebilirsiniz.</p>
                <button type="button" class="btn btn-warning w-100 fw-bold mb-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#rehberModal">
                    ❓ İade Formunu Nasıl Dolduracağım?
                </button>
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body">
                        <h6 class="fw-bold text-dark mb-3"><span class="badge bg-primary me-2">Adım 1</span> Şüpheli Raporunu İndir</h6>
                        <form action="islemler.php" method="GET" class="d-flex gap-2">
                            <input type="hidden" name="islem" value="iade_indir">
                            <select class="form-select form-select-sm fw-bold border-secondary" name="gun">
                                <option value="7">Son 7 Günlük Kayıtlar</option>
                                <option value="14">Son 14 Günlük Kayıtlar</option>
                                <option value="30" selected>Son 1 Aylık Kayıtlar</option>
                                <option value="0">Tüm Zamanlar</option>
                            </select>
                            <button type="submit" class="btn btn-dark btn-sm fw-bold w-100">📥 Excel İndir</button>
                        </form>
                    </div>
                </div>
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h6 class="fw-bold text-dark mb-2"><span class="badge bg-primary me-2">Adım 2</span> Google'a İtiraz Et</h6>
                        <p class="small text-muted mb-3">İndirdiğiniz Excel dosyasını form sayfasına yükleyerek süreci başlatın.</p>
                        <a href="https://support.google.com/google-ads/contact/click_quality" target="_blank" class="btn btn-outline-success btn-sm fw-bold w-100">🌐 Resmi İade Formuna Git ↗</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- İADE DOLDURMA REHBERİ MODALI -->
<div class="modal fade" id="rehberModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
            <div class="modal-header bg-warning text-dark border-0 py-3">
                <h5 class="modal-title fw-bold">
                    <span class="me-2">📋</span> Form Doldurma Rehberi
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-white">
                <p class="mb-4">Google Ads Geçersiz Tıklama formunu doldururken aşağıdaki taslağı birebir kullanabilirsiniz. Bilgileri kendinize göre güncellemeyi unutmayın.</p>
                <div class="guide-box">
                    <strong class="d-block text-primary mb-1">Müşteri Kimliği (Customer ID)</strong>
                    <span class="small text-dark">Google Ads panelinizin sağ üst köşesinde yazan 10 haneli hesap numaranızdır. (Örn: 123-456-7890)</span>
                </div>
                <div class="guide-box">
                    <strong class="d-block text-primary mb-1">Etkilenen Kampanyalar</strong>
                    <span class="small text-dark">Saldırı aldığını düşündüğünüz aktif kampanyalarınızın adını yazın. (Örn: Çanakkale Oto Çekici Arama)</span>
                </div>
                <div class="guide-box">
                    <strong class="d-block text-primary mb-1">Etkilenen Tıklamaların Tarihi</strong>
                    <span class="small text-dark">Panelimizden indirdiğiniz Excel raporundaki en eski tarihi başlangıç, en yeni tarihi bitiş olarak girin.</span>
                </div>
                <div class="guide-box">
                    <strong class="d-block text-primary mb-1">Geçersiz Olduğunu Düşünme Nedeniniz? (Kopyala/Yapıştır)</strong>
                    <textarea class="form-control small mt-2 bg-white" rows="4" readonly>Ekte sunduğum kapsamlı IP ve sistem log kayıtlarında da görüleceği üzere; aynı IP adreslerinden ve belirli veri merkezlerinden arka arkaya olağandışı ardışık tıklamalar tespit edilmiştir. İlgili IP'lerin sitemizde kalma süreleri (0 saniye) ve site içi etkileşimleri yapay bot trafiği veya kasıtlı rakip tıklaması olduğunu doğrulamaktadır. Bütçemin haksız yere tüketilmesi nedeniyle ekteki logların incelenerek para iademin yapılmasını talep ediyorum.</textarea>
                </div>
                <div class="guide-box">
                    <strong class="d-block text-primary mb-1">Dosya Ekle</strong>
                    <span class="small text-dark">Bir önceki sayfadan indirdiğiniz <b>Google_Ads_Iade_Raporu.csv</b> dosyasını buraya yükleyin.</span>
                </div>
            </div>
            <div class="modal-footer bg-light border-0">
                <button type="button" class="btn btn-secondary w-100 fw-bold" data-bs-toggle="modal" data-bs-target="#iadeModal">Geri Dön</button>
            </div>
        </div>
    </div>
</div>

<!-- KELİME VE YARDIM MODALLARI -->
<div class="modal fade" id="kelimeModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-dialog-scrollable"><div class="modal-content border-0 shadow-lg"><div class="modal-header bg-primary text-white border-0 py-3"><h5 class="modal-title fw-bold"><span class="me-2">🔑</span> Kelime Analizi Raporu</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body p-0"><ul class="list-group list-group-flush"><?php if(count($kelime_sorgu_tum) > 0) { foreach($kelime_sorgu_tum as $kelime) { echo '<li class="list-group-item d-flex justify-content-between align-items-center p-3 border-bottom"><span class="fw-bold text-dark">'.($kelime['kelime'] != '' ? $kelime['kelime'] : 'Bilinmiyor').'</span><span class="badge bg-primary rounded-pill px-3 py-2 shadow-sm">'.$kelime['sayi'].' Tık</span></li>'; } } else { echo '<div class="p-5 text-center text-muted fw-bold">Henüz kaydedilmiş kelime bulunamadı.</div>'; } ?></ul></div><div class="modal-footer bg-light border-0"><button type="button" class="btn btn-secondary w-100 fw-bold" data-bs-dismiss="modal">Kapat</button></div></div></div></div>
<div class="modal fade" id="yardimModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content border-0 shadow-lg"><div class="modal-header bg-dark text-white border-0 py-3"><h5 class="modal-title fw-bold"><span class="me-2">🛡️</span> Ads Kalkanı Kullanım Rehberi</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body p-4"><div class="row"><div class="col-md-6 border-end"><h6 class="fw-bold text-primary mb-3">🎨 Renklerin Anlamı</h6><div class="d-flex align-items-center mb-3"><span class="badge bg-success me-3" style="width: 20px; height: 20px; border-radius: 50%;"> </span><div><strong class="d-block small text-dark">Güvenli Kullanıcılar</strong><small class="text-muted">Gerçek operatör ve temiz geçmişe sahip müşteriler.</small></div></div><div class="d-flex align-items-center mb-3"><span class="badge bg-danger me-3" style="width: 20px; height: 20px; border-radius: 50%;"> </span><div><strong class="d-block small text-dark">Şüpheli Aktiviteler</strong><small class="text-muted">Belirlenen sınırın üstünde tıklayanlar.</small></div></div><div class="d-flex align-items-center mb-3"><span class="badge bg-dark me-3" style="width: 20px; height: 20px; border-radius: 50%;"> </span><div><strong class="d-block small text-white bg-dark px-1 rounded">Otomatik Botlar</strong><small class="text-muted">Veri merkezlerinden gelen, sistemin otomatik durdurduğu botlar.</small></div></div></div><div class="col-md-6 ps-md-4"><h6 class="fw-bold text-primary mb-3">⚙️ Temel Fonksiyonlar</h6><div class="mb-3"><strong class="d-block small text-dark">🔍 Akıllı Filtreleme</strong><p class="small text-muted mb-0">Üstteki butonlarla listelere anında odaklanabilirsiniz.</p></div><div class="mb-3"><strong class="d-block small text-dark">🚫 Aktif Erişim Engeli</strong><p class="small text-muted mb-0">Engellenen IP, web sitenize bir daha giremez.</p></div><div><strong class="d-block small text-dark">📊 Dışa Aktar</strong><p class="small text-muted mb-0">İndirdiğiniz listeyi Ads panelinize yükleyebilirsiniz.</p></div></div></div><hr class="my-4"><div class="bg-light p-3 rounded-3 border-start border-primary border-4"><div class="d-flex"><div class="me-3 fs-4">💡</div><div><strong class="small d-block text-dark">Profesyonel İpucu</strong><small class="text-muted">Reklam bütçenizi korumak için haftada en az bir kez <strong>"Şüpheliler"</strong> listesini dışa aktarıp Google Ads panelinize yüklemeniz önerilir.</small></div></div></div></div><div class="modal-footer bg-white border-0 pt-0"><button type="button" class="btn btn-primary w-100 fw-bold py-2" data-bs-dismiss="modal">Anladım, Korumaya Başla!</button></div></div></div></div>

<div class="bottom-refresh-bar shadow-lg py-3">
    <div class="container d-flex justify-content-between align-items-center">
        <div class="text-muted small">Sistem Veritabanı: <strong class="text-dark">Aktif</strong></div>
        <button onclick="window.location.reload();" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm">🔄 Verileri Şimdi Yenile</button>
        <span class="small text-muted d-none d-md-inline">Son Güncelleme: <strong><?php echo date('H:i'); ?></strong></span>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- GRAFİK SCRİPTİ -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('kelimeGrafik').getContext('2d');
    const labels = <?php echo json_encode($grafik_kelimeler); ?>;
    const data = <?php echo json_encode($grafik_sayilar); ?>;
    
    if(labels.length > 0) {
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Tıklama Sayısı',
                    data: data,
                    backgroundColor: 'rgba(13, 110, 253, 0.7)',
                    borderColor: 'rgba(13, 110, 253, 1)',
                    borderWidth: 2,
                    borderRadius: 6,
                    maxBarThickness: 60, // YENİ EKLENDİ: Çubuk kalınlığı sınırlandırıldı
                    hoverBackgroundColor: 'rgba(13, 110, 253, 1)'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleFont: { size: 14 },
                        bodyFont: { size: 14, weight: 'bold' },
                        padding: 10,
                        cornerRadius: 8
                    }
                },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0, color: '#6c757d' }, grid: { color: '#e9ecef' } },
                    x: { ticks: { color: '#495057', font: { weight: 'bold' } }, grid: { display: false } }
                }
            }
        });
    } else {
        ctx.font = "14px Arial";
        ctx.fillStyle = "#6c757d";
        ctx.textAlign = "center";
        ctx.fillText("Henüz yeterli kelime verisi birikmedi.", ctx.canvas.width / 2, ctx.canvas.height / 2);
    }
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const ipLinks = document.querySelectorAll('.ip-link');
    ipLinks.forEach(link => {
        link.addEventListener('click', function() {
            document.getElementById('r_ip').innerText = this.getAttribute('data-ip');
            document.getElementById('r_sehir').innerText = this.getAttribute('data-sehir');
            document.getElementById('r_isp').innerText = this.getAttribute('data-isp');
            document.getElementById('r_ilk').innerText = this.getAttribute('data-ilk');
            document.getElementById('r_son').innerText = this.getAttribute('data-son');
            document.getElementById('r_tiklama').innerText = this.getAttribute('data-tiklama');
            document.getElementById('r_sure').innerText = this.getAttribute('data-sure');
            document.getElementById('r_sitetik').innerText = this.getAttribute('data-sitetik');
            document.getElementById('r_cihaz').innerText = this.getAttribute('data-cihaz');
            document.getElementById('r_ua').innerText = this.getAttribute('data-ua');
            document.getElementById('r_fp').innerText = this.getAttribute('data-fingerprint');
            
            let skor = parseInt(this.getAttribute('data-skor'));
            let bar = document.getElementById('r_skor_bar');
            bar.style.width = skor + "%";
            document.getElementById('r_skor_yazi').innerText = "%" + skor + " Şüpheli";
            bar.className = "progress-bar progress-bar-striped progress-bar-animated";
            if(skor < 40) bar.classList.add('bg-success');
            else if(skor < 75) bar.classList.add('bg-warning', 'text-dark');
            else bar.classList.add('bg-danger');
            
            let subnet = this.getAttribute('data-subnet');
            let cBlokBtn = document.getElementById('modal_c_blok_btn');
            cBlokBtn.href = "islemler.php?islem=c_blok_engelle&ip=" + this.getAttribute('data-ip');
            cBlokBtn.innerHTML = "🚧 C-Blok Mahalle Engeli (" + subnet + ")";
            
            document.getElementById('ip-tab').click();
        });
    });
});

function checkStates() {
    let engelliSecimVar = false; let temizSecimVar = false; let seciliSayisi = 0;
    const actionBtn = document.getElementById('akilliButon');
    document.querySelectorAll('.ip-secici').forEach(function(cb) {
        if (cb.checked) {
            seciliSayisi++;
            if (cb.getAttribute('data-durum') === "1") engelliSecimVar = true;
            else temizSecimVar = true;
        }
    });

    if (seciliSayisi === 0) {
        actionBtn.className = "btn btn-secondary btn-sm shadow-sm fw-bold px-3"; actionBtn.innerHTML = "Seçim Yapın"; actionBtn.disabled = true; actionBtn.onclick = null;
    } else if (temizSecimVar && !engelliSecimVar) {
        actionBtn.className = "btn btn-danger btn-sm shadow-sm fw-bold px-3"; actionBtn.innerHTML = "🚫 Seçilenleri Engelle"; actionBtn.disabled = false; actionBtn.setAttribute("formaction", "islemler.php?islem=toplu_engelle");
        actionBtn.onclick = function() { return confirm('Seçili IP adreslerini engellemek istediğinize emin misiniz?'); };
    } else if (engelliSecimVar && !temizSecimVar) {
        actionBtn.className = "btn btn-success btn-sm shadow-sm fw-bold px-3"; actionBtn.innerHTML = "✅ Engeli Kaldır"; actionBtn.disabled = false; actionBtn.setAttribute("formaction", "islemler.php?islem=toplu_engel_kaldir");
        actionBtn.onclick = function() { return confirm('Seçili IP adreslerinin engelini kaldırmak istediğinize emin misiniz?'); };
    } else {
        actionBtn.className = "btn btn-warning btn-sm shadow-sm fw-bold px-3 text-dark"; actionBtn.innerHTML = "⚠️ Karışık Seçim"; actionBtn.disabled = false;
        actionBtn.onclick = function(e) { e.preventDefault(); alert("Seçiminizde hem engelli hem de temiz IP'ler var!"); };
    }
}

document.getElementById('tumunuSec').addEventListener('change', function(e) {
    document.querySelectorAll('.ip-secici').forEach(function(cb) { cb.checked = e.target.checked; });
    checkStates();
});
document.querySelectorAll('.ip-secici').forEach(function(cb) { cb.addEventListener('change', checkStates); });

document.getElementById('tabloArama').addEventListener('keyup', function() {
    let filter = this.value.toUpperCase(); let rows = document.querySelector("#anaTablo tbody").rows;
    for (let i = 0; i < rows.length; i++) {
        let firstCol = rows[i].cells[1] ? rows[i].cells[1].textContent.toUpperCase() : "";
        let secondCol = rows[i].cells[2] ? rows[i].cells[2].textContent.toUpperCase() : "";
        if (firstCol.indexOf(filter) > -1 || secondCol.indexOf(filter) > -1) rows[i].style.display = ""; else rows[i].style.display = "none";      
    }
});

document.addEventListener("DOMContentLoaded", function() {
    let scrollYeri = sessionStorage.getItem("sayfaKaydirmaYeri");
    if (scrollYeri !== null) { window.scrollTo({ top: parseInt(scrollYeri), behavior: "instant" }); sessionStorage.removeItem("sayfaKaydirmaYeri"); }
});
window.addEventListener("beforeunload", function() { sessionStorage.setItem("sayfaKaydirmaYeri", window.scrollY); });
</script>

</body>
</html>