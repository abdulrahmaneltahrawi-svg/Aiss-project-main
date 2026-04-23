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

if (!isset($_SESSION["user_id"])) {
    respond(false, "Unauthorized");
}

$user_id = (int) $_SESSION["user_id"];

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

$magazine_id = isset($_POST["magazine_id"]) ? (int)$_POST["magazine_id"] : 0;

if ($magazine_id <= 0) {
    respond(false, "Invalid magazine id");
}

$stmt = $conn->prepare("
    SELECT cover_image, file_path
    FROM magazines
    WHERE id = ?
    LIMIT 1
");
$stmt->bind_param("i", $magazine_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    respond(false, "Magazine not found");
}

$magazine = $result->fetch_assoc();

$stmt = $conn->prepare("DELETE FROM magazines WHERE id = ?");
$stmt->bind_param("i", $magazine_id);

if ($stmt->execute()) {
    if (!empty($magazine["cover_image"])) {
        $coverPath = dirname(__DIR__) . "/" . $magazine["cover_image"];
        if (file_exists($coverPath)) {
            unlink($coverPath);
        }
    }

    if (!empty($magazine["file_path"])) {
        $filePath = dirname(__DIR__) . "/" . $magazine["file_path"];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    respond(true, "Magazine deleted successfully");
} else {
    respond(false, "Failed to delete magazine");
}
?>