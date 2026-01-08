<?php
session_start();

require __DIR__ . '/firebase_auth.php';
require __DIR__ . '/firestore_rest.php';

$errors = [];
$formData = [];

/* ============================
   HANDLE LOGIN
============================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Store form data for repopulation
    $formData = $_POST;

    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    /* ===== VALIDATION ===== */
    
    // Email Validation (Required)
    if (empty($email)) {
        $errors['email'] = "Email is required";
    } else {
        // Sanitize and validate email format
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = "Invalid email format";
        }
    }

    // Password Validation (Required)
    if (empty($password)) {
        $errors['password'] = "Password is required";
    }

    // If no validation errors, proceed with login
    if (empty($errors)) {
        try {
            /* ============================
               1️⃣ FIREBASE AUTH LOGIN
            ============================ */
            $signInResult = $auth->signInWithEmailAndPassword($email, $password);
            $uid = $signInResult->firebaseUserId();

            /* ============================
               2️⃣ GET STALL OWNER DATA
            ============================ */
            $stallOwner = firestoreGetByField('stall_owners', 'email', $email);

            if (!$stallOwner) {
                $errors['general'] = "Account not found. Please check your email or register.";
            } else {
                $fields = $stallOwner['fields'];
                $status = $fields['approvalStatus']['stringValue'] ?? 'Pending';

                /* ============================
                   3️⃣ CHECK APPROVAL STATUS
                ============================ */
                if ($status === 'Pending') {
                    $errors['general'] = "Your registration is still pending admin approval. Please wait for confirmation.";
                } elseif ($status === 'Rejected') {
                    $errors['general'] = "Your registration has been rejected. Please contact admin for more information.";
                } else {
                    /* ============================
                       4️⃣ LOGIN SUCCESS
                    ============================ */
                    $_SESSION['stallOwnerId'] = $uid;
                    $_SESSION['stallOwnerEmail'] = $email;

                    header("Location: stallownermanagestall.php");
                    exit();
                }
            }

        } catch (Exception $e) {
            // Check if it's a specific Firebase error
            $errorMessage = $e->getMessage();
            
            if (strpos($errorMessage, 'INVALID_PASSWORD') !== false) {
                $errors['password'] = "Incorrect password";
            } elseif (strpos($errorMessage, 'EMAIL_NOT_FOUND') !== false) {
                $errors['email'] = "No account found with this email";
            } elseif (strpos($errorMessage, 'USER_DISABLED') !== false) {
                $errors['general'] = "Your account has been disabled";
            } elseif (strpos($errorMessage, 'TOO_MANY_ATTEMPTS') !== false) {
                $errors['general'] = "Too many failed login attempts. Please try again later.";
            } else {
                $errors['general'] = "Invalid email or password";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>NYAM-NYAM! | Stall Owner Login</title>

    <!-- Google Font -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            min-height: 100vh;
            background: radial-gradient(circle at center, #c00000, #7a0000, #000);
            display: flex;
            justify-content: center;
            align-items: center;
            color: white;
        }

        /* Back link */
        .back {
            position: absolute;
            top: 30px;
            left: 40px;
            color: #fff;
            text-decoration: none;
            font-size: 14px;
        }

        .back:hover {
            text-decoration: underline;
        }

        /* Card */
        .login-card {
            background: #161616;
            width: 380px;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 0 40px rgba(0,0,0,0.7);
        }

        /* Header */
        .card-header {
            text-align: center;
            margin-bottom: 25px;
        }

        .card-header img {
            width: 45px;
            margin-bottom: 10px;
        }

        .card-header h1 {
            font-size: 22px;
            letter-spacing: 1px;
        }

        .card-header p {
            font-size: 13px;
            color: #bbb;
        }

        /* Form */
        .form-group {
            margin-bottom: 18px;
            position: relative;
        }

        .form-group label {
            font-size: 13px;
            display: block;
            margin-bottom: 6px;
        }

        .form-group input {
            width: 100%;
            padding: 10px 12px;
            background: #111;
            border: 1px solid #333;
            border-radius: 8px;
            color: white;
            font-size: 13px;
        }

        .form-group input::placeholder {
            color: #666;
        }

        .form-group input:focus {
            outline: none;
            border-color: #e00000;
        }

        .password-toggle {
    position: absolute;
    right: 12px;
    top: 35px;
    width: 20px;
    height: 20px;
    cursor: pointer;
}

.password-toggle svg {
    width: 20px;
    height: 20px;
    stroke: #999;   /* 👈 correct */
    fill: none;
}

.password-toggle:hover svg {
    stroke: #fff;
}

        /* Error styling */
        .form-group.has-error input {
            border-color: #ff6b6b;
        }

        .error-text {
            font-size: 11px;
            color: #ff6b6b;
            margin-top: 4px;
            display: block;
        }

        /* General error message at top */
        .general-error {
            background: #2a1414;
            border-left: 4px solid #ff6b6b;
            color: #ff6b6b;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 12px;
            line-height: 1.5;
        }

        .required {
            color: #ff6b6b;
        }

        /* Button */
        .btn-login {
            width: 100%;
            margin-top: 15px;
            padding: 12px;
            background: #e00000;
            border: none;
            border-radius: 10px;
            color: white;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }

        .btn-login:hover {
            background: #ff0000;
        }

        /* Footer */
        .form-footer {
            text-align: center;
            margin-top: 15px;
            font-size: 13px;
        }

        .form-footer a {
            color: #ff3b3b;
            text-decoration: none;
        }

        .form-footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>

<a href="starterpage.php" class="back">← Back to Home</a>

<div class="login-card">
    <div class="card-header">
        <img src="assets/images/logo-icon.png" alt="Logo">
        <h1>NYAM-NYAM!</h1>
        <p>Stall Owner Login</p>
    </div>

    <!-- General error message (approval status, account issues) -->
    <?php if (isset($errors['general'])): ?>
        <div class="general-error">
            <?php echo htmlspecialchars($errors['general']); ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <!-- Email Field -->
        <div class="form-group <?php echo isset($errors['email']) ? 'has-error' : ''; ?>">
            <label>Email <span class="required">*</span></label>
            <input type="email" 
                   name="email" 
                   placeholder="Enter your email" 
                   value="<?php echo htmlspecialchars($formData['email'] ?? ''); ?>">
            <?php if (isset($errors['email'])): ?>
                <small class="error-text"><?php echo htmlspecialchars($errors['email']); ?></small>
            <?php endif; ?>
        </div>

        <!-- Password Field -->
        <div class="form-group <?php echo isset($errors['password']) ? 'has-error' : ''; ?>">
            <label>Password <span class="required">*</span></label>
            <input type="password" 
                   id="password"
                   name="password" 
                   placeholder="Enter your password">
            <span class="password-toggle" onclick="togglePassword()" id="toggleIcon">
                <!-- Eye Open SVG -->
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                </svg>
            </span>
            <?php if (isset($errors['password'])): ?>
                <small class="error-text"><?php echo htmlspecialchars($errors['password']); ?></small>
            <?php endif; ?>
        </div>

        <button type="submit" class="btn-login">Login</button>
    </form>

    <div class="form-footer">
        Don't have an account?
        <a href="register.php">Register here</a>
    </div>
</div>

<script>
function togglePassword() {
    const passwordInput = document.getElementById('password');
    const toggleIcon = document.getElementById('toggleIcon');
    
    if (passwordInput.type === 'password') {
        // Show password - change to eye-off icon
        passwordInput.type = 'text';
        toggleIcon.innerHTML = `
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                <line x1="1" y1="1" x2="23" y2="23"></line>
            </svg>
        `;
    } else {
        // Hide password - change to eye icon
        passwordInput.type = 'password';
        toggleIcon.innerHTML = `
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                <circle cx="12" cy="12" r="3"></circle>
            </svg>
        `;
    }
}
</script>

</body>
</html>