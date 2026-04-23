<?php
ini_set('session.use_strict_mode', 1);

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => false, // خليها true في production مع HTTPS
    'httponly' => true,
    'samesite' => 'Lax'
]);

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

function saveImage($fileKey) {
    if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]["error"] !== 0) {
        return [false, "Image upload failed: " . $fileKey];
    }

    $uploadDir = dirname(__DIR__) . "/assets/uploads/articles/";

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $originalName = basename($_FILES[$fileKey]["name"]);
    $tmpPath = $_FILES[$fileKey]["tmp_name"];
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowedTypes = ["jpg", "jpeg", "png", "webp"];

    if (!in_array($extension, $allowedTypes, true)) {
        return [false, "Invalid image type for " . $fileKey];
    }

    $newFileName = time() . "_" . uniqid() . "_" . preg_replace("/[^A-Za-z0-9_\-\.]/", "_", $originalName);
    $targetPath = $uploadDir . $newFileName;

    if (!move_uploaded_file($tmpPath, $targetPath)) {
        return [false, "Failed to save image: " . $fileKey];
    }

    return [true, "assets/uploads/articles/" . $newFileName];
}

if (!isset($_SESSION["user_id"])) {
    respond(false, "Unauthorized");
}

$user_id = (int) $_SESSION["user_id"];

// التحقق من صلاحية إضافة المقال
$permissionStmt = $conn->prepare("SELECT can_add_article FROM users WHERE id = ?");
if (!$permissionStmt) {
    respond(false, "Server error");
}

$permissionStmt->bind_param("i", $user_id);
$permissionStmt->execute();
$permissionResult = $permissionStmt->get_result();

if ($permissionResult->num_rows === 0) {
    respond(false, "User not found");
}

$user = $permissionResult->fetch_assoc();

if ((int)$user["can_add_article"] !== 1) {
    respond(false, "You are not allowed to add articles");
}

$title = isset($_POST["title"]) ? trim($_POST["title"]) : "";
$slug = isset($_POST["slug"]) ? trim($_POST["slug"]) : "";
$content = isset($_POST["content"]) ? trim($_POST["content"]) : "";
$type = isset($_POST["type"]) ? (int)$_POST["type"] : 0;
$tags = isset($_POST["tags"]) ? json_decode($_POST["tags"], true) : [];


if ($title === "") {
    respond(false, "Title is required");
}

if ($slug === "") {
    respond(false, "Slug is required");
}

if ($content === "") {
    respond(false, "Content is required");
}

if (mb_strlen($title) > 255) {
    respond(false, "Title is too long");
}

if (mb_strlen($slug) > 255) {
    respond(false, "Slug is too long");
}

if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
    respond(false, "Slug must contain only lowercase letters, numbers, and hyphens");
}

if (!in_array($type, [0, 1, 2], true)) {
    respond(false, "Invalid article type");
}

if (!is_array($tags)) {
    respond(false, "Invalid tags format");
}

// التحقق من عدم تكرار slug
$checkSlugStmt = $conn->prepare("SELECT id FROM user_articles WHERE slug = ?");
if (!$checkSlugStmt) {
    respond(false, "Server error");
}

$checkSlugStmt->bind_param("s", $slug);
$checkSlugStmt->execute();
$checkSlugResult = $checkSlugStmt->get_result();

if ($checkSlugResult->num_rows > 0) {
    respond(false, "Slug already exists");
}

list($coverOk, $coverResult) = saveImage("cover_image");
if (!$coverOk) {
    respond(false, $coverResult);
}
$coverImagePath = $coverResult;

list($innerOk, $innerResult) = saveImage("inner_image");
if (!$innerOk) {
    if (file_exists(dirname(__DIR__) . "/" . $coverImagePath)) {
        unlink(dirname(__DIR__) . "/" . $coverImagePath);
    }
    respond(false, $innerResult);
}
$innerImagePath = $innerResult;

$stmt = $conn->prepare("
    INSERT INTO user_articles (user_id, title, slug, cover_image, inner_image, content, type)
    VALUES (?, ?, ?, ?, ?, ?, ?)
");

if (!$stmt) {
    if (file_exists(dirname(__DIR__) . "/" . $coverImagePath)) {
        unlink(dirname(__DIR__) . "/" . $coverImagePath);
    }
    if (file_exists(dirname(__DIR__) . "/" . $innerImagePath)) {
        unlink(dirname(__DIR__) . "/" . $innerImagePath);
    }
    respond(false, "Server error");
}

$stmt->bind_param("isssssi", $user_id, $title, $slug, $coverImagePath, $innerImagePath, $content, $type);

if ($stmt->execute()) {
    $article_id = $stmt->insert_id;

    // ربط المقال بالتاجات
    if (!empty($tags)) {
        $tagStmt = $conn->prepare("INSERT INTO article_tags (article_id, tag_id) VALUES (?, ?)");

        foreach ($tags as $tag_id) {
            $tag_id = (int)$tag_id;

            if ($tag_id > 0) {
                $tagStmt->bind_param("ii", $article_id, $tag_id);
                $tagStmt->execute();
            }
        }
    }

    respond(true, "Article added successfully", [
        "article_id" => $article_id,
        "slug" => $slug,
        "cover_image" => $coverImagePath,
        "inner_image" => $innerImagePath,
        "type" => $type
    ]);
}

 else {
    if (file_exists(dirname(__DIR__) . "/" . $coverImagePath)) {
        unlink(dirname(__DIR__) . "/" . $coverImagePath);
    }
    if (file_exists(dirname(__DIR__) . "/" . $innerImagePath)) {
        unlink(dirname(__DIR__) . "/" . $innerImagePath);
    }
    respond(false, "Failed to add article");
}
?>