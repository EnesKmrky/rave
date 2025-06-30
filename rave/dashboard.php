<?php
session_start();
require 'db.php'; // Veritabanı bağlantımızı dahil ediyoruz

// Kullanıcı giriş yapmamışsa, giriş sayfasına yönlendir
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.html');
    exit;
}

$citizenid = $_SESSION['citizenid'];
$username = $_SESSION['username']; 

$playerData = null;
$playerVehicles = [];
$phoneNumber = 'N/A';
$genderDisplay = 'N/A';
$discordIdentifier = 'N/A';

// Oyuncunun temel bilgilerini çekme
$stmt = $conn->prepare("SELECT * FROM players WHERE citizenid = ?");
$stmt->bind_param("s", $citizenid);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $playerData = $result->fetch_assoc();
    $playerData['money'] = json_decode($playerData['money'], true);
    $playerData['job'] = json_decode($playerData['job'], true);
    $playerData['gang'] = json_decode($playerData['gang'], true);
    $playerData['charinfo'] = json_decode($playerData['charinfo'], true);
    $playerData['inventory'] = json_decode($playerData['inventory'] ?? '[]', true);
    $playerData['identifiers'] = json_decode($playerData['identifiers'] ?? '[]', true);

    if (isset($playerData['charinfo']['gender'])) {
        $genderDisplay = $playerData['charinfo']['gender'] == 0 ? 'Erkek' : 'Kadın';
    }

    if (is_array($playerData['identifiers'])) {
        foreach ($playerData['identifiers'] as $id) {
            if (str_starts_with($id, 'discord:')) {
                $discordIdentifier = substr($id, 8);
                break;
            }
        }
    }

} else {
    echo "<p style='color: red;'>Oyuncu verisi bulunamadı!</p>";
    exit;
}
$stmt->close();

// Telefon numarasını 'phone_phones' tablosundan çekme
$phoneStmt = $conn->prepare("SELECT phone_number FROM phone_phones WHERE owner_id = ?");
$phoneStmt->bind_param("s", $citizenid);
$phoneStmt->execute();
$phoneResult = $phoneStmt->get_result();

if ($phoneResult->num_rows > 0) {
    $phoneRow = $phoneResult->fetch_assoc();
    $phoneNumber = $phoneRow['phone_number'];
}
$phoneStmt->close();

// --- ARAÇ TABLOSU AYARLARI ---
$vehicleTableName = "player_vehicles"; // << KENDİ ARAÇ TABLO ADINIZLA DEĞİŞTİRİN >>
$vehicleLinkColumn = "citizenid";        // << OYUNCUYU İLİŞKİLENDİREN SÜTUN ADIYLA DEĞİŞTİRİN (örn: 'citizenid' veya 'owner') >>
$vehicleModelColumn = "vehicle";     // << ARAÇ MODELİNİ İÇEREN SÜTUN ADIYLA DEĞİŞTİRİN (örn: 'vehicle' veya 'model') >>

// Oyuncunun araçlarını çekme
$vehicleStmt = $conn->prepare("SELECT plate, {$vehicleModelColumn} FROM {$vehicleTableName} WHERE {$vehicleLinkColumn} = ?");
$vehicleStmt->bind_param("s", $citizenid);
$vehicleStmt->execute();
$vehicleResult = $vehicleStmt->get_result();

if ($vehicleResult->num_rows > 0) {
    while ($row = $vehicleResult->fetch_assoc()) {
        $playerVehicles[] = $row;
    }
}
$vehicleStmt->close();

// Toplam kayıtlı oyuncu sayısını çekme (Statik bilgi)
$totalPlayers = 0;
$totalPlayersStmt = $conn->query("SELECT COUNT(*) FROM players");
if ($totalPlayersStmt) {
    $totalPlayers = $totalPlayersStmt->fetch_row()[0];
}

// FiveM Sunucu Durumu ve Aktif Oyuncu Çekme (Doğrudan sunucu players.json'dan)
$serverStatusDisplay = "Bilgi Alınamıyor";
$activePlayersDisplay = "N/A";
$serverStatusClass = "status-unknown";

// Buradaki IP ve Port değerlerini kendi sunucunuzun gerçek IP ve Portu ile değiştirin!
$serverIp = "38.3.137.2";   // << LÜTFEN KENDİ FiveM SUNUCU IP ADRESİNİZİ BURAYA GİRİN! >>
$serverPort = "30120"; // << LÜTFEN KENDİ FiveM SUNUCU PORTUNUZU BURAYA GİRİN! >>

$apiDebugMessage = ''; 

// BURADAKİ SABİTLER ASLA DEĞİŞTİRİLMEMELİ! Bunlar kontrol için kullanılan yer tutuculardır.
const FIVEM_PLACEHOLDER_IP = "YOUR_FIVEM_SERVER_IP"; 
const FIVEM_PLACEHOLDER_PORT = "YOUR_FIVEM_SERVER_PORT";

// API çağrısını sadece IP ve Port placeholder değerleri değiştirildiyse yap
if ($serverIp !== FIVEM_PLACEHOLDER_IP && $serverPort !== FIVEM_PLACEHOLDER_PORT) {
    $apiUrl = "http://{$serverIp}:{$serverPort}/players.json"; 
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    $apiResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    $curlErrno = curl_errno($ch);
    curl_close($ch);

    if ($apiResponse !== false && $httpCode == 200) {
        $playersDataAPI = json_decode($apiResponse, true); // playersDataAPI olarak adını değiştirdim çakışmasın diye

        if (is_array($playersDataAPI)) {
            $onlineClients = count($playersDataAPI);
            $serverStatusDisplay = "Açık";
            $activePlayersDisplay = $onlineClients . " / 64"; // Maksimum oyuncu sayısını manuel olarak 64 belirttim
            $serverStatusClass = "status-online";
        } else {
            $serverStatusDisplay = "Kapalı (Geçersiz Yanıt)";
            $serverStatusClass = "status-offline";
            $apiDebugMessage = 'API yanıtı geçerli bir dizi değil. Sunucu players.json sağlamıyor veya format hatalı.';
        }
    } elseif ($httpCode == 404) {
        $serverStatusDisplay = "Kapalı (Endpoint Yok)";
        $serverStatusClass = "status-offline";
        $apiDebugMessage = 'players.json endpointi bulunamadı. Sunucu kapalı olabilir veya `sv_enableOldकाळquery` ayarı aktif olmayabilir.';
    } else {
        $serverStatusDisplay = "Kapalı";
        $serverStatusClass = "status-offline";
        $apiDebugMessage = "API isteği başarısız oldu: HTTP Kodu: {$httpCode}. cURL Hatası ({$curlErrno}): {$curlError}.";
    }
} else {
    $apiDebugMessage = 'Sunucu IP veya Port bilgileri hâlâ varsayılan placeholder değerlerinde. Lütfen kodu güncelleyin.';
}


// Destek Talebi İşlemleri
$ticketSuccessMessage = '';
$ticketErrorMessage = '';
$currentTicketDetails = null;
$currentTicketMessages = [];
$userPunishments = []; // Cezalar için boş dizi tanımlaması

