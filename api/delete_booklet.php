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

$booklet_id = isset($_POST["booklet_id"]) ? (int)$_POST["booklet_id"] : 0;

if ($booklet_id <= 0) {
    respond(false, "Invalid booklet id");
}

$stmt = $conn->prepare("
    SELECT cover_image, file_path
    FROM booklets
    WHERE id = ?
    LIMIT 1
");
$stmt->bind_param("i", $booklet_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    respond(false, "Booklet not found");
}

$booklet = $result->fetch_assoc();

$stmt = $conn->prepare("DELETE FROM booklets WHERE id = ?");
$stmt->bind_param("i", $booklet_id);

if ($stmt->execute()) {
    if (!empty($booklet["cover_image"])) {
        $coverPath = dirname(__DIR__) . "/" . $booklet["cover_image"];
        if (file_exists($coverPath)) {
            unlink($coverPath);
        }
    }

    if (!empty($booklet["file_path"])) {
        $filePath = dirname(__DIR__) . "/" . $booklet["file_path"];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    respond(true, "Booklet deleted successfully");
} else {
    respond(false, "Failed to delete booklet");
}
?>