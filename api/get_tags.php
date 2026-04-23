<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . "/db.php";

$result = $conn->query("SELECT id, name FROM tags ORDER BY name ASC");

$tags = [];

while ($row = $result->fetch_assoc()) {
    $tags[] = $row;
}

echo json_encode([
    "success" => true,
    "tags" => $tags
], JSON_UNESCAPED_UNICODE);