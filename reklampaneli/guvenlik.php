<?php
session_start();
require_once 'db.php';

// Veritabanında admin tablosu yoksa otomatik oluştur ve varsayılan şifreyi belirle
try {
    $db->query("CREATE TABLE IF NOT EXISTS admin (
        id INT AUTO_INCREMENT PRIMARY KEY,
        kullanici_adi VARCHAR(50) NOT NULL,
        sifre VARCHAR(255) NOT NULL
    )");
    
    $kontrol = $db->query("SELECT * FROM admin WHERE id = 1")->fetch();
    if (!$kontrol) {
        // Varsayılan giriş bilgileri: admin / 123456
        $varsayilan_sifre = password_hash("123456", PASSWORD_DEFAULT);
        $db->query("INSERT INTO admin (kullanici_adi, sifre) VALUES ('admin', '$varsayilan_sifre')");
    }
} catch (PDOException $e) {}

// Kullanıcı giriş yapmamışsa login.php'ye şutla
if (!isset($_SESSION['admin_giris']) || $_SESSION['admin_giris'] !== true) {
    header("Location: login.php");
    exit;
}
?>