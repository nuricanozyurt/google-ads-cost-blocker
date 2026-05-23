<?php
require_once 'db.php';
date_default_timezone_set('Europe/Istanbul'); 

// 0. VERİTABANI GÜNCELLEMESİ 
try {
    $db->query("ALTER TABLE reklam_tiklamalari ADD COLUMN fingerprint VARCHAR(100) DEFAULT ''");
    $db->query("ALTER TABLE reklam_tiklamalari ADD COLUMN sitede_kalis_suresi INT DEFAULT 0");
    $db->query("ALTER TABLE reklam_tiklamalari ADD COLUMN site_ici_tiklama INT DEFAULT 0");
} catch (PDOException $e) { }

// --- JAVASCRIPT GİZLİ PING YAKALAYICISI ---
if (isset($_POST['kalkan_ping'])) {
    $id = intval($_POST['id']);
    $sure_ekle = intval($_POST['sure']);
    $tik_ekle = intval($_POST['tiklama']);
    $fingerprint = htmlspecialchars($_POST['fingerprint']);
    
    $guncelle = $db->prepare("UPDATE reklam_tiklamalari SET sitede_kalis_suresi = sitede_kalis_suresi + ?, site_ici_tiklama = site_ici_tiklama + ?, fingerprint = ? WHERE id = ?");
    $guncelle->execute([$sure_ekle, $tik_ekle, $fingerprint, $id]);
    exit; 
}

// --- AYARLARI ÇEK ---
$ayar_cek = $db->query("SELECT * FROM ayarlar WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
$tiklama_siniri = $ayar_cek ? $ayar_cek['tiklama_siniri'] : 3;
$oto_kalkan = $ayar_cek ? $ayar_cek['oto_kalkan'] : 1;
$izin_verilen_sehirler = $ayar_cek ? $ayar_cek['izin_verilen_sehirler'] : '';
$rakip_analizi = $ayar_cek ? $ayar_cek['rakip_analizi'] : 1;

$ip = $_SERVER['REMOTE_ADDR'];
if (!empty($_SERVER['HTTP_CLIENT_IP'])) { $ip = $_SERVER['HTTP_CLIENT_IP']; }
elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) { $ip = $_SERVER['HTTP_X_FORWARDED_FOR']; }

// C-BLOK HESAPLAMA
$ip_parcalar = explode('.', $ip);
$subnet_ip = (count($ip_parcalar) == 4) ? $ip_parcalar[0].'.'.$ip_parcalar[1].'.'.$ip_parcalar[2].'.*' : $ip;

// 1. GÜVENLİK DUVARI
$kontrol = $db->prepare("SELECT durum FROM reklam_tiklamalari WHERE (ip_adresi = ? OR ip_adresi = ?) ORDER BY id DESC LIMIT 1");
$kontrol->execute([$ip, $subnet_ip]);
$kayit = $kontrol->fetch(PDO::FETCH_ASSOC);

if ($kayit && ($kayit['durum'] == 1 || $kayit['durum'] == 2)) {
    header('HTTP/1.0 403 Forbidden');
    die("<div style='text-align:center; margin-top:50px; font-family:sans-serif;'><h1 style='color:red;'>403 - Erişim Engellendi</h1><p>Güvenlik Kalkanı devrede.</p></div>");
}

$son_kayit_id = 0; 

// OTOMATİK C-BLOK ATMA FONKSİYONU
function oto_c_blok_engelle($ip_adresi, $durum_kodu, $sebep, $gelen_sehir, $gelen_op, $db_baglanti) {
    $parcalar = explode('.', $ip_adresi);
    if(count($parcalar) == 4) {
        $like_ip = $parcalar[0].'.'.$parcalar[1].'.'.$parcalar[2].'.%';
        $sub_ip = $parcalar[0].'.'.$parcalar[1].'.'.$parcalar[2].'.*';
        
        $db_baglanti->prepare("UPDATE reklam_tiklamalari SET durum = ? WHERE ip_adresi LIKE ?")->execute([$durum_kodu, $like_ip]);
        
        $kontrol_sub = $db_baglanti->prepare("SELECT id FROM reklam_tiklamalari WHERE ip_adresi = ?");
        $kontrol_sub->execute([$sub_ip]);
        if(!$kontrol_sub->fetch()) {
            $db_baglanti->prepare("INSERT INTO reklam_tiklamalari (ip_adresi, durum, kelime, sehir, operator, cihaz, isletim_sistemi, user_agent) VALUES (?, ?, ?, ?, ?, '-', '-', '-')")
               ->execute([$sub_ip, $durum_kodu, $sebep, $gelen_sehir, $gelen_op]);
        }
    }
}

