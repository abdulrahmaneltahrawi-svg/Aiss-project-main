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
    respond(false, "Booklet ID is required");
}

$id = (int) $_GET["id"];

if ($id <= 0) {
    respond(false, "Invalid ID");
}

$stmt = $conn->prepare("
    SELECT id, title, slug, cover_image, file_path
    FROM booklets
    WHERE id = ?
");

if (!$stmt) {
    respond(false, "Server error");
}

$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    respond(false, "Booklet not found");
}

$booklet = $result->fetch_assoc();

respond(true, "Booklet fetched successfully", [
    "booklet" => $booklet
]);
?>