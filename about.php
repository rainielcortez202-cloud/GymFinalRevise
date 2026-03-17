<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us | ARTS GYM</title>

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
            --dark-bg: #0a0a0a;
            --darker-bg: #050505;
            --card-bg: #111111;
            --text-white: #ffffff;
            --text-gray: #b0b0b0;
            --transition: all 0.3s ease;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--dark-bg);
            color: var(--text-white);
            overflow-x: hidden;
        }

        h1, h2, h3, h4 {
            font-family: 'Oswald', sans-serif;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        /* ===== NAVBAR (MATCHES HOME) ===== */
        .navbar {
            background: transparent;
            padding: 20px 0;
            transition: var(--transition);
            z-index: 1000;
        }

        .navbar.scrolled {
            background: rgba(10, 10, 10, 0.95);
            backdrop-filter: blur(20px);
            padding: 15px 0;
            box-shadow: 0 5px 30px rgba(0, 0, 0, 0.5);
            border-bottom: 1px solid rgba(255,255,255,0.05);
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
            transition: var(--transition);
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

        .btn-contact:hover {
            background: transparent;
            color: var(--primary-red) !important;
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(230, 57, 70, 0.3);
        }

        /* ===== ABOUT HEADER ===== */
        .about-header {
            padding: 180px 0 100px;
            background: linear-gradient(rgba(10, 10, 10, 0.8), rgba(10, 10, 10, 0.8)), 
                        url('assets/6.jpg') center/cover;
            text-align: center;
        }

        .about-header h1 { font-size: clamp(3rem, 8vw, 5rem); }
        .about-header span { color: var(--primary-red); }

        /* ===== SECTIONS ===== */
        .section { padding: 100px 0; }
        .bg-alternate { background: var(--darker-bg); }

        .section-title { text-align: center; margin-bottom: 60px; }
        .section-title h2 span { color: var(--primary-red); }

        /* ===== CAROUSEL SECTION ===== */
        .carousel-container {
            border-radius: 20px;
            overflow: hidden;
            border: 3px solid var(--primary-red);
            box-shadow: 0 20px 50px rgba(0,0,0,0.5);
        }

        .carousel-item img {
            height: 600px;
            object-fit: cover;
        }

        .carousel-caption {
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 20px;
            bottom: 40px;
            border: 1px solid rgba(255,255,255,0.1);
        }

        /* ===== FEATURE BOXES ===== */
        .feature-box {
            background: var(--card-bg);
            padding: 40px;
            border-radius: 20px;
            border: 1px solid rgba(255,255,255,0.05);
            text-align: center;
            transition: var(--transition);
        }
        .feature-box:hover {
            transform: translateY(-10px);
            border-color: var(--primary-red);
        }
        .feature-box i { font-size: 3rem; color: var(--primary-red); margin-bottom: 20px; display: block; }

        /* ===== FOOTER ===== */
        footer {
            background: var(--darker-bg);
            padding: 60px 0;
            border-top: 1px solid rgba(255,255,255,0.05);
            text-align: center;
        }

       /* ===== RESPONSIVE ===== */
        @media (max-width: 991px) {
            .about-content {
                flex-direction: column;
            }

            .about-image img,
            .about-carousel .carousel-item img {
                height: 320px;
            }

            .navbar-collapse {
                background: rgba(10, 10, 10, 0.98);
                padding: 20px;
                border-radius: 15px;
                margin-top: 15px;
            }
        }

        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.5rem;
            }

            .section {
                padding: 70px 0;
            }

            .section-title h2 {
                font-size: 2rem;
            }

            .about-features {
                grid-template-columns: 1fr;
            }

            .about-carousel .carousel-item img {
                height: 280px;
            }

            .about-carousel .carousel-control-prev,
            .about-carousel .carousel-control-next {
                width: 40px;
                height: 40px;
            }

            .footer-content {
                flex-direction: column;
                text-align: center;
            }

            .footer-links {
                flex-wrap: wrap;
                justify-content: center;
            }
        }
    </style>
