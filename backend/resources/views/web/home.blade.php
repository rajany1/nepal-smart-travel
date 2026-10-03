<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Oripori is Nepal's all-in-one local intelligence platform — live community insights, emergency SOS, detailed maps, curated routes and real-time alerts right where you stand.">
    <title>Oripori — What's Around You</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        /* ==========================================================================
           CSS VARIABLES & BRAND DESIGN SYSTEM
           ========================================================================== */
        :root {
            --primary: #49C5B6;
            --primary-dark: #015048;
            --primary-deep: #002824;
            --primary-light: #EBF7F5;
            --primary-border: rgba(73, 197, 182, 0.25);

            --accent-red: #bf1706;
            --accent-red-bg: #fdf2f0;
            --accent-red-border: rgba(191, 23, 6, 0.2);

            --dark: #081715;
            --black: #000000;
            --white: #ffffff;
            --gray-50: #F7FAF9;
            --gray-100: #EDF3F1;
            --gray-200: #D8E4E1;
            --gray-600: #516360;
            --gray-800: #1C2B29;

            --font-main: 'Manrope', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;

            --header-height: 80px;
            --shadow-sm: 0 4px 12px rgba(1, 80, 72, 0.05);
            --shadow-md: 0 10px 30px rgba(1, 80, 72, 0.08);
            --shadow-lg: 0 20px 45px rgba(1, 80, 72, 0.12);
            --shadow-glow: 0 0 35px rgba(73, 197, 182, 0.3);

            --radius-sm: 10px;
            --radius-md: 18px;
            --radius-lg: 28px;
            --radius-full: 9999px;
        }

        /* ==========================================================================
           BASE & RESET
           ========================================================================== */
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html {
            scroll-behavior: smooth;
            scroll-padding-top: var(--header-height);
            font-family: var(--font-main);
            background-color: var(--white);
            color: var(--gray-800);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }

        body {
            padding-top: var(--header-height);
            overflow-x: hidden;
            position: relative;
        }

        h1, h2, h3, h4, h5 {
            color: var(--primary-dark);
            font-weight: 800;
            line-height: 1.2;
            letter-spacing: -0.02em;
        }

        p {
            color: var(--gray-600);
            font-size: 1.05rem;
            line-height: 1.7;
        }

        a {
            text-decoration: none;
            color: inherit;
            transition: all 0.25s ease;
        }

        img {
            max-width: 100%;
            height: auto;
            display: block;
            object-fit: cover;
        }

        .container {
            width: 100%;
            max-width: 1240px;
            margin: 0 auto;
            padding: 0 24px;
        }

        .section-padding {
            padding: 100px 0;
        }

        .section-title-wrap {
            text-align: center;
            max-width: 720px;
            margin: 0 auto 60px auto;
        }

        .badge-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 16px;
            background-color: var(--primary-light);
            border: 1px solid var(--primary-border);
            color: var(--primary-dark);
            font-size: 0.875rem;
            font-weight: 700;
            border-radius: var(--radius-full);
            margin-bottom: 16px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .badge-pill i {
            color: var(--primary);
        }

        .section-title {
            font-size: 2.75rem;
            margin-bottom: 18px;
            color: var(--primary-dark);
        }

        .section-subtitle {
            font-size: 1.15rem;
            color: var(--gray-600);
        }

        /* ==========================================================================
           HEADER & NAVIGATION
           ========================================================================== */
        .site-header {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: var(--header-height);
            background-color: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--gray-200);
            z-index: 300;
            display: flex;
            align-items: center;
        }

        .header-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
        }

        .brand-logo img {
            height: 38px;
            width: auto;
            object-fit: contain;
        }

        .nav-menu {
            display: flex;
            align-items: center;
            gap: 32px;
            list-style: none;
        }

        .nav-link {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--primary-dark);
            position: relative;
            padding: 6px 0;
        }

        .nav-link::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0%;
            height: 2px;
            background-color: var(--primary);
            transition: width 0.3s ease;
        }

        .nav-link:hover::after {
            width: 100%;
        }

        .btn-cta-placeholder {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 24px;
            background-color: var(--primary-dark);
            color: var(--white);
            font-weight: 700;
            font-size: 0.95rem;
            border-radius: var(--radius-full);
            box-shadow: 0 4px 14px rgba(1, 80, 72, 0.2);
            border: 2px solid var(--primary-dark);
            transition: all 0.3s ease;
            white-space: nowrap;
        }

        .btn-cta-placeholder:hover {
            background-color: var(--primary);
            border-color: var(--primary);
            color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-glow);
        }

        .nav-mobile-toggle {
            display: none;
            font-size: 1.4rem;
            color: var(--primary-dark);
            cursor: pointer;
            background: none;
            border: none;
            line-height: 1;
            padding: 6px;
        }
        /* ==========================================================================
           HERO SECTION
           ========================================================================== */
        .hero-section {
            position: relative;
            background-color: var(--primary-dark);
            color: var(--white);
            padding: 90px 0 120px 0;
            overflow: hidden;
        }

        .hero-bg-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0.18;
            pointer-events: none;
            background-size: cover;
            background-position: center;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            inset: 0;
            z-index: 1;
            pointer-events: none;
            background: linear-gradient(100deg, rgba(1, 80, 72, 0.94) 0%, rgba(1, 80, 72, 0.78) 45%, rgba(1, 80, 72, 0.35) 100%);
        }

        .hero-grid {
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            gap: 60px;
            align-items: center;
            position: relative;
            z-index: 2;
        }

        .hero-content {
            max-width: 620px;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 8px 18px;
            border-radius: var(--radius-full);
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 24px;
        }

        .hero-title {
            font-size: 3.75rem;
            color: var(--white);
            line-height: 1.1;
            margin-bottom: 20px;
        }

        .hero-title span {
            color: var(--primary);
            display: block;
        }

        .hero-description {
            font-size: 1.2rem;
            color: rgba(255, 255, 255, 0.85);
            margin-bottom: 36px;
            line-height: 1.6;
        }

        .hero-cta-group {
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .hero-tagline-chip {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            background: rgba(0, 0, 0, 0.3);
            border-radius: var(--radius-md);
            border: 1px solid rgba(73, 197, 182, 0.3);
        }

        .hero-tagline-chip i {
            color: var(--primary);
            font-size: 1.25rem;
        }

        .hero-tagline-chip span {
            font-weight: 600;
            font-size: 0.95rem;
            color: var(--white);
        }

        .hero-app-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 14px 26px;
            background-color: var(--primary);
            color: var(--primary-deep);
            font-weight: 800;
            font-size: 0.95rem;
            border-radius: var(--radius-full);
            border: 2px solid var(--primary);
            box-shadow: 0 8px 24px rgba(73, 197, 182, 0.3);
        }

        .hero-app-btn:hover {
            background-color: var(--white);
            border-color: var(--white);
            transform: translateY(-2px);
        }

        .hero-visual {
            position: relative;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .hero-phone-card {
            width: 280px;
            background: var(--dark);
            border-radius: 36px;
            padding: 10px;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.5);
            border: 4px solid rgba(255, 255, 255, 0.15);
            position: relative;
            z-index: 2;
            transition: transform 0.4s ease;
        }

        .hero-phone-card:hover {
            transform: translateY(-8px);
        }

        .hero-phone-screen {
            border-radius: 28px;
            background: var(--white);
            overflow: hidden;
        }

        .hero-phone-screen img {
            width: 100%;
            border-radius: 26px;
            object-fit: cover;
        }

        .floating-card {
            position: absolute;
            background: var(--white);
            border-radius: var(--radius-md);
            padding: 14px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.25);
            z-index: 3;
            display: flex;
            align-items: center;
            gap: 12px;
            border: 1px solid var(--gray-200);
            animation: floatAnim 4s ease-in-out infinite alternate;
        }

        .floating-card-1 {
            top: 10%;
            left: -20px;
            width: 200px;
        }

        .floating-card-2 {
            bottom: 12%;
            right: -20px;
            width: 220px;
            animation-delay: -2s;
        }

        @keyframes floatAnim {
            0% { transform: translateY(0); }
            100% { transform: translateY(-12px); }
        }

        .floating-card-icon {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        .floating-card-icon.red {
            background-color: var(--accent-red-bg);
            color: var(--accent-red);
        }

        .floating-card-icon.teal {
            background-color: var(--primary-light);
            color: var(--primary-dark);
        }

        .floating-card-text {
            display: flex;
            flex-direction: column;
        }

        .floating-card-title {
            font-weight: 800;
            font-size: 0.85rem;
            color: var(--primary-dark);
        }

        .floating-card-sub {
            font-size: 0.75rem;
            color: var(--gray-600);
        }

        /* ==========================================================================
           OVERVIEW & STATS BAR
           ========================================================================== */
        .overview-bar {
            background-color: var(--white);
            margin-top: -50px;
            position: relative;
            z-index: 10;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            padding: 36px 40px;
            border: 1px solid var(--gray-200);
        }

        .overview-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 30px;
        }

        .overview-item {
            text-align: center;
            padding: 0 10px;
            border-right: 1px solid var(--gray-200);
        }

        .overview-item:last-child {
            border-right: none;
        }

        .overview-number {
            font-size: 2.2rem;
            font-weight: 800;
            color: var(--primary-dark);
            margin-bottom: 4px;
        }

        .overview-label {
            font-size: 0.9rem;
            color: var(--gray-600);
            font-weight: 600;
        }
        /* ==========================================================================
           FEATURE ROWS
           ========================================================================== */
        .feature-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            align-items: center;
            margin-bottom: 100px;
        }

        .feature-row.reverse {
            direction: rtl;
        }

        .feature-row.reverse > * {
            direction: ltr;
        }

        .feature-text {
            max-width: 520px;
        }

        .feature-title {
            font-size: 2.25rem;
            margin-bottom: 20px;
        }

        .feature-desc {
            margin-bottom: 28px;
        }

        .feature-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 14px;
            margin-bottom: 32px;
        }

        .feature-list-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            font-weight: 600;
            color: var(--gray-800);
        }

        .feature-list-item i {
            color: var(--primary);
            font-size: 1.1rem;
            margin-top: 3px;
        }

        .feature-cta {
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
        }

        .btn-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 22px;
            background-color: var(--primary-dark);
            color: var(--white);
            font-weight: 700;
            font-size: 0.9rem;
            border-radius: var(--radius-full);
            border: 2px solid var(--primary-dark);
            transition: all 0.3s ease;
        }

        .btn-pill:hover {
            background-color: var(--primary);
            border-color: var(--primary);
            color: var(--primary-deep);
            transform: translateY(-2px);
            box-shadow: var(--shadow-glow);
        }

        .btn-pill.ghost {
            background-color: var(--white);
            color: var(--primary-dark);
            border-color: var(--gray-200);
        }

        .btn-pill.ghost:hover {
            background-color: var(--primary-light);
            border-color: var(--primary);
            box-shadow: none;
        }

        .app-screen-grid {
            display: flex;
            gap: 20px;
            justify-content: center;
        }

        .app-mockup-frame {
            background: var(--gray-50);
            border-radius: var(--radius-md);
            padding: 12px;
            border: 1px solid var(--gray-200);
            box-shadow: var(--shadow-md);
            max-width: 260px;
            transition: transform 0.3s ease;
        }

        .app-mockup-frame:hover {
            transform: translateY(-6px);
            border-color: var(--primary);
        }

        .app-mockup-frame img {
            border-radius: var(--radius-sm);
            width: 100%;
        }

        /* ==========================================================================
           SAFETY & EMERGENCY SUPPORT SECTION
           ========================================================================== */
        .safety-section {
            background-color: #0A1C1A;
            color: var(--white);
            position: relative;
            border-radius: var(--radius-lg);
            padding: 80px 60px;
            margin: 60px 0;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
        }

        .safety-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            align-items: center;
        }

        .badge-pill-danger {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 16px;
            background-color: rgba(191, 23, 6, 0.2);
            border: 1px solid var(--accent-red-border);
            color: #ff5242;
            font-size: 0.875rem;
            font-weight: 700;
            border-radius: var(--radius-full);
            margin-bottom: 20px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .safety-title {
            color: var(--white);
            font-size: 2.5rem;
            margin-bottom: 20px;
        }

        .safety-desc {
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 32px;
        }

        .safety-features-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .safety-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 20px;
            border-radius: var(--radius-md);
            transition: background 0.3s ease;
        }

        .safety-card:hover {
            background: rgba(255, 255, 255, 0.09);
            border-color: var(--accent-red);
        }

        .safety-card-icon {
            font-size: 1.5rem;
            color: var(--accent-red);
            margin-bottom: 12px;
        }

        .safety-card h4 {
            color: var(--white);
            font-size: 1.1rem;
            margin-bottom: 8px;
        }

        .safety-card p {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9rem;
        }

        .safety-visual {
            display: flex;
            gap: 16px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .safety-visual img {
            width: 210px;
            max-width: 100%;
            border-radius: var(--radius-md);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.1);
            object-fit: cover;
        }
        /* ==========================================================================
           COMMUNITY & REWARDS SECTION
           ========================================================================== */
        .rewards-banner {
            background: linear-gradient(135deg, var(--primary-light) 0%, #FFFFFF 100%);
            border: 1px solid var(--primary-border);
            border-radius: var(--radius-lg);
            padding: 60px;
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 50px;
            align-items: center;
            margin: 80px 0;
        }

        .rewards-content h3 {
            font-size: 2.2rem;
            margin-bottom: 16px;
        }

        .payout-badges {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-top: 24px;
            flex-wrap: wrap;
        }

        .payout-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 18px;
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-full);
            font-weight: 700;
            font-size: 0.9rem;
            color: var(--primary-dark);
            box-shadow: var(--shadow-sm);
        }

        .rewards-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 22px;
            font-weight: 700;
            font-size: 0.95rem;
            color: var(--primary-dark);
        }

        .rewards-link:hover {
            color: var(--primary);
            gap: 12px;
        }

        .rewards-visual img {
            max-width: 280px;
            margin: 0 auto;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-lg);
        }

        /* ==========================================================================
           CORE VALUES GRID
           ========================================================================== */
        .values-section {
            background-color: var(--gray-50);
            padding: 100px 0;
            border-top: 1px solid var(--gray-200);
            border-bottom: 1px solid var(--gray-200);
        }

        .values-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 24px;
        }

        .value-card {
            background: var(--white);
            padding: 30px 24px;
            border-radius: var(--radius-md);
            border: 1px solid var(--gray-200);
            box-shadow: var(--shadow-sm);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .value-card:hover {
            transform: translateY(-6px);
            box-shadow: var(--shadow-md);
            border-color: var(--primary);
        }

        .value-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-sm);
            background-color: var(--primary-light);
            color: var(--primary-dark);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
        }

        .value-title {
            font-size: 1.2rem;
            color: var(--primary-dark);
        }

        .value-desc {
            font-size: 0.92rem;
            color: var(--gray-600);
            line-height: 1.5;
        }

        /* ==========================================================================
           APP INTERFACE SHOWCASE GALLERY
           ========================================================================== */
        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 16px;
            margin-top: 40px;
        }

        .gallery-item {
            background: var(--white);
            border-radius: var(--radius-md);
            padding: 8px;
            border: 1px solid var(--gray-200);
            box-shadow: var(--shadow-sm);
            transition: transform 0.3s ease;
        }

        .gallery-item:hover {
            transform: scale(1.03);
            box-shadow: var(--shadow-md);
        }

        .gallery-item img {
            border-radius: var(--radius-sm);
            width: 100%;
        }
        /* ==========================================================================
           FOOTER
           ========================================================================== */
        .site-footer {
            background-color: var(--primary-deep);
            color: var(--white);
            padding: 80px 0 40px 0;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .footer-top {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 50px;
            margin-bottom: 60px;
        }

        .footer-brand {
            max-width: 360px;
        }

        .footer-logo {
            margin-bottom: 20px;
        }

        .footer-logo img {
            height: 36px;
            width: auto;
            object-fit: contain;
        }

        .footer-brand p {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.95rem;
            margin-bottom: 24px;
        }

        .footer-heading {
            color: var(--white);
            font-size: 1.1rem;
            margin-bottom: 20px;
            position: relative;
        }

        .footer-links {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .footer-links a {
            color: rgba(255, 255, 255, 0.75);
            font-size: 0.95rem;
        }

        .footer-links a:hover {
            color: var(--primary);
        }

        .footer-bottom {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.6);
        }

        /* ==========================================================================
           RESPONSIVE
           ========================================================================== */
        @media (max-width: 1024px) {
            .section-padding { padding: 70px 0; }
            .values-section { padding: 70px 0; }
            .section-title { font-size: 2.1rem; }
            .feature-title { font-size: 1.85rem; }
            .safety-title { font-size: 2rem; }
            .rewards-content h3 { font-size: 1.8rem; }
            .safety-section { padding: 50px 32px; }
            .rewards-banner { padding: 44px 28px; }
            .footer-top { grid-template-columns: 1fr 1fr; gap: 40px; }
            .values-grid { grid-template-columns: repeat(2, 1fr); }
            .gallery-grid { grid-template-columns: repeat(3, 1fr); }
        }

        @media (max-width: 992px) {
            .hero-grid {
                grid-template-columns: 1fr;
                text-align: center;
                gap: 50px;
            }
            .hero-content { margin: 0 auto; }
            .hero-title { font-size: 2.75rem; }
            .hero-cta-group { justify-content: center; }
            .floating-card-1 { left: 0; }
            .floating-card-2 { right: 0; }

            .nav-menu {
                display: none;
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                flex-direction: column;
                align-items: stretch;
                gap: 0;
                background: var(--white);
                border-bottom: 1px solid var(--gray-200);
                box-shadow: var(--shadow-md);
                padding: 8px 24px 16px;
            }
            .nav-menu.is-open { display: flex; }
            .nav-menu li { width: 100%; }
            .nav-menu .nav-link {
                display: block;
                padding: 13px 0;
                border-bottom: 1px solid var(--gray-100);
            }
            .nav-menu li:last-child .nav-link { border-bottom: none; }
            .nav-link::after { display: none; }
            .nav-mobile-toggle { display: block; }

            .feature-row,
            .feature-row.reverse {
                grid-template-columns: 1fr;
                gap: 40px;
                direction: ltr;
            }
            .feature-text { max-width: 100%; }
            .feature-row { margin-bottom: 70px; }
            .app-screen-grid { flex-wrap: wrap; }

            .safety-grid { grid-template-columns: 1fr; }
            .safety-features-grid { grid-template-columns: 1fr; }

            .rewards-banner { grid-template-columns: 1fr; text-align: center; }
            .payout-badges { justify-content: center; }
            .feature-cta { justify-content: center; }

            .overview-bar { padding: 28px 24px; }
        }

        @media (max-width: 768px) {
            .section-padding { padding: 60px 0; }
            .values-section { padding: 60px 0; }
            .section-title { font-size: 2rem; }
            .hero-section { padding: 60px 0 100px 0; }
            .hero-title { font-size: 2.4rem; }

            .overview-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 24px;
            }
            .overview-item:nth-child(2) { border-right: none; }
            .overview-item {
                border-bottom: 1px solid var(--gray-200);
                padding-bottom: 16px;
            }
            .overview-item:nth-child(3),
            .overview-item:nth-child(4) {
                border-bottom: none;
                padding-bottom: 0;
            }

            .safety-section { padding: 44px 22px; }
            .rewards-banner { padding: 36px 22px; }
            .site-footer { padding: 60px 0 32px 0; }
            .footer-bottom { justify-content: center; text-align: center; }
            .btn-cta-placeholder { padding: 10px 18px; font-size: 0.85rem; }
        }

        @media (max-width: 576px) {
            .values-grid { grid-template-columns: 1fr; }
            .gallery-grid { grid-template-columns: repeat(2, 1fr); }
            .footer-top { grid-template-columns: 1fr; }
            .hero-phone-card { width: 250px; }
            .floating-card { padding: 11px; }
            .floating-card-1 { width: 175px; }
            .floating-card-2 { width: 190px; }
            .safety-visual img { width: 155px; }
            .app-mockup-frame { max-width: 220px; }
        }

        @media (max-width: 480px) {
            .brand-logo img { height: 26px; }
            .btn-cta-placeholder { padding: 9px 15px; font-size: 0.85rem; }
            .hero-title { font-size: 2.1rem; }
            .section-title { font-size: 1.75rem; }
            .overview-number { font-size: 1.75rem; }
            .hero-tagline-chip, .hero-app-btn { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>

    <!-- HEADER -->
    <header class="site-header">
        <div class="container header-container">
            <a href="{{ route('web.home') }}" class="brand-logo" aria-label="Oripori Home">
                <img src="{{ asset('images/oripori_logo.svg') }}" alt="Oripori Logo">
            </a>

            <nav>
                <ul class="nav-menu" id="nav-menu">
                    <li><a href="#explore" class="nav-link">Explore</a></li>
                    <li><a href="#safety" class="nav-link">Safety &amp; SOS</a></li>
                    <li><a href="#community" class="nav-link">Community Insights</a></li>
                    <li><a href="#rewards" class="nav-link">Oripori Coins</a></li>
                    <li><a href="#values" class="nav-link">Core Values</a></li>
                </ul>
            </nav>

            <a href="{{ route('partner.login') }}" class="btn-cta-placeholder">Partner Login</a>

            <button class="nav-mobile-toggle" id="nav-toggle" type="button" aria-label="Toggle navigation" aria-expanded="false" aria-controls="nav-menu">
                <i class="fa-solid fa-bars"></i>
            </button>
        </div>
    </header>

    <main>
        <!-- HERO -->
        <section class="hero-section">
            <div class="hero-bg-overlay" style="background-image: url('{{ asset('images/landing/hero-bg.jpg') }}');"></div>

            <div class="container hero-grid">
                <div class="hero-content">
                    <div class="hero-badge">
                        <i class="fa-solid fa-compass"></i> Nepal-First Smart Platform
                    </div>
                    <h1 class="hero-title">
                        What's Around <span>You</span>
                    </h1>
                    <p class="hero-description">
                        Oripori is Nepal&rsquo;s all-in-one local intelligence platform connecting you with live community insights, emergency support, detailed maps, and real-time alerts right where you stand.
                    </p>
                    <div class="hero-cta-group">
                        <div class="hero-tagline-chip">
                            <i class="fa-solid fa-location-dot"></i>
                            <span>Hyper-Local Discovery across Nepal</span>
                        </div>
                        <a href="{{ env('PLAY_STORE_URL', '#') }}" class="hero-app-btn">
                            <i class="fa-brands fa-google-play"></i> Get the App
                        </a>
                    </div>
                </div>

                <div class="hero-visual">
                    <div class="floating-card floating-card-1">
                        <div class="floating-card-icon red">
                            <i class="fa-solid fa-shield-halved"></i>
                        </div>
                        <div class="floating-card-text">
                            <span class="floating-card-title">Live SOS Alert</span>
                            <span class="floating-card-sub">Instant Emergency Response</span>
                        </div>
                    </div>

                    <div class="hero-phone-card">
                        <div class="hero-phone-screen">
                            <img src="{{ asset('images/landing/app-explore.jpg') }}" alt="Oripori app home screen with live alerts and nearby highlights" width="720" height="1600">
                        </div>
                    </div>

                    <div class="floating-card floating-card-2">
                        <div class="floating-card-icon teal">
                            <i class="fa-solid fa-cloud-sun-rain"></i>
                        </div>
                        <div class="floating-card-text">
                            <span class="floating-card-title">Live Weather &amp; Flood</span>
                            <span class="floating-card-sub">Real-time local warnings</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <!-- OVERVIEW & METRICS BAR -->
        <div class="container">
            <div class="overview-bar">
                <div class="overview-grid">
                    <div class="overview-item">
                        <div class="overview-number">100%</div>
                        <div class="overview-label">Nepal First Design</div>
                    </div>
                    <div class="overview-item">
                        <div class="overview-number">24/7</div>
                        <div class="overview-label">Emergency SOS Network</div>
                    </div>
                    <div class="overview-item">
                        <div class="overview-number">Live</div>
                        <div class="overview-label">Community Weather &amp; Road Alerts</div>
                    </div>
                    <div class="overview-item">
                        <div class="overview-number">Smart</div>
                        <div class="overview-label">AI Nepal Travel Assistant</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- FEATURE 1: SMART LOCAL NAVIGATION & MAPS -->
        <section id="explore" class="section-padding">
            <div class="container">
                <div class="section-title-wrap">
                    <div class="badge-pill">
                        <i class="fa-solid fa-map-location-dot"></i> Navigation &amp; Places
                    </div>
                    <h2 class="section-title">Discover Trekking Trails &amp; Hidden Local Gems</h2>
                    <p class="section-subtitle">
                        From the Poon Hill Sunrise Trek to Kathmandu Valley Heritage Circles, explore {{ number_format($placesCount) }}+ mapped places across Nepal with interactive maps, offline trail overlays, and precise waypoint guidance.
                    </p>
                </div>

                <div class="feature-row">
                    <div class="feature-text">
                        <h3 class="feature-title">High-Precision Satellite &amp; Terrain Maps</h3>
                        <p class="feature-desc">
                            Whether you are exploring bustling city markets in Pokhara or trekking through alpine mountain passes, Oripori provides visual topographical maps layered with active location pins.
                        </p>
                        <ul class="feature-list">
                            <li class="feature-list-item">
                                <i class="fa-solid fa-circle-check"></i>
                                <span>Interactive satellite maps centered on local landmarks like Phewa Lake.</span>
                            </li>
                            <li class="feature-list-item">
                                <i class="fa-solid fa-circle-check"></i>
                                <span>Detailed trail itineraries with distance, duration, elevation, and waypoints.</span>
                            </li>
                            <li class="feature-list-item">
                                <i class="fa-solid fa-circle-check"></i>
                                <span>Categorized spot finder for Restaurants, Hotels, Attractions, and Local Shops.</span>
                            </li>
                        </ul>
                        <div class="feature-cta">
                            <a href="{{ route('web.places') }}" class="btn-pill">
                                <i class="fa-solid fa-map-location-dot"></i> Explore Places
                            </a>
                            <a href="{{ route('web.routes') }}" class="btn-pill ghost">
                                <i class="fa-solid fa-route"></i> Curated Routes
                            </a>
                        </div>
                    </div>

                    <div class="app-screen-grid">
                        <div class="app-mockup-frame">
                            <img src="{{ asset('images/landing/app-map.jpg') }}" alt="Oripori satellite map with nearby place pins" loading="lazy" width="720" height="1600">
                        </div>
                        <div class="app-mockup-frame">
                            <img src="{{ asset('images/landing/app-trail.jpg') }}" alt="Oripori Poon Hill trek route detail with trail waypoints" loading="lazy" width="720" height="1600">
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <!-- FEATURE 2: SAFETY & EMERGENCY SUPPORT -->
        <section id="safety" class="section-padding" style="padding-top: 0;">
            <div class="container">
                <div class="safety-section">
                    <div class="safety-grid">
                        <div>
                            <div class="badge-pill-danger">
                                <i class="fa-solid fa-triangle-exclamation"></i> Critical Safety Network
                            </div>
                            <h2 class="safety-title">Instant SOS &amp; Emergency Support</h2>
                            <p class="safety-desc">
                                Your safety is our top priority. Oripori equips everyone in Nepal with a direct red SOS trigger, real-time location sharing, and 1-tap connection to emergency response services.
                            </p>

                            <div class="safety-features-grid">
                                <div class="safety-card">
                                    <i class="fa-solid fa-truck-medical safety-card-icon"></i>
                                    <h4>Ambulance &amp; Hospitals</h4>
                                    <p>Direct contact with nearest medical centers &amp; blood banks.</p>
                                </div>
                                <div class="safety-card">
                                    <i class="fa-solid fa-building-shield safety-card-icon"></i>
                                    <h4>Police &amp; Fire Dispatch</h4>
                                    <p>Instant hotline access during critical incidents.</p>
                                </div>
                                <div class="safety-card">
                                    <i class="fa-solid fa-tower-broadcast safety-card-icon"></i>
                                    <h4>Live Location Broadcast</h4>
                                    <p>Share live coordinates with chosen emergency contacts.</p>
                                </div>
                                <div class="safety-card">
                                    <i class="fa-solid fa-circle-exclamation safety-card-icon"></i>
                                    <h4>Active SOS Status</h4>
                                    <p>Clear status tiles to resolve or manage live alerts.</p>
                                </div>
                            </div>
                        </div>

                        <div class="safety-visual">
                            <img src="{{ asset('images/landing/app-emergency.jpg') }}" alt="Oripori active SOS emergency screen" loading="lazy" width="720" height="1600">
                            <img src="{{ asset('images/landing/app-sos.jpg') }}" alt="Oripori emergency support menu with quick contacts" loading="lazy" width="720" height="1600">
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- FEATURE 3: COMMUNITY INSIGHTS & LIVE ALERTS -->
        <section id="community" class="section-padding">
            <div class="container">
                <div class="feature-row reverse">
                    <div class="feature-text">
                        <div class="badge-pill">
                            <i class="fa-solid fa-people-group"></i> Community Intelligence
                        </div>
                        <h3 class="feature-title">Real-Time Road, Weather &amp; Flood Alerts</h3>
                        <p class="feature-desc">
                            Stay ahead of unpredictable weather and landslides. Community members broadcast verified local reports on foggy trail conditions, flood levels, and road blockages across Nepal.
                        </p>
                        <ul class="feature-list">
                            <li class="feature-list-item">
                                <i class="fa-solid fa-circle-check"></i>
                                <span>Categorized alerts sorted by Critical, High, and Moderate priority levels.</span>
                            </li>
                            <li class="feature-list-item">
                                <i class="fa-solid fa-circle-check"></i>
                                <span>User-submitted reports complete with real photos and exact terrain coordinates.</span>
                            </li>
                            <li class="feature-list-item">
                                <i class="fa-solid fa-circle-check"></i>
                                <span>Community upvotes and verification to ensure maximum trust and reliability.</span>
                            </li>
                        </ul>
                    </div>

                    <div class="app-screen-grid">
                        <div class="app-mockup-frame">
                            <img src="{{ asset('images/landing/app-alerts.jpg') }}" alt="Oripori live alerts list sorted by severity" loading="lazy" width="720" height="1600">
                        </div>
                        <div class="app-mockup-frame">
                            <img src="{{ asset('images/landing/app-reports.jpg') }}" alt="Oripori community road report with photo" loading="lazy" width="720" height="1600">
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <!-- FEATURE 4: ORIPORI COINS -->
        <section id="rewards" class="container">
            <div class="rewards-banner">
                <div class="rewards-content">
                    <div class="badge-pill">
                        <i class="fa-solid fa-coins"></i> Contribution &amp; Rewards
                    </div>
                    <h3>Earn Oripori Coins by Helping Your Community</h3>
                    <p>
                        When you report updates, verify safety alerts, or review local businesses, you earn Oripori Coins. Instantly withdraw your coin balances directly to popular Nepalese digital wallets.
                    </p>
                    <div class="payout-badges">
                        <span class="payout-chip"><i class="fa-solid fa-wallet"></i> eSewa Supported</span>
                        <span class="payout-chip"><i class="fa-solid fa-mobile-screen"></i> Khalti Supported</span>
                        <span class="payout-chip"><i class="fa-solid fa-building-columns"></i> Bank Transfer</span>
                    </div>
                    <a href="{{ route('web.offers') }}" class="rewards-link">
                        Explore local offers &amp; rewards <i class="fa-solid fa-arrow-right"></i>
                    </a>
                </div>

                <div class="rewards-visual">
                    <img src="{{ asset('images/landing/app-coins.jpg') }}" alt="Oripori Coins wallet and balance screen" loading="lazy" width="720" height="1600">
                </div>
            </div>
        </section>

        <!-- CORE VALUES -->
        <section id="values" class="values-section">
            <div class="container">
                <div class="section-title-wrap">
                    <div class="badge-pill">
                        <i class="fa-solid fa-heart"></i> Our Foundation
                    </div>
                    <h2 class="section-title">Built on Uncompromised Principles</h2>
                    <p class="section-subtitle">
                        Oripori is designed specifically for the unique terrain, culture, and community spirit of Nepal.
                    </p>
                </div>

                <div class="values-grid">
                    <div class="value-card">
                        <div class="value-icon"><i class="fa-solid fa-flag"></i></div>
                        <h3 class="value-title">Nepal First</h3>
                        <p class="value-desc">Custom-built for local languages, Himalayan geography, and Nepalese digital infrastructure.</p>
                    </div>
                    <div class="value-card">
                        <div class="value-icon"><i class="fa-solid fa-shield-halved"></i></div>
                        <h3 class="value-title">Trust</h3>
                        <p class="value-desc">Verified community reports and official hotline sources ensure dependable information.</p>
                    </div>
                    <div class="value-card">
                        <div class="value-icon"><i class="fa-solid fa-brain"></i></div>
                        <h3 class="value-title">Smartness</h3>
                        <p class="value-desc">AI travel assistance and intelligent routing make exploring effortless.</p>
                    </div>
                    <div class="value-card">
                        <div class="value-icon"><i class="fa-solid fa-users"></i></div>
                        <h3 class="value-title">Community</h3>
                        <p class="value-desc">Empowering citizens to share live road conditions, safety updates, and reviews.</p>
                    </div>
                    <div class="value-card">
                        <div class="value-icon"><i class="fa-solid fa-life-ring"></i></div>
                        <h3 class="value-title">Safety</h3>
                        <p class="value-desc">Dedicated emergency SOS triggers, flood alerts, and direct emergency dispatches.</p>
                    </div>
                    <div class="value-card">
                        <div class="value-icon"><i class="fa-solid fa-lock"></i></div>
                        <h3 class="value-title">Privacy</h3>
                        <p class="value-desc">Strict data storage controls and transparent settings protect user identities.</p>
                    </div>
                    <div class="value-card">
                        <div class="value-icon"><i class="fa-solid fa-store"></i></div>
                        <h3 class="value-title">Local Empowerment</h3>
                        <p class="value-desc">Promoting local eateries, trekking guides, hotels, and neighborhood shops.</p>
                    </div>
                    <div class="value-card">
                        <div class="value-icon"><i class="fa-solid fa-lightbulb"></i></div>
                        <h3 class="value-title">Innovation</h3>
                        <p class="value-desc">Next-gen mapping UI, instant wallet coin withdrawals, and real-time feeds.</p>
                    </div>
                    <div class="value-card">
                        <div class="value-icon"><i class="fa-solid fa-user-gear"></i></div>
                        <h3 class="value-title">User First</h3>
                        <p class="value-desc">Clean, accessible, light-themed layouts optimized for all smartphone devices.</p>
                    </div>
                    <div class="value-card">
                        <div class="value-icon"><i class="fa-solid fa-leaf"></i></div>
                        <h3 class="value-title">Sustainability</h3>
                        <p class="value-desc">Supporting eco-friendly trekking routes and responsible local tourism across trails.</p>
                    </div>
                    <div class="value-card">
                        <div class="value-icon"><i class="fa-solid fa-wand-magic-sparkles"></i></div>
                        <h3 class="value-title">Simplicity</h3>
                        <p class="value-desc">Zero-clutter design allows 1-tap navigation to nearby places and emergency services.</p>
                    </div>
                    <div class="value-card">
                        <div class="value-icon"><i class="fa-solid fa-network-wired"></i></div>
                        <h3 class="value-title">Connectivity</h3>
                        <p class="value-desc">Bridging rural mountain communities with urban networks through shared insights.</p>
                    </div>
                </div>
            </div>
        </section>
        <!-- INTERFACE GALLERY SHOWCASE -->
        <section class="section-padding">
            <div class="container">
                <div class="section-title-wrap">
                    <div class="badge-pill">
                        <i class="fa-solid fa-mobile"></i> Experience Oripori
                    </div>
                    <h2 class="section-title">Designed for Clarity &amp; Ease</h2>
                    <p class="section-subtitle">
                        Take a look inside the intuitive interfaces of the Oripori mobile application.
                    </p>
                </div>

                <div class="gallery-grid">
                    <div class="gallery-item">
                        <img src="{{ asset('images/landing/app-home.jpg') }}" alt="Oripori home screen with live alerts" loading="lazy" width="720" height="1600">
                    </div>
                    <div class="gallery-item">
                        <img src="{{ asset('images/landing/app-routes.jpg') }}" alt="Oripori routes and treks screen" loading="lazy" width="720" height="1600">
                    </div>
                    <div class="gallery-item">
                        <img src="{{ asset('images/landing/app-ai.jpg') }}" alt="Oripori AI travel assistant chat" loading="lazy" width="720" height="1600">
                    </div>
                    <div class="gallery-item">
                        <img src="{{ asset('images/landing/app-profile.jpg') }}" alt="Oripori profile with XP and badges" loading="lazy" width="720" height="1600">
                    </div>
                    <div class="gallery-item">
                        <img src="{{ asset('images/landing/app-reports.jpg') }}" alt="Oripori community reports feed" loading="lazy" width="720" height="1600">
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- FOOTER -->
    <footer class="site-footer">
        <div class="container">
            <div class="footer-top">
                <div class="footer-brand">
                    <div class="footer-logo">
                        <img src="{{ asset('images/oripori_logo_wordmark.png') }}" alt="Oripori White Logo">
                    </div>
                    <p>
                        A Nepal-first platform connecting people with what&rsquo;s around them&mdash;local information, community insights, safety, places, and businesses.
                    </p>
                </div>

                <div>
                    <h4 class="footer-heading">Services</h4>
                    <ul class="footer-links">
                        <li><a href="{{ route('web.places') }}">Places &amp; Trails</a></li>
                        <li><a href="{{ route('web.category', 'hotels') }}">Hotels &amp; Stays</a></li>
                        <li><a href="{{ route('web.category', 'restaurants') }}">Restaurants</a></li>
                        <li><a href="{{ route('web.routes') }}">Curated Routes</a></li>
                    </ul>
                </div>

                <div>
                    <h4 class="footer-heading">Company</h4>
                    <ul class="footer-links">
                        <li><a href="#">About Us</a></li>
                        <li><a href="{{ route('partner.register') }}">Partner With Us</a></li>
                        <li><a href="#">Careers</a></li>
                        <li><a href="#">Blog</a></li>
                    </ul>
                </div>

                <div>
                    <h4 class="footer-heading">Support</h4>
                    <ul class="footer-links">
                        <li><a href="#">Help Center</a></li>
                        <li><a href="#">Safety</a></li>
                        <li><a href="{{ route('web.legal.show', 'terms') }}">Terms of Service</a></li>
                        <li><a href="{{ route('web.legal.show', 'privacy') }}">Privacy Policy</a></li>
                    </ul>
                </div>
            </div>

            <div class="footer-bottom">
                <div>&copy; Oripori. All rights reserved.</div>
            </div>
        </div>
    </footer>

    <script>
        (function () {
            // App detection: visiting via the app's webview sets this cookie
            if (new URLSearchParams(window.location.search).has('app')) {
                document.cookie = 'nst_app=1; path=/; max-age=31536000';
            }

            // Mobile navigation toggle
            var toggle = document.getElementById('nav-toggle');
            var menu = document.getElementById('nav-menu');
            if (toggle && menu) {
                toggle.addEventListener('click', function () {
                    var open = menu.classList.toggle('is-open');
                    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                    var icon = toggle.querySelector('i');
                    if (icon) icon.className = open ? 'fa-solid fa-xmark' : 'fa-solid fa-bars';
                });
                menu.querySelectorAll('a').forEach(function (link) {
                    link.addEventListener('click', function () {
                        menu.classList.remove('is-open');
                        toggle.setAttribute('aria-expanded', 'false');
                        var icon = toggle.querySelector('i');
                        if (icon) icon.className = 'fa-solid fa-bars';
                    });
                });
            }
        })();
    </script>
</body>
</html>
