<?php
session_start();
require_once 'db.php';

if (isset($_SESSION['admin_giris']) && $_SESSION['admin_giris'] === true) {
    header("Location: index.php");
    exit;
}

$hata = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $kullanici = htmlspecialchars($_POST['kullanici_adi']);
    $sifre = $_POST['sifre'];
    
    $sorgu = $db->prepare("SELECT * FROM admin WHERE kullanici_adi = ?");
    $sorgu->execute([$kullanici]);
    $admin = $sorgu->fetch(PDO::FETCH_ASSOC);
    
    if ($admin && password_verify($sifre, $admin['sifre'])) {
        $_SESSION['admin_giris'] = true;
        header("Location: index.php");
        exit;
    } else {
        $hata = "Kullanıcı adı veya şifre hatalı!";
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ads Kalkanı | Giriş</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f6f9; display: flex; align-items: center; justify-content: center; height: 100vh; }
        .login-card { border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); border: none; overflow: hidden; }
        .login-header { background: linear-gradient(135deg, #212529 0%, #343a40 100%); color: white; padding: 30px; text-align: center; }
    </style>
</head>
<body>
<div class="container" style="max-width: 450px;">
    <div class="card login-card">
        <div class="login-header">
            <h3 class="fw-bold mb-0">🛡️ Ads Kalkanı</h3>
            <p class="small text-muted mb-0 mt-2">Yönetim Paneline Giriş Yapın</p>
        </div>
        <div class="card-body p-4">
            <?php if($hata != "") echo "<div class='alert alert-danger fw-bold small'>$hata</div>"; ?>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label fw-bold text-secondary small">Kullanıcı Adı</label>
                    <input type="text" name="kullanici_adi" class="form-control form-control-lg bg-light" required>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-bold text-secondary small">Şifre</label>
                    <input type="password" name="sifre" class="form-control form-control-lg bg-light" required>
                </div>
                <button type="submit" class="btn btn-primary w-100 fw-bold btn-lg shadow-sm">Sisteme Giriş Yap</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>