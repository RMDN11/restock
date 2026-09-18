<?php

// Seluruh aplikasi menggunakan waktu Indonesia Barat (WIB).
date_default_timezone_set('Asia/Jakarta');

$host = 'localhost';
$db   = 'wegqxcgv_restock';
$user = 'wegqxcgv_restock';
$pass = 'fikhy6-pybwuk-dicmYf';

try {

    $pdo = new PDO(
        "mysql:host=$host;dbname=$db;charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );

    // Sinkronkan timezone sesi MySQL dengan WIB agar NOW()/CURRENT_TIMESTAMP konsisten.
    $pdo->exec("SET time_zone = '+07:00'");

} catch (PDOException $e) {

    die("Koneksi database gagal: " . $e->getMessage());

}