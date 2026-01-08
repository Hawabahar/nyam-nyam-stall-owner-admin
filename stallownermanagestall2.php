<?php
session_start();

require __DIR__ . '/firestore_rest.php';

/* =======================
   AUTH
======================= */
if (!isset($_SESSION['stallOwnerId'])) {
    header("Location: login.php");
    exit();
}

$uid   = $_SESSION['stallOwnerId'];
$step1 = $_SESSION['stall_step1'] ?? null;

/* =======================
   GET STALL
======================= */
$stallDoc = firestoreGetByField('stall', 'ownerId', $uid);

if (!$stallDoc) {
    die("Stall not found");
}

// Extract document ID (needed for update)
$stallDocId = basename($stallDoc['name']);

$f = $stallDoc['fields'];
$status = $f['updateStatus']['stringValue'] ?? 'Draft';
$isPending = ($status === 'Pending');

// If Pending, we can view existing data without session
// If not Pending and no session data, redirect back to step 1
if (!$isPending && !$step1) {
    header("Location: stallownermanagestall.php");
    exit();
}

// Get existing data for form repopulation
$existingCuisineId = $f['cuisineId']['stringValue'] ?? '';
$existingBusinessPhone = $f['businessPhone']['stringValue'] ?? '';
$existingOperatingHours = $f['operatingHours']['stringValue'] ?? '';
$existingDaysOpen = $f['daysOpen']['stringValue'] ?? '';
$existingHalalStatus = $f['halalStatus']['stringValue'] ?? 'No';
$existingVegetarianOption = $f['vegetarianOption']['stringValue'] ?? 'No';

// Parse operating hours
$openTime = '';
$closeTime = '';
if ($existingOperatingHours) {
    $times = explode(' - ', $existingOperatingHours);
    $openTime = $times[0] ?? '';
    $closeTime = $times[1] ?? '';
}

// Parse days open
$daysSelected = $existingDaysOpen ? explode(',', $existingDaysOpen) : [];

/* =======================
   GET CUISINE LIST
======================= */
function firestoreListCollection($collection) {
    $projectId = 'nyam-nyam-77a54';
    $token = getAccessToken();
    
    $url = "https://firestore.googleapis.com/v1/projects/$projectId/databases/(default)/documents/$collection";
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $token",
            "Content-Type: application/json"
        ]
    ]);
    
    $response = json_decode(curl_exec($ch), true);
    curl_close($ch);
    
    return $response['documents'] ?? [];
}

$cuisines = firestoreListCollection('cuisine');

/* =======================
   GET EXISTING STALL PHOTOS
   Note: stall_photos uses stallId (not stallOwnerId) as FK
======================= */
function getStallPhotos($stallDocId) {
    $projectId = 'nyam-nyam-77a54';
    $token = getAccessToken();
    
    $url = "https://firestore.googleapis.com/v1/projects/$projectId/databases/(default)/documents/stall_photos";
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $token",
            "Content-Type: application/json"
        ]
    ]);
    
    $response = json_decode(curl_exec($ch), true);
    curl_close($ch);
    
    $photos = [null, null, null]; // 3 slots
    
    if (isset($response['documents'])) {
        foreach ($response['documents'] as $doc) {
            $fields = $doc['fields'];
            
            // Extract stallId (handle both string and reference types)
            $stallId = '';
            if (isset($fields['stallId']['stringValue'])) {
                $stallId = $fields['stallId']['stringValue'];
            } elseif (isset($fields['stallId']['referenceValue'])) {
                $stallId = basename($fields['stallId']['referenceValue']);
            }
            
            if ($stallId === $stallDocId) {
                // Extract slot number
                $slot = 0;
                if (isset($fields['slot']['integerValue'])) {
                    $slot = (int)$fields['slot']['integerValue'];
                } elseif (isset($fields['slot']['doubleValue'])) {
                    $slot = (int)$fields['slot']['doubleValue'];
                }
                
                // Extract photo path (check both photoPath and photoUrl)
                $photoPath = '';
                if (isset($fields['photoPath']['stringValue'])) {
                    $photoPath = $fields['photoPath']['stringValue'];
                } elseif (isset($fields['photoUrl']['stringValue'])) {
                    $photoPath = $fields['photoUrl']['stringValue'];
                }
                
                if ($slot >= 1 && $slot <= 3 && !empty($photoPath)) {
                    $photos[$slot - 1] = $photoPath;
                }
            }
        }
    }
    
    return $photos;
}

// Use stallDocId instead of uid
$existingPhotos = getStallPhotos($stallDocId);

