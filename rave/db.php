<?php

$servername = "38.3.137.2"; // VDS'inizin genel IP adresi
$db_username  = "paneluser";
$db_password  = "Enesenes1905";
$dbname = "effronte13_pck";

// Bağlantı oluştur
$conn = new mysqli($servername, $db_username, $db_password, $dbname);

// Bağlantıyı kontrol et
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}
?>