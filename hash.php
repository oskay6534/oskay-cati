<?php
// Yeni şifre belirlemek için bu dosyayı tarayıcıda çalıştır
$sifre = "osman1234"; 
$hash = password_hash($sifre, PASSWORD_BCRYPT);

echo "Aşağıdaki kodu .env dosyanızdaki ADMIN_PASSWORD_HASH kısmına yapıştırın:<br><br>";
echo "<b>" . $hash . "</b>";