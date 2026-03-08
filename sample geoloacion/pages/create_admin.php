<?php
require __DIR__ . '/db.php';

$pdo = db();

// choose an existing barangay id, e.g. 1
$barangayId = 1;

// choose your admin login
$email = 'admin@example.com';
$password = 'Admin12345!'; // change this
$fullName = 'System Administrator';

$passwordHash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("
    INSERT INTO users (
        full_name,
        email,
        password_hash,
        role,
        barangay_id,
        house_number,
        is_email_verified,
        otp_code_hash,
        otp_expires_at,
        is_active
    ) VALUES (
        ?, ?, ?, 'admin', ?, ?, 1, NULL, NULL, 1
    )
");

$stmt->execute([
    $fullName,
    $email,
    $passwordHash,
    $barangayId,
    'Admin Office'
]);

echo 'Admin user created: ' . $email;