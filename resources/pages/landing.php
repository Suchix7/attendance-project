<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>CMS College | Attendance System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700;800&family=Inter:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="resources/assets/css/landing_styles.css?v=<?php echo time(); ?>" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <link rel="icon" href="resources/images/logo/face logo.png" />
</head>

<body>
    <header class="landing-header">
        <div class="container header-flex">
            <div class="logo-area">
                <img src="resources/images/logo/attnlg.png" alt="College Logo" class="logo-img" />
                <span class="site-title">CMS College</span>
            </div>
            <nav>
                <a href="#about">About</a>
                <a href="#news">News</a>
                <a href="#features">Features</a>
                <a href="#gallery">Gallery</a>
                <a href="#contact">Contact</a>
                <a href="login" class="login-btn">Sign In</a>
            </nav>
        </div>
    </header>

    <section class="hero">
        <div class="container hero-flex">
            <div class="hero-text">
                <h1>The Future of <span>Attendance</span> is Here</h1>
                <p>
                    Empowering students and staff with technology. Modern, secure, and smart attendance management for a
                    seamless educational experience.
                </p>
                <a href="login" class="cta-btn">Access Dashboard <i class="fas fa-arrow-right"
                        style="margin-left: 10px;"></i></a>
            </div>
            <div class="hero-img">
                <div class="carousel" id="heroCarousel">
                    <img src="resources/images/lecture hall.jpeg" class="carousel-slide active" alt="Lecture Hall"
                        data-caption="Modern Lecture Halls for Interactive Learning" />
                    <img src="resources/images/laboratory.jpeg" class="carousel-slide" alt="Laboratory"
                        data-caption="State-of-the-Art Science Laboratories" />
                    <img src="resources/images/computer lab.jpeg" class="carousel-slide" alt="Computer Lab"
                        data-caption="Advanced Computer Labs for Digital Education" />

                    <div class="carousel-controls">
                        <button class="carousel-btn" id="prevSlide" aria-label="Previous Slide">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <button class="carousel-btn" id="nextSlide" aria-label="Next Slide">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>

                    <div id="carouselCaption" class="carousel-caption">
                        Modern Lecture Halls for Interactive Learning
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Moving features before news for better storytelling flow -->
    <section id="features" class="features">
        <div class="container">
            <div class="features-header">
                <h2 class="section-title">Why Choose Us?</h2>
                <p class="section-subtitle">Experience the perfect blend of innovation and seamless management with our
                    cutting-edge attendance system.</p>
            </div>
            <div class="features-grid">
                <div class="feature-card">
                    <i class="fas fa-user-check"></i>
                    <h3>Smart Attendance</h3>
                    <p>Facial recognition-based automated attendance tracking ensuring precision with zero effort.</p>
                </div>
                <div class="feature-card">
                    <i class="fas fa-shield-alt"></i>
                    <h3>Data Security</h3>
                    <p>Protected by top-tier encryption standards ensuring absolute privacy compliance and safe data
                        handling.</p>
                </div>
                <div class="feature-card">
                    <i class="fas fa-chart-line"></i>
                    <h3>Real-Time Analytics</h3>
                    <p>Instant visualization and dynamic reports to track daily engagement and academic progression.</p>
                </div>
                <div class="feature-card">
                    <i class="fas fa-laptop"></i>
                    <h3>Easy Accessibility</h3>
                    <p>Cloud-hosted infrastructure allowing instant access across all form factors and mobile devices.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <section id="about" class="about">
        <div class="container about-flex">
            <div class="about-img">
                <img src="resources/images/class.jpeg" alt="Classroom" />
            </div>
            <div class="about-text">
                <h2 class="section-title">About CMS College</h2>
                <p class="section-subtitle" style="margin-bottom: 1.5em;">
                    CMS College is dedicated to providing world-class education while fostering digital innovation. We
                    leverage modern web technologies to ensure a holistic, frictionless learning ecosystem.
                </p>
                <ul class="about-list">
                    <li><i class="fas fa-check"></i> Fully Accredited Global Programs</li>
                    <li><i class="fas fa-check"></i> Industry-Experienced Faculty Experts</li>
                    <li><i class="fas fa-check"></i> State-of-the-Art Digital Infrastructure</li>
                </ul>
            </div>
        </div>
    </section>

    <section id="news" class="news">
        <div class="container">
            <div class="news-header" style="text-align: center;">
                <h2 class="section-title">Latest & Greatest</h2>
                <p class="section-subtitle" style="margin: 0 auto;">Stay updated with the latest technological and
                    academic milestones at our campus.</p>
            </div>
            <div class="news-grid">
                <article class="news-card">
                    <h3>Admissions Open 2024</h3>
                    <p>Applications are now being accepted for undergraduate and postgraduate future-ready degree
                        programs.</p>
                    <a href="#">Learn more</a>
                    <span class="news-date">May 2024</span>
                </article>
                <article class="news-card">
                    <h3>Innovation Fair 2024</h3>
                    <p>Join us for the annual tech fair showcasing robust student prototypes and AI-driven innovations.
                    </p>
                    <a href="#">Event details</a>
                    <span class="news-date">June 2024</span>
                </article>
                <article class="news-card">
                    <h3>New Tech Wing Open</h3>
                    <p>Our brand new state-of-the-art computer science and artificial intelligence lab is now
                        operational.</p>
                    <a href="#">See photos</a>
                    <span class="news-date">April 2024</span>
                </article>
            </div>
        </div>
    </section>

    <section id="gallery" class="gallery">
        <div class="container">
            <div class="gallery-header" style="text-align: center;">
                <h2 class="section-title">Campus Gallery</h2>
                <p class="section-subtitle" style="margin: 0 auto;">A glimpse into our immersive learning environments.
                </p>
            </div>
            <div class="gallery-grid">
                <img src="resources/images/laboratory.jpeg" alt="Laboratory" loading="lazy" />
                <img src="resources/images/computer lab.jpeg" alt="Computer Lab" loading="lazy" />
                <img src="resources/images/office image.jpeg" alt="Office" loading="lazy" />
                <img src="resources/images/class.jpeg" alt="Class" loading="lazy" />
                <img src="resources/images/college.jpg" alt="College Campus" loading="lazy" />
                <img src="resources/images/student.jpg" alt="Students" loading="lazy" />
            </div>
        </div>
    </section>

    <section id="contact" class="contact">
        <div class="container contact-flex">
            <div class="contact-info">
                <h2>Get in Touch</h2>
                <p>We’re here to answer any questions you may have about SAS College or our smart infrastructure.</p>
                <ul>
                    <li><i class="fas fa-envelope"></i> info@sascollege.edu</li>
                    <li><i class="fas fa-phone-alt"></i> +977-1-1234567</li>
                    <li><i class="fas fa-map-marker-alt"></i> Kathmandu, Nepal</li>
                </ul>
            </div>
            <form class="contact-form" action="#" method="post">
                <h3>Drop a Message</h3>
                <div class="input-wrapper">
                    <input type="text" name="name" placeholder="Your Full Name" required />
                </div>
                <div class="input-wrapper">
                    <input type="email" name="email" placeholder="Email Address" required />
                </div>
                <div class="input-wrapper">
                    <textarea name="message" rows="4" placeholder="How can we help?" required></textarea>
                </div>
                <button type="submit" class="cta-btn">Send Message</button>
            </form>
        </div>
    </section>

    <footer class="footer">
        <div class="container footer-flex">
            <div class="footer-logo">
                <img src="resources/images/logo/attnlg.png" alt="Logo" />
                <span>CMS College</span>
            </div>
            <div class="footer-links">
                <a href="#about">About</a>
                <a href="#features">Features</a>
                <a href="#gallery">Gallery</a>
                <a href="#contact">Contact</a>
            </div>
            <div class="footer-social">
                <a href="#"><i class="fab fa-facebook-f"></i></a>
                <a href="#"><i class="fab fa-twitter"></i></a>
                <a href="#"><i class="fab fa-instagram"></i></a>
                <a href="#"><i class="fab fa-linkedin-in"></i></a>
            </div>
        </div>
        <div class="footer-copy">
            &copy; <?php echo date('Y'); ?> SAS College. All rights reserved. | Empowered by Next-Gen Technology
        </div>
    </footer>

    <script>
        const slides = document.querySelectorAll(".carousel-slide");
        const captions = Array.from(slides).map((slide) =>
            slide.getAttribute("data-caption")
        );
        const captionEl = document.getElementById("carouselCaption");
        let currentSlide = 0;

        function showSlide(idx) {
            slides.forEach((slide, i) => {
                slide.classList.toggle("active", i === idx);
            });
            captionEl.textContent = captions[idx];
        }

        document.getElementById("prevSlide").onclick = function () {
            currentSlide = (currentSlide - 1 + slides.length) % slides.length;
            showSlide(currentSlide);
        };

        document.getElementById("nextSlide").onclick = function () {
            currentSlide = (currentSlide + 1) % slides.length;
            showSlide(currentSlide);
        };

        setInterval(() => {
            currentSlide = (currentSlide + 1) % slides.length;
            showSlide(currentSlide);
        }, 6000);

        // Navbar blur effect on scroll
        const header = document.querySelector('.landing-header');
        window.addEventListener('scroll', () => {
            if (window.scrollY > 50) {
                header.style.background = 'rgba(255, 255, 255, 0.9)';
                header.style.boxShadow = '0 4px 20px rgba(0,0,0,0.05)';
            } else {
                header.style.background = 'rgba(255, 255, 255, 0.75)';
                header.style.boxShadow = 'none';
            }
        });
    </script>
</body>

</html>