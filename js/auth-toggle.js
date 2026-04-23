// ملف مستقل للتحكم في تبديل نوافذ تسجيل الدخول وإنشاء الحساب
document.addEventListener('click', function (e) {
    // جلب النوافذ في كل نقرة لضمان وجودها بعد تحميل الهيدر
    const loginModel = document.getElementById('loginModel');
    const signupModel = document.getElementById('signupModel');
    const forgotModel = document.getElementById('forgotModel');
    const verifyCodeModel = document.getElementById('verifyCodeModel');
    const resetPasswordModel = document.getElementById('resetPasswordModel');

    // 1. فتح نافذة تسجيل الدخول الأساسية
    if (e.target.closest('#openModel')) {
        if (loginModel) loginModel.style.display = 'flex';
    }

    // 2. عند الضغط على "أنشئ حسابًا" داخل نافذة الدخول
    if (e.target.closest('#toSignup')) {
        e.preventDefault();
        if (loginModel) loginModel.style.setProperty('display', 'none');
        if (signupModel) signupModel.style.setProperty('display', 'flex');
    }

    // 3. عند الضغط على "سجل الدخول" داخل نافذة التسجيل
    if (e.target.closest('#toLogin')) {
        e.preventDefault();
        if (signupModel) signupModel.style.setProperty('display', 'none');
        if (loginModel) loginModel.style.setProperty('display', 'flex');
    }

    // 4. عند الضغط على "نسيت كلمة المرور" داخل نافذة الدخول
    if (e.target.closest('#toForgot')) {
        e.preventDefault();
        if (loginModel) loginModel.style.display = 'none';
        if (forgotModel) forgotModel.style.display = 'flex';
    }

    // 5. منطق الإغلاق عند الضغط على علامة (X)
    if (e.target.closest('#closeLogin') && loginModel) loginModel.style.display = 'none';
    if (e.target.closest('#closeSignup') && signupModel) signupModel.style.display = 'none';
    if (e.target.closest('#closeForgot') && forgotModel) forgotModel.style.display = 'none';
    if (e.target.closest('#closeVerify') && verifyCodeModel) verifyCodeModel.style.display = 'none';
    if (e.target.closest('#closeReset') && resetPasswordModel) resetPasswordModel.style.display = 'none';

    // 6. الإغلاق عند النقر خارج الصندوق الأبيض
    if (e.target === loginModel) loginModel.style.display = 'none';
    if (e.target === signupModel) signupModel.style.display = 'none';
    if (e.target === forgotModel) forgotModel.style.display = 'none';
    if (e.target === verifyCodeModel) verifyCodeModel.style.display = 'none';
    if (e.target === resetPasswordModel) resetPasswordModel.style.display = 'none';

    // 7. منطق إرسال بيانات تسجيل الدخول إلى PHP
    const loginBtn = e.target.closest('#login-submit');
    if (loginBtn) {
        e.preventDefault();
        const emailEl = document.getElementById('login-email');
        const passwordEl = document.getElementById('login-password');
        const messageBox = document.getElementById('auth-message');
        const email = emailEl ? emailEl.value.trim() : '';
        const password = passwordEl ? passwordEl.value : '';

        if (!email || !password) {
            if (messageBox) {
                messageBox.textContent = "يرجى إدخال البريد الإلكتروني وكلمة المرور";
                messageBox.style.color = "red";
            }
            return;
        }

        const formData = new FormData();
        formData.append('email', email);
        formData.append('password', password);

        fetch('api/user_login.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) throw new Error('خطأ في الاتصال بالسيرفر');
            return response.json();
        })
        .then(data => {
            if (data && data.success) {
                if (messageBox) {
                    messageBox.textContent = "تم تسجيل الدخول بنجاح! جاري التحويل...";
                    messageBox.style.color = "green";
                }
                setTimeout(() => location.reload(), 1500);
            } else {
                if (messageBox) {
                    messageBox.textContent = data.message || "خطأ في البيانات";
                    messageBox.style.color = "red";
                }
            }
        })
        .catch(error => {
            console.error('Login Fetch Error:', error);
            if (messageBox) messageBox.textContent = "حدث خطأ في الاتصال بالسيرفر";
            if (messageBox) messageBox.style.color = "red";
        });
    }

    // 8. منطق إرسال بيانات إنشاء حساب جديد إلى PHP
    const signupBtn = e.target.closest('#signup-submit');
    if (signupBtn) {
        e.preventDefault();
        const nameEl = document.getElementById('signup-name');
        const emailEl = document.getElementById('signup-email');
        const phoneEl = document.getElementById('signup-phone');
        const passEl = document.getElementById('signup-password');
        const messageBox = document.getElementById('signup-auth-message');

        const name = nameEl ? nameEl.value.trim() : '';
        const email = emailEl ? emailEl.value.trim() : '';
        const phone = phoneEl ? phoneEl.value.trim() : '';
        const password = passEl ? passEl.value : '';

        // التحقق من الحقول قبل الإرسال
        if (!name || !email || !phone || !password) {
            if (messageBox) {
                messageBox.textContent = "يرجى ملء جميع الحقول المطلوبة";
                messageBox.style.color = "red";
            }
            return;
        }

        const formData = new FormData();
        formData.append('name', name);
        formData.append('email', email);
        formData.append('phone', phone);
        formData.append('password', password);

        fetch('api/user_register.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.json();
        })
        .then(data => {
            if (data && data.success) {
                if (messageBox) {
                    messageBox.textContent = data.message;
                    messageBox.style.color = "green";
                }
                // تفريغ الحقول عند النجاح
                document.getElementById('signup-name').value = "";
                document.getElementById('signup-email').value = "";
                document.getElementById('signup-phone').value = "";
                document.getElementById('signup-password').value = "";
                
                setTimeout(() => {
                    const toLoginBtn = document.getElementById('toLogin');
                    if (toLoginBtn) toLoginBtn.click();
                }, 2000);
            } else {
                if (messageBox) messageBox.textContent = data.message || "فشل التسجيل";
                if (messageBox) messageBox.style.color = "red";
            }
        })
        .catch(error => {
            console.error('Registration Fetch Error:', error);
            if (messageBox) messageBox.textContent = "حدث خطأ في الاتصال بالسيرفر: " + error.message;
            if (messageBox) messageBox.style.color = "red";
        });
    }

    // 9. منطق إرسال طلب استعادة كلمة المرور
    const forgotBtn = e.target.closest('#forgot-submit');
    if (forgotBtn) {
        e.preventDefault();
        const emailEl = document.getElementById('forgot-email');
        const messageBox = document.getElementById('forgot-auth-message');
        const email = emailEl ? emailEl.value.trim() : '';

        if (!email) {
            if (messageBox) {
                messageBox.textContent = "يرجى إدخال البريد الإلكتروني";
                messageBox.style.color = "red";
            }
            return;
        }

        const formData = new FormData();
        formData.append('email', email);

        fetch('api/user_forgot_password.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (messageBox) {
                    messageBox.textContent = data.message || "تم إرسال رابط الاستعادة إلى بريدك الإلكتروني";
                    messageBox.style.color = "green";
                }
                // التحويل لنافذة الكود بعد ثانية
                setTimeout(() => {
                    if (forgotModel) forgotModel.style.display = 'none';
                    if (verifyCodeModel) verifyCodeModel.style.display = 'flex';
                    // حفظ الإيميل لاستخدامه في المراحل القادمة
                    sessionStorage.setItem('reset_email', email);
                }, 1500);
            } else {
                if (messageBox) {
                    messageBox.textContent = data.message || "البريد الإلكتروني غير موجود";
                    messageBox.style.color = "red";
                }
            }
        })
        .catch(error => {
            console.error('Forgot Password Fetch Error:', error);
            if (messageBox) messageBox.textContent = "حدث خطأ في الاتصال بالسيرفر";
            if (messageBox) messageBox.style.color = "red";
        });
    }

    // 10. منطق التحقق من رمز الـ OTP
    const verifyBtn = e.target.closest('#verify-code-submit');
    if (verifyBtn) {
        e.preventDefault();
        const code = document.getElementById('verify-code').value.trim();
        const messageBox = document.getElementById('verify-auth-message');
        const email = sessionStorage.getItem('reset_email');

        if (!code) {
            if (messageBox) messageBox.textContent = "يرجى إدخال رمز التحقق";
            return;
        }

        const formData = new FormData();
        formData.append('email', email);
        formData.append('code', code);

        fetch('api/user_verify_code.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                if (verifyCodeModel) verifyCodeModel.style.display = 'none';
                if (resetPasswordModel) resetPasswordModel.style.display = 'flex';
            } else {
                if (messageBox) {
                    messageBox.textContent = data.message || "الرمز غير صحيح";
                    messageBox.style.color = "red";
                }
            }
        })
        .catch(error => {
            console.error('Verify Code Fetch Error:', error);
            if (messageBox) messageBox.textContent = "حدث خطأ في الاتصال بالسيرفر";
            if (messageBox) messageBox.style.color = "red";
        });
    }

    // 11. منطق تعيين كلمة المرور الجديدة
    const resetBtn = e.target.closest('#reset-password-submit');
    if (resetBtn) {
        e.preventDefault();
        const pass = document.getElementById('new-password').value;
        const confirmPass = document.getElementById('confirm-password').value;
        const messageBox = document.getElementById('reset-auth-message');
        const email = sessionStorage.getItem('reset_email');

        if (pass !== confirmPass) {
            if (messageBox) messageBox.textContent = "كلمات المرور غير متطابقة";
            return;
        }

        const formData = new FormData();
        formData.append('email', email);
        formData.append('password', pass);

        fetch('api/user_reset_password.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                if (messageBox) {
                    messageBox.textContent = "تم تغيير كلمة المرور بنجاح!";
                    messageBox.style.color = "green";
                }
                setTimeout(() => {
                    if (resetPasswordModel) resetPasswordModel.style.display = 'none';
                    if (loginModel) loginModel.style.display = 'flex';
                    sessionStorage.removeItem('reset_email');
                }, 2000);
            } else {
                if (messageBox) {
                    messageBox.textContent = data.message || "فشل التغيير";
                    messageBox.style.color = "red";
                }
            }
        })
        .catch(error => {
            console.error('Reset Password Fetch Error:', error);
            if (messageBox) messageBox.textContent = "حدث خطأ في الاتصال بالسيرفر";
            if (messageBox) messageBox.style.color = "red";
        });
    }

    // 12. منطق تسجيل الخروج
    const logoutBtn = e.target.closest('#logout-btn');
    if (logoutBtn) {
        e.preventDefault();
        fetch('api/user_logout.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload(); // إعادة تحميل الصفحة لتحديث الواجهة
                }
            })
            .catch(error => console.error('خطأ أثناء تسجيل الخروج:', error));
    }
});