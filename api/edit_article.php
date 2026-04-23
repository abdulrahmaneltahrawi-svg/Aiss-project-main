<?php
ini_set('session.use_strict_mode', 1);

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => false,
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

function saveImageOptional($fileKey) {
    if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]["error"] === UPLOAD_ERR_NO_FILE) {
        return [true, null];
    }

    if ($_FILES[$fileKey]["error"] !== 0) {
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
    respond(false, "You are not allowed to edit articles");
}

$article_id = isset($_POST["article_id"]) ? (int)$_POST["article_id"] : 0;
$title = isset($_POST["title"]) ? trim($_POST["title"]) : "";
$slug = isset($_POST["slug"]) ? trim($_POST["slug"]) : "";
$content = isset($_POST["content"]) ? trim($_POST["content"]) : "";
$type = isset($_POST["type"]) ? (int)$_POST["type"] : 0;
$tags = isset($_POST["tags"]) ? json_decode($_POST["tags"], true) : [];

if ($article_id <= 0) respond(false, "Invalid article id");
if ($title === "") respond(false, "Title is required");
if ($slug === "") respond(false, "Slug is required");
if ($content === "") respond(false, "Content is required");
if (mb_strlen($title) > 255) respond(false, "Title is too long");
if (mb_strlen($slug) > 255) respond(false, "Slug is too long");
if (!preg_match('/^[a-z0-9-]+$/', $slug)) respond(false, "Slug must contain only lowercase letters, numbers, and hyphens");
if (!in_array($type, [0, 1, 2], true)) respond(false, "Invalid article type");
if (!is_array($tags)) respond(false, "Invalid tags format");

$articleStmt = $conn->prepare("
    SELECT id, cover_image, inner_image
    FROM user_articles
    WHERE id = ?
    LIMIT 1
");

if (!$articleStmt) {
    respond(false, "Server error");
}

$articleStmt->bind_param("i", $article_id);
$articleStmt->execute();
$articleResult = $articleStmt->get_result();

if ($articleResult->num_rows === 0) {
    respond(false, "Article not found");
}

$oldArticle = $articleResult->fetch_assoc();

$checkSlugStmt = $conn->prepare("SELECT id FROM user_articles WHERE slug = ? AND id != ?");
if (!$checkSlugStmt) {
    respond(false, "Server error");
}
$checkSlugStmt->bind_param("si", $slug, $article_id);
$checkSlugStmt->execute();
$checkSlugResult = $checkSlugStmt->get_result();

if ($checkSlugResult->num_rows > 0) {
    respond(false, "Slug already exists");
}

$coverImagePath = $oldArticle["cover_image"];
$innerImagePath = $oldArticle["inner_image"];

$newUploadedCover = null;
$newUploadedInner = null;

list($coverOk, $coverResult) = saveImageOptional("cover_image");
if (!$coverOk) {
    respond(false, $coverResult);
}
if ($coverResult !== null) {
    $coverImagePath = $coverResult;
    $newUploadedCover = $coverResult;
}

list($innerOk, $innerResult) = saveImageOptional("inner_image");
if (!$innerOk) {
    if ($newUploadedCover && file_exists(dirname(__DIR__) . "/" . $newUploadedCover)) {
        unlink(dirname(__DIR__) . "/" . $newUploadedCover);
    }
    respond(false, $innerResult);
}
if ($innerResult !== null) {
    $innerImagePath = $innerResult;
    $newUploadedInner = $innerResult;
}

$updateStmt = $conn->prepare("
    UPDATE user_articles
    SET title = ?, slug = ?, cover_image = ?, inner_image = ?, content = ?, type = ?
    WHERE id = ?
");

if (!$updateStmt) {
    if ($newUploadedCover && file_exists(dirname(__DIR__) . "/" . $newUploadedCover)) {
        unlink(dirname(__DIR__) . "/" . $newUploadedCover);
    }
    if ($newUploadedInner && file_exists(dirname(__DIR__) . "/" . $newUploadedInner)) {
        unlink(dirname(__DIR__) . "/" . $newUploadedInner);
    }
    respond(false, "Server error");
}

$updateStmt->bind_param("sssssii", $title, $slug, $coverImagePath, $innerImagePath, $content, $type, $article_id);

if ($updateStmt->execute()) {

    // حذف كل التاجات القديمة المرتبطة بالمقال
    $deleteTagsStmt = $conn->prepare("DELETE FROM article_tags WHERE article_id = ?");
    if (!$deleteTagsStmt) {
        respond(false, "Server error");
    }

    $deleteTagsStmt->bind_param("i", $article_id);
    $deleteTagsStmt->execute();

    // إضافة التاجات الجديدة
    if (!empty($tags)) {
        $insertTagStmt = $conn->prepare("INSERT INTO article_tags (article_id, tag_id) VALUES (?, ?)");
        if (!$insertTagStmt) {
            respond(false, "Server error");
        }

        foreach ($tags as $tag_id) {
            $tag_id = (int)$tag_id;

            if ($tag_id > 0) {
                $insertTagStmt->bind_param("ii", $article_id, $tag_id);
                $insertTagStmt->execute();
            }
        }
    }

    if ($newUploadedCover && !empty($oldArticle["cover_image"])) {
        $oldCoverFullPath = dirname(__DIR__) . "/" . $oldArticle["cover_image"];
        if (file_exists($oldCoverFullPath)) unlink($oldCoverFullPath);
    }

    if ($newUploadedInner && !empty($oldArticle["inner_image"])) {
        $oldInnerFullPath = dirname(__DIR__) . "/" . $oldArticle["inner_image"];
        if (file_exists($oldInnerFullPath)) unlink($oldInnerFullPath);
    }

    respond(true, "Article updated successfully", [
        "article_id" => $article_id,
        "slug" => $slug,
        "cover_image" => $coverImagePath,
        "inner_image" => $innerImagePath,
        "type" => $type
    ]);
} else {
    if ($newUploadedCover && file_exists(dirname(__DIR__) . "/" . $newUploadedCover)) {
        unlink(dirname(__DIR__) . "/" . $newUploadedCover);
    }
    if ($newUploadedInner && file_exists(dirname(__DIR__) . "/" . $newUploadedInner)) {
        unlink(dirname(__DIR__) . "/" . $newUploadedInner);
    }
    respond(false, "Failed to update article");
}
?>