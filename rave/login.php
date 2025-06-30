<?php
require 'db.php'; // session_start(); db.php içinde başlatılıyor
// Eğer session_start() hala db.php'de ise, buraya yazmanıza gerek yok.
// Eğer db.php'den kaldırdıysanız, buraya session_start(); ekleyin.
// Önceki talimatlara göre db.php'den kaldırılmış olmalı, yani buraya session_start(); eklemeliyiz.
session_start();

$response = ['status' => 'error', 'message' => 'Bir hata oluştu.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $response['message'] = 'Kullanıcı adı ve şifre gereklidir.';
    } else {
        // Kullanıcıyı veritabanında bul
        // db.php içindeki $db_username ve $db_password kullanılmalı
        $login_conn = new mysqli($servername, $db_username, $db_password, $dbname); 
        if ($login_conn->connect_error) { 
            $response['message'] = 'Veritabanı bağlantısı başarısız: ' . $login_conn->connect_error;
        } else {
            $stmt = $login_conn->prepare("SELECT * FROM players WHERE name = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                // Hashlenmiş şifreyi doğrula
                if ($user['web_password'] && password_verify($password, $user['web_password'])) {
                    // Session bilgilerini ayarla
                    $_SESSION['loggedin'] = true;
                    $_SESSION['username'] = $user['name'];
                    $_SESSION['citizenid'] = $user['citizenid'];
                    // $_SESSION['job'] = json_decode($user['job'])->name; // Job bilgisi dashboard'da çekildiği için burada saklamaya gerek yok

                    $response['status'] = 'success';
                    $response['message'] = 'Giriş başarılı!';
                    
                    // Giriş başarılı olduğunda buradan herhangi bir HTML çıktısı VERMEMELİ!
                    // Yönlendirmeyi JavaScript yapacak.

                } else {
                    $response['message'] = 'Geçersiz kullanıcı adı veya şifre.';
                }
            } else {
                $response['message'] = 'Geçersiz kullanıcı adı veya şifre.';
            }
            $stmt->close();
            $login_conn->close();
        }
    }
}

header('Content-Type: application/json');
echo json_encode($response);
exit; // Her ihtimale karşı çıkış yap
?>