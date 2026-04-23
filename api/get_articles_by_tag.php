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

if (!isset($_GET["tag"])) {
    respond(false, "Tag is required");
}

$tag = trim($_GET["tag"]);

if ($tag === "") {
    respond(false, "Tag is required");
}

$stmt = $conn->prepare("
    SELECT ua.id, ua.title, ua.slug, ua.cover_image, ua.type
    FROM user_articles ua
    INNER JOIN article_tags at ON ua.id = at.article_id
    INNER JOIN tags t ON at.tag_id = t.id
    WHERE t.slug = ?
    ORDER BY ua.id DESC
");

if (!$stmt) {
    respond(false, "Server error");
}

$stmt->bind_param("s", $tag);
$stmt->execute();
$result = $stmt->get_result();

$articles = [];

while ($row = $result->fetch_assoc()) {
    $articles[] = $row;
}

respond(true, "Articles fetched successfully", [
    "articles" => $articles
]);
?>