<?php include 'includes/db.php'; // Maintain connection ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ServiceHub - Expert Vehicle Care</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Keeping your requested color codes */
        :root {
            --primary-orange: #FF7A00;
            --dark-bg: #0D1117;
            --navbar-bg: #161B22;
            --text-main: #FFFFFF;
            --text-muted: #C9D1D9;
        }

        body, html {
            height: 100%;
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--dark-bg);
            color: var(--text-main);
            scroll-behavior: smooth;
        }

        /* Navbar */
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 40px;
            background-color: var(--navbar-bg);
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.5);
        }

        .navbar-brand {
            font-size: 20px;
            font-weight: bold;
            color: #fff;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .navbar-brand span { color: var(--primary-orange); }

        .nav-links { display: flex; gap: 20px; align-items: center; }
        .nav-links a { color: var(--text-main); text-decoration: none; font-size: 14px; }

        .nav-btn {
            background-color: var(--primary-orange);
            color: #fff;
            padding: 8px 20px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            transition: 0.3s;
        }

        /* Hero Section */
        .hero {
            background-image: linear-gradient(rgba(13, 17, 23, 0.7), rgba(13, 17, 23, 0.7)), url('https://images.unsplash.com/photo-1558981403-c5f9899a28bc?q=80&w=2070&auto=format&fit=crop');
            background-size: cover;
            background-position: center;
            height: 80vh;
            display: flex;
            justify-content: center;
            align-items: center;
            text-align: center;
        }

        .highlight { color: var(--primary-orange); }

        /* New Features: Services Section */
        .section-padding { padding: 60px 40px; text-align: center; }
        
        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 40px;
        }

        .service-card {
            background: var(--navbar-bg);
            padding: 30px;
            border-radius: 10px;
            border-bottom: 3px solid transparent;
            transition: 0.3s;
        }

        .service-card:hover {
            border-bottom: 3px solid var(--primary-orange);
            transform: translateY(-5px);
        }

        .service-card i { font-size: 40px; color: var(--primary-orange); margin-bottom: 15px; }

        /* New Features: About Us */
        .about-container {
            display: flex;
            align-items: center;
            gap: 50px;
            max-width: 1200px;
            margin: 0 auto;
            text-align: left;
        }

        .about-image img {
            width: 100%;
            max-width: 500px;
            border-radius: 10px;
            box-shadow: 10px 10px 0px var(--primary-orange);
        }

        /* Footer */
        footer {
            background: #090c10;
            padding: 40px;
            text-align: center;
            border-top: 1px solid #30363d;
        }

        .cta-btn {
            background-color: var(--primary-orange);
            color: #fff;
            padding: 15px 30px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            display: inline-block;
            transition: 0.3s;
        }
    </style>
</head>
<body>

    <nav class="navbar">
        <a href="index.php" class="navbar-brand"><span>🔧</span> ServiceHub</a>
        <div class="nav-links">
            <a href="#services">Services</a>
            <a href="#about">About</a>
            <a href="login.php" class="nav-btn">Login | Sign Up</a>
        </div>
    </nav>

    <header class="hero">
        <div class="hero-content">
            <h1>Expert Care for <br> Your <span class="highlight">DREAM MACHINE</span></h1>
            <p>Top-notch professional auto maintenance. Fast, reliable, and pocket-friendly.</p>
            <a href="login.php" class="cta-btn">Book Appointment Now</a>
        </div>
    </header>

    <section id="services" class="section-padding">
        <h2>Our <span class="highlight">Services</span></h2>
        <p>We provide a wide range of mechanical services for your vehicle.</p>
        <div class="services-grid">
            <div class="service-card">
                <h3>Engine Tune-up</h3>
                <p>Comprehensive diagnostics and performance optimization for your motor.</p>
            </div>
            <div class="service-card">
                <h3>Oil Change</h3>
                <p>Premium oil replacements to keep your engine running smoothly.</p>
            </div>
            <div class="service-card">
                <h3>Tire Services</h3>
                <p>Alignment, pressure checks, and replacements for maximum safety.</p>
            </div>
            <div class="service-card">
                <h3>Brake Repair</h3>
                <p>Expert brake inspection and part replacements you can trust.</p>
            </div>
        </div>
    </section>

    <section id="about" class="section-padding" style="background-color: #161b22;">
        <div class="about-container">
            <div class="about-image">
                <img src="https://images.unsplash.com/photo-1486006396193-471a2abc881a?q=80&w=2000&auto=format&fit=crop" alt="Workshop">
            </div>
            <div class="about-text">
                <h2>Why Choose <span class="highlight">ServiceHub?</span></h2>
                <p>With years of experience in the industry, our certified mechanics use the latest technology to ensure your vehicle receives the best care possible. We prioritize transparency, speed, and quality in every job order.</p>
                <ul style="list-style: none; padding: 0;">
                    <li>✔️ Certified Professional Mechanics</li>
                    <li>✔️ Quality Parts & Equipment</li>
                    <li>✔️ Affordable & Transparent Pricing</li>
                </ul>
            </div>
        </div>
    </section>

    <footer>
        <p>&copy; 2026 ServiceHub - Expert Vehicle Care. All rights reserved.</p>
    </footer>

</body>
</html>