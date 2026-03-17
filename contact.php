<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us | ARTS GYM</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        /* ===== CSS VARIABLES ===== */
        :root {
            --primary-red: #e63946;
            --dark-red: #9d0208;
            --accent-red: #ff4d5a;
            --dark-bg: #0a0a0a;
            --darker-bg: #050505;
            --card-bg: #111111;
            --text-white: #ffffff;
            --text-gray: #b0b0b0;
            --transition: all 0.3s ease;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--dark-bg);
            color: var(--text-white);
            overflow-x: hidden;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Oswald', sans-serif;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        a { text-decoration: none; transition: var(--transition); }

        /* ===== NAVBAR ===== */
        .navbar {
            background: rgba(10, 10, 10, 0.95);
            backdrop-filter: blur(20px);
            padding: 15px 0;
            box-shadow: 0 5px 30px rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }

        .navbar-brand {
            font-family: 'Oswald', sans-serif;
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--text-white) !important;
            letter-spacing: 3px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .navbar-brand i { color: var(--primary-red); font-size: 2rem; }

        .nav-link {
            color: var(--text-white) !important;
            font-weight: 500;
            padding: 10px 20px !important;
            font-size: 0.95rem;
            letter-spacing: 1px;
        }

        .nav-link:hover { color: var(--primary-red) !important; }

        .btn-contact {
            background: var(--primary-red);
            color: var(--text-white) !important;
            padding: 12px 28px !important;
            border-radius: 50px;
            font-weight: 600;
            letter-spacing: 1px;
            border: 2px solid var(--primary-red);
            margin-left: 15px;
        }

        /* ===== PAGE HERO ===== */
        .contact-hero {
            padding: 140px 0 40px;
            text-align: center;
            background: linear-gradient(180deg, rgba(5, 5, 5, 0.6) 0%, transparent 100%);
        }

        .contact-hero h1 { font-size: 3.5rem; margin-bottom: 15px; }
        .contact-hero h1 span { color: var(--primary-red); }

        /* ===== MAP SECTION ===== */
        .map-section {
            padding: 20px 0;
        }

        .map-card {
            background: var(--card-bg);
            border-radius: 25px;
            padding: 15px;
            border: 1px solid rgba(255, 255, 255, 0.05);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5), 0 0 20px rgba(230, 57, 70, 0.1);
            overflow: hidden;
            transition: var(--transition);
        }

        .map-card:hover {
            border-color: var(--primary-red);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5), 0 0 30px rgba(230, 57, 70, 0.2);
        }

        .map-container {
            width: 100%;
            height: 450px;
            border-radius: 18px;
            overflow: hidden;
            /* Inverted for Dark Mode - Optional: Remove the filters below if you want normal colors */
            filter: grayscale(0.2) invert(0.9) hue-rotate(180deg) brightness(0.8); 
        }

        /* ===== CONTACT INFO ===== */
        .contact-section {
            padding: 60px 0 100px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
            margin-top: 40px;
        }

        .contact-card {
            background: var(--card-bg);
            padding: 40px;
            border-radius: 20px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.05);
            transition: var(--transition);
        }

        .contact-card:hover {
            transform: translateY(-10px);
            background: linear-gradient(145deg, #111, #1a1a1a);
            border-color: var(--primary-red);
        }

        .contact-card i {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, var(--primary-red), var(--dark-red));
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            margin-bottom: 25px;
            box-shadow: 0 10px 20px rgba(230, 57, 70, 0.3);
        }

        .contact-card h5 { font-family: 'Oswald', sans-serif; margin-bottom: 10px; }
        .contact-card p { color: var(--text-gray); margin: 0; }

        /* ===== FOOTER ===== */
        .footer {
            background: var(--darker-bg);
            padding: 60px 0 30px;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
        }

        .footer-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 30px;
        }

        .footer-logo { font-family: 'Oswald', sans-serif; font-size: 1.5rem; font-weight: 700; color: var(--text-white); letter-spacing: 3px; }
        .footer-logo i { color: var(--primary-red); }
        .footer-links { display: flex; gap: 30px; }
        .footer-links a { color: var(--text-gray); transition: var(--transition); }
        .footer-links a:hover { color: var(--primary-red); }
        .footer-social a { width: 45px; height: 45px; background: rgba(255, 255, 255, 0.05); border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; color: var(--text-white); margin-left: 10px; transition: var(--transition); }
        .footer-social a:hover { background: var(--primary-red); transform: translateY(-5px); }
        .footer-bottom { text-align: center; padding-top: 30px; margin-top: 30px; border-top: 1px solid rgba(255, 255, 255, 0.05); color: var(--text-gray); font-size: 0.9rem; }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 991px) {
            .info-grid { grid-template-columns: 1fr; }
            .contact-hero h1 { font-size: 2.5rem; }
        }
    </style>