// POST/Redirect/GET (PRG) Deseni için POST işleminden sonra yönlendirme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['create_ticket']) || isset($_POST['reply_to_ticket']) || isset($_POST['delete_ticket']) || isset($_POST['change_password']))) {
    $db_conn = new mysqli($servername, $db_username, $db_password, $dbname); 
    if ($db_conn->connect_error) { 
        die("Connection failed for ticket process: " . $db_conn->connect_error); 
    }

    if (isset($_POST['create_ticket'])) {
        $ticketSubject = $_POST['ticket_subject'] ?? '';
        $ticketMessage = $_POST['ticket_message'] ?? '';
        $ticketPriority = $_POST['ticket_priority'] ?? 'medium';

        if (empty($ticketSubject) || empty($ticketMessage)) {
            $ticketErrorMessage = 'Konu ve mesaj alanları boş bırakılamaz.';
        } else {
            $insertStmt = $db_conn->prepare("INSERT INTO support_tickets (citizenid, subject, message, priority) VALUES (?, ?, ?, ?)");
            $insertStmt->bind_param("ssss", $citizenid, $ticketSubject, $ticketMessage, $ticketPriority);

            if ($insertStmt->execute()) {
                $newTicketId = $insertStmt->insert_id;
                $insertFirstMessageStmt = $db_conn->prepare("INSERT INTO ticket_messages (ticket_id, sender_citizenid, message, is_admin) VALUES (?, ?, ?, 0)");
                $insertFirstMessageStmt->bind_param("iss", $newTicketId, $citizenid, $ticketMessage);
                $insertFirstMessageStmt->execute();
                $insertFirstMessageStmt->close();

                $ticketSuccessMessage = 'Destek talebiniz başarıyla oluşturuldu!';
            } else {
                $ticketErrorMessage = 'Destek talebi oluşturulurken bir hata oluştu: ' . $insertStmt->error;
            }
            $insertStmt->close();
        }
    } elseif (isset($_POST['reply_to_ticket'])) {
        $ticketIdToReply = $_POST['ticket_id_to_reply'] ?? '';
        $replyMessage = $_POST['reply_message'] ?? '';

        if (!empty($ticketIdToReply) && !empty($replyMessage)) {
            $checkTicketStmt = $db_conn->prepare("SELECT id FROM support_tickets WHERE id = ? AND citizenid = ?");
            $checkTicketStmt->bind_param("is", $ticketIdToReply, $citizenid);
            $checkTicketStmt->execute();
            $checkTicketResult = $checkTicketStmt->get_result();

            if ($checkTicketResult->num_rows > 0) {
                $insertReplyStmt = $db_conn->prepare("INSERT INTO ticket_messages (ticket_id, sender_citizenid, message, is_admin) VALUES (?, ?, ?, 0)");
                $insertReplyStmt->bind_param("iss", $ticketIdToReply, $citizenid, $replyMessage);
                if ($insertReplyStmt->execute()) {
                    $updateStatusStmt = $db_conn->prepare("UPDATE support_tickets SET status = 'pending' WHERE id = ?");
                    $updateStatusStmt->bind_param("i", $ticketIdToReply);
                    $updateStatusStmt->execute();
                    $updateStatusStmt->close();

                    $ticketSuccessMessage = 'Yanıtınız gönderildi!';
                } else {
                    $ticketErrorMessage = 'Yanıt gönderilirken bir hata oluştu: ' . $insertReplyStmt->error;
                }
                $insertReplyStmt->close();
            } else {
                $ticketErrorMessage = 'Geçersiz talep ID\'si veya bu talebe yanıt yetkiniz yok.';
            }
            $checkTicketStmt->close();
        } else {
            $ticketErrorMessage = 'Yanıt göndermek için talep ID ve mesaj gerekli.';
        }
    } elseif (isset($_POST['delete_ticket'])) {
        $ticketIdToDelete = $_POST['ticket_id_to_delete'] ?? '';

        if (!empty($ticketIdToDelete)) {
            $checkTicketStmt = $db_conn->prepare("SELECT id, status FROM support_tickets WHERE id = ? AND citizenid = ?");
            $checkTicketStmt->bind_param("is", $ticketIdToDelete, $citizenid);
            $checkTicketStmt->execute();
            $checkTicketResult = $checkTicketStmt->get_result();

            if ($checkTicketResult->num_rows > 0) {
                $ticketRow = $checkTicketResult->fetch_assoc();
                if ($ticketRow['status'] == 'open' || $ticketRow['status'] == 'pending') {
                    $deleteStmt = $db_conn->prepare("DELETE FROM support_tickets WHERE id = ? AND citizenid = ?");
                    $deleteStmt->bind_param("is", $ticketIdToDelete, $citizenid);
                    if ($deleteStmt->execute()) {
                        $ticketSuccessMessage = 'Talep başarıyla silindi.';
                    } else {
                        $ticketErrorMessage = 'Talep silinirken bir hata oluştu: ' . $deleteStmt->error;
                    }
                    $deleteStmt->close();
                } else {
                    $ticketErrorMessage = 'Bu durumdaki bir talebi silemezsiniz. Sadece açık veya bekleyen talepler silinebilir.';
                }
            } else {
                $ticketErrorMessage = 'Geçersiz talep ID\'si veya bu talebi silme yetkiniz yok.';
            }
            $checkTicketStmt->close();
        } else {
            $ticketErrorMessage = 'Silinecek talep ID\'si gerekli.';
        }
    } elseif (isset($_POST['change_password'])) {
        $oldPassword = $_POST['old_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmNewPassword = $_POST['confirm_new_password'] ?? '';

        if (empty($oldPassword) || empty($newPassword) || empty($confirmNewPassword)) {
            $ticketErrorMessage = 'Tüm şifre alanları doldurulmalıdır.';
        } elseif ($newPassword !== $confirmNewPassword) {
            $ticketErrorMessage = 'Yeni şifreler eşleşmiyor.';
        } elseif (strlen($newPassword) < 6) {
            $ticketErrorMessage = 'Yeni şifre en az 6 karakter olmalıdır.';
        } else {
            $checkPassStmt = $db_conn->prepare("SELECT web_password FROM players WHERE citizenid = ?");
            $checkPassStmt->bind_param("s", $citizenid);
            $checkPassStmt->execute();
            $checkPassResult = $checkPassStmt->get_result();

            if ($checkPassResult->num_rows > 0) {
                $playerRow = $checkPassResult->fetch_assoc();
                if (password_verify($oldPassword, $playerRow['web_password'])) {
                    $hashedNewPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    $updatePassStmt = $db_conn->prepare("UPDATE players SET web_password = ? WHERE citizenid = ?");
                    $updatePassStmt->bind_param("ss", $hashedNewPassword, $citizenid);
                    if ($updatePassStmt->execute()) {
                        $ticketSuccessMessage = 'Şifreniz başarıyla değiştirildi! Yeni şifrenizle tekrar giriş yapmanız gerekebilir.';
                        session_destroy();
                        header('Location: login.html?message=' . urlencode($ticketSuccessMessage));
                        exit;
                    } else {
                        $ticketErrorMessage = 'Şifre değiştirilirken veritabanı hatası: ' . $updatePassStmt->error;
                    }
                    $updatePassStmt->close();
                } else {
                    $ticketErrorMessage = 'Mevcut şifreniz yanlış.';
                }
            } else {
                $ticketErrorMessage = 'Kullanıcı bulunamadı.';
            }
            $checkPassStmt->close();
        }
    }
    
    $db_conn->close();
    
    // PRG deseni: POST işleminden sonra yönlendirme yap
    $redirectUrl = 'dashboard.php';
    if ($ticketSuccessMessage) {
        $redirectUrl .= '?success_message=' . urlencode($ticketSuccessMessage);
    } elseif ($ticketErrorMessage) {
        $redirectUrl .= '?error_message=' . urlencode($ticketErrorMessage);
    }
    // Her işlemden sonra doğru sekmeye yönlendir
    if (isset($_POST['create_ticket'])) {
        $redirectUrl .= '&section=my-tickets-section'; 
    } elseif (isset($_POST['reply_to_ticket'])) {
        $redirectUrl .= '&view_ticket_id=' . urlencode($_POST['ticket_id_to_reply']); // Yanıt sonrası aynı talebe geri dön
    } elseif (isset($_POST['delete_ticket'])) {
        $redirectUrl .= '&section=my-tickets-section'; // Silme sonrası taleplerim sayfasına dön
    } elseif (isset($_POST['change_password'])) {
        $redirectUrl .= '&section=settings'; // Şifre değişim sonrası ayarlar sayfasına dön
    }

    header('Location: ' . $redirectUrl);
    exit;
}

