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

$result = $conn->query("
    SELECT id, title, slug, cover_image
    FROM booklets
    ORDER BY id DESC
");

if (!$result) {
    respond(false, "Server error");
}

$booklets = [];

while ($row = $result->fetch_assoc()) {
    $booklets[] = $row;
}

respond(true, "Booklets fetched successfully", [
    "booklets" => $booklets
]);
?>