</head>
<body>

    <!-- ===== NAVBAR ===== -->
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand" href="index.html">
                <i class=""></i> ARTS GYM
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav ms-auto align-items-lg-center">
                    <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="index.php#services">Services</a></li>
                    <li class="nav-item"><a class="nav-link" href="index.php#about">About</a></li>
                    <li class="nav-item"><a class="nav-link" href="index.php#faq">FAQ</a></li>
                    <li class="nav-item"><a class="nav-link btn-contact" href="contact.php">Contact Us</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- ===== PAGE HERO ===== -->
    <section class="contact-hero">
        <div class="container">
            <h1>Our <span>Location</span></h1>
            <p class="text-gray">Visit our world-class facility and start your transformation today.</p>
        </div>
    </section>

    <!-- ===== MAP SECTION ===== -->
    <section class="map-section">
        <div class="container">
            <div class="map-card">
                <div class="map-container">
                    <!-- Corrected Embed Link Below -->
                    <iframe 
                        src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3858.330896005705!2d120.95847891114227!3d14.737666473347535!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3397b30037d95b73%3A0x39f43dd8f9a8f7ab!2sArt&#39;s%20Gym!5e0!3m2!1sen!2sph!4v1738072000000!5m2!1sen!2sph" 
                        width="100%" 
                        height="100%" 
                        style="border:0;" 
                        allowfullscreen="" 
                        loading="lazy">
                    </iframe>
                </div>
            </div>
        </div>
    </section>

    <!-- ===== CONTACT DETAILS SECTION ===== -->
    <section class="contact-section">
        <div class="container">
            <div class="text-center mb-5">
                <h2>Get In <span>Touch</span></h2>
                <p class="text-gray">Choose your preferred way to reach out to us.</p>
            </div>

            <div class="info-grid">
                <!-- Address -->
                <div class="contact-card">
                    <i class="bi bi-geo-alt"></i>
                    <h5>Our Address</h5>
                    <p>456 Gym Road, Brgy. Banga<br>Meycauayan, Bulacan</p>
                </div>

                <!-- Phone -->
                <div class="contact-card">
                    <i class="bi bi-telephone"></i>
                    <h5>Call Us</h5>
                    <p>+63 900 123 4567<br>+63 44 888 9999</p>
                </div>

                <!-- Email -->
                <div class="contact-card">
                    <i class="bi bi-envelope"></i>
                    <h5>Email Us</h5>
                    <p>support@artsgym.com<br>info@artsgym.com</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ===== FOOTER ===== -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-logo">
                    <a href="index.php">
                        <img src="assets/gymlogo1.png" width="50" length="50" alt="">
                    </a> ARTS GYM
                </div>
                <div class="footer-links">
                    <a href="index.php">Home</a>
                    <a href="index.php#services">Services</a>
                    <a href="index.php#about">About</a>
                    <a href="index.php#faq">FAQ</a>
                </div>
                <div class="footer-social">
                    <a href="#"><i class="bi bi-facebook"></i></a>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2026 Arts Gym. All Rights Reserved.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>