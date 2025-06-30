<?php

require 'db.php';

$response = ['status' => 'error', 'message' => 'Bir hata oluştu.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $key = $_POST['key'] ?? '';
    $username = $_POST['username'] ?? ''; // Oyuncu adı
    $password = $_POST['password'] ?? '';

    if (empty($key) || empty($username) || empty($password)) {
        $response['message'] = 'Tüm alanları doldurun.';
    } else {
        // Anahtarın veritabanında olup olmadığını kontrol et
        $stmt = $conn->prepare("SELECT citizenid FROM players WHERE web_key = ? AND name = ?");
        $stmt->bind_param("ss", $key, $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $citizenid = $user['citizenid'];

            // Şifreyi güvenli bir şekilde hash'le
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Oyuncu bilgilerini güncelle: şifreyi ekle ve anahtarı sil
            $update_stmt = $conn->prepare("UPDATE players SET web_password = ?, web_key = NULL WHERE citizenid = ?");
            $update_stmt->bind_param("ss", $hashed_password, $citizenid);

            if ($update_stmt->execute()) {
                $response['status'] = 'success';
                $response['message'] = 'Kayıt başarılı! Şimdi giriş yapabilirsiniz.';
            } else {
                $response['message'] = 'Kayıt sırasında bir hata oluştu.';
            }
            $update_stmt->close();
        } else {
            $response['message'] = 'Geçersiz anahtar veya oyuncu adı.';
        }
        $stmt->close();
    }
}

$conn->close();
header('Content-Type: application/json');
echo json_encode($response);
?>