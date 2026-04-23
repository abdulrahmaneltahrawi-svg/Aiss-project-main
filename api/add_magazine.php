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
$title = isset($_POST["title"]) ? trim($_POST["title"]) : "";
$slug  = isset($_POST["slug"]) ? trim($_POST["slug"]) : "";

// Validation
if ($title === "") {
    respond(false, "Title is required");
}

if ($slug === "") {
    respond(false, "Slug is required");
}

// slug validation
if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
    respond(false, "Slug must contain only lowercase letters, numbers, and hyphens");
}

// منع التكرار
$checkSlug = $conn->prepare("SELECT id FROM magazines WHERE slug = ?");
$checkSlug->bind_param("s", $slug);
$checkSlug->execute();
$res = $checkSlug->get_result();

if ($res->num_rows > 0) {
    respond(false, "Slug already exists");
}

// ===== رفع صورة الغلاف =====
if (!isset($_FILES["cover_image"]) || $_FILES["cover_image"]["error"] !== 0) {
    respond(false, "Cover image is required");
}

$imgExt = strtolower(pathinfo($_FILES["cover_image"]["name"], PATHINFO_EXTENSION));
$allowedImages = ["jpg", "jpeg", "png", "webp"];

if (!in_array($imgExt, $allowedImages)) {
    respond(false, "Invalid image type");
}

$imageName = time() . "_" . uniqid() . "." . $imgExt;

$imagesDir = dirname(__DIR__) . "/assets/uploads/magazines/images/";
if (!is_dir($imagesDir)) {
    mkdir($imagesDir, 0777, true);
}

if (!move_uploaded_file($_FILES["cover_image"]["tmp_name"], $imagesDir . $imageName)) {
    respond(false, "Failed to upload cover image");
}

$coverPath = "assets/uploads/magazines/images/" . $imageName;

// ===== رفع PDF =====
if (!isset($_FILES["file"]) || $_FILES["file"]["error"] !== 0) {
    respond(false, "PDF file is required");
}

$fileExt = strtolower(pathinfo($_FILES["file"]["name"], PATHINFO_EXTENSION));

if ($fileExt !== "pdf") {
    respond(false, "Only PDF allowed");
}

$pdfName = time() . "_" . uniqid() . ".pdf";

$filesDir = dirname(__DIR__) . "/assets/uploads/magazines/files/";
if (!is_dir($filesDir)) {
    mkdir($filesDir, 0777, true);
}

if (!move_uploaded_file($_FILES["file"]["tmp_name"], $filesDir . $pdfName)) {
    respond(false, "Failed to upload PDF");
}

$pdfPath = "assets/uploads/magazines/files/" . $pdfName;

// ===== حفظ في الداتا بيز =====
$stmt = $conn->prepare("
    INSERT INTO magazines (title, slug, cover_image, file_path)
    VALUES (?, ?, ?, ?)
");

$stmt->bind_param("ssss", $title, $slug, $coverPath, $pdfPath);

if ($stmt->execute()) {
    respond(true, "Magazine added successfully", [
        "slug" => $slug
    ]);
} else {
    respond(false, "Failed to add magazine");
}