// Debug: Check if photos were found
// Uncomment to debug:
// echo "<pre>DEBUG: Stall Doc ID: " . $stallDocId . "</pre>";
// echo "<pre>DEBUG: Existing Photos: "; print_r($existingPhotos); echo "</pre>";

/* =======================
   SUBMIT
   Note: Form submission only allowed when NOT Pending
   When Pending: User can only VIEW data, cannot submit
======================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isPending) {

    // Validate days open
    if (!isset($_POST['days_open']) || empty($_POST['days_open'])) {
        echo "<script>alert('Please select at least one operating day');</script>";
    } else {

        $errors = [];

        // Validate required fields
        if (empty($_POST['cuisine_id'])) {
            $errors[] = "Cuisine type is required";
        }
        if (empty($_POST['business_phone'])) {
            $errors[] = "Business phone is required";
        }
        if (empty($_POST['open_time']) || empty($_POST['close_time'])) {
            $errors[] = "Operating hours are required";
        }
        if (!isset($_POST['halal_status'])) {
            $errors[] = "Halal status is required";
        }
        if (!isset($_POST['vegetarian_option'])) {
            $errors[] = "Vegetarian option is required";
        }

        // Check if photos are uploaded or already exist
        $photoCount = 0;
        $hasExistingPhotos = 0;
        
        for ($i = 0; $i < 3; $i++) {
            // Check if new photo uploaded
            if (isset($_FILES['stall_photos']['error'][$i]) && $_FILES['stall_photos']['error'][$i] === UPLOAD_ERR_OK) {
                $photoCount++;
            }
            // Check if photo already exists
            elseif (!empty($existingPhotos[$i])) {
                $hasExistingPhotos++;
            }
        }

        // Need total of 3 photos (new uploads + existing)
        if (($photoCount + $hasExistingPhotos) < 3) {
            $errors[] = "Please upload 3 photos total (you have " . ($photoCount + $hasExistingPhotos) . ")";
        }

        if (!empty($errors)) {
            echo "<script>alert('" . implode("\\n", $errors) . "');</script>";
        } else {

            /* =======================
               PHOTO UPLOAD (3 SLOTS)
               Keep existing photos if not replaced
            ======================= */
            // Create uploads directory if not exists
            if (!is_dir('uploads/stalls')) {
                mkdir('uploads/stalls', 0755, true);
            }

            $photoPaths = [];
            $photoInsertSuccess = 0;
            $photoInsertFailed = 0;

            for ($i = 0; $i < 3; $i++) {
                // Check if new photo uploaded for this slot
                if (isset($_FILES['stall_photos']['tmp_name'][$i]) && 
                    isset($_FILES['stall_photos']['error'][$i]) && 
                    $_FILES['stall_photos']['error'][$i] === UPLOAD_ERR_OK) {
                    
                    // New photo uploaded - save it
                    $tmp  = $_FILES['stall_photos']['tmp_name'][$i];
                    $originalName = $_FILES['stall_photos']['name'][$i];
                    $ext = pathinfo($originalName, PATHINFO_EXTENSION);
                    $filename = $uid . '_' . ($i + 1) . '_' . time() . '.' . $ext;
                    $path = "uploads/stalls/" . $filename;

                    if (move_uploaded_file($tmp, $path)) {
                        // Insert new photo record to Firestore
                        // Use stallId (not stallOwnerId) as FK
                        $photoData = [
                            'stallId'   => $stallDocId,  // Stall document ID (FK)
                            'slot'      => $i + 1,
                            'photoUrl'  => $path,        // Use photoUrl field name
                            'createdAt' => date('c')     // Timestamp
                        ];
                        
                        $photoDocId = uniqid('photo_' . $stallDocId . '_');
                        $result = firestoreInsert('stall_photos', $photoDocId, $photoData);
                        
                        if ($result) {
                            $photoInsertSuccess++;
                            $photoPaths[] = $path;
                            error_log("Photo uploaded successfully: Slot " . ($i + 1) . " - " . $path);
                        } else {
                            $photoInsertFailed++;
                            error_log("ERROR: Failed to insert photo to Firestore: Slot " . ($i + 1));
                        }
                    } else {
                        error_log("ERROR: Failed to move uploaded file for slot " . ($i + 1));
                    }
                } else {
                    // No new photo uploaded - keep existing
                    if (!empty($existingPhotos[$i])) {
                        $photoPaths[] = $existingPhotos[$i];
                    }
                }
            }
            
            // Log upload summary
            error_log("Photo upload summary: Success=$photoInsertSuccess, Failed=$photoInsertFailed");

            /* =======================
               UPDATE STALL DOCUMENT
            ======================= */
            $updateData = [
                'ownerId'           => $uid,
                'stallName'         => $step1['stall_name'],
                'stallAddress'      => $step1['stall_address'],
                'latitude'          => (float) $step1['latitude'],
                'longitude'         => (float) $step1['longitude'],
                'distanceKm'        => (float) $step1['distance_km'],
                'cuisineId'         => $_POST['cuisine_id'],
                'businessPhone'     => $_POST['business_phone'],
                'operatingHours'    => $_POST['open_time'] . " - " . $_POST['close_time'],
                'daysOpen'          => implode(",", $_POST['days_open']),
                'halalStatus'       => $_POST['halal_status'],
                'vegetarianOption'  => $_POST['vegetarian_option'],
                'updateStatus'      => 'Pending'
            ];

            // Update stall document
            $stallUpdateResult = firestoreInsert('stall', $stallDocId, $updateData);

            // Clear session data
            unset($_SESSION['stall_step1']);

            // Show detailed success message
            $message = "Update submitted for admin approval!\\n\\n";
            $message .= "Photos uploaded: " . $photoInsertSuccess . " of 3\\n";
            
            if ($photoInsertFailed > 0) {
                $message .= "Warning: " . $photoInsertFailed . " photo(s) failed to save!";
            }

            echo "<script>
                alert('" . $message . "');
                window.location.href = 'stallownermanagestall.php';
            </script>";
            exit();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>NYAM-NYAM! | Additional Stall Information</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Alfa+Slab+One&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Abhaya+Libre:wght@600&display=swap" rel="stylesheet">

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Poppins', sans-serif;
}

