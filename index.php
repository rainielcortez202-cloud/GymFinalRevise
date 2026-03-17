<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ARTS GYM | The Best Place For Your Fitness</title>

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

        /* ===== GLOBAL STYLES ===== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

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

        a {
            text-decoration: none;
            transition: var(--transition);
        }

        /* ===== NAVBAR ===== */
        .navbar {
            background: transparent;
            padding: 20px 0;
            transition: var(--transition);
            z-index: 1000;
        }

        .navbar.scrolled {
            background: white  (10, 10, 10, 0.95);
            backdrop-filter: blur(20px);
            padding: 10px 0; /* Reduced padding when scrolled */
            box-shadow: 0 5px 30px white(0, 0, 0, 0.5);
        }

        .navbar-brand {
            font-family: 'Oswald', sans-serif;
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--text-white) !important;
            letter-spacing: 3px;
            display: flex;
            align-items: center;
            gap: 12px; /* Gap between image and text */
        }

        /* --- FIXED LOGO STYLES --- */
        .navbar-brand img {
            height: 45px; /* Adjust this size based on your logo design */
            width: auto;
            object-fit: contain;
            transition: var(--transition);
        }

        .navbar.scrolled .navbar-brand img {
            height: 38px; /* Slightly smaller logo when scrolled */
        }

        .nav-link {
            color: var(--text-white) !important;
            font-weight: 500;
            padding: 10px 20px !important;
            position: relative;
            font-size: 0.95rem;
            letter-spacing: 1px;
        }

        .nav-link::after {
            content: '';
            position: absolute;
            bottom: 5px;
            left: 50%;
            transform: translateX(-50%);
            width: 0;
            height: 2px;
            background: var(--primary-red);
            transition: var(--transition);
        }

        .nav-link:hover::after,
        .nav-link.active::after {
            width: 60%;
        }

        .nav-link:hover {
            color: var(--primary-red) !important;
        }

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

        .btn-contact:hover {
            background: transparent;
            color: var(--primary-red) !important;
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(230, 57, 70, 0.3);
        }

        /* ===== HERO SECTION ===== */
        .hero {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .hero-bg {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                linear-gradient(135deg, rgba(10, 10, 10, 0.9) 0%, rgba(10, 10, 10, 0.7) 50%, rgba(157, 2, 8, 0.3) 100%),
                url('assets/5.jpg') center/cover no-repeat;
            z-index: -1;
        }

        .hero-content {
            text-align: center;
            padding: 0 20px;
            max-width: 900px;
        }

        .hero-subtitle {
            color: var(--primary-red);
            font-size: 1.2rem;
            font-weight: 600;
            letter-spacing: 4px;
            margin-bottom: 20px;
            text-transform: uppercase;
        }

        .hero-title {
            font-size: clamp(2.5rem, 8vw, 5rem);
            font-weight: 700;
            line-height: 1.1;
            margin-bottom: 25px;
            text-shadow: 0 5px 30px rgba(0, 0, 0, 0.5);
        }

        .hero-title span {
            color: var(--primary-red);
            display: block;
        }

        .hero-description {
            font-size: 1.2rem;
            color: var(--text-gray);
            max-width: 600px;
            margin: 0 auto 40px;
            line-height: 1.8;
        }

        /* ===== BUTTONS ===== */
        .btn-primary-custom {
            background: linear-gradient(135deg, var(--primary-red), var(--dark-red));
            color: var(--text-white);
            padding: 18px 45px;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1rem;
            letter-spacing: 2px;
            text-transform: uppercase;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: 0 10px 40px rgba(230, 57, 70, 0.4);
            position: relative;
            overflow: hidden;
        }

        .btn-primary-custom:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 50px rgba(249, 115, 22, 0.5);
        }

        .btn-outline-custom {
            background: transparent;
            color: var(--text-white);
            padding: 18px 45px;
            border: 2px solid var(--text-white);
            border-radius: 50px;
            font-weight: 600;
            font-size: 1rem;
            letter-spacing: 2px;
            text-transform: uppercase;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-outline-custom:hover {
            background: var(--text-white);
            color: var(--dark-bg);
            transform: translateY(-5px);
        }

        /* ===== SECTIONS ===== */
        .section {
            padding: 100px 0;
            position: relative;
        }

        .section-title {
            text-align: center;
            margin-bottom: 60px;
        }

        .section-title h2 {
            font-size: 3rem;
            margin-bottom: 20px;
            position: relative;
            display: inline-block;
        }

        .section-title h2::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: var(--primary-red);
        }

        /* ===== SERVICES SECTION ===== */
        .service-card {
            background: var(--card-bg);
            border-radius: 20px;
            padding: 40px 30px;
            text-align: center;
            transition: var(--transition);
            border: 1px solid rgba(255, 255, 255, 0.05);
            height: 100%;
        }

        .service-card:hover {
            transform: translateY(-15px);
            border-color: var(--primary-red);
        }

        .service-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary-red), var(--dark-red));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
            font-size: 2rem;
            color: var(--text-white);
        }

        /* ===== FAQ SECTION ===== */
        .accordion-item {
            background: var(color white);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            margin-bottom: 15px;
            overflow: hidden;
        }

        .accordion-button {
            background: var(--card-bg);
            color: var(--text-white);
            font-weight: 600;
            padding: 25px 30px;
            border: none;
            box-shadow: none !important;
        }

        .accordion-button:not(.collapsed) {
            background: linear-gradient(135deg, var(--primary-red), var(--dark-red));
            color: var(--text-white);
        }

        .accordion-button::after {
            filter: brightness(0) invert(1);
        }

        /* ===== FOOTER ===== */
        .footer {
            background: var(--darker-bg);
            padding: 60px 0 30px;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
        }

        .footer-logo {
            font-family: 'Oswald', sans-serif;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-white);
            letter-spacing: 3px;
        }

        @media (max-width: 991px) {
            .navbar-collapse {
                background: rgba(10, 10, 10, 0.98);
                padding: 20px;
                border-radius: 15px;
                margin-top: 15px;
            }
        }

        @media (max-width: 768px) {
            .hero-title { font-size: 2.5rem; }
            .section { padding: 70px 0; }
            .section-title h2 { font-size: 2rem; }
            .navbar-brand img { height: 35px; } /* Smaller on mobile */
        }
    </style>