// GET isteğiyle gelen mesajları al (PRG deseninden sonra)
if (isset($_GET['success_message'])) {
    $ticketSuccessMessage = htmlspecialchars($_GET['success_message']);
}
if (isset($_GET['error_message'])) {
    $ticketErrorMessage = htmlspecialchars($_GET['error_message']);
}


// Destek Taleplerini Çekme (Sayfa yüklendiğinde veya talep listesine dönüldüğünde)
$userTickets = [];
// Yeni bağlantı, db.php'den gelen doğru kullanıcı adı ve şifre ile
$db_conn_tickets = new mysqli($servername, $db_username, $db_password, $dbname); 
if ($db_conn_tickets->connect_error) { 
    die("Connection failed for fetching tickets: " . $db_conn_tickets->connect_error); 
}

$ticketsStmt = $db_conn_tickets->prepare("SELECT id, subject, status, priority, created_at FROM support_tickets WHERE citizenid = ? ORDER BY created_at DESC");
$ticketsStmt->bind_param("s", $citizenid);
$ticketsStmt->execute();
$ticketsResult = $ticketsStmt->get_result();

if ($ticketsResult->num_rows > 0) {
    while ($row = $ticketsResult->fetch_assoc()) {
        $userTickets[] = $row;
    }
}
$ticketsStmt->close();

// Cezaları çekme (dashboard.php içinde, diğer verilerle birlikte)
$userPunishments = [];
$punishmentsStmt = $db_conn_tickets->prepare("SELECT id, type, reason, duration, start_time, end_time, active, admin_citizenid FROM punishments WHERE citizenid = ? ORDER BY start_time DESC");
$punishmentsStmt->bind_param("s", $citizenid);
$punishmentsStmt->execute();
$punishmentsResult = $punishmentsStmt->get_result();

if ($punishmentsResult->num_rows > 0) {
    while ($row = $punishmentsResult->fetch_assoc()) {
        $userPunishments[] = $row;
    }
}
$punishmentsStmt->close();


// Tek bir talep detayını ve mesajlarını çekme (URL'de view_ticket_id varsa)
if (isset($_GET['view_ticket_id'])) {
    $viewTicketId = intval($_GET['view_ticket_id']);
    
    $ticketDetailsStmt = $db_conn_tickets->prepare("SELECT id, subject, message, status, priority, created_at FROM support_tickets WHERE id = ? AND citizenid = ?");
    $ticketDetailsStmt->bind_param("is", $viewTicketId, $citizenid);
    $ticketDetailsStmt->execute();
    $ticketDetailsResult = $ticketDetailsStmt->get_result();

    if ($ticketDetailsResult->num_rows > 0) {
        $currentTicketDetails = $ticketDetailsResult->fetch_assoc();

        $messagesStmt = $db_conn_tickets->prepare("SELECT sender_citizenid, message, created_at, is_admin FROM ticket_messages WHERE ticket_id = ? ORDER BY created_at ASC");
        $messagesStmt->bind_param("i", $viewTicketId);
        $messagesStmt->execute();
        $messagesResult = $messagesStmt->get_result();

        if ($messagesResult->num_rows > 0) {
            while ($row = $messagesResult->fetch_assoc()) {
                $currentTicketMessages[] = $row;
            }
        }
        $messagesStmt->close();
    }
    $ticketDetailsStmt->close();

    $_SESSION['active_dashboard_section'] = 'view-ticket-details-section';

} else {
    // Varsayılan olarak Kişisel Bilgiler sekmesini göster veya GET ile gelen section'ı al
    $_SESSION['active_dashboard_section'] = $_GET['section'] ?? ($_SESSION['active_dashboard_section'] ?? 'personal-info');
}

$db_conn_tickets->close(); // Bağlantıyı kapat

// İlk bağlantıyı kapat (eğer hala açıksa, script sonunda otomatik kapanır ama iyi pratik)
if (isset($conn) && $conn->ping()) {
    $conn->close();
}

// Hoş Geldin mesajı için oyuncunun adını/soyadını al
$displayPlayerName = 'Misafir';
if (isset($playerData['charinfo']['firstname']) && isset($playerData['charinfo']['lastname'])) {
    $displayPlayerName = htmlspecialchars($playerData['charinfo']['firstname']) . ' ' . htmlspecialchars($playerData['charinfo']['lastname']);
    if (empty(trim($displayPlayerName)) || $displayPlayerName === ' ') { 
        $displayPlayerName = htmlspecialchars($username); 
    }
} elseif (isset($playerData['name'])) { 
    $displayPlayerName = htmlspecialchars($playerData['name']);
    if (empty(trim($displayPlayerName)) || $displayPlayerName === ' ') {
        $displayPlayerName = htmlspecialchars($username);
    }
} else {
    $displayPlayerName = htmlspecialchars($username);
}

