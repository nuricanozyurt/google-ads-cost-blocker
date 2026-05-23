<?php
require_once 'guvenlik.php'; // Güvenlik kalkanımız
date_default_timezone_set('Europe/Istanbul');

// 1. OTOMATİK TABLO OLUŞTURUCU VE GÜNCELLEYİCİ
$db->query("CREATE TABLE IF NOT EXISTS ayarlar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tiklama_siniri INT DEFAULT 3,
    oto_kalkan INT DEFAULT 1
)");

try { $db->query("ALTER TABLE ayarlar ADD COLUMN izin_verilen_sehirler TEXT DEFAULT ''"); } catch (PDOException $e) {}
try { $db->query("ALTER TABLE ayarlar ADD COLUMN rakip_analizi INT DEFAULT 1"); } catch (PDOException $e) {}

$kontrol = $db->query("SELECT * FROM ayarlar WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
if (!$kontrol) {
    $db->query("INSERT INTO ayarlar (id, tiklama_siniri, oto_kalkan, izin_verilen_sehirler, rakip_analizi) VALUES (1, 3, 1, '', 1)");
    $kontrol = ['tiklama_siniri' => 3, 'oto_kalkan' => 1, 'izin_verilen_sehirler' => '', 'rakip_analizi' => 1];
}

$mesaj = "";

// KALKAN AYARLARI GÜNCELLEME İŞLEMİ
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['kalkan_ayarlari'])) {
    $yeni_sinir = intval($_POST['tiklama_siniri']);
    $yeni_kalkan = isset($_POST['oto_kalkan']) ? 1 : 0;
    
    $yeni_sehirler = "";
    if (isset($_POST['sehirler']) && is_array($_POST['sehirler'])) {
        $yeni_sehirler = implode(", ", $_POST['sehirler']);
    }
    
    $yeni_rakip = isset($_POST['rakip_analizi']) ? 1 : 0;
    
    $guncelle = $db->prepare("UPDATE ayarlar SET tiklama_siniri = ?, oto_kalkan = ?, izin_verilen_sehirler = ?, rakip_analizi = ? WHERE id = 1");
    if ($guncelle->execute([$yeni_sinir, $yeni_kalkan, $yeni_sehirler, $yeni_rakip])) {
        $mesaj = '<div class="alert alert-success fw-bold shadow-sm">✅ Güvenlik algoritmaları başarıyla güncellendi ve sisteme işlendi!</div>';
        $kontrol['tiklama_siniri'] = $yeni_sinir;
        $kontrol['oto_kalkan'] = $yeni_kalkan;
        $kontrol['izin_verilen_sehirler'] = $yeni_sehirler;
        $kontrol['rakip_analizi'] = $yeni_rakip;
    }
}

// YÖNETİCİ BİLGİLERİ GÜNCELLEME İŞLEMİ (YENİ EKLENDİ)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['admin_guncelle'])) {
    $yeni_kullanici = htmlspecialchars($_POST['kullanici_adi']);
    $yeni_sifre = $_POST['yeni_sifre'];

    if (!empty($yeni_sifre)) {
        // Şifre girildiyse şifreyi güvenli (hash) olarak şifrele ve kaydet
        $hash_sifre = password_hash($yeni_sifre, PASSWORD_DEFAULT);
        $db->prepare("UPDATE admin SET kullanici_adi = ?, sifre = ? WHERE id = 1")->execute([$yeni_kullanici, $hash_sifre]);
    } else {
        // Şifre kutusu boş bırakıldıysa sadece kullanıcı adını değiştir
        $db->prepare("UPDATE admin SET kullanici_adi = ? WHERE id = 1")->execute([$yeni_kullanici]);
    }
    $mesaj .= '<div class="alert alert-info fw-bold shadow-sm mt-2">🔑 Yönetici giriş bilgileri başarıyla değiştirildi! (Bir sonraki girişinizde geçerli olacaktır.)</div>';
}

$secili_sehirler_dizisi = array_map('trim', explode(',', $kontrol['izin_verilen_sehirler']));

