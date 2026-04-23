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
    respond(false, "Unauthorized");
}

$user_id = (int) $_SESSION["user_id"];

$permissionStmt = $conn->prepare("SELECT can_add_article FROM users WHERE id = ?");
if (!$permissionStmt) {
    respond(false, "Server error");
}

$permissionStmt->bind_param("i", $user_id);
$permissionStmt->execute();
$permissionResult = $permissionStmt->get_result();

if ($permissionResult->num_rows === 0) {
    respond(false, "User not found");
}

$user = $permissionResult->fetch_assoc();

if ((int)$user["can_add_article"] !== 1) {
    respond(false, "Not allowed");
}

$article_id = isset($_POST["article_id"]) ? (int)$_POST["article_id"] : 0;

if ($article_id <= 0) {
    respond(false, "Invalid article id");
}

// هات بيانات المقال
$articleStmt = $conn->prepare("
    SELECT cover_image, inner_image
    FROM user_articles
    WHERE id = ?
    LIMIT 1
");

if (!$articleStmt) {
    respond(false, "Server error");
}

$articleStmt->bind_param("i", $article_id);
$articleStmt->execute();
$articleResult = $articleStmt->get_result();

if ($articleResult->num_rows === 0) {
    respond(false, "Article not found");
}

$article = $articleResult->fetch_assoc();

// احذف ربط التاجات أولًا
$deleteTagsStmt = $conn->prepare("DELETE FROM article_tags WHERE article_id = ?");
if (!$deleteTagsStmt) {
    respond(false, "Server error");
}

$deleteTagsStmt->bind_param("i", $article_id);
$deleteTagsStmt->execute();

// احذف المقال
$deleteStmt = $conn->prepare("DELETE FROM user_articles WHERE id = ?");
if (!$deleteStmt) {
    respond(false, "Server error");
}

$deleteStmt->bind_param("i", $article_id);

if ($deleteStmt->execute()) {
    // احذف الصور من السيرفر
    if (!empty($article["cover_image"])) {
        $coverPath = dirname(__DIR__) . "/" . $article["cover_image"];
        if (file_exists($coverPath)) {
            unlink($coverPath);
        }
    }

    if (!empty($article["inner_image"])) {
        $innerPath = dirname(__DIR__) . "/" . $article["inner_image"];
        if (file_exists($innerPath)) {
            unlink($innerPath);
        }
    }

    respond(true, "Article deleted successfully");
} else {
    respond(false, "Failed to delete article");
}
?>