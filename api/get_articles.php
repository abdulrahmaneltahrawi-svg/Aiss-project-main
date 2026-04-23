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

$type = isset($_GET["type"]) ? trim($_GET["type"]) : "";

if ($type !== "") {
    if (!in_array($type, ["0", "1", "2"], true)) {
        respond(false, "Invalid article type");
    }

    $stmt = $conn->prepare("
        SELECT id, title, cover_image, type
        FROM user_articles
        WHERE type = ?
        ORDER BY id DESC
    ");

    if (!$stmt) {
        respond(false, "Server error");
    }

    $typeInt = (int) $type;
    $stmt->bind_param("i", $typeInt);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query("
        SELECT id, title, cover_image, type
        FROM user_articles
        ORDER BY id DESC
    ");

    if (!$result) {
        respond(false, "Server error");
    }
}

$articles = [];

while ($row = $result->fetch_assoc()) {
    $articles[] = $row;
}

respond(true, "Articles fetched successfully", [
    "articles" => $articles
]);
?>