// Formda göstermek için mevcut kullanıcı adını çekelim
$admin_cek = $db->query("SELECT kullanici_adi FROM admin WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
$mevcut_kullanici = $admin_cek ? $admin_cek['kullanici_adi'] : 'admin';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ads Kalkanı | Ayarlar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f6f9; padding-bottom: 50px;}
        .setting-card { border-radius: 12px; border: none; overflow: hidden; margin-bottom: 25px; }
        .form-switch .form-check-input { width: 3em; height: 1.5em; cursor: pointer; }
        .city-box { max-height: 180px; overflow-y: auto; background: #fff; border: 1px solid #ced4da; border-radius: 0.375rem; padding: 10px; }
        .city-box::-webkit-scrollbar { width: 6px; }
        .city-box::-webkit-scrollbar-thumb { background-color: #adb5bd; border-radius: 10px; }
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
                <li class="nav-item"><a class="nav-link text-light" href="index.php">📊 Dashboard</a></li>
                <li class="nav-item"><a class="nav-link text-light" href="kurulum.php">🔌 Kurulum & Entegrasyon</a></li>
                <li class="nav-item"><a class="nav-link active text-white" href="ayarlar.php">⚙️ Ayarlar</a></li>
                <!-- ÇIKIŞ YAP BUTONU -->
                <li class="nav-item ms-lg-3"><a class="btn btn-danger btn-sm mt-1 fw-bold shadow-sm" href="logout.php">Çıkış Yap</a></li>
            </ul>
        </div>
    </div>
</nav>

<div class="container mt-5" style="max-width: 800px;">
    <?php echo $mesaj; ?>
    
    <!-- 1. KART: KALKAN AYARLARI -->
    <div class="card setting-card shadow-sm border-top border-primary border-4">
        <div class="card-header bg-white border-bottom py-3">
            <h5 class="mb-0 fw-bold text-dark">⚙️ Algoritma ve Güvenlik Ayarları</h5>
        </div>
        <div class="card-body p-4">
            <form method="POST" action="">
                <input type="hidden" name="kalkan_ayarlari" value="1">
                
                <div class="row mb-4 align-items-center bg-light p-3 rounded">
                    <div class="col-md-8">
                        <h6 class="fw-bold text-dark mb-1">Şüpheli Tıklama Sınırı</h6>
                        <p class="text-muted small mb-0">Aynı IP adresi reklamlarınıza belirlediğiniz sayıdan fazla tıklarsa otomatik engellenir.</p>
                    </div>
                    <div class="col-md-4 text-md-end mt-3 mt-md-0">
                        <select class="form-select fw-bold border-secondary" name="tiklama_siniri">
                            <option value="2" <?php echo $kontrol['tiklama_siniri'] == 2 ? 'selected' : ''; ?>>2 Tıklamada Yakala</option>
                            <option value="3" <?php echo $kontrol['tiklama_siniri'] == 3 ? 'selected' : ''; ?>>3 Tıklamada Yakala (Önerilen)</option>
                            <option value="4" <?php echo $kontrol['tiklama_siniri'] == 4 ? 'selected' : ''; ?>>4 Tıklamada Yakala</option>
                            <option value="5" <?php echo $kontrol['tiklama_siniri'] == 5 ? 'selected' : ''; ?>>5 Tıklamada Yakala</option>
                        </select>
                    </div>
                </div>

                <div class="row mb-4 align-items-center bg-light p-3 rounded border-start border-dark border-4">
                    <div class="col-md-9">
                        <h6 class="fw-bold text-dark mb-1">🤖 Akıllı Botnet Kalkanı</h6>
                        <p class="text-muted small mb-0">Veri merkezlerinden gelen yapay trafikleri saniyesinde tespit eder ve keser.</p>
                    </div>
                    <div class="col-md-3 text-md-end mt-3 mt-md-0">
                        <div class="form-check form-switch d-flex justify-content-md-end align-items-center">
                            <input class="form-check-input" type="checkbox" name="oto_kalkan" id="botKalkani" <?php echo $kontrol['oto_kalkan'] == 1 ? 'checked' : ''; ?>>
                        </div>
                    </div>
                </div>

                <div class="row mb-4 bg-light p-3 rounded border-start border-primary border-4">
                    <div class="col-md-6">
                        <h6 class="fw-bold text-dark mb-1">📍 Bölgesel Lokasyon Kalkanı</h6>
                        <p class="text-muted small mb-0">Hizmet verdiğiniz illeri seçin. İşaretlediğiniz iller dışından gelenler dışarıda kalır.</p>
                    </div>
                    <div class="col-md-6 mt-3 mt-md-0">
                        <div class="position-relative mb-2">
                            <input type="text" id="sehirAramaInput" class="form-control form-control-sm border-secondary fw-bold" placeholder="🔍 Hızlı Şehir Ara...">
                        </div>
                        
                        <div class="city-box" id="cityContainer">
                            <div class="row g-2">
                                <?php
                                $iller = ["Adana", "Adıyaman", "Afyonkarahisar", "Ağrı", "Aksaray", "Amasya", "Ankara", "Antalya", "Ardahan", "Artvin", "Aydın", "Balıkesir", "Bartın", "Batman", "Bayburt", "Bilecik", "Bingöl", "Bitlis", "Bolu", "Burdur", "Bursa", "Çanakkale", "Çankırı", "Çorum", "Denizli", "Diyarbakır", "Düzce", "Edirne", "Elazığ", "Erzincan", "Erzurum", "Eskişehir", "Gaziantep", "Giresun", "Gümüşhane", "Hakkari", "Hatay", "Iğdır", "Isparta", "İstanbul", "İzmir", "Kahramanmaraş", "Karabük", "Karaman", "Kars", "Kastamonu", "Kayseri", "Kırıkkale", "Kırklareli", "Kırşehir", "Kilis", "Kocaeli", "Konya", "Kütahya", "Malatya", "Manisa", "Mardin", "Mersin", "Muğla", "Muş", "Nevşehir", "Niğde", "Ordu", "Osmaniye", "Rize", "Sakarya", "Samsun", "Şanlıurfa", "Siirt", "Sinop", "Şırnak", "Sivas", "Tekirdağ", "Tokat", "Trabzon", "Tunceli", "Uşak", "Van", "Yalova", "Yozgat", "Zonguldak"];
                                
                                foreach($iller as $il) {
                                    $is_checked = in_array($il, $secili_sehirler_dizisi) ? 'checked' : '';
                                    echo '<div class="col-6 city-item">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="sehirler[]" value="'.$il.'" id="il_'.$il.'" '.$is_checked.'>
                                                <label class="form-check-label small fw-bold" for="il_'.$il.'">'.$il.'</label>
                                            </div>
                                          </div>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mb-4 align-items-center bg-light p-3 rounded border-start border-danger border-4">
                    <div class="col-md-9">
                        <h6 class="fw-bold text-dark mb-1">🕵️ Rakip İşletme Algoritması</h6>
                        <p class="text-muted small mb-0">Şüpheli davranış örüntüsü sergileyen sinsi rakipleri sistem üzerinden otomatik yakalar.</p>
                    </div>
                    <div class="col-md-3 text-md-end mt-3 mt-md-0">
                        <div class="form-check form-switch d-flex justify-content-md-end align-items-center">
                            <input class="form-check-input" type="checkbox" name="rakip_analizi" id="rakipKalkani" <?php echo $kontrol['rakip_analizi'] == 1 ? 'checked' : ''; ?>>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 fw-bold py-3 shadow-sm" style="letter-spacing: 1px;">KALKAN AYARLARINI UYGULA</button>
            </form>
        </div>
    </div>

    <!-- 2. KART: YÖNETİCİ (MÜŞTERİ) ŞİFRE DEĞİŞTİRME -->
    <div class="card setting-card shadow-sm border-top border-dark border-4">
        <div class="card-header bg-white border-bottom py-3">
            <h5 class="mb-0 fw-bold text-dark">🔐 Yönetici Giriş Bilgileri</h5>
        </div>
        <div class="card-body p-4 bg-light">
            <form method="POST" action="">
                <input type="hidden" name="admin_guncelle" value="1">
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold small text-secondary">Kullanıcı Adı</label>
                        <input type="text" class="form-control fw-bold" name="kullanici_adi" value="<?php echo htmlspecialchars($mevcut_kullanici); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold small text-secondary">Yeni Şifre</label>
                        <input type="password" class="form-control" name="yeni_sifre" placeholder="Değiştirmek istemiyorsanız boş bırakın">
                    </div>
                </div>
                <button type="submit" class="btn btn-dark w-100 fw-bold shadow-sm">GİRİŞ BİLGİLERİNİ GÜNCELLE</button>
            </form>
        </div>
    </div>

</div>

<!-- ÇIKIŞ YAP (LOGOUT) İŞLEMİ İÇİN KÜÇÜK BİR DOSYA GEREKİYOR, KODUN SONUNDA ONU DA VERECEĞİM -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('sehirAramaInput').addEventListener('keyup', function() {
    let filter = this.value.toLocaleUpperCase('tr-TR'); 
    let cityItems = document.querySelectorAll('.city-item');
    
    cityItems.forEach(function(item) {
        let label = item.querySelector('label').textContent.toLocaleUpperCase('tr-TR');
        if (label.indexOf(filter) > -1) {
            item.style.display = ""; 
        } else {
            item.style.display = "none"; 
        }
    });
});
</script>
</body>
</html>