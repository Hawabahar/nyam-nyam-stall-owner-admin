<?php
require __DIR__ . '/firebase_auth.php';
require __DIR__ . '/firestore_rest.php';

$errors = [];
$formData = [];

/* ==============================
   DISTANCE FUNCTION
   ============================== */
function calculateDistanceKm($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371;

    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);

    $a = sin($dLat / 2) ** 2 +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon / 2) ** 2;

    return round($earthRadius * (2 * atan2(sqrt($a), sqrt(1 - $a))), 2);
}

/* ==============================
   HANDLE REGISTER
   ============================== */
if (isset($_POST['register'])) {

    // Store form data for repopulation
    $formData = $_POST;

    /* ===== VALIDATION ===== */
    
    // Owner Name Validation (Required)
    if (empty(trim($_POST['owner_name']))) {
        $errors['owner_name'] = "Business owner name is required";
    }

    // Email Validation (Required)
    if (empty(trim($_POST['email']))) {
        $errors['email'] = "Email is required";
    } else {
        $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = "Invalid email format";
        } else {
            // Check if email already exists in Firebase Auth
            try {
                $existingUser = $auth->getUserByEmail($email);
                if ($existingUser) {
                    $errors['email'] = "This email is already registered";
                }
            } catch (Exception $e) {
                // Email doesn't exist, which is good
            }
        }
    }

    // Phone Number Validation (Required, 10 digits)
    if (empty(trim($_POST['phone']))) {
        $errors['phone'] = "Phone number is required";
    } elseif (!preg_match('/^[0-9]{10}$/', trim($_POST['phone']))) {
        $errors['phone'] = "Phone number must be exactly 10 digits";
    }

    // Password Validation (Required, min 8 chars, 1 uppercase, 1 number)
    if (empty($_POST['password'])) {
        $errors['password'] = "Password is required";
    } elseif (strlen($_POST['password']) < 8) {
        $errors['password'] = "Password must be at least 8 characters";
    } elseif (!preg_match('/[A-Z]/', $_POST['password'])) {
        $errors['password'] = "Password must contain at least 1 uppercase letter";
    } elseif (!preg_match('/[0-9]/', $_POST['password'])) {
        $errors['password'] = "Password must contain at least 1 number";
    }

    // Confirm Password Validation
    if (empty($_POST['confirm_password'])) {
        $errors['confirm_password'] = "Please confirm your password";
    } elseif ($_POST['password'] !== $_POST['confirm_password']) {
        $errors['confirm_password'] = "Passwords do not match";
    }

    // Company Name Validation (Required)
    if (empty(trim($_POST['company_name']))) {
        $errors['company_name'] = "Company name is required";
    }

    // Business Proof Validation (Required)
    if (!isset($_FILES['business_proof']) || $_FILES['business_proof']['error'] !== 0) {
        $errors['business_proof'] = "Business proof document is required";
    }

    // Stall Name Validation (Required)
    if (empty(trim($_POST['stall_name']))) {
        $errors['stall_name'] = "Stall name is required";
    }

    // Stall Address Validation (Required)
    if (empty(trim($_POST['stall_address']))) {
        $errors['stall_address'] = "Stall address is required";
    }

    // Location Validation (Required)
    if (empty($_POST['latitude']) || empty($_POST['longitude'])) {
        $errors['location'] = "Please pin your stall location on the map";
    }

    // If no errors, proceed with registration
    if (empty($errors)) {

        /* ===== FORM DATA ===== */
        $ownerName    = trim($_POST['owner_name']);
        $email        = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
        $password     = $_POST['password'];
        $phone        = trim($_POST['phone']);
        $company      = trim($_POST['company_name']);
        $stallName    = trim($_POST['stall_name']);
        $stallAddress = trim($_POST['stall_address']);
        $latitude     = (float)$_POST['latitude'];
        $longitude    = (float)$_POST['longitude'];

        /* ===== DISTANCE CHECK ===== */
        $uniklLat = 3.1593343;
        $uniklLng = 101.7013095;

        $distanceKm = calculateDistanceKm($uniklLat, $uniklLng, $latitude, $longitude);

        if ($distanceKm > 5) {
            $errors['location'] = "Location must be within 5 km of UniKL MIIT";
        } else {

            /* ===== FILE UPLOAD ===== */
            if (!is_dir('uploads')) {
                mkdir('uploads', 0755, true);
            }

            $fileName   = time() . '_' . basename($_FILES['business_proof']['name']);
            $uploadPath = 'uploads/' . $fileName;
            move_uploaded_file($_FILES['business_proof']['tmp_name'], $uploadPath);

            try {
                /* ==============================
                   1️⃣ FIREBASE AUTH
                   ============================== */
                $user = $auth->createUser([
                    'email'    => $email,
                    'password' => $password,
                ]);

                $uid = $user->uid;

                /* ==============================
                   2️⃣ FIRESTORE: stall_owners
                   ============================== */
                firestoreInsert('stall_owners', $uid, [
                    'ownerName'      => $ownerName,
                    'email'          => $email,
                    'mobilePhone'    => $phone,
                    'companyName'    => $company,
                    'businessProof'  => $uploadPath,
                    'approvalStatus' => 'Pending'
                ]);

                /* ==============================
                   3️⃣ FIRESTORE: stall
                   ============================== */
                firestoreInsert('stall', $uid, [
                    'ownerId'      => $uid,
                    'stallName'    => $stallName,
                    'stallAddress' => $stallAddress,
                    'latitude'     => $latitude,
                    'longitude'    => $longitude,
                    'distanceKm'   => $distanceKm,
                    'updateStatus' => 'Draft'
                ]);

                echo "<script>
                    alert('Registration successful! Please wait for admin approval.');
                    window.location.href = 'login.php';
                </script>";

            } catch (\Kreait\Firebase\Exception\Auth\AuthError $e) {
                $errors['email'] = "Email already registered";
            } catch (Exception $e) {
                echo "<script>alert('Error: ".$e->getMessage()."');</script>";
            }
        }
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>NYAM-NYAM | Stall Owner Registration</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>

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
    padding: 20px 0;
    color: white;
}

