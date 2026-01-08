<?php
session_start();

require __DIR__ . '/firestore_rest.php';

/* =========================
   AUTH CHECK
========================= */
if (!isset($_SESSION['stallOwnerId'], $_SESSION['stallOwnerEmail'])) {
    header("Location: login.php");
    exit();
}

$uid   = $_SESSION['stallOwnerId'];
$email = $_SESSION['stallOwnerEmail'];

/* =========================
   GET STALL OWNER
========================= */
$ownerDoc = firestoreGetByField('stall_owners', 'email', $email);

if (!$ownerDoc) {
    die('Stall owner record not found');
}

$ownerFields    = $ownerDoc['fields'];
$ownerName      = $ownerFields['ownerName']['stringValue'] ?? 'Owner';
$approvalStatus = $ownerFields['approvalStatus']['stringValue'] ?? 'Pending';

/* =========================
   GET / CREATE STALL
========================= */
$stallDoc = firestoreGetByField('stall', 'ownerId', $uid);

if (!$stallDoc) {
    firestoreInsert('stall', uniqid(), [
        'ownerId'      => $uid,
        'stallName'    => '',
        'stallAddress' => '',
        'latitude'     => 0.0,
        'longitude'    => 0.0,
        'distanceKm'   => 0.0,
        'updateStatus' => 'Draft'
    ]);

    $stallDoc = firestoreGetByField('stall', 'ownerId', $uid);
}

$f = $stallDoc['fields'];

/* =========================
   SAFE FIELD EXTRACTION
========================= */
$stallName    = $f['stallName']['stringValue'] ?? '';
$stallAddress = $f['stallAddress']['stringValue'] ?? '';

$latitude = $f['latitude']['doubleValue']
         ?? $f['latitude']['integerValue']
         ?? 0.0;

$longitude = $f['longitude']['doubleValue']
          ?? $f['longitude']['integerValue']
          ?? 0.0;

$distanceKm = $f['distanceKm']['doubleValue']
           ?? $f['distanceKm']['integerValue']
           ?? 0.0;

$status = $f['updateStatus']['stringValue'] ?? 'Draft';

/* =========================
   READONLY CONTROL
========================= */
$isReadonly = ($status === 'Pending');

/* =========================
   HANDLE FORM SUBMIT
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // If Pending, just navigate to step 2 for viewing (read-only)
    if ($isReadonly) {
        header("Location: stallownermanagestall2.php");
        exit();
    }

    // If not Pending, save to session and proceed
    $_SESSION['stall_step1'] = [
        'stall_name'    => $_POST['stall_name'],
        'stall_address' => $_POST['stall_address'],
        'latitude'      => (float) $_POST['latitude'],   // 🔥 FORCE DOUBLE
        'longitude'     => (float) $_POST['longitude'],  // 🔥 FORCE DOUBLE
        'distance_km'   => (float) $_POST['distance_km']
    ];

    header("Location: stallownermanagestall2.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>NYAM-NYAM! | Manage Stall</title>

<!-- FONTS -->
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Alfa+Slab+One&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Abhaya+Libre:wght@600&display=swap" rel="stylesheet">

<!-- LEAFLET -->
<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>

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

/* ===== LAYOUT ===== */
.layout {
    display: flex;
    min-height: calc(100vh - 82px);
}

/* ===== SIDEBAR ===== */
.sidebar {
    width: 260px;
    background: #121212;
    padding: 25px;
    transition: 0.3s;
}

.sidebar.hide {
    margin-left: -260px;
}

.profile {
    text-align: center;
    margin-bottom: 30px;
}

.avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: #ff3b3b;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: auto;
    font-size: 22px;
}

.profile p {
    margin-top: 10px;
    font-size: 13px;
}

nav a {
    display: block;
    padding: 10px 0;
    color: #ccc;
    text-decoration: none;
    font-size: 14px;
}

nav a.active {
    color: #fff;
    font-weight: 600;
}

nav a.logout {
    color: #ff3b3b;
    margin-top: 20px;
}

/* ===== CONTENT ===== */
.content {
    flex: 1;
    padding: 40px;
}

/* ===== CARD ===== */
.card {
    background: #1a1a1a;
    border-radius: 16px;
    padding: 25px;
}

/* ===== FORM ===== */
.form-group {
    margin-top: 15px;
}

.form-group label {
    font-size: 13px;
    display: block;
    margin-bottom: 6px;
}

.form-group input {
    width: 100%;
    padding: 12px;
    border-radius: 10px;
    background: #0f0f0f;
    border: 1px solid #333;
    color: white;
}