</head>
<body>

    <!-- ===== NAVBAR ===== -->
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand" href="#home">
               <img src="assets/gymlogo1.png" alt=""> <span>ARTS GYM</span>
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
                <i class="bi bi-list text-white fs-1"></i>
            </button>

            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav ms-auto align-items-lg-center">
                    <li class="nav-item"><a class="nav-link active" href="#home">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="#services">Services</a></li>
                    <li class="nav-item"><a class="nav-link" href="about.php">About</a></li>
                    <li class="nav-item"><a class="nav-link" href="#faq">FAQ</a></li>
                    <li class="nav-item"><a class="nav-link btn-contact" href="contact.php">Contact Us</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- ===== HERO SECTION ===== -->
    <section id="home" class="hero">
        <div class="hero-bg"></div>
        <div class="hero-content">
            <p class="hero-subtitle">Welcome to Arts Gym</p>
            <h1 class="hero-title">
                THE BEST PLACE FOR <span>YOUR FITNESS</span>
            </h1>
            <p class="hero-description">
                Transform your body, elevate your mind. Join our community of dedicated fitness enthusiasts and achieve the results you've always dreamed of.
            </p>
            <div class="d-flex gap-4 flex-wrap justify-content-center">
                <a href="login.php#registerPanel" class="btn-primary-custom">GET STARTED</a>
                <a href="about.php" class="btn-outline-custom">LEARN MORE</a>
            </div>
        </div>
    </section>

    <!-- ===== SERVICES SECTION ===== -->
    <section id="services" class="section">
        <div class="container">
            <div class="section-title">
                <h2>Our Services</h2>
                <p>We offer a wide range of fitness programs designed to help you reach your goals</p>
            </div>
            <div class="row g-4">
                <div class="col-lg-4 col-md-6">
                    <div class="service-card">
                        <div class="service-icon"><i class="bi bi-lightning-charge"></i></div>
                        <h4>Strength Training</h4>
                        <p>Our gym offers a range of key machines and free weights, providing everything you need for focused and effective strength training.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="service-card">
                        <div class="service-icon"><i class="bi bi-person-check"></i></div>
                        <h4>Personal Training</h4>
                        <p>Receive tailored workouts and one-on-one coaching from our certified fitness professional, <a href="">Mr. Gianni Bordador</a>.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="service-card">
                        <div class="service-icon"><i class="bi bi-graph-up-arrow"></i></div>
                        <h4>Progress Tracking</h4>
                        <p>Monitor your fitness journey with our advanced tracking system and detailed analytics.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ===== FAQ SECTION ===== -->
    <section id="faq" class="section">
        <div class="container">
            <div class="section-title">
                <h2>Frequently Asked Questions</h2>
            </div>
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="accordion" id="faqAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                    Do I need an account to access the gym?
                                </button>
                            </h2>
                            <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Yes, all members must register to access the gym and its facilities. Each account comes with a personal QR code, which acts as your entrance pass.You'll need to scan it every time you enter the gym. This helps us track your attendance and ensures a secure and personalized experience for all our members.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                    Can I track my workouts and progress?
                                </button>
                            </h2>
                            <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                   Absolutely! Our portal lets you track your workouts, monitor attendance, and view detailed progress analytics. You can check your fitness journey anytime through your member dashboard.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                    What are the gym's operating hours?
                                </button>
                            </h2>
                            <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                   Our gym is open daily from 7:00 AM to 8:00 PM, Monday through Sunday.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                                    How much does membership cost?
                                </button>
                            </h2>
                            <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                   We offer a daily pass for ₱40. You can also choose a monthly membership for ₱500, or ₱400 for students.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ===== FOOTER ===== -->
    <footer class="footer">
        <div class="container text-center">
            <div class="footer-logo mb-3">ARTS GYM</div>
            <div class="footer-bottom">
                <p>&copy; 2026 Arts Gym. All Rights Reserved.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        window.addEventListener('scroll', () => {
            const navbar = document.querySelector('.navbar');
            navbar.classList.toggle('scrolled', window.scrollY > 50);
        });

        const sections = document.querySelectorAll('section[id]');
        const navLinks = document.querySelectorAll('.nav-link:not(.btn-contact)');

        window.addEventListener('scroll', () => {
            let current = '';
            sections.forEach(section => {
                const sectionTop = section.offsetTop - 100;
                if (scrollY >= sectionTop) { current = section.getAttribute('id'); }
            });

            navLinks.forEach(link => {
                link.classList.remove('active');
                if (link.getAttribute('href') === `#${current}`) { link.classList.add('active'); }
            });
        });
    </script>
    <!-- GLOBAL SCANNER -->
    <script src="assets/js/global_attendance.js"></script>
</body>
</html>
