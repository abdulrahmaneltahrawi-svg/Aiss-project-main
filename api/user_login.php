<?php
// إعدادات السيشن الآمنة
ini_set('session.use_strict_mode', 1);

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => false, // خليها true لما تستخدم HTTPS
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_start();

header("Content-Type: application/json; charset=UTF-8");
include "db.php";

function respond($success, $message, $extra = []) {
    echo json_encode(array_merge([
        "success" => $success,
        "message" => $message
    ], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

function clean_input($data) {
    return trim($data);
}

$email = isset($_POST["email"]) ? clean_input($_POST["email"]) : "";
$password = isset($_POST["password"]) ? $_POST["password"] : "";

// Validation
if ($email === "") {
    respond(false, "Email is required");
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(false, "Invalid email format");
}

if ($password === "") {
    respond(false, "Password is required");
}

// استعلام المستخدم
$stmt = $conn->prepare("SELECT id, name, email, password FROM users WHERE email = ?");
if (!$stmt) {
    respond(false, "Server error");
}

$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

// ⚠️ رسالة موحدة (ما نكشفش إذا الإيميل موجود)
if ($result->num_rows === 0) {
    respond(false, "Invalid email or password");
}

$user = $result->fetch_assoc();

// تحقق من الباسورد
if (!password_verify($password, $user["password"])) {
    respond(false, "Invalid email or password");
}

// 🔥 حماية من Session Fixation
session_regenerate_id(true);

// تخزين بيانات المستخدم
$_SESSION["user_id"] = $user["id"];
$_SESSION["user_name"] = $user["name"];
$_SESSION["user_email"] = $user["email"];

respond(true, "Login successful", [
    "user" => [
        "id" => $user["id"],
        "name" => $user["name"],
        "email" => $user["email"]
    ]
]);
?>