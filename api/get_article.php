<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . "/db.php";

function respond($success, $message, $extra = []) {
    echo json_encode(array_merge([
        "success" => $success,
        "message" => $message
    ], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_GET["id"])) {
    respond(false, "Article ID is required");
}

$id = (int) $_GET["id"];

if ($id <= 0) {
    respond(false, "Invalid ID");
}

$stmt = $conn->prepare("
    SELECT title, slug, inner_image, content, type
    FROM user_articles
    WHERE id = ?
");

if (!$stmt) {
    respond(false, "Server error");
}

$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    respond(false, "Article not found");
}

$article = $result->fetch_assoc();

respond(true, "Article fetched successfully", [
    "article" => $article
]);
?>