.back {
    position: absolute;
    top: 30px;
    left: 40px;
    color: white;
    text-decoration: none;
    font-size: 14px;
}

.register-card {
    background: #161616;
    width: 430px;
    padding: 30px;
    border-radius: 16px;
    box-shadow: 0 0 40px rgba(0,0,0,0.7);
    margin: 60px auto;
}

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
}

.card-header p {
    font-size: 13px;
    color: #bbb;
}

.form-group {
    margin-bottom: 18px;
    position: relative;
}

.form-group label {
    font-size: 13px;
    display: block;
    margin-bottom: 6px;
}

.form-group input,
.form-group input[type="file"] {
    width: 100%;
    padding: 10px;
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

.form-group.has-error input {
    border-color: #ff6b6b;
}

.error-text {
    font-size: 11px;
    color: #ff6b6b;
    margin-top: 4px;
    display: block;
}

.required {
    color: #ff6b6b;
}

/* Password toggle eye icon */
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
    stroke: #999;   /* Same as login.php */
    fill: none;
}

.password-toggle:hover svg {
    stroke: #fff;
}

#map {
    height: 250px;
    border-radius: 10px;
    margin-top: 8px;
    border: 1px solid #333;
}

#map.has-error {
    border-color: #ff6b6b;
}

.btn-register {
    width: 100%;
    margin-top: 20px;
    padding: 12px;
    background: #e00000;
    border: none;
    border-radius: 10px;
    color: white;
    font-size: 15px;
    cursor: pointer;
    font-weight: 600;
}

.btn-register:hover {
    background: #ff0000;
}

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

/* File input styling */
input[type="file"] {
    padding: 8px !important;
}