// 2. REKLAM TIKLAMASI ANALİZİ
if (isset($_GET['gclid'])) {
    $gclid = htmlspecialchars($_GET['gclid']);
    $kelime = isset($_GET['keyword']) ? htmlspecialchars($_GET['keyword']) : 'Bilinmiyor';
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    
    $f5_kontrol = $db->prepare("SELECT id, kelime, site_ici_tiklama, sehir, operator FROM reklam_tiklamalari WHERE ip_adresi = ? AND gclid_kodu = ? LIMIT 1");
    $f5_kontrol->execute([$ip, $gclid]);
    $f5_kayit = $f5_kontrol->fetch(PDO::FETCH_ASSOC);
    
    if ($f5_kayit) {
        $son_kayit_id = $f5_kayit['id'];
        
        if($f5_kayit['kelime'] != $kelime && $kelime != 'Bilinmiyor') {
            $db->prepare("UPDATE reklam_tiklamalari SET kelime = ? WHERE id = ?")->execute([$kelime, $son_kayit_id]);
        }

        $db->prepare("UPDATE reklam_tiklamalari SET site_ici_tiklama = site_ici_tiklama + 1 WHERE id = ?")->execute([$son_kayit_id]);
        
        if ($f5_kayit['site_ici_tiklama'] > 15) {
            // SPAM TESPİT EDİLDİ -> TÜM MAHALLEYİ (C-BLOK) OTOMATİK ENGELLE
            oto_c_blok_engelle($ip, 1, 'OTO C-BLOK (Spam / F5 Saldırısı)', $f5_kayit['sehir'], $f5_kayit['operator'], $db);
            die("<div style='text-align:center; margin-top:50px; font-family:sans-serif;'><h1 style='color:darkred;'>403 - Güvenlik Kalkanı</h1><p>Sistemimiz aşırı sayfa yenileme nedeniyle sizi ve ağınızı engelledi.</p></div>");
        }

    } else {
        $cihaz = (preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i',$user_agent)) ? 'Mobil' : 'Masaüstü';
        
        $isletim_sistemi = 'Bilinmiyor';
        if (preg_match('/windows/i', $user_agent)) $isletim_sistemi = 'Windows';
        elseif (preg_match('/macintosh|mac os x/i', $user_agent)) $isletim_sistemi = 'Mac OS';
        elseif (preg_match('/iphone|ipad|ipod/i', $user_agent)) $isletim_sistemi = 'iOS';
        elseif (preg_match('/android/i', $user_agent)) $isletim_sistemi = 'Android';

        $sehir = "Bilinmiyor"; $operator = "Bilinmiyor"; $durum = 0; $engel_sebebi = "";

        // YENİ: API DARBOĞAZ KORUMASI (CACHE SİSTEMİ)
        // Eğer bu IP'nin şehri zaten veritabanımızda varsa, API'ye gidip limiti doldurma!
        $cache_kontrol = $db->prepare("SELECT sehir, operator FROM reklam_tiklamalari WHERE ip_adresi = ? AND sehir != 'Bilinmiyor' AND sehir != 'Yerel Sunucu' ORDER BY id DESC LIMIT 1");
        $cache_kontrol->execute([$ip]);
        $cache_kayit = $cache_kontrol->fetch(PDO::FETCH_ASSOC);

        if ($cache_kayit) {
            $sehir = $cache_kayit['sehir'];
            $operator = $cache_kayit['operator'];
        } elseif ($ip !== '::1' && $ip !== '127.0.0.1') {
            $api_url = "http://ip-api.com/json/{$ip}?fields=city,isp,status";
            $api_yanit = @file_get_contents($api_url);
            if ($api_yanit) {
                $api_veri = json_decode($api_yanit, true);
                if (isset($api_veri['status']) && $api_veri['status'] == 'success') {
                    $sehir = $api_veri['city'];
                    $operator = $api_veri['isp'];
                }
            }
        } else {
            $sehir = "Yerel Sunucu (Localhost)";
            $operator = "Geliştirici";
        }

        // Lokasyon Kalkanı
        if (!empty($izin_verilen_sehirler) && $sehir != "Bilinmiyor" && $sehir != "Yerel Sunucu (Localhost)") {
            $search = array('ç','Ç','ğ','Ğ','ı','İ','ö','Ö','ş','Ş','ü','Ü');
            $replace = array('c','c','g','g','i','i','o','o','s','s','u','u');
            $gelen_sehir_temiz = strtolower(str_replace($search, $replace, $sehir));
            $izinli_sehirler_temiz = strtolower(str_replace($search, $replace, $izin_verilen_sehirler));
            $izinli_dizi = array_map('trim', explode(',', $izinli_sehirler_temiz));
            
            $sehir_onay = false;
            foreach($izinli_dizi as $izinli_il) {
                if (strpos($gelen_sehir_temiz, $izinli_il) !== false || strpos($izinli_il, $gelen_sehir_temiz) !== false) {
                    $sehir_onay = true; break;
                }
            }
            if (!$sehir_onay) { $durum = 1; $engel_sebebi = "Lokasyon Dışı Trafik"; }
        }
        
        // Botnet Kalkanı
        if ($durum == 0 && $oto_kalkan == 1) {
            $yasakli = ['amazon', 'aws', 'digitalocean', 'hetzner', 'ovh', 'google cloud', 'datacenter', 'server'];
            foreach ($yasakli as $y) { 
                if (stripos($operator, $y) !== false) { 
                    $durum = 2; $engel_sebebi = "Yapay Veri Merkezi Trafiği"; break; 
                } 
            }
        }

        // Rakip Analizi
        if ($durum == 0 && $rakip_analizi == 1) {
            $rakip_sorgu = $db->prepare("SELECT COUNT(DISTINCT DATE(tarih_saat)) as gun_sayisi FROM reklam_tiklamalari WHERE ip_adresi = ? AND tarih_saat >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
            $rakip_sorgu->execute([$ip]);
            if ($rakip_sorgu->fetch(PDO::FETCH_ASSOC)['gun_sayisi'] >= 3) {
                $durum = 1; $engel_sebebi = "Sistemli Rakip Tıklaması";
            }
        }

        $kaydet = $db->prepare("INSERT INTO reklam_tiklamalari (ip_adresi, gclid_kodu, kelime, sehir, operator, cihaz, isletim_sistemi, user_agent, durum) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $kaydet->execute([$ip, $gclid, $kelime, $sehir, $operator, $cihaz, $isletim_sistemi, $user_agent, $durum]);
        $son_kayit_id = $db->lastInsertId();
        
        // BOTNET (Veri Merkezi) TESPİT EDİLDİ -> TÜM MAHALLEYİ OTOMATİK ENGELLE
        if ($durum == 2) {
            oto_c_blok_engelle($ip, 2, 'OTO C-BLOK (Veri Merkezi Botnet)', $sehir, $operator, $db);
        }
        
        if ($durum == 0) {
            // Sadece son 30 gün içindeki tıklamaları sayar!
            $count = $db->prepare("SELECT COUNT(*) FROM reklam_tiklamalari WHERE ip_adresi = ? AND tarih_saat >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
            $count->execute([$ip]);
            if ($count->fetchColumn() > $tiklama_siniri) {
                $db->prepare("UPDATE reklam_tiklamalari SET durum = 1 WHERE ip_adresi = ?")->execute([$ip]);
                $durum = 1; $engel_sebebi = "Aşırı Tıklama Sınırı Aşıldı";
            }
        }
        
        if ($durum > 0) {
            header('HTTP/1.0 403 Forbidden');
            $gosterilecek = ($durum == 2) ? "Zararlı Bot Tespiti" : ($engel_sebebi != "" ? $engel_sebebi : "Erişim Reddedildi");
            die("<div style='text-align:center; margin-top:50px; font-family:sans-serif;'><h1 style='color:darkred;'>403 - Güvenlik Kalkanı</h1><p>İhlal tespit edildi: <b>$gosterilecek</b></p></div>");
        }
    }
}
?>
<script>
(function(){
    let kalkanId = localStorage.getItem('kalkan_bot_id');
    <?php if($son_kayit_id > 0): ?>
    kalkanId = "<?php echo $son_kayit_id; ?>";
    localStorage.setItem('kalkan_bot_id', kalkanId);
    <?php endif; ?>

    if(!kalkanId) return;

    let fp_data = navigator.userAgent + screen.width + screen.height + navigator.language + navigator.platform;
    let hash = 0;
    for (let i = 0; i < fp_data.length; i++) {
        hash = ((hash << 5) - hash) + fp_data.charCodeAt(i);
        hash |= 0;
    }
    let fingerprint = "FP-" + Math.abs(hash).toString(16).toUpperCase();

    let clicks = 0;
    document.addEventListener('click', function(){ clicks++; });
    let startTime = Date.now();

    function sendKalkanPing() {
        let timeSpent = Math.floor((Date.now() - startTime) / 1000);
        if(timeSpent <= 0 && clicks === 0) return; 

        let formData = new FormData();
        formData.append('kalkan_ping', '1');
        formData.append('id', kalkanId);
        formData.append('sure', timeSpent);
        formData.append('tiklama', clicks);
        formData.append('fingerprint', fingerprint);

        if (navigator.sendBeacon) {
            navigator.sendBeacon(window.location.href, formData);
        } else {
            fetch(window.location.href, { method: 'POST', body: formData, keepalive: true }).catch(()=> {});
        }
        clicks = 0; startTime = Date.now(); 
    }

    setInterval(sendKalkanPing, 5000);
    window.addEventListener('beforeunload', sendKalkanPing);
    window.addEventListener('visibilitychange', function() {
        if (document.visibilityState === 'hidden') sendKalkanPing();
    });
})();
</script>