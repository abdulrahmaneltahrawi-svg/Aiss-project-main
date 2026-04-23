<?php
header("Content-Type: application/json; charset=UTF-8");
include "db.php"; // تأكد أن ملف db.php يحتوي على متغير الاتصال $conn

function respond($success, $message) {
    echo json_encode(["success" => $success, "message" => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // تنظيف البيانات المستلمة
    $name     = trim($_POST["name"] ?? "");
    $email    = trim($_POST["email"] ?? "");
    $phone    = trim($_POST["phone"] ?? "");
    $password = $_POST["password"] ?? "";

    // 1. التحقق من الحقول الفارغة
    if (empty($name) || empty($email) || empty($password)) {
        respond(false, "يرجى ملء جميع الحقول المطلوبة.");
    }

    // 2. التحقق من صيغة البريد الإلكتروني
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        respond(false, "صيغة البريد الإلكتروني غير صحيحة.");
    }

    // 3. التحقق من وجود البريد الإلكتروني مسبقاً لمنع التكرار
    $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $checkStmt->bind_param("s", $email);
    $checkStmt->execute();
    if ($checkStmt->get_result()->num_rows > 0) {
        respond(false, "هذا البريد الإلكتروني مسجل بالفعل.");
    }
    $checkStmt->close();

    // 4. تشفير كلمة المرور (أمان عالٍ)
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // 5. إدخال البيانات في قاعدة البيانات باستخدام Prepared Statements
    $stmt = $conn->prepare("INSERT INTO users (name, email, phone, password) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
        respond(false, "خطأ في تجهيز الطلب: " . $conn->error);
    }

    $stmt->bind_param("ssss", $name, $email, $phone, $hashedPassword);

    if ($stmt->execute()) {
        respond(true, "تم إنشاء الحساب بنجاح! يمكنك الآن تسجيل الدخول.");
    } else {
        respond(false, "حدث خطأ أثناء حفظ البيانات: " . $stmt->error);
    }

    $stmt->close();
    $conn->close();
} else {
    respond(false, "طلب غير صالح.");
}
?>