</head>
<body>

    <!-- ===== NAVBAR ===== -->
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-dumbbell"></i> ARTS GYM  <!-- Added icon for branding -->
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="index.php#services">Services</a></li>
                    <li class="nav-item"><a class="nav-link text-danger" href="about.php">About</a></li>
                    <li class="nav-item"><a class="nav-link" href="index.php#faq">FAQ</a></li>
                    <li class="nav-item"><a class="nav-link btn-contact" href="contact.php">Contact Us</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- ===== HEADER ===== -->
    <header class="about-header">
        <div class="container">
            <h1 class="fw-bold">THE ARTS GYM <span>EXPERIENCE</span></h1>
            <p class="text-gray fs-5"> More than just weights. Feel the energy and community at Arts Gym.</p>
        </div>
    </header>

    <!-- ===== OUR STORY ===== -->
    <section class="section">
        <div class="container">
            <div class="row align-items-center g-5">
                <!-- Text Column -->
                <div class="col-lg-6">
                    <h2 class="mb-4">THE <span>ARTS GYM</span> PHILOSOPHY</h2>
                    <p class="text-secondary lh-lg">Founded in 2020, Arts Gym was built on the belief that fitness is the ultimate form of self-expression. We don't just provide equipment; we provide the canvas for you to sculpt the best version of yourself.</p>
                    <p class="text-secondary lh-lg">Our community is driven by discipline, intensity, and mutual respect. Whether you are lifting your first dumbbell or training for a competition, our environment is engineered to push you further than you thought possible.</p>
                </div>
                <!-- Image Column Added Below -->
                <div class="col-lg-6">
                    <div class="about-image-wrapper">
                        <img src="assets/13.jpg" width="70%" height="80%" alt="Arts Gym Philosophy" class="img-fluid rounded-4 shadow-lg" style="border: 2px solid var(--primary-red);">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ===== FACILITY CAROUSEL ===== -->
    <section class="section bg-alternate">
        <div class="container">
            <div class="section-title">
                <h2>OUR <span>FACILITY</span></h2>
                <p>All the essentials for a full workout, in a welcoming, high-energy environment.</p>
            </div>
            
            <div class="carousel-container">
                <div id="facilityCarousel" class="carousel slide carousel-fade" data-bs-ride="carousel">
                    <div class="carousel-indicators">
                        <button type="button" data-bs-target="#facilityCarousel" data-bs-slide-to="0" class="active"></button>
                        <button type="button" data-bs-target="#facilityCarousel" data-bs-slide-to="1"></button>
                        <button type="button" data-bs-target="#facilityCarousel" data-bs-slide-to="2"></button>
                        <button type="button" data-bs-target="#facilityCarousel" data-bs-slide-to="3"></button>
                        <button type="button" data-bs-target="#facilityCarousel" data-bs-slide-to="4"></button>
                    </div>
                    <div class="carousel-inner">
                        <div class="carousel-item active">
                            <img src="assets/3.jpg" class="d-block w-100" alt="Main Training Floor" loading="lazy">
                            <div class="carousel-caption d-block">
                            </div>
                        </div>
                        <div class="carousel-item">
                            <img src="assets/5.jpg" class="d-block w-100" alt="Cardio Deck" loading="lazy">
                            <div class="carousel-caption d-block">
                            </div>
                        </div>
                        <div class="carousel-item">
                            <img src="assets/9.jpg" class="d-block w-100" alt="Recovery Lounge" loading="lazy">
                            <div class="carousel-caption d-block">
                            </div>
                        </div>
                        <div class="carousel-item">
                            <img src="assets/10.jpg" class="d-block w-100" alt="Recovery Lounge" loading="lazy">
                            <div class="carousel-caption d-block">
                            </div>
                        </div>
                        <div class="carousel-item">
                            <img src="assets/11.jpg" class="d-block w-100" alt="Recovery Lounge" loading="lazy">
                            <div class="carousel-caption d-block">
                            </div>
                        </div>
                        
                    </div>
                    <button class="carousel-control-prev" type="button" data-bs-target="#facilityCarousel" data-bs-slide="prev">
                        <span class="carousel-control-prev-icon"></span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#facilityCarousel" data-bs-slide="next">
                        <span class="carousel-control-next-icon"></span>
                    </button>
                </div>
            </div>
        </div>
    </section>

    <footer>
        <div class="container">
            <div class="mb-4">
                <h3 class="fw-bold"><i class="bi bi-dumbbell"></i> ARTS GYM</h3>
            </div>
            <p class="text-secondary mb-0">&copy; 2024 Arts Gym. All Rights Reserved.</p>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Navbar Scroll Effect
        window.addEventListener('scroll', () => {
            const navbar = document.querySelector('.navbar');
            navbar.classList.toggle('scrolled', window.scrollY > 50);
        });
    </script>
</body>
</html>