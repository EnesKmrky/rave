<?php
// header.php dosyası
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RP ŞEHİR - <?php echo ucfirst(str_replace('.php', '', $current_page)); ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

    <header class="navbar">
        <a href="index.php" class="logo">RP ŞEHİR</a>
        <nav class="nav-links">
            <a href="index.php" class="<?php if($current_page == 'index.php') echo 'active'; ?>">Anasayfa</a>
            <a href="guncellemeler.php" class="<?php if($current_page == 'guncellemeler.php') echo 'active'; ?>">Güncellemeler</a>
            <a href="kurallar.php" class="<?php if($current_page == 'kurallar.php') echo 'active'; ?>">Kurallar</a>
            <a href="ekip.php" class="<?php if($current_page == 'ekip.php') echo 'active'; ?>">Ekip</a>
            <a href="galeri.php" class="<?php if($current_page == 'galeri.php') echo 'active'; ?>">Galeri</a>
        </nav>
        <div class="auth-buttons">
             <a href="login.html">Giriş Yap/Kayıt Ol</a>
        </div>
        <div class="panel-buttons">
            </div>
    </header>

    <main>