body {
    background: #0e0e0e;
    color: white;
}

/* ===== HEADER ===== */
.navbar {
    display: flex;
    align-items: center;
    padding: 20px 60px;
    background: #161616;
}

.navbar-left {
    display: flex;
    align-items: center;
    gap: 20px;
}

.hamburger {
    font-size: 24px;
    cursor: pointer;
}

.logo {
    display: flex;
    align-items: center;
    gap: 12px;
}

.logo img {
    width: 42px;
    height: 42px;
    border-radius: 8px;
}

.logo-text {
    display: flex;
    flex-direction: column;
}

.brand-name {
    font-family: 'Alfa Slab One', serif;
    font-size: 22px;
}

.brand-sub {
    font-family: 'Abhaya Libre', serif;
    font-size: 12px;
    color: #bbb;
}

.content {
    padding: 40px;
    max-width: 1200px;
    margin: 0 auto;
}

.card {
    background: #1a1a1a;
    border-radius: 16px;
    padding: 30px;
}

.form-group {
    margin-top: 20px;
}

.form-group label {
    font-size: 14px;
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
}

.form-group input,
.form-group select {
    width: 100%;
    padding: 12px;
    border-radius: 10px;
    background: #0f0f0f;
    border: 1px solid #333;
    color: white;
    font-size: 14px;
}

.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: #e00000;
}

.inline {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
}

