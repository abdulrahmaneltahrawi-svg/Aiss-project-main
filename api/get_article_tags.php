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

$article_id = (int) $_GET["id"];

if ($article_id <= 0) {
    respond(false, "Invalid article ID");
}

$stmt = $conn->prepare("
    SELECT t.id, t.name, t.slug
    FROM tags t
    INNER JOIN article_tags at ON t.id = at.tag_id
    WHERE at.article_id = ?
    ORDER BY t.name ASC
");

if (!$stmt) {
    respond(false, "Server error");
}

$stmt->bind_param("i", $article_id);
$stmt->execute();
$result = $stmt->get_result();

$tags = [];

while ($row = $result->fetch_assoc()) {
    $tags[] = $row;
}

respond(true, "Article tags fetched successfully", [
    "tags" => $tags
]);
?>