input[type="file"]::file-selector-button {
    background: #333;
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 5px;
    cursor: pointer;
    margin-right: 10px;
}

input[type="file"]::file-selector-button:hover {
    background: #555;
}

/* Only show red button when there's an error */
.form-group.has-error input[type="file"]::file-selector-button {
    background: #e00000;
}

.form-group.has-error input[type="file"]::file-selector-button:hover {
    background: #ff0000;
}
</style>
</head>

<body>

<a href="starterpage.php" class="back">← Back to Home</a>

<div class="register-card">
    <div class="card-header">
        <img src="assets/images/logo-icon.png" alt="Logo">
        <h1>NYAM-NYAM</h1>
        <p>Stall Owner Registration</p>
    </div>

    <form method="POST" enctype="multipart/form-data">

        <!-- Business Owner Name -->
        <div class="form-group <?php echo isset($errors['owner_name']) ? 'has-error' : ''; ?>">
            <label>Business Owner Name <span class="required">*</span></label>
            <input type="text" name="owner_name" placeholder="Enter your full name" 
                   value="<?php echo htmlspecialchars($formData['owner_name'] ?? ''); ?>">
            <?php if(isset($errors['owner_name'])): ?>
                <small class='error-text'><?php echo $errors['owner_name']; ?></small>
            <?php endif; ?>
        </div>

        <!-- Email -->
        <div class="form-group <?php echo isset($errors['email']) ? 'has-error' : ''; ?>">
            <label>Email <span class="required">*</span></label>
            <input type="email" name="email" placeholder="example@email.com" 
                   value="<?php echo htmlspecialchars($formData['email'] ?? ''); ?>">
            <?php if(isset($errors['email'])): ?>
                <small class='error-text'><?php echo $errors['email']; ?></small>
            <?php endif; ?>
        </div>

        <!-- Phone Number -->
        <div class="form-group <?php echo isset($errors['phone']) ? 'has-error' : ''; ?>">
            <label>Phone Number <span class="required">*</span></label>
            <input type="text" name="phone" placeholder="0123456789 (10 digits)" maxlength="10"
                   value="<?php echo htmlspecialchars($formData['phone'] ?? ''); ?>">
            <?php if(isset($errors['phone'])): ?>
                <small class='error-text'><?php echo $errors['phone']; ?></small>
            <?php endif; ?>
        </div>

        <!-- Password -->
        <div class="form-group <?php echo isset($errors['password']) ? 'has-error' : ''; ?>">
            <label>Password <span class="required">*</span></label>
            <input type="password" id="password" name="password" placeholder="Min 8 chars, 1 uppercase, 1 number">
            <span class="password-toggle" onclick="togglePassword('password', this)" id="togglePassword">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                </svg>
            </span>
            <?php if(isset($errors['password'])): ?>
                <small class='error-text'><?php echo $errors['password']; ?></small>
            <?php endif; ?>
        </div>

        <!-- Confirm Password -->
        <div class="form-group <?php echo isset($errors['confirm_password']) ? 'has-error' : ''; ?>">
            <label>Confirm Password <span class="required">*</span></label>
            <input type="password" id="confirm_password" name="confirm_password" placeholder="Re-enter your password">
            <span class="password-toggle" onclick="togglePassword('confirm_password', this)" id="toggleConfirmPassword">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                </svg>
            </span>
            <?php if(isset($errors['confirm_password'])): ?>
                <small class='error-text'><?php echo $errors['confirm_password']; ?></small>
            <?php endif; ?>
        </div>

        <!-- Company Name -->
        <div class="form-group <?php echo isset($errors['company_name']) ? 'has-error' : ''; ?>">
            <label>Company Name <span class="required">*</span></label>
            <input type="text" name="company_name" placeholder="Enter your company name"
                   value="<?php echo htmlspecialchars($formData['company_name'] ?? ''); ?>">
            <?php if(isset($errors['company_name'])): ?>
                <small class='error-text'><?php echo $errors['company_name']; ?></small>
            <?php endif; ?>
        </div>

        <!-- Business Proof -->
        <div class="form-group <?php echo isset($errors['business_proof']) ? 'has-error' : ''; ?>">
            <label>Business Proof (SSM) <span class="required">*</span></label>
            <input type="file" name="business_proof" accept=".pdf,.jpg,.jpeg,.png">
            <?php if(isset($errors['business_proof'])): ?>
                <small class='error-text'><?php echo $errors['business_proof']; ?></small>
            <?php endif; ?>
        </div>

        <!-- Stall Name -->
        <div class="form-group <?php echo isset($errors['stall_name']) ? 'has-error' : ''; ?>">
            <label>Stall Name <span class="required">*</span></label>
            <input type="text" name="stall_name" placeholder="Enter your stall name"
                   value="<?php echo htmlspecialchars($formData['stall_name'] ?? ''); ?>">
            <?php if(isset($errors['stall_name'])): ?>
                <small class='error-text'><?php echo $errors['stall_name']; ?></small>
            <?php endif; ?>
        </div>

        <!-- Stall Address -->
        <div class="form-group <?php echo isset($errors['stall_address']) ? 'has-error' : ''; ?>">
            <label>Stall Address <span class="required">*</span></label>
            <input type="text" name="stall_address" placeholder="Enter your stall address"
                   value="<?php echo htmlspecialchars($formData['stall_address'] ?? ''); ?>">
            <?php if(isset($errors['stall_address'])): ?>
                <small class='error-text'><?php echo $errors['stall_address']; ?></small>
            <?php endif; ?>
        </div>

        <!-- Map Location -->
        <div class="form-group">
            <label>Select Stall Location (≤ 5 km from UniKL MIIT) <span class="required">*</span></label>
            <div id="map" class="<?php echo isset($errors['location']) ? 'has-error' : ''; ?>"></div>
            <input type="hidden" name="latitude" id="latitude" value="<?php echo htmlspecialchars($formData['latitude'] ?? ''); ?>">
            <input type="hidden" name="longitude" id="longitude" value="<?php echo htmlspecialchars($formData['longitude'] ?? ''); ?>">
            <?php if(isset($errors['location'])): ?>
                <small class='error-text'><?php echo $errors['location']; ?></small>
            <?php endif; ?>
        </div>

        <button class="btn-register" name="register" type="submit">Register</button>

        <div class="form-footer">
            Already have an account?
            <a href="login.php">Login here</a>
        </div>
    </form>
