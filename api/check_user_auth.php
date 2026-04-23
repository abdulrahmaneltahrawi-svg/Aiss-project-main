<?php
ini_set('session.use_strict_mode', 1);

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_start();

header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . "/db.php";

function respond($success, $message, $extra = []) {
    echo json_encode(array_merge([
        "success" => $success,
        "message" => $message
    ], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_SESSION["user_id"])) {
    respond(true, "Not logged in", [
        "authenticated" => false
    ]);
}

$user_id = (int) $_SESSION["user_id"];

// نجيب صلاحية إضافة المقال
$stmt = $conn->prepare("SELECT name, email, can_add_article FROM users WHERE id = ?");
if (!$stmt) {
    respond(false, "Server error");
}

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    respond(false, "User not found");
}

$user = $result->fetch_assoc();

respond(true, "User authenticated", [
    "authenticated" => true,
    "user" => [
        "id" => $user_id,
        "name" => $user["name"],
        "email" => $user["email"],
        "can_add_article" => (int)$user["can_add_article"]
    ]
]);
?>