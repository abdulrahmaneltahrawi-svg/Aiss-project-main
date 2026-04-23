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

function saveOptionalImage($fileKey) {
    if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]["error"] === UPLOAD_ERR_NO_FILE) {
        return [true, null];
    }

    if ($_FILES[$fileKey]["error"] !== 0) {
        return [false, "Image upload failed"];
    }

    $ext = strtolower(pathinfo($_FILES[$fileKey]["name"], PATHINFO_EXTENSION));
    $allowed = ["jpg", "jpeg", "png", "webp"];

    if (!in_array($ext, $allowed, true)) {
        return [false, "Invalid image type"];
    }

    $dir = dirname(__DIR__) . "/assets/uploads/magazines/images/";
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $fileName = time() . "_" . uniqid() . "." . $ext;
    $target = $dir . $fileName;

    if (!move_uploaded_file($_FILES[$fileKey]["tmp_name"], $target)) {
        return [false, "Failed to upload cover image"];
    }

    return [true, "assets/uploads/magazines/images/" . $fileName];
}

function saveOptionalPdf($fileKey) {
    if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]["error"] === UPLOAD_ERR_NO_FILE) {
        return [true, null];
    }

    if ($_FILES[$fileKey]["error"] !== 0) {
        return [false, "PDF upload failed"];
    }

    $ext = strtolower(pathinfo($_FILES[$fileKey]["name"], PATHINFO_EXTENSION));

    if ($ext !== "pdf") {
        return [false, "Only PDF allowed"];
    }

    $dir = dirname(__DIR__) . "/assets/uploads/magazines/files/";
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $fileName = time() . "_" . uniqid() . ".pdf";
    $target = $dir . $fileName;

    if (!move_uploaded_file($_FILES[$fileKey]["tmp_name"], $target)) {
        return [false, "Failed to upload PDF"];
    }

    return [true, "assets/uploads/magazines/files/" . $fileName];
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

// البيانات
$magazine_id = isset($_POST["magazine_id"]) ? (int)$_POST["magazine_id"] : 0;
$title = isset($_POST["title"]) ? trim($_POST["title"]) : "";
$slug = isset($_POST["slug"]) ? trim($_POST["slug"]) : "";

if ($magazine_id <= 0) {
    respond(false, "Invalid magazine id");
}

if ($title === "") {
    respond(false, "Title is required");
}

if ($slug === "") {
    respond(false, "Slug is required");
}

if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
    respond(false, "Slug must contain only lowercase letters, numbers, and hyphens");
}

// هات البيانات القديمة
$stmt = $conn->prepare("SELECT cover_image, file_path FROM magazines WHERE id = ?");
$stmt->bind_param("i", $magazine_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    respond(false, "Magazine not found");
}

$oldMagazine = $result->fetch_assoc();

// منع تكرار slug
$stmt = $conn->prepare("SELECT id FROM magazines WHERE slug = ? AND id != ?");
$stmt->bind_param("si", $slug, $magazine_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    respond(false, "Slug already exists");
}

$coverPath = $oldMagazine["cover_image"];
$filePath = $oldMagazine["file_path"];

$newCover = null;
$newFile = null;

// رفع صورة جديدة لو موجودة
list($coverOk, $coverResult) = saveOptionalImage("cover_image");
if (!$coverOk) {
    respond(false, $coverResult);
}
if ($coverResult !== null) {
    $coverPath = $coverResult;
    $newCover = $coverResult;
}

// رفع pdf جديد لو موجود
list($fileOk, $fileResult) = saveOptionalPdf("file");
if (!$fileOk) {
    if ($newCover && file_exists(dirname(__DIR__) . "/" . $newCover)) {
        unlink(dirname(__DIR__) . "/" . $newCover);
    }
    respond(false, $fileResult);
}
if ($fileResult !== null) {
    $filePath = $fileResult;
    $newFile = $fileResult;
}

// تحديث الداتا بيز
$stmt = $conn->prepare("
    UPDATE magazines
    SET title = ?, slug = ?, cover_image = ?, file_path = ?
    WHERE id = ?
");

$stmt->bind_param("ssssi", $title, $slug, $coverPath, $filePath, $magazine_id);

if ($stmt->execute()) {
    // امسح الملفات القديمة لو تم رفع ملفات جديدة
    if ($newCover && !empty($oldMagazine["cover_image"])) {
        $oldCoverFullPath = dirname(__DIR__) . "/" . $oldMagazine["cover_image"];
        if (file_exists($oldCoverFullPath)) {
            unlink($oldCoverFullPath);
        }
    }

    if ($newFile && !empty($oldMagazine["file_path"])) {
        $oldFileFullPath = dirname(__DIR__) . "/" . $oldMagazine["file_path"];
        if (file_exists($oldFileFullPath)) {
            unlink($oldFileFullPath);
        }
    }

    respond(true, "Magazine updated successfully", [
        "magazine_id" => $magazine_id,
        "slug" => $slug,
        "cover_image" => $coverPath,
        "file_path" => $filePath
    ]);
} else {
    if ($newCover && file_exists(dirname(__DIR__) . "/" . $newCover)) {
        unlink(dirname(__DIR__) . "/" . $newCover);
    }

    if ($newFile && file_exists(dirname(__DIR__) . "/" . $newFile)) {
        unlink(dirname(__DIR__) . "/" . $newFile);
    }

    respond(false, "Failed to update magazine");
}
?>