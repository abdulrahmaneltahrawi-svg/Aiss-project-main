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

if (!isset($_GET["slug"])) {
    respond(false, "Tag slug is required");
}

$slug = trim($_GET["slug"]);

if ($slug === "") {
    respond(false, "Tag slug is required");
}

$stmt = $conn->prepare("
    SELECT id, name, slug
    FROM tags
    WHERE slug = ?
    LIMIT 1
");

if (!$stmt) {
    respond(false, "Server error");
}

$stmt->bind_param("s", $slug);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    respond(false, "Tag not found");
}

$tag = $result->fetch_assoc();

respond(true, "Tag fetched successfully", [
    "tag" => $tag
]);
?>