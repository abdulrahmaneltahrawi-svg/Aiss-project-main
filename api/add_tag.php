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

// لازم يكون عامل login
if (!isset($_SESSION["user_id"])) {
    respond(false, "Unauthorized");
}

$user_id = (int) $_SESSION["user_id"];

// التحقق من الصلاحية
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

// ================= البيانات =================
$name = isset($_POST["name"]) ? trim($_POST["name"]) : "";
$slug = isset($_POST["slug"]) ? trim($_POST["slug"]) : "";

// validation
if ($name === "") {
    respond(false, "Name is required");
}

if ($slug === "") {
    respond(false, "Slug is required");
}

// slug format
if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
    respond(false, "Slug must contain only lowercase letters, numbers, and hyphens");
}

// منع التكرار (name)
$stmt = $conn->prepare("SELECT id FROM tags WHERE name = ?");
$stmt->bind_param("s", $name);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows > 0) {
    respond(false, "Tag name already exists");
}

// منع التكرار (slug)
$stmt = $conn->prepare("SELECT id FROM tags WHERE slug = ?");
$stmt->bind_param("s", $slug);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows > 0) {
    respond(false, "Slug already exists");
}

// ===== إضافة التاج =====
$stmt = $conn->prepare("
    INSERT INTO tags (name, slug)
    VALUES (?, ?)
");

$stmt->bind_param("ss", $name, $slug);

if ($stmt->execute()) {
    respond(true, "Tag added successfully", [
        "tag_id" => $stmt->insert_id
    ]);
} else {
    respond(false, "Failed to add tag");
}