if (empty(trim($displayPlayerName)) || strtolower($displayPlayerName) === 'guest' || strtolower($displayPlayerName) === 'n/a') { 
    $displayPlayerName = 'Misafir';
}

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rave Panel - Yönetim Paneli</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Roboto:wght@300;400;700&display=swap" rel="stylesheet">
    
    <style>
        /* Renk Paleti ve Genel Ayarlar */
        :root {
            --dark-bg: #0A0A0A; /* Siyah */
            --container-bg: #1A1A1A; /* Koyu Gri */
            --sidebar-bg: #222222; /* Daha Koyu Gri */
            --content-bg: #2C2C2C; /* Orta Gri */
            --text-color-light: #F0F0F0; /* Beyaz */
            --text-color-dark: #AAAAAA; /* Açık Gri */
            --highlight-blue: #17A2B8; /* Mavi Vurgu */
            --highlight-pink: #FF00FF; /* Fuşya Vurgu (arka plan border için) */
            --highlight-purple: #800080; /* Mor Vurgu (arka plan border için) */
            --glow-light: rgba(255, 255, 255, 0.1); /* Beyaz Glow */
            --glow-neon-purple: rgba(160, 32, 240, 0.3); /* Mor Neon Glow */
            --glow-neon-pink: rgba(255, 0, 255, 0.3); /* Pembe Neon Glow */
            --active-item-bg: #3A3A3A; /* Aktif menü öğesi arka planı */
            --vehicle-bg: rgba(255, 255, 255, 0.05); /* Araç kutusu arka planı (hafif şeffaf beyaz) */
            --vehicle-border: rgba(255, 255, 255, 0.1); /* Araç kutusu kenarlığı (hafif şeffaf beyaz) */
        }

        /* Temel Stil */
        body {
            font-family: 'Roboto', sans-serif;
            background-color: var(--dark-bg);
            color: var(--text-color-light);
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            overflow-x: hidden;
            background-image: url('https://raw.githubusercontent.com/FiveM/fivem-docs/master/docs/assets/images/gtav-bg.jpg'); /* Hafif parallax arka plan */
            background-attachment: fixed;
            background-position: center center;
            background-size: cover;
            position: relative;
        }
        /* Arka plan üzerine hafif koyuluk için overlay */
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.7); /* %70 siyah opaklık */
            z-index: 0;
        }

        /* Ana Kapsayıcı */
        .dashboard-wrapper {
            display: flex;
            background-color: var(--container-bg);
            border-radius: 15px;
            box-shadow: 0 0 10px var(--glow-light), 0 0 20px rgba(255,255,255,0.05);
            width: 95%;
            max-width: 1400px;
            min-height: 750px;
            overflow: hidden;
            opacity: 0;
            animation: fadeInScale 1s ease-out forwards;
            position: relative;
            z-index: 1;
        }
        
        /* Neon Border - Sade */
        .dashboard-wrapper::before {
            content: '';
            position: absolute;
            top: -5px;
            left: -5px;
            right: -5px;
            bottom: -5px;
            border-radius: 18px;
            background: linear-gradient(45deg, var(--highlight-purple), var(--highlight-pink), var(--highlight-purple));
            background-size: 400% 400%;
            z-index: -1;
            animation: gradientBorder 12s ease infinite;
            opacity: 0.2;
        }

        /* Başlıklar */
        h1, h2, h3 {
            font-family: 'Bebas Neue', sans-serif;
            letter-spacing: 1px;
            text-align: center;
            color: var(--text-color-light);
            text-shadow: none;
            margin-bottom: 20px;
            opacity: 0;
            animation: flyInDown 1s ease-out forwards;
            animation-fill-mode: both;
        }
        h1 { animation-delay: 0.5s; font-size: 3em; margin-top: 15px; color: var(--highlight-blue);}
        h2 { animation-delay: 0.8s; font-size: 1.8em; color: var(--text-color-light); }
        h3 { 
            color: var(--text-color-light);
            text-shadow: none;
            font-size: 1.6em;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background-color: var(--sidebar-bg);
            padding: 25px 0;
            display: flex;
            flex-direction: column;
            box-shadow: 3px 0 10px rgba(0, 0, 0, 0.3);
            border-right: 1px solid rgba(255, 255, 255, 0.05);
        }

        .sidebar-item {
            padding: 15px 25px;
            color: var(--text-color-light);
            text-decoration: none;
            display: flex;
            align-items: center;
            font-size: 1.05em;
            border-left: 4px solid transparent;
            transition: background-color 0.3s ease, border-left-color 0.3s ease, color 0.3s ease;
            font-family: 'Roboto', sans-serif;
            letter-spacing: 0;
        }
        .sidebar-item:hover {
            background-color: rgba(255, 255, 255, 0.05);
            border-left-color: var(--highlight-blue);
            color: var(--highlight-blue);
        }
        .sidebar-item.active {
            background-color: var(--active-item-bg);
            border-left-color: var(--highlight-blue);
            color: var(--highlight-blue);
            font-weight: bold;
            box-shadow: inset 0 0 5px rgba(255, 255, 255, 0.1);
        }
        .sidebar-item i {
            margin-right: 12px;
            font-size: 1.3em;
            color: var(--text-color-dark);
        }
        .sidebar-item.active i {
            color: var(--highlight-blue);
        }

        /* Ana İçerik Alanı */
        .main-content {
            flex-grow: 1;
            padding: 35px;
            background-color: var(--content-bg);
            border-top-right-radius: 15px;
            border-bottom-right-radius: 15px;
        }

        .content-section {
            display: none;
            opacity: 0;
            animation: fadeIn 0.6s ease-out forwards;
            animation-fill-mode: both;
        }
        .content-section.active {
            display: block;
        }
        
        .data-item {
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            font-size: 1em;
            color: var(--text-color-dark);
        }
        .data-item strong {
                color: var(--text-color-light);
            min-width: 170px;
            text-shadow: none;
        }
        .data-item i {
            margin-right: 10px;
            font-size: 1.1em;
            color: var(--highlight-blue);
        }

        /* Progress Bar Stili */
        .progress-container {
            width: 100%;
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 5px;
            overflow: hidden;
            height: 12px;
            margin-top: 5px;
            box-shadow: inset 0 0 3px rgba(0, 0, 0, 0.5);
        }

        .progress-bar {
            height: 100%;
            width: 0%; 
            background: linear-gradient(90deg, var(--highlight-purple), var(--highlight-pink));
            border-radius: 5px;
            transition: width 1s ease-out;
        }

        /* Vehicle List */
        .vehicle-list {
            list-style: none;
            padding: 0;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 20px;
        }
        .vehicle-item {
            background-color: var(--vehicle-bg);
            padding: 15px;
            border-radius: 10px;
            display: flex;
            flex-direction: column;
            align-items: center;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            box-shadow: 0 0 5px rgba(255, 255, 255, 0.08);
        }
        .vehicle-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 0 10px var(--highlight-blue); /* Mavi hover glow */
        }
        .vehicle-item span {
            color: var(--text-color-light);
            font-size: 0.95em;
            text-align: center;
            margin-top: 10px;
        }
        .vehicle-item strong {
            color: var(--highlight-blue);
            text-shadow: none;
        }
        .vehicle-item img {
            max-width: 90%;
            height: 80px;
            object-fit: contain;
            border-radius: 5px;
            margin-bottom: 10px;
            box-shadow: 0 0 5px rgba(255, 255, 255, 0.1);
        }

        .no-data {
            color: var(--text-color-dark);
            text-align: center;
            padding: 15px;
            background-color: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            border: 1px dashed rgba(255, 255, 255, 0.1);
        }

        .logout-btn-container {
            text-align: center;
            margin-top: auto;
            padding-top: 25px;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
        }

        .logout-btn {
            background: linear-gradient(90deg, var(--highlight-purple), var(--highlight-pink));
            color: var(--text-color-light);
            padding: 12px 30px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1em;
            transition: transform 0.3s ease, box-shadow 0.3s ease, background-size 0.5s ease;
            box-shadow: 0 0 8px rgba(255, 0, 255, 0.2);
            background-size: 200% 100%;
            background-position: 0% 50%;
        }
        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 0 12px rgba(255, 0, 255, 0.4);
            background-position: 100% 50%;
        }
        .logout-btn i {
            margin-right: 8px;
        }

        /* Animasyonlar */
        @keyframes fadeInScale {
            from { opacity: 0; transform: scale(0.98); }
            to { opacity: 1; transform: scale(1); }
        }

        @keyframes flyInDown {
            from { opacity: 0; transform: translateY(-30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(5px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes gradientBorder {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* Server Stats Box */
        .server-info-box {
            background-color: var(--sidebar-bg);
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            margin-bottom: 25px;
            box-shadow: 0 0 8px rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        .server-info-box img.server-logo {
            max-width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid var(--highlight-blue);
            box-shadow: 0 0 10px var(--highlight-blue);
            margin-bottom: 10px;
        }
        .server-info-box h1 {
            font-size: 2em;
            margin-top: 0;
            margin-bottom: 10px;
            color: var(--text-color-light);
            text-shadow: none;
            letter-spacing: 1px;
        }
        .server-stats-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 10px;
            margin-top: 15px;
        }
        .stat-item {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 1em;
            color: var(--text-color-dark);
        }
        .stat-item i {
            font-size: 1.1em;
            color: var(--highlight-blue);
        }
        .stat-item strong {
            color: var(--text-color-light);
        }
        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
        }
        .status-online { background-color: #28a745; box-shadow: 0 0 5px #28a745; }
        .status-offline { background-color: #dc3545; box-shadow: 0 0 5px #dc3545; }
        .status-unknown { background-color: #ffc107; box-shadow: 0 0 5px #ffc107; }

        /* Destek Talebi Formu Stili */
        .ticket-form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .ticket-form label {
            font-size: 1em;
            color: var(--text-color-light);
            margin-bottom: 5px;
            display: block;
        }
        .ticket-form input[type="text"],
        .ticket-form select,
        .ticket-form textarea {
            width: calc(100% - 20px);
            padding: 10px;
            border-radius: 5px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            background-color: rgba(255, 255, 255, 0.05);
            color: var(--text-color-light);
            font-size: 1em;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .ticket-form input[type="text"]:focus,
        .ticket-form select:focus,
        .ticket-form textarea:focus {
            border-color: var(--highlight-blue);
            box-shadow: 0 0 8px rgba(23, 162, 184, 0.4);
            outline: none;
        }
        .ticket-form textarea {
            min-height: 100px;
            resize: vertical;
        }
        .ticket-form button {
            background-color: var(--highlight-blue);
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1.1em;
            transition: background-color 0.3s ease, box-shadow 0.3s ease;
            box-shadow: 0 0 8px rgba(23, 162, 184, 0.3);
        }
        .ticket-form button:hover {
            background-color: #1a8a9e;
            box-shadow: 0 0 15px rgba(23, 162, 184, 0.6);
        }
        .ticket-message {
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            font-size: 0.95em;
            text-align: center;
        }
        .ticket-message.success {
            background-color: rgba(40, 167, 69, 0.2);
            color: #28a745;
            border: 1px solid #28a745;
        }
        .ticket-message.error {
            background-color: rgba(220, 53, 69, 0.2);
            color: #dc3545;
            border: 1px solid #dc3545;
        }

        /* Destek Talepleri Listesi Stili */
        .tickets-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .tickets-table th, .tickets-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            color: var(--text-color-dark);
        }
        .tickets-table th {
            background-color: rgba(255, 255, 255, 0.05);
            color: var(--text-color-light);
            font-family: 'Bebas Neue', sans-serif;
            letter-spacing: 1px;
            font-size: 1.1em;
        }
        .tickets-table tbody tr:hover {
            background-color: rgba(255, 255, 255, 0.03);
        }
        .ticket-status-open { color: #28a745; }
        .ticket-status-pending { color: #ffc107; }
        .ticket-status-answered { color: #17a2b8; } /* Mavi */
        .ticket-status-resolved { color: #6f42c1; } /* Mor */
        .ticket-status-closed { color: #dc3545; }
        .ticket-priority-low { color: #17a2b8; }
        .ticket-priority-medium { color: #ffc107; }
        .ticket-priority-high { color: #dc3545; }

        /* Ticket Detayları (Mesajlaşma Ekranı) Stili */
        .ticket-detail-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding-bottom: 10px;
        }
        .ticket-detail-header h3 {
            margin: 0;
            border-bottom: none; /* Üstteki h3 stilini ezer */
            padding-bottom: 0;
        }
        .ticket-back-btn {
            background-color: var(--text-color-dark);
            color: var(--dark-bg);
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 0.9em;
            transition: background-color 0.3s ease;
        }
        .ticket-back-btn:hover {
            background-color: var(--text-color-light);
        }

        .ticket-meta-info {
            display: flex;
            justify-content: space-around;
            background-color: rgba(255, 255, 255, 0.05);
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .ticket-meta-item {
            text-align: center;
            color: var(--text-color-dark);
            font-size: 0.9em;
        }
        .ticket-meta-item strong {
            display: block;
            font-size: 1.1em;
            color: var(--text-color-light);
            margin-top: 5px;
        }

        .messages-container {
            max-height: 400px;
            overflow-y: auto;
            padding: 15px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            background-color: rgba(255, 255, 255, 0.02);
            margin-bottom: 20px;
        }
        .message-box {
            display: flex;
            margin-bottom: 15px;
        }
        .message-box.user {
            justify-content: flex-start;
        }
        .message-box.admin {
            justify-content: flex-end;
        }
        .message-content {
            max-width: 70%;
            padding: 10px 15px;
            border-radius: 15px;
            font-size: 0.95em;
            line-height: 1.4;
            position: relative;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.3);
        }
        .message-box.user .message-content {
            background-color: var(--highlight-blue);
            color: white;
            border-bottom-left-radius: 2px;
        }
        .message-box.admin .message-content {
            background-color: var(--active-item-bg); /* Koyu gri */
            color: var(--text-color-light);
            border-bottom-right-radius: 2px;
        }
        .message-sender {
            font-size: 0.8em;
            color: var(--text-color-dark);
            margin-top: 5px;
        }
        .message-box.user .message-sender {
            text-align: left;
        }
        .message-box.admin .message-sender {
            text-align: right;
        }
        .reply-form-container {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 20px;
            margin-top: 20px;
        }
        .reply-form-container textarea {
            width: calc(100% - 20px);
            padding: 10px;
            border-radius: 5px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            background-color: rgba(255, 255, 255, 0.05);
            color: var(--text-color-light);
            font-size: 1em;
            resize: vertical;
            min-height: 60px;
            margin-bottom: 10px;
        }
        .reply-form-container button {
            background-color: var(--highlight-blue);
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1em;
            transition: background-color 0.3s ease;
        }
        .reply-form-container button:hover {
            background-color: #1a8a9e;
        }

    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <div class="sidebar">
            <div class="server-info-box">
                <img src="assets/logo.png" alt="Sunucu Logosu" class="server-logo">
                <h1>Your Server Name</h1> 
                <div class="server-stats-grid">
                    <div class="stat-item">
                        <i class="fas fa-users"></i>
                        <span>Toplam Kayıtlı Oyuncu: <strong><?php echo number_format($totalPlayers); ?></strong></span>
                    </div>
                    <div class="stat-item">
                        <i class="fas fa-network-wired"></i> 
                        <span>Sunucu Durumu: 
                            <span class="status-indicator <?php echo $serverStatusClass; ?>"></span>
                            <strong><?php echo $serverStatusDisplay; ?></strong>
                        </span>
                    </div>
                    <?php if ($activePlayersDisplay != 'N/A'): ?>
                    <div class="stat-item">
                        <i class="fas fa-user-friends"></i>
                        <span>Aktif Oyuncu: <strong><?php echo $activePlayersDisplay; ?></strong></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($apiDebugMessage): ?>
                        <p style="font-size:0.7em; margin-top:10px; color:#ff6b6b;"><?php echo $apiDebugMessage; ?></p>
                    <?php endif; ?>
                </div>
            </div>
            
            <a href="#" class="sidebar-item active" data-target="personal-info">
                <i class="fas fa-id-card"></i> Kişisel Bilgiler
            </a>
            <a href="#" class="sidebar-item" data-target="economy">
                <i class="fas fa-wallet"></i> Ekonomik Durum
            </a>
            <a href="#" class="sidebar-item" data-target="job-gang">
                <i class="fas fa-briefcase"></i> Meslek & Çete
            </a>
            <a href="#" class="sidebar-item" data-target="vehicles">
                <i class="fas fa-car"></i> Araçlarım
            </a>
            
            <a href="#" class="sidebar-item" data-target="create-ticket-section">
                <i class="fas fa-plus-circle"></i> Yeni Destek Talebi
            </a>
            <a href="#" class="sidebar-item" data-target="my-tickets-section">
                <i class="fas fa-list-alt"></i> Destek Taleplerim
            </a>

            <a href="#" class="sidebar-item" data-target="punishments">
                <i class="fas fa-gavel"></i> Cezalar
            </a>
            <a href="#" class="sidebar-item" data-target="settings">
                <i class="fas fa-cog"></i> Ayarlar
            </a>

            <div class="logout-btn-container">
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Çıkış Yap
                </a>
            </div>
        </div>

        <div class="main-content">
            <div id="personal-info" class="content-section <?php echo ($_SESSION['active_dashboard_section'] == 'personal-info' ? 'active' : ''); ?>">
                <h3><i class="fas fa-id-card"></i> Kişisel Bilgiler</h3>
                <div class="data-item"><strong><i class="fas fa-user"></i> Ad:</strong> <?php echo htmlspecialchars($playerData['charinfo']['firstname'] ?? 'N/A'); ?></div>
                <div class="data-item"><strong><i class="fas fa-user"></i> Soyad:</strong> <?php echo htmlspecialchars($playerData['charinfo']['lastname'] ?? 'N/A'); ?></div>
                <div class="data-item"><strong><i class="fas fa-fingerprint"></i> Citizen ID:</strong> <?php echo htmlspecialchars($playerData['citizenid']); ?></div>
                <div class="data-item"><strong><i class="fas fa-calendar-alt"></i> Doğum Tarihi:</strong> <?php echo htmlspecialchars($playerData['charinfo']['birthdate'] ?? 'N/A'); ?></div>
                <div class="data-item"><strong><i class="fas fa-venus-mars"></i> Cinsiyet:</strong> <?php echo htmlspecialchars($genderDisplay); ?></div>
                <div class="data-item"><strong><i class="fas fa-ruler-vertical"></i> Boy:</strong> <?php echo htmlspecialchars($playerData['charinfo']['height'] ?? 'N/A'); ?> cm</div>
                <div class="data-item"><strong><i class="fas fa-phone"></i> Telefon Numarası:</strong> <?php echo htmlspecialchars($phoneNumber); ?></div>
                <?php if ($discordIdentifier != 'N/A'): ?>
                    <div class="data-item">
                        <strong><i class="fab fa-discord" style="color: #7289DA;"></i> Discord ID:</strong> <?php echo htmlspecialchars($discordIdentifier); ?>
                    </div>
                <?php endif; ?>
            </div>

            <div id="economy" class="content-section <?php echo ($_SESSION['active_dashboard_section'] == 'economy' ? 'active' : ''); ?>">
                <h3><i class="fas fa-wallet"></i> Ekonomik Durum</h3>
                <div class="data-item"><strong><i class="fas fa-money-bill-wave"></i> Nakit Para:</strong> $<?php echo number_format($playerData['money']['cash'] ?? 0); ?></div>
                <div class="data-item"><strong><i class="fas fa-university"></i> Banka Hesabı:</strong> $<?php echo number_format($playerData['money']['bank'] ?? 0); ?></div>
            </div>

            <div id="job-gang" class="content-section <?php echo ($_SESSION['active_dashboard_section'] == 'job-gang' ? 'active' : ''); ?>">
                <h3><i class="fas fa-briefcase"></i> Meslek & Çete Bilgileri</h3>
                <div class="data-item">
                    <strong><i class="fas fa-user-tie"></i> Meslek:</strong> 
                    <span><?php echo htmlspecialchars($playerData['job']['label'] ?? 'İşsiz'); ?></span> 
                </div>
                <div class="data-item">
                    <strong><i class="fas fa-star"></i> Meslek Seviyesi:</strong> 
                    <span><?php echo htmlspecialchars($playerData['job']['grade']['name'] ?? 'N/A'); ?></span>
                    <?php 
                        $jobLevel = intval($playerData['job']['grade']['level'] ?? 0); 
                        $maxJobLevel = 5; // << Kendi maksimum meslek seviyenizi girin >>
                        $jobProgress = ($maxJobLevel > 0) ? ($jobLevel / $maxJobLevel) * 100 : 0;
                    ?>
                    <div class="progress-container">
                        <div class="progress-bar" style="width: <?php echo $jobProgress; ?>%;"></div>
                    </div>
                </div>
                <div class="data-item">
                    <strong><i class="fas fa-users-cog"></i> Çete:</strong> 
                    <span><?php echo htmlspecialchars($playerData['gang']['label'] ?? 'Yok'); ?></span>
                </div>
                <div class="data-item">
                    <strong><i class="fas fa-medal"></i> Çete Rütbesi:</strong> 
                    <span><?php echo htmlspecialchars($playerData['gang']['grade']['name'] ?? 'N/A'); ?></span>
                    <?php 
                        $gangLevel = intval($playerData['gang']['grade']['level'] ?? 0);
                        $maxGangLevel = 5; // << Kendi maksimum çete seviyenizi girin >>
                        $gangProgress = ($maxGangLevel > 0) ? ($gangLevel / $maxGangLevel) * 100 : 0;
                    ?>
                    <div class="progress-container">
                        <div class="progress-bar" style="width: <?php echo $gangProgress; ?>%;"></div>
                    </div>
                </div>
            </div>

            <div id="vehicles" class="content-section <?php echo ($_SESSION['active_dashboard_section'] == 'vehicles' ? 'active' : ''); ?>">
                <h3><i class="fas fa-car"></i> Araçlarım</h3>
                <?php if (!empty($playerVehicles)): ?>
                    <ul class="vehicle-list">
                        <?php foreach ($playerVehicles as $vehicle): 
                            $modelNameForUrl = strtolower($vehicle[$vehicleModelColumn]);
                            $imageUrl = "https://docs.fivem.net/vehicles/{$modelNameForUrl}.webp";
                        ?>
                            <li class="vehicle-item">
                                <img src="<?php echo htmlspecialchars($imageUrl); ?>" 
                                     alt="<?php echo htmlspecialchars($vehicle[$vehicleModelColumn]); ?> Resmi" 
                                     onerror="this.onerror=null;this.src='images/default_car.png';"
                                     loading="lazy">
                                <span>
                                    <strong>Plaka:</strong> <?php echo htmlspecialchars($vehicle['plate']); ?><br>
                                    <strong>Model:</strong> <?php echo htmlspecialchars($vehicle[$vehicleModelColumn]); ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="no-data">Hiç aracınız bulunmamaktadır.</p>
                <?php endif; ?>
                <p style="font-size: 0.9em; color: var(--text-color-dark); text-align: center; margin-top: 15px;">
                    *Araç resimleri FiveM dökümantasyon sitesinden çekilir. Eğer resimler görünmüyorsa, model adlarının FiveM dökümanlarındaki adlarla eşleştiğinden ve lokalinizdeki `images/default_car.png` dosyasının var olduğundan emin olun.
                </p>
            </div>

            <div id="create-ticket-section" class="content-section <?php echo ($_SESSION['active_dashboard_section'] == 'create-ticket-section' ? 'active' : ''); ?>">
                <h3><i class="fas fa-plus-circle"></i> Yeni Destek Talebi Oluştur</h3>
                <?php if ($ticketSuccessMessage): ?>
                    <div class="ticket-message success"><?php echo $ticketSuccessMessage; ?></div>
                <?php elseif ($ticketErrorMessage): ?>
                    <div class="ticket-message error"><?php echo $ticketErrorMessage; ?></div>
                <?php endif; ?>
                <form method="POST" class="ticket-form">
                    <input type="hidden" name="create_ticket" value="1">
                    <label for="ticket_subject">Konu:</label>
                    <input type="text" id="ticket_subject" name="ticket_subject" required value="<?php echo htmlspecialchars($_POST['ticket_subject'] ?? ''); ?>">

                    <label for="ticket_message">Mesajınız:</label>
                    <textarea id="ticket_message" name="ticket_message" rows="5" required><?php echo htmlspecialchars($_POST['ticket_message'] ?? ''); ?></textarea>

                    <label for="ticket_priority">Öncelik:</label>
                    <select id="ticket_priority" name="ticket_priority">
                        <option value="low">Düşük</option>
                        <option value="medium" selected>Orta</option>
                        <option value="high">Yüksek</option>
                    </select>

                    <button type="submit">Talep Oluştur</button>
                </form>
            </div>

            <div id="my-tickets-section" class="content-section <?php echo ($_SESSION['active_dashboard_section'] == 'my-tickets-section' ? 'active' : ''); ?>">
                <h3><i class="fas fa-list-alt"></i> Destek Taleplerim</h3>
                <?php if (!empty($userTickets)): ?>
                    <table class="tickets-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Konu</th>
                                <th>Durum</th>
                                <th>Öncelik</th>
                                <th>Oluşturulma Tarihi</th>
                                <th>Aksiyon</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($userTickets as $ticket): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($ticket['id']); ?></td>
                                    <td><?php echo htmlspecialchars($ticket['subject']); ?></td>
                                    <td class="ticket-status-<?php echo htmlspecialchars($ticket['status']); ?>">
                                        <?php echo htmlspecialchars(ucfirst($ticket['status'])); ?>
                                    </td>
                                    <td class="ticket-priority-<?php echo htmlspecialchars($ticket['priority']); ?>">
                                        <?php echo htmlspecialchars(ucfirst($ticket['priority'])); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($ticket['created_at']); ?></td>
                                    <td>
                                        <a href="?view_ticket_id=<?php echo htmlspecialchars($ticket['id']); ?>" class="ticket-back-btn" style="background-color: var(--highlight-blue); color:white; margin-right: 5px;">
                                            <i class="fas fa-eye"></i> Görüntüle
                                        </a>
                                        <?php if ($ticket['status'] == 'open' || $ticket['status'] == 'pending'): ?>
                                        <form method="POST" style="display:inline-block;" onsubmit="return confirm('Bu talebi silmek istediğinizden emin misiniz? Bu işlem geri alınamaz!');">
                                            <input type="hidden" name="delete_ticket" value="1">
                                            <input type="hidden" name="ticket_id_to_delete" value="<?php echo htmlspecialchars($ticket['id']); ?>">
                                            <button type="submit" class="ticket-back-btn" style="background-color: #dc3545; color:white; border:none;">
                                                <i class="fas fa-trash-alt"></i> Sil
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="no-data">Henüz oluşturulmuş bir destek talebiniz bulunmamaktadır.</p>
                <?php endif; ?>
            </div>

            <div id="view-ticket-details-section" class="content-section <?php echo ($_SESSION['active_dashboard_section'] == 'view-ticket-details-section' ? 'active' : ''); ?>">
                <?php if ($currentTicketDetails): ?>
                    <div class="ticket-detail-header">
                        <h3><i class="fas fa-ticket-alt"></i> Talep #<?php echo htmlspecialchars($currentTicketDetails['id']); ?>: <?php echo htmlspecialchars($currentTicketDetails['subject']); ?></h3>
                        <a href="#" class="ticket-back-btn" onclick="showSection('my-tickets-section'); return false;"><i class="fas fa-arrow-left"></i> Geri</a>
                    </div>

                    <div class="ticket-meta-info">
                        <div class="ticket-meta-item">Durum: <strong class="ticket-status-<?php echo htmlspecialchars($currentTicketDetails['status']); ?>"><?php echo htmlspecialchars(ucfirst($currentTicketDetails['status'])); ?></strong></div>
                        <div class="ticket-meta-item">Öncelik: <strong class="ticket-priority-<?php echo htmlspecialchars($currentTicketDetails['priority']); ?>"><?php echo htmlspecialchars(ucfirst($currentTicketDetails['priority'])); ?></strong></div>
                        <div class="ticket-meta-item">Oluşturulma: <strong><?php echo htmlspecialchars($currentTicketDetails['created_at']); ?></strong></div>
                    </div>

                    <div class="messages-container">
                        <div class="message-box user">
                            <div class="message-content">
                                <?php echo nl2br(htmlspecialchars($currentTicketDetails['message'])); ?>
                                <div class="message-sender">Siz - <?php echo htmlspecialchars($currentTicketDetails['created_at']); ?></div>
                            </div>
                        </div>

                        <?php foreach ($currentTicketMessages as $message): ?>
                            <div class="message-box <?php echo ($message['is_admin'] ? 'admin' : 'user'); ?>">
                                <div class="message-content">
                                    <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                                    <div class="message-sender">
                                        <?php echo ($message['is_admin'] ? 'Admin' : 'Siz'); ?> - <?php echo htmlspecialchars($message['created_at']); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="reply-form-container">
                        <h3>Yanıtla</h3>
                        <form method="POST">
                            <input type="hidden" name="reply_to_ticket" value="1">
                            <input type="hidden" name="ticket_id_to_reply" value="<?php echo htmlspecialchars($currentTicketDetails['id']); ?>">
                            <textarea name="reply_message" placeholder="Mesajınızı buraya yazın..." required></textarea>
                            <button type="submit">Yanıt Gönder</button>
                        </form>
                    </div>

                <?php else: ?>
                    <p class="no-data">Talep detayları bulunamadı veya bu talebi görüntüleme yetkiniz yok.</p>
                <?php endif; ?>
            </div>

            <div id="punishments" class="content-section <?php echo ($_SESSION['active_dashboard_section'] == 'punishments' ? 'active' : ''); ?>">
                <h3><i class="fas fa-gavel"></i> Cezalar</h3>
                <?php if (!empty($userPunishments)): ?>
                    <table class="tickets-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Tür</th>
                                <th>Sebep</th>
                                <th>Başlangıç</th>
                                <th>Bitiş</th>
                                <th>Durum</th>
                                <th>Veren Admin</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($userPunishments as $punishment): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($punishment['id']); ?></td>
                                    <td><?php echo htmlspecialchars(ucfirst($punishment['type'])); ?></td>
                                    <td><?php echo htmlspecialchars($punishment['reason'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($punishment['start_time']); ?></td>
                                    <td><?php echo htmlspecialchars($punishment['end_time'] ?? 'Sınırsız'); ?></td>
                                    <td>
                                        <?php 
                                            $punishmentStatus = 'Bilinmiyor';
                                            $statusClass = 'ticket-status-unknown'; 
                                            // end_time varsa ve geçmişse
                                            if ($punishment['end_time'] && strtotime($punishment['end_time']) < time()) {
                                                $punishmentStatus = 'Süresi Doldu';
                                                $statusClass = 'ticket-status-closed'; 
                                            } elseif ($punishment['active'] == 1) { // active = 1 ve süresi dolmamışsa
                                                $punishmentStatus = 'Aktif';
                                                $statusClass = 'ticket-status-open'; 
                                            } else { // active = 0 (manuel kaldırılmış)
                                                $punishmentStatus = 'Kaldırıldı/Bitti';
                                                $statusClass = 'ticket-status-closed';
                                            }
                                        ?>
                                        <span class="<?php echo $statusClass; ?>"><?php echo $punishmentStatus; ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($punishment['admin_citizenid'] ?? 'Sunucu'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="no-data">Henüz aktif veya geçmiş bir cezanız bulunmamaktadır.</p>
                <?php endif; ?>
            </div>

            <div id="settings" class="content-section <?php echo ($_SESSION['active_dashboard_section'] == 'settings' ? 'active' : ''); ?>">
                <h3><i class="fas fa-cog"></i> Ayarlar</h3>
                
                <h4>Panel Şifresini Değiştir</h4>
                <form method="POST" class="ticket-form"> <input type="hidden" name="change_password" value="1">
                    <label for="old_password">Mevcut Şifre:</label>
                    <input type="password" id="old_password" name="old_password" required>

                    <label for="new_password">Yeni Şifre:</label>
                    <input type="password" id="new_password" name="new_password" required>

                    <label for="confirm_new_password">Yeni Şifre Tekrar:</label>
                    <input type="password" id="confirm_new_password" name="confirm_new_password" required>

                    <button type="submit">Şifreyi Değiştir</button>
                </form>

                <p style="font-size: 0.9em; color: var(--text-color-dark); margin-top: 20px;">
                    *Bu şifre yalnızca panel girişiniz içindir, oyun içi şifrenizi etkilemez.
                </p>
            </div>

        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const sidebarItems = document.querySelectorAll('.sidebar-item');
            const contentSections = document.querySelectorAll('.content-section');

            // URL'deki success_message ve error_message parametrelerini temizle
            const url = new URL(window.location.href);
            let shouldCleanUrl = false;
            if (url.searchParams.has('success_message') || url.searchParams.has('error_message')) {
                url.searchParams.delete('success_message');
                url.searchParams.delete('error_message');
                shouldCleanUrl = true;
            }
            // view_ticket_id parametresini sadece eğer aktif bölüm view-ticket-details-section DEĞİLSE temizle
            // veya section parametresi ile gelmişsek onu da temizle
            if (url.searchParams.has('view_ticket_id') && window.location.hash !== '') { // # varsa sayfa içi link olabilir
                // Eğer sayfa view_ticket_id ile açıldıysa ve hala o bölümdeyiz, linki temizleme
                // Aksi takdirde, yani başka bir sekmeye geçilmişse veya sayfa yenilendiyse, temizle.
                // Bu kısım PHP tarafından zaten yönetiliyor ($_SESSION['active_dashboard_section'] ile),
                // o yüzden URL'deki parametrenin kalıcı olmaması için temizlik yapılabilir.
                // Ancak view_ticket_id ile direkt URL paylaşılabilir, o yüzden dikkatli olalım.
                // Basitçe: Eğer GET ile geldiysek, sayfa yüklendikten sonra URL'yi temizle.
                if (!url.searchParams.has('section')) { // Eğer section parametresi yoksa ve view_ticket_id varsa
                     // Bu, dışarıdan view_ticket_id ile direkt gelindiği senaryo, bu durumda temizleme.
                     // Aksi halde (PRG sonrası) temizle.
                     // Ancak PRG sonrası zaten section parametresi de ekleniyor.
                }
            }
             // Her halükarda PRG sonrası temizlik yapalım
            if (url.searchParams.has('view_ticket_id') && !url.searchParams.has('section')) {
                // Eğer sadece view_ticket_id ile gelinmişse, o zaman URL'yi temizleme, kalsın.
                // Bu, bir talebin direkt linkini paylaşma senaryosu olabilir.
            } else if (shouldCleanUrl || url.searchParams.has('section')) { // Mesaj varsa veya section değişmişse temizle
                url.searchParams.delete('view_ticket_id');
                url.searchParams.delete('section');
                shouldCleanUrl = true;
            }


            if (shouldCleanUrl) {
                history.replaceState({}, document.title, url.toString());
            }


            // Aktif bölümü PHP'den alıp ayarla
            const initialActiveSectionId = '<?php echo $_SESSION['active_dashboard_section']; ?>';
            if (initialActiveSectionId) {
                showSection(initialActiveSectionId);
            } else if (sidebarItems.length > 0) {
                showSection(sidebarItems[0].dataset.target); // Varsayılan olarak ilk sekmeyi aç
            }


            function showSection(targetId) {
                contentSections.forEach(section => {
                    section.classList.remove('active'); // Tüm bölümleri gizle
                });
                document.getElementById(targetId).classList.add('active'); // Hedef bölümü göster

                sidebarItems.forEach(item => {
                    item.classList.remove('active'); // Tüm sidebar öğelerinden aktif sınıfını kaldır
                });
                // Tıklanan sidebar öğesine aktif sınıfını ekle
                const clickedItem = document.querySelector(`.sidebar-item[data-target="${targetId}"]`);
                if (clickedItem) {
                    clickedItem.classList.add('active');
                }
            }

            sidebarItems.forEach(item => {
                item.addEventListener('click', (e) => {
                    e.preventDefault();
                    const targetId = e.currentTarget.dataset.target;
                    showSection(targetId);
                });
            });

            // Mesajlar kutusunu en aşağı kaydır (eğer mesajlar varsa)
            const messagesContainer = document.querySelector('.messages-container');
            if (messagesContainer) {
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            }
        });
    </script>
</body>
</html>