/* ===== STATUS ===== */
.badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 11px;
}

.badge.Approved { background: #1f8f4a; }
.badge.Draft    { background: #555; }
.badge.Pending  { background: #ff9800; }
.badge.Rejected { background: #e00000; }

/* ===== MAP ===== */
#map {
    height: 400px;
    border-radius: 10px;
    margin-top: 10px;
}

/* ===== BUTTON ===== */
.btn-next {
    margin-top: 25px;
    padding: 12px 20px;
    background: #e00000;
    border: none;
    border-radius: 10px;
    color: white;
    font-size: 14px;
    cursor: pointer;
}

input[readonly] {
    opacity: 0.6;
    cursor: not-allowed;
}
</style>
</head>

<body>

<!-- HEADER -->
<header class="navbar">
    <div class="navbar-left">
        <div class="hamburger" onclick="toggleSidebar()">☰</div>
        <div class="logo">
            <img src="assets/images/logo-icon.png">
            <div class="logo-text">
                <span class="brand-name">NYAM-NYAM!</span>
                <span class="brand-sub">Stall Owner Portal</span>
            </div>
        </div>
    </div>
</header>

<div class="layout">

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
    <div class="profile">
        <div class="avatar">👤</div>
        <p>Hello, <?php echo htmlspecialchars($ownerName); ?>!</p>
    </div>

    <nav>
        <a href="dashboardstallowner.php">Dashboard</a>
        <a class="active">Manage Stall Information</a>
        <a href="#">Manage Menu</a>
        <a href="#">Rate & Review</a>
        <a href="#">Message</a>
        <a href="#">Contact Admin</a>
        <a href="#">Settings</a>
        <a href="logout.php" class="logout">Logout</a>
    </nav>
</aside>

<!-- CONTENT -->
<main class="content">
<form method="POST">
<section class="card">

<h3>Stall Details</h3>
<p>Status:
    <span class="badge <?php echo $status; ?>">
        <?php echo $status; ?>
    </span>
</p>

<div class="form-group">
    <label>Stall Name *</label>
    <input type="text" name="stall_name"
           value="<?php echo htmlspecialchars($stallName); ?>"
           <?php echo $isReadonly ? 'readonly' : ''; ?>>
</div>

<div class="form-group">
    <label>Stall Address *</label>
    <input type="text" name="stall_address"
           value="<?php echo htmlspecialchars($stallAddress); ?>"
           <?php echo $isReadonly ? 'readonly' : ''; ?>>
</div>

<div class="form-group">
    <label>Stall Location (≤ 5 km from UniKL MIIT) *</label>
    <div id="map"></div>
</div>

<input type="hidden" name="latitude" id="latitude" value="<?php echo $latitude; ?>">
<input type="hidden" name="longitude" id="longitude" value="<?php echo $longitude; ?>">
<input type="hidden" name="distance_km" id="distance_km" value="<?php echo $distanceKm; ?>">

<button type="submit" class="btn-next">Next →</button>

</section>
</form>
</main>

</div>

<script>
function toggleSidebar() {
    document.getElementById("sidebar").classList.toggle("hide");
}

document.addEventListener("DOMContentLoaded", function () {

    const unikl = [3.1593343, 101.7013095];
    const existingLat = <?php echo $latitude ?: 'null'; ?>;
    const existingLng = <?php echo $longitude ?: 'null'; ?>;

    const map = L.map('map').setView(
        (existingLat && existingLng) ? [existingLat, existingLng] : unikl,
        14
    );

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png')
        .addTo(map);

    L.marker(unikl).addTo(map).bindPopup("UniKL MIIT");

    L.circle(unikl, {
        radius: 5000,
        color: 'red',
        fillOpacity: 0.15
    }).addTo(map);

    let marker;

    if (existingLat && existingLng) {
        marker = L.marker([existingLat, existingLng]).addTo(map);
    }

    <?php if (!$isReadonly): ?>
    map.on('click', function(e) {
        const distance = map.distance(unikl, e.latlng) / 1000;

        if (distance <= 5) {
            if (marker) map.removeLayer(marker);
            marker = L.marker(e.latlng).addTo(map);

            document.getElementById('latitude').value = e.latlng.lat;
            document.getElementById('longitude').value = e.latlng.lng;
            document.getElementById('distance_km').value = distance.toFixed(2);
        } else {
            alert("Location must be within 5 km of UniKL MIIT.");
        }
    });
    <?php endif; ?>

});
</script>

</body>
</html>