</div>

<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script>
const unikl = [3.1593343, 101.7013095];

const map = L.map('map').setView(unikl, 14);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors'
}).addTo(map);

// UniKL MIIT marker
L.marker(unikl).addTo(map).bindPopup("UniKL MIIT");

// 5km radius circle
L.circle(unikl, {
    radius: 5000,
    color: 'red',
    fillColor: '#f03',
    fillOpacity: 0.15
}).addTo(map);

let marker;

// Restore marker if coordinates exist (form repopulation)
const savedLat = document.getElementById('latitude').value;
const savedLng = document.getElementById('longitude').value;

if (savedLat && savedLng) {
    marker = L.marker([savedLat, savedLng]).addTo(map);
}

// Map click event
map.on('click', function (e) {
    const distance = map.distance(unikl, e.latlng);

    if (distance <= 5000) {
        if (marker) map.removeLayer(marker);

        marker = L.marker(e.latlng).addTo(map).bindPopup("Your Stall Location").openPopup();
        document.getElementById('latitude').value = e.latlng.lat;
        document.getElementById('longitude').value = e.latlng.lng;
        
        // Remove error styling
        document.getElementById('map').classList.remove('has-error');
    } else {
        alert("⚠️ Please select a location within 5 km of UniKL MIIT.");
    }
});

// Password toggle function
function togglePassword(inputId, toggleIcon) {
    const passwordInput = document.getElementById(inputId);
    
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