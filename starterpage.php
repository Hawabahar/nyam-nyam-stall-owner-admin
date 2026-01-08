<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NYAM-NYAM! | Stall Owner Portal</title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background: #000;
            color: white;
            overflow-x: hidden;
        }

        /* Navigation Bar */
        .navbar {
            background: rgba(0, 0, 0, 0.95);
            padding: 15px 50px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
            border-bottom: 2px solid #e00000;
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .navbar-brand img {
            width: 35px;
            height: 35px;
        }

        .navbar-brand span {
            font-size: 20px;
            font-weight: 700;
            color: white;
            letter-spacing: 1px;
        }

        .nav-links {
            display: flex;
            gap: 30px;
            list-style: none;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s;
        }

        .nav-links a:hover {
            color: #e00000;
        }

        .nav-buttons {
            display: flex;
            gap: 15px;
        }

        .btn-login, .btn-register {
            padding: 8px 20px;
            border-radius: 20px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-login {
            background: transparent;
            border: 2px solid #e00000;
            color: white;
        }

        .btn-login:hover {
            background: #e00000;
        }

        .btn-register {
            background: #e00000;
            border: 2px solid #e00000;
            color: white;
        }

        .btn-register:hover {
            background: #ff0000;
            border-color: #ff0000;
        }

        /* Hero Section */
        .hero {
            position: relative;
            padding: 0;
            text-align: center;
            min-height: 100vh; /* Full screen height */
            display: flex;
            align-items: center;
            justify-content: center;
            background-image: url('assets/images/hero-collage.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed; /* Parallax effect */
        }

        /* Dark overlay on top of image */
        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5); /* Lighter overlay for full screen */
            z-index: 1;
        }

        .hero-content {
            position: relative;
            z-index: 2;
            max-width: 900px;
            padding: 0 20px;
        }

        .hero-content {
            max-width: 800px;
            margin: 0 auto;
        }

        .hero h1 {
            font-size: 48px;
            font-weight: 800;
            margin-bottom: 20px;
            line-height: 1.2;
        }

        .hero p {
            font-size: 18px;
            color: #ccc;
            margin-bottom: 40px;
            line-height: 1.6;
        }

        .hero-cta {
            padding: 15px 40px;
            background: #e00000;
            color: white;
            text-decoration: none;
            border-radius: 30px;
            font-size: 16px;
            font-weight: 600;
            display: inline-block;
            transition: all 0.3s;
        }

        .hero-cta:hover {
            background: #ff0000;
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(224, 0, 0, 0.4);
        }

        /* Features Section */
        .features-title {
            text-align: center;
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 50px;
            padding: 0 50px;
        }

        .features {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
            padding: 50px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .feature-card {
            background: #e00000;
            border-radius: 15px;
            padding: 40px 30px;
            text-align: center;
            transition: all 0.3s;
            cursor: pointer;
        }

        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(224, 0, 0, 0.3);
        }

        .feature-icon {
            width: 60px;
            height: 60px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 30px;
        }

        .feature-card h3 {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 15px;
        }

        .feature-card p {
            font-size: 14px;
            line-height: 1.6;
            color: rgba(255, 255, 255, 0.9);
        }

        /* About Section */
        .about {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 50px;
            padding: 80px 50px;
            max-width: 1200px;
            margin: 0 auto;
            align-items: center;
        }

        .about-content h2 {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 20px;
        }

        .about-content p {
            font-size: 15px;
            line-height: 1.8;
            color: #ccc;
            margin-bottom: 15px;
        }

        .about-image {
            border-radius: 15px;
            overflow: hidden;
            height: 400px;
            background: #1a1a1a;
        }

        /* How It Works */
        .how-it-works {
            padding: 80px 50px;
            background: #0a0a0a;
        }

        .how-it-works h2 {
            text-align: center;
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 60px;
        }

        .steps {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 30px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .step {
            text-align: center;
        }

        .step-number {
            width: 60px;
            height: 60px;
            background: #e00000;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: 700;
            margin: 0 auto 20px;
        }

        .step h3 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .step p {
            font-size: 13px;
            color: #999;
            line-height: 1.6;
        }

        /* Contact Section */
        .contact {
            padding: 80px 50px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .contact h2 {
            text-align: center;
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 50px;
        }

        .contact-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 30px;
        }

        .contact-item {
            background: #1a1a1a;
            padding: 30px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .contact-icon {
            width: 50px;
            height: 50px;
            background: #e00000;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            flex-shrink: 0;
        }

        .contact-info h3 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .contact-info p {
            font-size: 14px;
            color: #999;
        }

        /* Mobile App Section */
        .mobile-app {
            padding: 80px 50px;
            background: #0a0a0a;
            text-align: center;
        }

        .mobile-app h2 {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 20px;
        }

        .mobile-app p {
            font-size: 16px;
            color: #ccc;
            margin-bottom: 30px;
        }

        .app-stores {
            display: flex;
            gap: 20px;
            justify-content: center;
        }

        .store-badge {
            width: 150px;
            height: 50px;
            background: #1a1a1a;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            color: white;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .store-badge:hover {
            background: #e00000;
            transform: translateY(-3px);
        }

        /* Footer */
        .footer {
            background: #0a0a0a;
            padding: 30px 50px;
            text-align: center;
            border-top: 2px solid #e00000;
        }

        .footer p {
            color: #666;
            font-size: 14px;
        }

        .footer-links {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-top: 20px;
        }

        .footer-links a {
            color: #999;
            text-decoration: none;
            font-size: 13px;
            transition: color 0.3s;
        }

        .footer-links a:hover {
            color: #e00000;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .navbar {
                padding: 15px 20px;
            }

            .nav-links {
                display: none;
            }

            .hero h1 {
                font-size: 32px;
            }

            .hero p {
                font-size: 16px;
            }

            .food-grid,
            .features,
            .steps,
            .contact-grid {
                grid-template-columns: 1fr;
            }

            .about {
                grid-template-columns: 1fr;
            }

            .features,
            .contact,
            .how-it-works {
                padding: 50px 20px;
            }
        }
    </style>
</head>
<body>

    <!-- Navigation Bar -->
    <nav class="navbar">
        <div class="navbar-brand">
            <img src="assets/images/logo-icon.png" alt="NYAM-NYAM Logo">
            <span>NYAM-NYAM!</span>
        </div>
        <ul class="nav-links">
            <li><a href="#home">Home</a></li>
            <li><a href="#about">About</a></li>
            <li><a href="#contact">Contact</a></li>
        </ul>
        <div class="nav-buttons">
            <a href="login.php" class="btn-login">Login</a>
            <a href="register.php" class="btn-register">Register</a>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero" id="home">
        <div class="hero-content">
            <h1>Own a Stall Near UniKL MIIT?</h1>
            <p>Let thousands of students and staff discover your food stall! Promote your menu, get orders and connect with hungry customers!</p>
            <a href="register.php" class="hero-cta">Register Your Stall Now</a>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features-section">
        <h2 class="features-title">Why You Should Join Us?</h2>
        <div class="features">
            <div class="feature-card">
                <div class="feature-icon">🍽️</div>
                <h3>Discover Your Menu</h3>
                <p>Upload and display all photos of your dishes and let students fall in love with your stall!</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">👥</div>
                <h3>Connect with Customers</h3>
                <p>Get directly in touch with students, respond to their reviews, and maintain your reputation!</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">📊</div>
                <h3>Grow Your Business</h3>
                <p>Reach more customers, boost your sales, and maximize your stall's performance!</p>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section class="about" id="about">
        <div class="about-content">
            <h2>About Stall Owner Portal</h2>
            <p>This portal is built to make life easier for stall owners around UniKL MIIT. We aim to bridge the gap between you and your customers, allowing you to showcase your amazing food to thousands of hungry students and staff.</p>
            <p>By joining our platform, you are not just reaching more customers, but you are also joining a community that loves to support local businesses and enjoys tasty, authentic meals every day!</p>
            <p>Register today and let your stall shine in the community!</p>
        </div>
        <div class="about-image">
            <img src="assets/images/image1.jpg" alt="About Nyam-Nyam">
        </div>
    </section>

    <!-- How It Works -->
    <section class="how-it-works">
        <h2>How It Works</h2>
        <div class="steps">
            <div class="step">
                <div class="step-number">1</div>
                <h3>Sign up with your business details and verification documents</h3>
                <p>Register your stall with required business proof</p>
            </div>
            
            <div class="step">
                <div class="step-number">2</div>
                <h3>Add your menu, photos, operating hours, and location</h3>
                <p>Showcase your delicious food to students</p>
            </div>
            
            <div class="step">
                <div class="step-number">3</div>
                <h3>Your stall will goes live for students to discover</h3>
                <p>Wait for admin approval to go live</p>
            </div>
            
            <div class="step">
                <div class="step-number">4</div>
                <h3>Engage with customers and watch your business thrive</h3>
                <p>Respond to reviews and grow your business</p>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section class="contact" id="contact">
        <h2>Contact Us</h2>
        <div class="contact-grid">
            <div class="contact-item">
                <div class="contact-icon">📧</div>
                <div class="contact-info">
                    <h3>Email</h3>
                    <p>food.transit@s.unikl.edu.my</p>
                </div>
            </div>
            
            <div class="contact-item">
                <div class="contact-icon">📞</div>
                <div class="contact-info">
                    <h3>Phone</h3>
                    <p>012-3456789</p>
                </div>
            </div>
            
            <div class="contact-item">
                <div class="contact-icon">📍</div>
                <div class="contact-info">
                    <h3>Address</h3>
                    <p>UniKL MIIT, Jalan Gombak</p>
                </div>
            </div>
            
            <div class="contact-item">
                <div class="contact-icon">🕐</div>
                <div class="contact-info">
                    <h3>Open Hours</h3>
                    <p>Mon-Fri: 8:00 AM - 5:00 PM</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Mobile App Section -->
    <section class="mobile-app">
        <h2>Mobile Application</h2>
        <p>Users can access NYAM-NYAM! seamlessly from both our website and mobile application for Android and iOS. Download our app from Google Play Store!</p>
        <div class="app-stores">
            <a href="#" class="store-badge">📱 Google Play</a>
            <a href="#" class="store-badge">🍎 App Store</a>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <p>&copy; 2025 NYAM-NYAM! Food Transit System | UniKL MIIT</p>
        <div class="footer-links">
            <a href="#">Privacy Policy</a>
            <a href="#">Terms of Service</a>
            <a href="#">Support</a>
        </div>
    </footer>

</body>
</html>