.badge {
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.badge.Approved { background: #1f8f4a; }
.badge.Draft { background: #555; }
.badge.Pending { background: #ff9800; }
.badge.Rejected { background: #e00000; }

input[readonly], select[disabled] {
    opacity: 0.6;
    cursor: not-allowed;
}

.btn-row {
    display: flex;
    justify-content: space-between;
    margin-top: 30px;
    gap: 15px;
}

.btn-back {
    padding: 12px 30px;
    background: #333;
    border: none;
    border-radius: 10px;
    color: white;
    font-size: 14px;
    cursor: pointer;
    font-weight: 600;
}

.btn-back:hover {
    background: #444;
}

.btn-next {
    padding: 12px 30px;
    background: #e00000;
    border: none;
    border-radius: 10px;
    color: white;
    font-size: 14px;
    cursor: pointer;
    font-weight: 600;
}

.btn-next:hover {
    background: #ff0000;
}

.photo-section {
    margin-top: 30px;
}

.photo-row {
    display: flex;
    gap: 20px;
    margin-top: 15px;
    flex-wrap: wrap;
}

.photo-box {
    width: 200px;
    height: 200px;
    border: 2px dashed #e00000;
    border-radius: 14px;
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    background: #0f0f0f;
}

.photo-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 12px;
}

.photo-label {
    color: #e00000;
    font-size: 13px;
    font-weight: 500;
}

.photo-box input[type="file"] {
    position: absolute;
    inset: 0;
    opacity: 0;
    cursor: pointer;
}

.remove-btn {
    position: absolute;
    top: 8px;
    right: 8px;
    width: 26px;
    height: 26px;
    border-radius: 50%;
    border: none;
    background: rgba(224, 0, 0, 0.9);
    color: white;
    font-size: 16px;
    cursor: pointer;
    display: none;
}

.photo-box:hover .remove-btn {
    display: flex;
    align-items: center;
    justify-content: center;
}

.checkbox-group {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    margin-top: 10px;
}

.checkbox-group label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    cursor: pointer;
}

.checkbox-group input[type="checkbox"] {
    width: auto;
    cursor: pointer;
}

.radio-group {
    display: flex;
    gap: 20px;
    margin-top: 10px;
}

.radio-group label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    cursor: pointer;
}

.radio-group input[type="radio"] {
    width: auto;
    cursor: pointer;
}

h3 {
    font-size: 20px;
    margin-bottom: 10px;
}

.status-row {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 20px;
}
</style>
</head>

<body>

<!-- HEADER -->
<header class="navbar">
    <div class="navbar-left">
        <div class="logo">
            <img src="assets/images/logo-icon.png" alt="Logo">
            <div class="logo-text">
                <span class="brand-name">NYAM-NYAM!</span>
                <span class="brand-sub">Stall Owner Portal</span>
            </div>
        </div>
    </div>
</header>

<main class="content">

<form method="POST" enctype="multipart/form-data">

<section class="card">

    <h3>Additional Stall Information</h3>
    
    <div class="status-row">
        <span>Status:</span>
        <span class="badge <?php echo $status; ?>">
            <?php echo $status; ?>
        </span>
    </div>

 

    <!-- STALL PHOTOS -->
    <div class="photo-section">
        <h3>Stall Photos</h3>
        <p style="color:#aaa;font-size:13px; margin-top: 5px;">Upload exactly 3 photos of your stall</p>

        <div class="photo-row">

            <!-- SLOT 1 -->
            <div class="photo-box">
                <?php if (!empty($existingPhotos[0])): ?>
                    <img id="preview1" src="<?= htmlspecialchars($existingPhotos[0]) ?>" class="photo-img">
                <?php else: ?>
                    <img id="preview1" class="photo-img" style="display:none;">
                    <span id="label1" class="photo-label">📷 Photo 1 (Required)</span>
                <?php endif; ?>

                <?php if (!$isPending): ?>
                    <input type="file" id="file1" name="stall_photos[]" accept="image/*"
                           onchange="previewImage(this, 1)" <?php echo empty($existingPhotos[0]) ? 'required' : ''; ?>>
                    <?php if (!empty($existingPhotos[0])): ?>
                        <button type="button" class="remove-btn" onclick="removeImage(1)">×</button>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- SLOT 2 -->
            <div class="photo-box">
                <?php if (!empty($existingPhotos[1])): ?>
                    <img id="preview2" src="<?= htmlspecialchars($existingPhotos[1]) ?>" class="photo-img">
                <?php else: ?>
                    <img id="preview2" class="photo-img" style="display:none;">
                    <span id="label2" class="photo-label">📷 Photo 2 (Required)</span>
                <?php endif; ?>

                <?php if (!$isPending): ?>
                    <input type="file" id="file2" name="stall_photos[]" accept="image/*"
                           onchange="previewImage(this, 2)" <?php echo empty($existingPhotos[1]) ? 'required' : ''; ?>>
                    <?php if (!empty($existingPhotos[1])): ?>
                        <button type="button" class="remove-btn" onclick="removeImage(2)">×</button>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- SLOT 3 -->
            <div class="photo-box">
                <?php if (!empty($existingPhotos[2])): ?>
                    <img id="preview3" src="<?= htmlspecialchars($existingPhotos[2]) ?>" class="photo-img">
                <?php else: ?>
                    <img id="preview3" class="photo-img" style="display:none;">
                    <span id="label3" class="photo-label">📷 Photo 3 (Required)</span>
                <?php endif; ?>

                <?php if (!$isPending): ?>
                    <input type="file" id="file3" name="stall_photos[]" accept="image/*"
                           onchange="previewImage(this, 3)" <?php echo empty($existingPhotos[2]) ? 'required' : ''; ?>>
                    <?php if (!empty($existingPhotos[2])): ?>
                        <button type="button" class="remove-btn" onclick="removeImage(3)">×</button>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <!-- CUISINE TYPE -->
    <div class="form-group">
        <label>Cuisine Type *</label>
        <select name="cuisine_id" 
                <?php echo $isPending ? 'disabled' : ''; ?>
                required>
            <option value="">-- Select Cuisine Type --</option>
            <?php foreach ($cuisines as $cuisine): 
                $cuisineFields = $cuisine['fields'];
                $cuisineId = basename($cuisine['name']);
                $cuisineName = $cuisineFields['name']['stringValue'] ?? 'Unknown';
            ?>
                <option value="<?php echo htmlspecialchars($cuisineId); ?>"
                        <?php echo ($existingCuisineId === $cuisineId) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($cuisineName); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- BUSINESS PHONE -->
    <div class="form-group">
        <label>Business Phone *</label>
        <input type="text"
               name="business_phone"
               placeholder="e.g., 0123456789"
               value="<?php echo htmlspecialchars($existingBusinessPhone); ?>"
               <?php echo $isPending ? 'readonly' : ''; ?>
               maxlength="11"
               required>
    </div>

    <!-- OPERATING HOURS -->
    <div class="form-group">
        <label>Operating Hours *</label>
        <div class="inline">
            <input type="time" 
                   name="open_time" 
                   value="<?php echo $openTime; ?>" 
                   <?php echo $isPending ? 'readonly' : ''; ?>
                   required
                   style="flex: 1;">
            <span style="display: flex; align-items: center; color: #999;">to</span>
            <input type="time" 
                   name="close_time" 
                   value="<?php echo $closeTime; ?>" 
                   <?php echo $isPending ? 'readonly' : ''; ?>
                   required
                   style="flex: 1;">
        </div>
    </div>

    <!-- DAYS OPEN -->
    <div class="form-group">
        <label>Days Open *</label>
        <div class="checkbox-group">
            <?php
            $days = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"];
            foreach ($days as $d):
            ?>
            <label>
                <input type="checkbox"
                       name="days_open[]"
                       value="<?php echo $d; ?>"
                       <?php echo in_array($d, $daysSelected) ? 'checked' : ''; ?>
                       <?php echo $isPending ? 'disabled' : ''; ?>>
                <?php echo $d; ?>
            </label>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- HALAL STATUS -->
    <div class="form-group">
        <label>Halal Status *</label>
        <div class="radio-group">
            <label>
                <input type="radio" name="halal_status" value="Yes"
                    <?php echo ($existingHalalStatus === 'Yes') ? 'checked' : ''; ?>
                    <?php echo $isPending ? 'disabled' : ''; ?>
                    required> Yes
            </label>
            <label>
                <input type="radio" name="halal_status" value="No"
                    <?php echo ($existingHalalStatus === 'No') ? 'checked' : ''; ?>
                    <?php echo $isPending ? 'disabled' : ''; ?>
                    required> No
            </label>
        </div>
    </div>

    <!-- VEGETARIAN OPTION -->
    <div class="form-group">
        <label>Vegetarian Option *</label>
        <div class="radio-group">
            <label>
                <input type="radio" name="vegetarian_option" value="Yes"
                    <?php echo ($existingVegetarianOption === 'Yes') ? 'checked' : ''; ?>
                    <?php echo $isPending ? 'disabled' : ''; ?>
                    required> Yes
            </label>
            <label>
                <input type="radio" name="vegetarian_option" value="No"
                    <?php echo ($existingVegetarianOption === 'No') ? 'checked' : ''; ?>
                    <?php echo $isPending ? 'disabled' : ''; ?>
                    required> No
            </label>
        </div>
    </div>

    <div class="btn-row">
        <!-- Back button (always shown) -->
        <button type="button" class="btn-back" onclick="goBack()">
            ← Back
        </button>

        <!-- Submit button (hidden when Pending) -->
        <?php if (!$isPending): ?>
        <button type="submit" class="btn-next">
            Submit Update for Approval
        </button>
        <?php endif; ?>
    </div>

</section>

</form>

</main>

<script>
function goBack() {
    window.location.href = "stallownermanagestall.php";
}

function previewImage(input, index) {
    const img   = document.getElementById('preview' + index);
    const label = document.getElementById('label' + index);

    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            img.src = e.target.result;
            img.style.display = 'block';
            if (label) label.style.display = 'none';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function removeImage(index) {
    const img = document.getElementById('preview' + index);
    const label = document.getElementById('label' + index);
    const fileInput = document.getElementById('file' + index);

    img.src = '';
    img.style.display = 'none';
    if (label) label.style.display = 'block';

    fileInput.value = '';
}
</script>

</body>
</html>