<?php
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

// لازم يكون عامل login
if (!isset($_SESSION["user_id"])) {
    respond(false, "Unauthorized");
}

$user_id = (int) $_SESSION["user_id"];

// التحقق من الصلاحية
$stmt = $conn->prepare("SELECT can_add_article FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    respond(false, "User not found");
}

$user = $result->fetch_assoc();

if ((int)$user["can_add_article"] !== 1) {
    respond(false, "Not allowed");
}

// البيانات
$tag_id = isset($_POST["tag_id"]) ? (int)$_POST["tag_id"] : 0;

if ($tag_id <= 0) {
    respond(false, "Invalid tag id");
}

// تأكد إن التاج موجود
$stmt = $conn->prepare("SELECT id FROM tags WHERE id = ?");
$stmt->bind_param("i", $tag_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    respond(false, "Tag not found");
}

// 🔥 1) امسح الربط من المقالات
$stmt = $conn->prepare("DELETE FROM article_tags WHERE tag_id = ?");
$stmt->bind_param("i", $tag_id);
$stmt->execute();

// 🔥 2) امسح التاج نفسها
$stmt = $conn->prepare("DELETE FROM tags WHERE id = ?");
$stmt->bind_param("i", $tag_id);

if ($stmt->execute()) {
    respond(true, "Tag deleted successfully");
} else {
    respond(false, "Failed to delete tag");
}
?>