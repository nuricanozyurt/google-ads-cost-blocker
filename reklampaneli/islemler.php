<?php
require_once 'guvenlik.php'; // Artık güvenli giriş yapmadan kimse bu dosyayı tetikleyemez

if (isset($_GET['islem'])) {
    
    // 1. İŞLEM: KLASİK TXT OLARAK İNDİRME (Sadece Kırmızı ve Şüpheliler)
    if ($_GET['islem'] == 'indir') {
        // Dinamik sınırı çekelim ki kimin şüpheli olduğunu bilelim
        $ayar = $db->query("SELECT tiklama_siniri FROM ayarlar WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
        $sinir = $ayar ? $ayar['tiklama_siniri'] : 3;

        // SORGU: Sadece durumu 1-2 olanlar veya sınırı aşanlar listelensin. (Yeşiller yok)
        $sql = "SELECT ip_adresi FROM reklam_tiklamalari 
                GROUP BY ip_adresi 
                HAVING (MAX(durum) IN (1,2) OR COUNT(*) > $sinir)";
        
        $sorgu = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        
        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="engellenen_ipler_' . date('Y-m-d') . '.txt"');
        
        foreach ($sorgu as $row) { 
            echo $row['ip_adresi'] . "\r\n"; 
        }
        exit;
    }

    // 2. İŞLEM: ENGELLENDİ OLARAK İŞARETLEME
    if ($_GET['islem'] == 'engelle' && isset($_GET['ip'])) {
        $ip = $_GET['ip'];
        $guncelle = $db->prepare("UPDATE reklam_tiklamalari SET durum = 1 WHERE ip_adresi = ?");
        $guncelle->execute([$ip]);
        header("Location: index.php");
        exit;
    }

    // 3. İŞLEM: ENGEL KALDIRMA
    if ($_GET['islem'] == 'engelkaldir' && isset($_GET['ip'])) {
        $ip = $_GET['ip'];
        $guncelle = $db->prepare("UPDATE reklam_tiklamalari SET durum = 0 WHERE ip_adresi = ?");
        $guncelle->execute([$ip]);
        header("Location: index.php");
        exit;
    }
    
    // 4. İŞLEM: C-BLOK (SUBNET MAHALLE) ENGELLEME
    if ($_GET['islem'] == 'c_blok_engelle' && isset($_GET['ip'])) {
        $ip = $_GET['ip'];
        $ip_parcalar = explode('.', $ip);
        if(count($ip_parcalar) == 4) {
            $subnet_ip = $ip_parcalar[0].'.'.$ip_parcalar[1].'.'.$ip_parcalar[2].'.*';
            $like_ip = $ip_parcalar[0].'.'.$ip_parcalar[1].'.'.$ip_parcalar[2].'.%';
            $guncelle = $db->prepare("UPDATE reklam_tiklamalari SET durum = 1 WHERE ip_adresi LIKE ?");
            $guncelle->execute([$like_ip]);
            
            $kontrol = $db->prepare("SELECT id FROM reklam_tiklamalari WHERE ip_adresi = ?");
            $kontrol->execute([$subnet_ip]);
            if(!$kontrol->fetch()) {
                $ekle = $db->prepare("INSERT INTO reklam_tiklamalari (ip_adresi, durum, kelime, sehir, operator, cihaz, isletim_sistemi, user_agent) VALUES (?, 1, 'C-BLOK ENGELİ', '-', '-', '-', '-', '-')");
                $ekle->execute([$subnet_ip]);
            }
        }
        header("Location: index.php");
        exit;
    }
    
    // 5. İŞLEM: TOPLU ENGELLEME
    if ($_GET['islem'] == 'toplu_engelle' && isset($_POST['secilen_ipler'])) {
        $ipler = $_POST['secilen_ipler'];
        if(is_array($ipler) && count($ipler) > 0) {
            $soru_isaretleri = str_repeat('?,', count($ipler) - 1) . '?';
            $sql = "UPDATE reklam_tiklamalari SET durum = 1 WHERE ip_adresi IN ($soru_isaretleri)";
            $guncelle = $db->prepare($sql);
            $guncelle->execute($ipler);
        }
        header("Location: index.php");
        exit;
    }

    // 6. İŞLEM: TOPLU ENGEL KALDIRMA
    if ($_GET['islem'] == 'toplu_engel_kaldir' && isset($_POST['secilen_ipler'])) {
        $ipler = $_POST['secilen_ipler'];
        if(is_array($ipler) && count($ipler) > 0) {
            $soru_isaretleri = str_repeat('?,', count($ipler) - 1) . '?';
            $sql = "UPDATE reklam_tiklamalari SET durum = 0 WHERE ip_adresi IN ($soru_isaretleri)";
            $guncelle = $db->prepare($sql);
            $guncelle->execute($ipler);
        }
        header("Location: index.php");
        exit;
    }

    // YENİ EKLENEN 7. İŞLEM: GOOGLE ADS İADE RAPORU (EXCEL/CSV ÇIKTISI)
    if ($_GET['islem'] == 'iade_indir') {
        $gun = isset($_GET['gun']) ? intval($_GET['gun']) : 30;
        $where = $gun > 0 ? "AND tarih_saat >= DATE_SUB(NOW(), INTERVAL $gun DAY)" : "";
        
        // BURASI ÇOK KRİTİK: Sadece durumu 1 (Şüpheli) ve 2 (Bot) olanları listeler!
        $sorgu = $db->query("SELECT * FROM reklam_tiklamalari WHERE durum IN (1,2) $where ORDER BY tarih_saat DESC")->fetchAll(PDO::FETCH_ASSOC);
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="Google_Ads_Iade_Raporu_' . date('Y-m-d') . '.csv"');
        
        // Excel'de Türkçe karakterlerin bozulmaması için BOM (Byte Order Mark) ekliyoruz
        echo "\xEF\xBB\xBF"; 
        
        $output = fopen('php://output', 'w');
        // Google'ın sevdiği başlık formatı
        fputcsv($output, array('IP Adresi', 'Tarih', 'Saat', 'Arama Kelimesi', 'Cihaz ve Tarayıcı', 'Engellenme Sebebi'));
        
        foreach ($sorgu as $row) {
            $tarih = date('d.m.Y', strtotime($row['tarih_saat']));
            $saat = date('H:i:s', strtotime($row['tarih_saat']));
            $sebep = ($row['durum'] == 2) ? "Veri Merkezi / Bot Trafiği" : "Aşırı Tıklama / Rakip Şüphesi";
            
            fputcsv($output, array(
                $row['ip_adresi'],
                $tarih,
                $saat,
                $row['kelime'],
                $row['cihaz'] . ' - ' . $row['user_agent'],
                $sebep
            ));
        }
        fclose($output);
        exit;
    }
}
?>