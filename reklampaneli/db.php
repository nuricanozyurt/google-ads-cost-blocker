<?php
$host = 'localhost';
$dbname = 'reklam_paneli'; // Az önce oluşturduğun veritabanı adı
$username = 'root';       // XAMPP kullanıyorsan varsayılan budur
$password = '';           // XAMPP kullanıyorsan varsayılan boştur

try {
    $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo "Bağlantı hatası: " . $e->getMessage();
    exit;
}
?>