<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Nepal Smart Travel & Local Intelligence Platform — discover places, live road conditions, routes, and exclusive local offers.">
    <title>Nepal Smart Travel - Discover Nepal, Live & Local</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #020e0e;
            color: #fff;
            overflow-x: hidden;
            position: relative;
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background:
                radial-gradient(ellipse at 15% 20%, rgba(245,158,11,0.15) 0%, transparent 50%),
                radial-gradient(ellipse at 85% 80%, rgba(16,185,129,0.1) 0%, transparent 50%),
                radial-gradient(ellipse at 50% 50%, rgba(245,158,11,0.06) 0%, transparent 60%);
            pointer-events: none;
            z-index: 0;
        }

        .orb {
            position: fixed;
            border-radius: 50%;
            filter: blur(100px);
            pointer-events: none;
            z-index: 0;
        }
        .orb-1 { width: 500px; height: 500px; background: rgba(245,158,11,0.12); top: -150px; right: -100px; animation: orbFloat 25s ease-in-out infinite; }
        .orb-2 { width: 400px; height: 400px; background: rgba(16,185,129,0.08); bottom: 5%; left: -150px; animation: orbFloat 30s ease-in-out infinite reverse; }
        .orb-3 { width: 300px; height: 300px; background: rgba(245,158,11,0.06); top: 50%; right: 10%; animation: orbFloat 20s ease-in-out infinite 3s; }

        @keyframes orbFloat {
            0%,100% { transform: translate(0,0) scale(1); }
            33% { transform: translate(40px,-40px) scale(1.1); }
            66% { transform: translate(-30px,30px) scale(0.9); }
        }

        /* NAVBAR */
        .k-nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem 3rem;
            position: sticky;
            top: 0;
            z-index: 100;
            backdrop-filter: blur(24px);
            background: rgba(2,14,14,0.7);
            border-bottom: 1px solid rgba(255,255,255,0.04);
        }

        .k-logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .k-logo-icon {
            width: 42px; height: 42px;
            background: linear-gradient(135deg, #f59e0b, #ea580c);
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 8px 32px rgba(245,158,11,0.3);
            position: relative; overflow: hidden;
        }
        .k-logo-icon::after {
            content: '';
            position: absolute;
            inset: -50%;
            background: linear-gradient(45deg, transparent 30%, rgba(255,255,255,0.25) 50%, transparent 70%);
            animation: shine 4s infinite;
        }
        @keyframes shine {
            0% { transform: translateX(-100%) rotate(45deg); }
            100% { transform: translateX(100%) rotate(45deg); }
        }

        .k-logo-text h1 {
            font-family: 'Outfit', sans-serif;
            font-size: 1.2rem; font-weight: 800;
            background: linear-gradient(135deg, #fff, #e2e8f0);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            margin: 0; line-height: 1.1;
        }
        .k-logo-text span {
            font-size: 0.65rem; color: #5eead4;
            font-weight: 600; letter-spacing: 0.1em; text-transform: uppercase;
        }

        .k-nav-links {
            display: flex; gap: 2rem; list-style: none; margin: 0; padding: 0;
        }
        .k-nav-links a {
            color: rgba(255,255,255,0.55);
            text-decoration: none; font-size: 0.85rem; font-weight: 500;
            transition: all 0.3s ease; position: relative; padding: 0.25rem 0;
        }
        .k-nav-links a:hover { color: #fff; }
        .k-nav-links a::after {
            content: ''; position: absolute; bottom: -2px; left: 50%;
            width: 0; height: 2px;
            background: linear-gradient(90deg, #f59e0b, #ea580c);
            transition: all 0.3s ease; transform: translateX(-50%); border-radius: 2px;
        }
        .k-nav-links a:hover::after { width: 100%; }

        .k-nav-cta {
            background: linear-gradient(135deg, #f59e0b, #ea580c);
            color: #fff; border: none; padding: 0.6rem 1.4rem;
            border-radius: 10px; font-weight: 600; font-size: 0.85rem;
            cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem;
            box-shadow: 0 4px 20px rgba(245,158,11,0.25);
            transition: all 0.3s ease; text-decoration: none;
        }
        .k-nav-cta:hover { transform: translateY(-2px); box-shadow: 0 8px 30px rgba(245,158,11,0.4); }

        /* HERO */
        .k-hero {
            display: grid; grid-template-columns: 1fr 1fr;
            align-items: center; max-width: 1400px;
            margin: 0 auto; padding: 5rem 3rem 7rem;
            gap: 4rem; position: relative; z-index: 2;
            min-height: calc(100vh - 80px);
        }

        .k-hero-left { animation: slideInLeft 1s cubic-bezier(0.4,0,0.2,1); }

        .k-badge {
            display: inline-flex; align-items: center; gap: 0.5rem;
            background: rgba(245,158,11,0.08);
            border: 1px solid rgba(245,158,11,0.15);
            padding: 0.4rem 1rem; border-radius: 100px;
            font-size: 0.8rem; color: #fbbf24; font-weight: 500;
            margin-bottom: 1.5rem; backdrop-filter: blur(10px);
        }
        .k-badge-dot {
            width: 6px; height: 6px; background: #f59e0b;
            border-radius: 50%; box-shadow: 0 0 12px rgba(245,158,11,0.6);
            animation: pulse 2s infinite;
        }

        .k-hero h2 {
            font-family: 'Outfit', sans-serif;
            font-size: clamp(3rem, 5.5vw, 5rem);
            font-weight: 900; line-height: 1.05;
            margin: 0 0 1.25rem; letter-spacing: -0.03em;
        }
        .k-hero .line1 {
            display: block;
            background: linear-gradient(135deg, #fff, #94a3b8);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .k-hero .line2 {
            display: block;
            background: linear-gradient(135deg, #f59e0b, #fb923c, #ea580c);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            margin-top: 0.125rem;
        }

        .k-hero p {
            font-size: 1.15rem; color: rgba(255,255,255,0.5);
            line-height: 1.8; margin: 0 0 2rem; max-width: 500px;
        }

        .k-hero-btns { display: flex; gap: 1rem; margin-bottom: 3rem; }

        .k-btn-primary {
            background: linear-gradient(135deg, #f59e0b, #ea580c);
            color: #fff; border: none; padding: 0.9rem 2rem;
            border-radius: 14px; font-weight: 600; font-size: 1rem;
            cursor: pointer; display: inline-flex; align-items: center; gap: 0.625rem;
            box-shadow: 0 10px 40px rgba(245,158,11,0.3);
            transition: all 0.3s cubic-bezier(0.4,0,0.2,1);
            text-decoration: none; position: relative; overflow: hidden;
        }
        .k-btn-primary:hover { transform: translateY(-3px) scale(1.02); box-shadow: 0 14px 50px rgba(245,158,11,0.4); }

        .k-btn-secondary {
            background: rgba(255,255,255,0.04);
            color: #fff; border: 1px solid rgba(255,255,255,0.1);
            padding: 0.9rem 2rem; border-radius: 14px;
            font-weight: 600; font-size: 1rem; cursor: pointer;
            display: inline-flex; align-items: center; gap: 0.625rem;
            backdrop-filter: blur(20px);
            transition: all 0.3s ease; text-decoration: none;
        }
        .k-btn-secondary:hover { background: rgba(255,255,255,0.08); border-color: rgba(255,255,255,0.2); transform: translateY(-3px); }

        .k-hero-stats { display: flex; gap: 3rem; }
        .k-hstat { position: relative; }
        .k-hstat::after {
            content: ''; position: absolute; right: -1.5rem; top: 50%;
            transform: translateY(-50%); width: 1px; height: 35px;
            background: rgba(255,255,255,0.08);
        }
        .k-hstat:last-child::after { display: none; }
        .k-hstat-num {
            font-family: 'Outfit', sans-serif; font-size: 2rem; font-weight: 800;
            background: linear-gradient(135deg, #f59e0b, #fb923c);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            display: block; line-height: 1;
        }
        .k-hstat-label {
            font-size: 0.7rem; color: rgba(255,255,255,0.35);
            text-transform: uppercase; letter-spacing: 0.12em;
            margin-top: 0.5rem; font-weight: 500;
        }

        /* Hero Right - Phone Mockup */
        .k-hero-right {
            position: relative; animation: slideInRight 1s cubic-bezier(0.4,0,0.2,1) 0.2s both;
            display: flex; justify-content: center;
        }

        .k-phone {
            width: 280px; height: 560px;
            background: linear-gradient(180deg, #0a1f1f 0%, #061414 100%);
            border-radius: 40px; padding: 12px;
            border: 1px solid rgba(255,255,255,0.08);
            box-shadow:
                0 50px 100px -20px rgba(0,0,0,0.7),
                0 30px 60px -30px rgba(245,158,11,0.15),
                inset 0 1px 0 rgba(255,255,255,0.1);
            position: relative; overflow: hidden;
        }
        .k-phone::before {
            content: ''; position: absolute; top: 0; left: 50%; transform: translateX(-50%);
            width: 120px; height: 30px; background: #020e0e;
            border-bottom-left-radius: 16px; border-bottom-right-radius: 16px;
            z-index: 5;
        }

        .k-phone-screen {
            width: 100%; height: 100%;
            background: linear-gradient(180deg, rgba(245,158,11,0.05) 0%, transparent 40%, #020e0e 100%);
            border-radius: 32px; overflow: hidden; position: relative;
            border: 1px solid rgba(255,255,255,0.04);
        }

        .k-phone-header {
            padding: 2.5rem 1.5rem 1rem;
            display: flex; align-items: center; gap: 0.75rem;
        }
        .k-ph-avatar {
            width: 36px; height: 36px; border-radius: 50%;
            background: linear-gradient(135deg, #f59e0b, #ea580c);
            display: flex; align-items: center; justify-content: center;
            font-size: 0.75rem; font-weight: 700; color: #fff;
        }
        .k-ph-info h4 { margin: 0; font-size: 0.8rem; font-weight: 600; color: #fff; }
        .k-ph-info span { font-size: 0.65rem; color: rgba(255,255,255,0.4); }

        .k-phone-map {
            height: 160px; margin: 0 1rem;
            background: linear-gradient(135deg, rgba(245,158,11,0.08), rgba(16,185,129,0.03));
            border-radius: 16px; position: relative; overflow: hidden;
            border: 1px solid rgba(255,255,255,0.04);
        }
        .k-ph-grid {
            position: absolute; inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,0.02) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.02) 1px, transparent 1px);
            background-size: 16px 16px;
        }
        .k-ph-route {
            position: absolute; top: 35%; left: 15%; right: 15%;
            height: 3px; background: linear-gradient(90deg, #f59e0b, #ea580c);
            border-radius: 3px; box-shadow: 0 0 20px rgba(245,158,11,0.3);
        }
        .k-ph-route::before, .k-ph-route::after {
            content: ''; position: absolute; width: 10px; height: 10px;
            background: #f59e0b; border-radius: 50%; top: 50%; transform: translateY(-50%);
            box-shadow: 0 0 15px rgba(245,158,11,0.5);
        }
        .k-ph-route::before { left: 0; }
        .k-ph-route::after { right: 0; }

        .k-phone-cards {
            padding: 1rem; display: flex; flex-direction: column; gap: 0.75rem;
        }
        .k-ph-card {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 12px; padding: 0.875rem;
            display: flex; align-items: center; gap: 0.75rem;
            backdrop-filter: blur(10px);
        }
        .k-ph-card-icon {
            width: 32px; height: 32px; border-radius: 8px;
            background: rgba(245,158,11,0.1);
            display: flex; align-items: center; justify-content: center;
            color: #f59e0b; flex-shrink: 0;
        }
        .k-ph-card-info h5 { margin: 0; font-size: 0.75rem; font-weight: 600; color: #fff; }
        .k-ph-card-info span { font-size: 0.65rem; color: rgba(255,255,255,0.4); }

        /* Floating elements around phone */
        .k-float-tag {
            position: absolute; background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px; padding: 0.75rem 1rem;
            backdrop-filter: blur(20px); font-size: 0.8rem; font-weight: 500;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            animation: floatTag 6s ease-in-out infinite;
        }
        .k-float-tag-1 { top: 10%; left: -20px; animation-delay: 0s; }
        .k-float-tag-2 { bottom: 20%; right: -30px; animation-delay: -3s; }
        .k-float-tag-3 { top: 50%; left: -40px; animation-delay: -1.5s; }

        @keyframes floatTag {
            0%,100% { transform: translateY(0); }
            50% { transform: translateY(-15px); }
        }

        @keyframes slideInLeft {
            from { opacity: 0; transform: translateX(-60px); }
            to { opacity: 1; transform: translateX(0); }
        }
        @keyframes slideInRight {
            from { opacity: 0; transform: translateX(60px); }
            to { opacity: 1; transform: translateX(0); }
        }
        @keyframes pulse {
            0%,100% { opacity: 1; transform: scale(1); box-shadow: 0 0 10px rgba(245,158,11,0.5); }
            50% { opacity: 0.5; transform: scale(1.4); box-shadow: 0 0 25px rgba(245,158,11,0.8); }
        }

        /* SECTION HEADERS */
        .k-section {
            position: relative; z-index: 2;
            padding: 6rem 3rem; max-width: 1400px; margin: 0 auto;
        }

        .k-section-header {
            text-align: center; margin-bottom: 4rem;
        }
        .k-section-tag {
            display: inline-block;
            background: rgba(245,158,11,0.08); border: 1px solid rgba(245,158,11,0.15);
            padding: 0.4rem 1rem; border-radius: 100px;
            font-size: 0.8rem; color: #fbbf24; font-weight: 600;
            margin-bottom: 1rem; text-transform: uppercase; letter-spacing: 0.1em;
        }
        .k-section-header h3 {
            font-family: 'Outfit', sans-serif;
            font-size: clamp(2rem, 4vw, 3rem); font-weight: 800;
            margin: 0 0 1rem; letter-spacing: -0.02em;
        }
        .k-section-header p {
            font-size: 1.1rem; color: rgba(255,255,255,0.45);
            max-width: 600px; margin: 0 auto; line-height: 1.7;
        }

        /* SERVICES */
        .k-services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
        }

        .k-service-card {
            background: rgba(255,255,255,0.02);
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 20px; padding: 2rem;
            backdrop-filter: blur(20px);
            transition: all 0.4s cubic-bezier(0.4,0,0.2,1);
            position: relative; overflow: hidden;
            cursor: pointer;
        }
        .k-service-card::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; height: 1px;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
        }
        .k-service-card:hover {
            transform: translateY(-8px);
            border-color: rgba(245,158,11,0.2);
            box-shadow: 0 20px 60px rgba(0,0,0,0.4), 0 0 40px rgba(245,158,11,0.05);
        }

        .k-svc-icon {
            width: 56px; height: 56px; border-radius: 16px;
            background: linear-gradient(135deg, rgba(245,158,11,0.15), rgba(234,88,12,0.1));
            border: 1px solid rgba(245,158,11,0.15);
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 1.25rem; color: #f59e0b;
            transition: all 0.3s ease;
        }
        .k-service-card:hover .k-svc-icon {
            transform: scale(1.1) rotate(-5deg);
            box-shadow: 0 8px 25px rgba(245,158,11,0.2);
        }

        .k-service-card h4 {
            font-family: 'Outfit', sans-serif;
            font-size: 1.15rem; font-weight: 700; margin: 0 0 0.5rem;
        }
        .k-service-card p {
            font-size: 0.9rem; color: rgba(255,255,255,0.45);
            line-height: 1.6; margin: 0;
        }

        /* PLACES CARDS */
        .k-places-grid {
            display: grid; grid-template-columns: repeat(5, 1fr); gap: 1.25rem;
        }
        .k-place-card {
            position: relative; border-radius: 20px; overflow: hidden;
            aspect-ratio: 3/4; cursor: pointer;
            border: 1px solid rgba(255,255,255,0.06);
            transition: all 0.4s cubic-bezier(0.4,0,0.2,1);
        }
        .k-place-card:hover {
            transform: translateY(-8px) scale(1.02);
            border-color: rgba(245,158,11,0.3);
            box-shadow: 0 20px 60px rgba(0,0,0,0.5), 0 0 40px rgba(245,158,11,0.1);
        }
        .k-place-card-img {
            position: absolute; inset: 0;
            background-size: cover; background-position: center;
            transition: transform 0.6s cubic-bezier(0.4,0,0.2,1);
        }
        .k-place-card:hover .k-place-card-img {
            transform: scale(1.1);
        }
        .k-place-card-overlay {
            position: absolute; inset: 0;
            background: linear-gradient(180deg, transparent 30%, rgba(2,14,14,0.85) 70%, rgba(2,14,14,0.95) 100%);
        }
        .k-place-card-content {
            position: absolute; bottom: 0; left: 0; right: 0;
            padding: 1.5rem; z-index: 2;
        }
        .k-place-card-icon {
            width: 40px; height: 40px; border-radius: 12px;
            background: rgba(245,158,11,0.15);
            border: 1px solid rgba(245,158,11,0.2);
            display: flex; align-items: center; justify-content: center;
            color: #f59e0b; margin-bottom: 0.75rem;
            backdrop-filter: blur(10px);
        }
        .k-place-card h4 {
            font-family: 'Outfit', sans-serif;
            font-size: 1.1rem; font-weight: 700; margin: 0 0 0.25rem; color: #fff;
        }
        .k-place-card p {
            font-size: 0.8rem; color: rgba(255,255,255,0.5); margin: 0;
        }
        .k-place-card-count {
            position: absolute; top: 1rem; right: 1rem; z-index: 2;
            background: rgba(2,14,14,0.6); backdrop-filter: blur(12px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 100px; padding: 0.3rem 0.75rem;
            font-size: 0.7rem; font-weight: 600; color: #fbbf24;
        }

        /* FEATURES */
        .k-features-grid {
            display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem;
        }
        .k-feature {
            background: rgba(255,255,255,0.02);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 20px; padding: 2rem;
            backdrop-filter: blur(20px);
            transition: all 0.4s ease;
        }
        .k-feature:hover {
            border-color: rgba(245,158,11,0.15);
            transform: translateY(-5px);
        }
        .k-feature-icon {
            width: 48px; height: 48px; border-radius: 14px;
            background: rgba(245,158,11,0.08);
            border: 1px solid rgba(245,158,11,0.12);
            display: flex; align-items: center; justify-content: center;
            color: #f59e0b; margin-bottom: 1rem;
        }
        .k-feature h4 {
            font-family: 'Outfit', sans-serif;
            font-size: 1.1rem; font-weight: 700; margin: 0 0 0.5rem;
        }
        .k-feature p {
            font-size: 0.9rem; color: rgba(255,255,255,0.4);
            line-height: 1.6; margin: 0;
        }

        /* STATS BAR */
        .k-stats-bar {
            display: flex; justify-content: center; gap: 5rem;
            padding: 3rem; margin: 2rem auto; max-width: 1000px;
            background: rgba(255,255,255,0.02);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 24px; backdrop-filter: blur(20px);
            position: relative; z-index: 2;
        }
        .k-sbar-stat { text-align: center; }
        .k-sbar-num {
            font-family: 'Outfit', sans-serif; font-size: 2.5rem; font-weight: 800;
            background: linear-gradient(135deg, #f59e0b, #fb923c);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .k-sbar-label {
            font-size: 0.8rem; color: rgba(255,255,255,0.4);
            text-transform: uppercase; letter-spacing: 0.1em; margin-top: 0.5rem;
        }

        /* TRENDING DESTINATIONS */
        .k-dest-grid {
            display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem;
        }
        .k-dest-card {
            position: relative; border-radius: 20px; overflow: hidden;
            height: 320px; cursor: pointer;
            border: 1px solid rgba(255,255,255,0.06);
            transition: all 0.4s cubic-bezier(0.4,0,0.2,1);
        }
        .k-dest-card:hover {
            transform: translateY(-8px);
            border-color: rgba(245,158,11,0.3);
            box-shadow: 0 20px 60px rgba(0,0,0,0.5), 0 0 40px rgba(245,158,11,0.1);
        }
        .k-dest-card-img {
            position: absolute; inset: 0;
            background-size: cover; background-position: center;
            transition: transform 0.6s cubic-bezier(0.4,0,0.2,1);
        }
        .k-dest-card:hover .k-dest-card-img {
            transform: scale(1.08);
        }
        .k-dest-card-overlay {
            position: absolute; inset: 0;
            background: linear-gradient(180deg, transparent 40%, rgba(2,14,14,0.9) 100%);
        }
        .k-dest-card-content {
            position: absolute; bottom: 0; left: 0; right: 0;
            padding: 1.75rem; z-index: 2;
        }
        .k-dest-card-tag {
            display: inline-flex; align-items: center; gap: 0.4rem;
            background: rgba(245,158,11,0.15); backdrop-filter: blur(10px);
            border: 1px solid rgba(245,158,11,0.2);
            border-radius: 100px; padding: 0.3rem 0.75rem;
            font-size: 0.7rem; font-weight: 600; color: #fbbf24;
            margin-bottom: 0.75rem;
        }
        .k-dest-card h4 {
            font-family: 'Outfit', sans-serif;
            font-size: 1.4rem; font-weight: 800; margin: 0 0 0.25rem; color: #fff;
        }
        .k-dest-card p {
            font-size: 0.85rem; color: rgba(255,255,255,0.55); margin: 0 0 1rem;
            line-height: 1.5;
        }
        .k-dest-card-meta {
            display: flex; align-items: center; gap: 1.25rem;
            font-size: 0.75rem; color: rgba(255,255,255,0.5);
        }
        .k-dest-card-meta span {
            display: inline-flex; align-items: center; gap: 0.35rem;
        }
        .k-dest-card-meta i { color: #f59e0b; }

        /* CTA SECTION */
        .k-cta {
            text-align: center; padding: 6rem 3rem;
            position: relative; z-index: 2;
            background: linear-gradient(180deg, transparent 0%, rgba(245,158,11,0.03) 50%, transparent 100%);
        }
        .k-cta-inner {
            max-width: 800px; margin: 0 auto;
            background: rgba(255,255,255,0.02);
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 32px; padding: 4rem 3rem;
            backdrop-filter: blur(30px);
            position: relative; overflow: hidden;
        }
        .k-cta-inner::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; height: 1px;
            background: linear-gradient(90deg, transparent, rgba(245,158,11,0.3), transparent);
        }
        .k-cta h3 {
            font-family: 'Outfit', sans-serif;
            font-size: clamp(2rem, 4vw, 3rem); font-weight: 800;
            margin: 0 0 1rem;
        }
        .k-cta p {
            font-size: 1.1rem; color: rgba(255,255,255,0.5);
            margin: 0 0 2rem; line-height: 1.7;
        }
        .k-cta-btns { display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; }

        .k-store-btn {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 14px; padding: 0.875rem 1.5rem;
            display: inline-flex; align-items: center; gap: 0.75rem;
            color: #fff; text-decoration: none;
            transition: all 0.3s ease; backdrop-filter: blur(20px);
        }
        .k-store-btn:hover {
            background: rgba(255,255,255,0.1);
            border-color: rgba(255,255,255,0.2);
            transform: translateY(-3px);
        }
        .k-store-btn strong { display: block; font-size: 0.9rem; }
        .k-store-btn span { font-size: 0.7rem; color: rgba(255,255,255,0.5); }

        /* FOOTER */
        .k-footer {
            border-top: 1px solid rgba(255,255,255,0.04);
            padding: 4rem 3rem 2rem; position: relative; z-index: 2;
            background: rgba(0,0,0,0.2);
        }
        .k-footer-inner {
            max-width: 1400px; margin: 0 auto;
            display: grid; grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 3rem; margin-bottom: 3rem;
        }
        .k-footer-brand h4 {
            font-family: 'Outfit', sans-serif; font-size: 1.3rem; font-weight: 800;
            margin: 0 0 1rem;
            background: linear-gradient(135deg, #fff, #e2e8f0);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .k-footer-brand p {
            font-size: 0.9rem; color: rgba(255,255,255,0.4);
            line-height: 1.7; margin: 0 0 1.5rem; max-width: 300px;
        }
        .k-footer-col h5 {
            font-family: 'Outfit', sans-serif; font-size: 0.9rem; font-weight: 700;
            margin: 0 0 1.25rem; color: #fff;
        }
        .k-footer-col ul { list-style: none; margin: 0; padding: 0; }
        .k-footer-col li { margin-bottom: 0.75rem; }
        .k-footer-col a {
            color: rgba(255,255,255,0.4); text-decoration: none;
            font-size: 0.85rem; transition: all 0.3s ease;
        }
        .k-footer-col a:hover { color: #f59e0b; }
        .k-footer-bottom {
            text-align: center; padding-top: 2rem;
            border-top: 1px solid rgba(255,255,255,0.04);
            font-size: 0.8rem; color: rgba(255,255,255,0.3);
        }

        /* MOBILE */
        @media (max-width: 1024px) {
            .k-hero { grid-template-columns: 1fr; text-align: center; padding: 3rem 1.5rem; }
            .k-hero-left { order: 2; }
            .k-hero-right { order: 1; margin-bottom: 2rem; }
            .k-hero p { margin: 0 auto 2rem; }
            .k-hero-btns { justify-content: center; }
            .k-hero-stats { justify-content: center; }
            .k-places-grid { grid-template-columns: repeat(3, 1fr); }
            .k-features-grid { grid-template-columns: 1fr; }
            .k-dest-grid { grid-template-columns: 1fr; }
            .k-footer-inner { grid-template-columns: 1fr; gap: 2rem; }
            .k-nav-links { display: none; }
            .k-nav { padding: 1rem 1.5rem; }
            .k-section { padding: 4rem 1.5rem; }
            .k-stats-bar { flex-wrap: wrap; gap: 2rem; padding: 2rem; }
        }
        @media (max-width: 640px) {
            .k-places-grid { grid-template-columns: repeat(2, 1fr); }
            .k-phone { width: 240px; height: 480px; }
        }
    </style>
</head>
<body>
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="orb orb-3"></div>

    <!-- NAVBAR -->
    <nav class="k-nav">
        <a href="{{ route('web.home') }}" class="k-logo" style="text-decoration:none">
            <div style="background:rgba(255,255,255,0.9);border-radius:16px;padding:8px 12px;display:flex;align-items:center;box-shadow:0 4px 20px rgba(0,0,0,0.3)">
                <img src="{{ asset('images/oripori_logo.png') }}" alt="Oripori" style="height:80px;width:auto;object-fit:contain">
            </div>
        </a>
        <ul class="k-nav-links">
            <li><a href="#services">Services</a></li>
            <li><a href="#places">Places</a></li>
            <li><a href="#features">Features</a></li>
            <li><a href="#destinations">Destinations</a></li>
            <li><a href="{{ route('partner.login') }}">Partner</a></li>
            <li><a href="#download">Download</a></li>
        </ul>
        <a href="{{ env('PLAY_STORE_URL', '#download') }}" class="k-nav-cta">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                <polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
            </svg>
            Get the App
        </a>
    </nav>

    <!-- HERO -->
    <section class="k-hero">
        <div class="k-hero-left">
            <div class="k-badge">
                <span class="k-badge-dot"></span>
                {{ number_format($placesCount) }}+ places mapped across Nepal
            </div>
            <h2>
                <span class="line1">Discover Nepal,</span>
                <span class="line2">Live & Local.</span>
            </h2>
            <p>Real-time road conditions, community reports, hidden gems, curated routes, and exclusive local offers — powered by locals, for travelers.</p>
            <div class="k-hero-btns">
                <a href="{{ route('web.places') }}" class="k-btn-primary">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polygon points="1 6 1 22 8 18 16 22 21 18 21 2 16 6 8 2 1 6"/>
                        <line x1="8" y1="2" x2="8" y2="18"/><line x1="16" y1="6" x2="16" y2="22"/>
                    </svg>
                    Explore Places
                </a>
                <a href="{{ env('PLAY_STORE_URL', '#download') }}" class="k-btn-secondary">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/>
                    </svg>
                    Get the App
                </a>
            </div>
            <div class="k-hero-stats">
                <div class="k-hstat"><span class="k-hstat-num" data-target="78">0</span><span class="k-hstat-label">K+ Places</span></div>
                <div class="k-hstat"><span class="k-hstat-num" data-target="12">0</span><span class="k-hstat-label">K+ Guides</span></div>
                <div class="k-hstat"><span class="k-hstat-num" data-target="4.9">0</span><span class="k-hstat-label">App Rating</span></div>
                <div class="k-hstat"><span class="k-hstat-num" data-target="77">0</span><span class="k-hstat-label">Districts</span></div>
            </div>
        </div>

        <div class="k-hero-right">
            <div class="k-float-tag k-float-tag-1"><i class="fas fa-mountain"></i> Everest Base Camp</div>
            <div class="k-float-tag k-float-tag-2"><i class="fas fa-road"></i> Road: Clear</div>
            <div class="k-float-tag k-float-tag-3"><i class="fas fa-star"></i> 4.9 Rating</div>

            <div class="k-phone">
                <div class="k-phone-screen">
                    <div class="k-phone-header">
                        <div class="k-ph-avatar">NP</div>
                        <div class="k-ph-info"><h4>Pokhara &rarr; Mustang</h4><span>Curated Route &bull; 156km</span></div>
                    </div>
                    <div class="k-phone-map"><div class="k-ph-grid"></div><div class="k-ph-route"></div></div>
                    <div class="k-phone-cards">
                        <div class="k-ph-card">
                            <div class="k-ph-card-icon"><i class="fas fa-road"></i></div>
                            <div class="k-ph-card-info"><h5>Road Condition</h5><span>Good &bull; Updated 5m ago</span></div>
                        </div>
                        <div class="k-ph-card">
                            <div class="k-ph-card-icon"><i class="fas fa-clock"></i></div>
                            <div class="k-ph-card-info"><h5>Est. Time</h5><span>6h 30m via Beni</span></div>
                        </div>
                        <div class="k-ph-card">
                            <div class="k-ph-card-icon"><i class="fas fa-tag"></i></div>
                            <div class="k-ph-card-info"><h5>Local Deals</h5><span>3 offers near you</span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- SERVICES -->
    <section class="k-section" id="services">
        <div class="k-section-header">
            <span class="k-section-tag">Our Services</span>
            <h3>One App, Endless Possibilities</h3>
            <p>From hidden trails to luxury stays, Nepal Smart Travel has everything you need to explore Nepal like a local.</p>
        </div>
        <div class="k-services-grid">
            <a href="{{ route('web.places') }}" class="k-service-card" style="text-decoration:none;color:inherit">
                <div class="k-svc-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 22h20L12 2z"/></svg>
                </div>
                <h4>Places & Trails</h4>
                <p>Discover {{ number_format($placesCount) }}+ mapped locations including hidden trails, viewpoints, and local gems across all 77 districts.</p>
            </a>
            <a href="{{ route('web.category', 'hotels') }}" class="k-service-card" style="text-decoration:none;color:inherit">
                <div class="k-svc-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                </div>
                <h4>Hotels & Stays</h4>
                <p>From budget homestays in remote villages to luxury resorts in Pokhara — book with local prices and real reviews.</p>
            </a>
            <a href="{{ route('web.category', 'restaurants') }}" class="k-service-card" style="text-decoration:none;color:inherit">
                <div class="k-svc-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8h1a4 4 0 0 1 0 8h-1"/><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"/><line x1="6" y1="1" x2="6" y2="4"/><line x1="10" y1="1" x2="10" y2="4"/><line x1="14" y1="1" x2="14" y2="4"/></svg>
                </div>
                <h4>Restaurants & Food</h4>
                <p>Find authentic local cuisine, from Newari bhoj in Kathmandu to Thakali thali in Mustang. Community verified.</p>
            </a>
            <a href="{{ route('web.routes') }}" class="k-service-card" style="text-decoration:none;color:inherit">
                <div class="k-svc-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/></svg>
                </div>
                <h4>Curated Routes</h4>
                <p>Pre-planned routes for every type of traveler — adventure, spiritual, cultural, or scenic road trips.</p>
            </a>
            <a href="{{ route('web.offers') }}" class="k-service-card" style="text-decoration:none;color:inherit">
                <div class="k-svc-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                </div>
                <h4>Local Offers</h4>
                <p>Exclusive discounts from local businesses — hotels, restaurants, adventure sports, and souvenir shops.</p>
            </a>
            <div class="k-service-card">
                <div class="k-svc-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </div>
                <h4>Community Reports</h4>
                <p>Real-time updates from fellow travelers — road conditions, weather, safety alerts, and hidden spots.</p>
            </div>
        </div>
    </section>

    <!-- POPULAR PLACES -->
    <section class="k-section" id="places">
        <div class="k-section-header">
            <span class="k-section-tag">Explore Nepal</span>
            <h3>Popular Places & Categories</h3>
            <p>From ancient temples to mountain trails — discover Nepal by category.</p>
        </div>
        <div class="k-places-grid">
            <a href="{{ route('web.category', 'hotels') }}" class="k-place-card" style="text-decoration:none">
                <div class="k-place-card-img" style="background-image:url('https://images.unsplash.com/photo-1566073771259-6a8506099945?w=600&q=80')"></div>
                <div class="k-place-card-overlay"></div>
                <div class="k-place-card-count"><i class="fas fa-bed"></i> Hotels</div>
                <div class="k-place-card-content">
                    <div class="k-place-card-icon"><i class="fas fa-hotel"></i></div>
                    <h4>Hotels & Stays</h4>
                    <p>Homestays to luxury resorts</p>
                </div>
            </a>
            <a href="{{ route('web.category', 'restaurants') }}" class="k-place-card" style="text-decoration:none">
                <div class="k-place-card-img" style="background-image:url('https://images.unsplash.com/photo-1555396273-367ea4eb4db5?w=600&q=80')"></div>
                <div class="k-place-card-overlay"></div>
                <div class="k-place-card-count"><i class="fas fa-utensils"></i> Restaurants</div>
                <div class="k-place-card-content">
                    <div class="k-place-card-icon"><i class="fas fa-utensils"></i></div>
                    <h4>Restaurants & Food</h4>
                    <p>Authentic Nepali cuisine</p>
                </div>
            </a>
            <a href="{{ route('web.category', 'attractions') }}" class="k-place-card" style="text-decoration:none">
                <div class="k-place-card-img" style="background-image:url('https://images.unsplash.com/photo-1544735716-392fe2489ffa?w=600&q=80')"></div>
                <div class="k-place-card-overlay"></div>
                <div class="k-place-card-count"><i class="fas fa-landmark"></i> Attractions</div>
                <div class="k-place-card-content">
                    <div class="k-place-card-icon"><i class="fas fa-landmark"></i></div>
                    <h4>Attractions</h4>
                    <p>Temples, forts & heritage</p>
                </div>
            </a>
            <a href="{{ route('web.category', 'cafes') }}" class="k-place-card" style="text-decoration:none">
                <div class="k-place-card-img" style="background-image:url('https://images.unsplash.com/photo-1554118811-1e0d58224f24?w=600&q=80')"></div>
                <div class="k-place-card-overlay"></div>
                <div class="k-place-card-count"><i class="fas fa-mug-hot"></i> Cafes</div>
                <div class="k-place-card-content">
                    <div class="k-place-card-icon"><i class="fas fa-mug-hot"></i></div>
                    <h4>Cafes & hangouts</h4>
                    <p>Cozy spots & live music</p>
                </div>
            </a>
            <a href="{{ route('web.category', 'activities') }}" class="k-place-card" style="text-decoration:none">
                <div class="k-place-card-img" style="background-image:url('https://images.unsplash.com/photo-1551632811-561732d1e306?w=600&q=80')"></div>
                <div class="k-place-card-overlay"></div>
                <div class="k-place-card-count"><i class="fas fa-person-hiking"></i> Activities</div>
                <div class="k-place-card-content">
                    <div class="k-place-card-icon"><i class="fas fa-person-hiking"></i></div>
                    <h4>Activities</h4>
                    <p>Trekking, rafting & more</p>
                </div>
            </a>
        </div>
        <div style="text-align:center;margin-top:2.5rem">
            <a href="{{ route('web.places') }}" class="k-btn-primary" style="display:inline-flex">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="1 6 1 22 8 18 16 22 21 18 21 2 16 6 8 2 1 6"/></svg>
                View All Places
            </a>
        </div>
    </section>

    <!-- STATS BAR -->
    <div class="k-stats-bar">
        <div class="k-sbar-stat"><div class="k-sbar-num" data-target="78">0</div><div class="k-sbar-label">K+ Places Mapped</div></div>
        <div class="k-sbar-stat"><div class="k-sbar-num" data-target="12">0</div><div class="k-sbar-label">K+ Local Guides</div></div>
        <div class="k-sbar-stat"><div class="k-sbar-num" data-target="4.9">0</div><div class="k-sbar-label">App Store Rating</div></div>
        <div class="k-sbar-stat"><div class="k-sbar-num" data-target="77">0</div><div class="k-sbar-label">Districts Covered</div></div>
    </div>

    <!-- FEATURES -->
    <section class="k-section" id="features">
        <div class="k-section-header">
            <span class="k-section-tag">App Features</span>
            <h3>Built for the Mountains</h3>
            <p>Every feature is designed for Nepal's unique terrain, connectivity, and travel culture.</p>
        </div>
        <div class="k-features-grid">
            <div class="k-feature">
                <div class="k-feature-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="1 6 1 22 8 18 16 22 21 18 21 2 16 6 8 2 1 6"/></svg>
                </div>
                <h4>Offline Maps</h4>
                <p>Download maps for remote areas with no signal. Works even in the Himalayas.</p>
            </div>
            <div class="k-feature">
                <div class="k-feature-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                </div>
                <h4>Community Reports</h4>
                <p>Get real-time updates from fellow travelers about road conditions, landslides, and weather.</p>
            </div>
            <div class="k-feature">
                <div class="k-feature-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                </div>
                <h4>Live Tracking</h4>
                <p>Share your live location with family. SOS alerts for emergency situations in remote areas.</p>
            </div>
            <div class="k-feature">
                <div class="k-feature-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                </div>
                <h4>Verified Safety</h4>
                <p>All guides, hotels, and services are community-verified with real ratings and reviews.</p>
            </div>
            <div class="k-feature">
                <div class="k-feature-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                </div>
                <h4>Local Wallet</h4>
                <p>One digital wallet for all services — no need to carry cash in remote areas.</p>
            </div>
            <div class="k-feature">
                <div class="k-feature-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                </div>
                <h4>Hidden Gems</h4>
                <p>Discover places no guidebook mentions — shared exclusively by locals and experienced travelers.</p>
            </div>
        </div>
    </section>

    <!-- TRENDING DESTINATIONS -->
    <section class="k-section" id="destinations">
        <div class="k-section-header">
            <span class="k-section-tag">Trending Destinations</span>
            <h3>Where Nepal Is Heading</h3>
            <p>Most visited places this season — handpicked by travelers and locals alike.</p>
        </div>
        <div class="k-dest-grid">
            <a href="{{ route('web.places') }}" class="k-dest-card" style="text-decoration:none">
                <div class="k-dest-card-img" style="background-image:url('https://images.unsplash.com/photo-1544735716-392fe2489ffa?w=800&q=80')"></div>
                <div class="k-dest-card-overlay"></div>
                <div class="k-dest-card-content">
                    <div class="k-dest-card-tag"><i class="fas fa-fire"></i> Most Popular</div>
                    <h4>Everest Base Camp</h4>
                    <p>The iconic trek to the foot of the world's highest peak. 14-day adventure through Sherpa villages.</p>
                    <div class="k-dest-card-meta">
                        <span><i class="fas fa-star"></i> 4.9</span>
                        <span><i class="fas fa-route"></i> 14 Days</span>
                        <span><i class="fas fa-mountain"></i> 5,364m</span>
                        <span><i class="fas fa-users"></i> 12.4k visitors</span>
                    </div>
                </div>
            </a>
            <a href="{{ route('web.places') }}" class="k-dest-card" style="text-decoration:none">
                <div class="k-dest-card-img" style="background-image:url('https://images.unsplash.com/photo-1605649487212-47bdab064df7?w=800&q=80')"></div>
                <div class="k-dest-card-overlay"></div>
                <div class="k-dest-card-content">
                    <div class="k-dest-card-tag"><i class="fas fa-water"></i> Scenic</div>
                    <h4>Pokhara</h4>
                    <p>Lakeside paradise with Annapurna views. Paragliding, boating, and sunrise at Sarangkot.</p>
                    <div class="k-dest-card-meta">
                        <span><i class="fas fa-star"></i> 4.8</span>
                        <span><i class="fas fa-route"></i> 3-5 Days</span>
                        <span><i class="fas fa-mountain"></i> 827m</span>
                        <span><i class="fas fa-users"></i> 9.8k visitors</span>
                    </div>
                </div>
            </a>
            <a href="{{ route('web.places') }}" class="k-dest-card" style="text-decoration:none">
                <div class="k-dest-card-img" style="background-image:url('https://images.unsplash.com/photo-1602216056096-3b40cc0c9944?w=800&q=80')"></div>
                <div class="k-dest-card-overlay"></div>
                <div class="k-dest-card-content">
                    <div class="k-dest-card-tag"><i class="fas fa-paw"></i> Wildlife</div>
                    <h4>Chitwan National Park</h4>
                    <p>Jungle safari home to Bengal tigers, one-horned rhinos, and exotic bird species.</p>
                    <div class="k-dest-card-meta">
                        <span><i class="fas fa-star"></i> 4.7</span>
                        <span><i class="fas fa-route"></i> 2-3 Days</span>
                        <span><i class="fas fa-tree"></i> 932 km²</span>
                        <span><i class="fas fa-users"></i> 6.2k visitors</span>
                    </div>
                </div>
            </a>
            <a href="{{ route('web.places') }}" class="k-dest-card" style="text-decoration:none">
                <div class="k-dest-card-img" style="background-image:url('https://images.unsplash.com/photo-1544735716-392fe2489ffa?w=800&q=80')"></div>
                <div class="k-dest-card-overlay"></div>
                <div class="k-dest-card-content">
                    <div class="k-dest-card-tag"><i class="fas fa-om"></i> Spiritual</div>
                    <h4>Lumbini</h4>
                    <p>Birthplace of Lord Buddha. Sacred monasteries, Maya Devi Temple, and world peace pagoda.</p>
                    <div class="k-dest-card-meta">
                        <span><i class="fas fa-star"></i> 4.6</span>
                        <span><i class="fas fa-route"></i> 1-2 Days</span>
                        <span><i class="fas fa-landmark"></i> UNESCO Site</span>
                        <span><i class="fas fa-users"></i> 4.5k visitors</span>
                    </div>
                </div>
            </a>
        </div>
        <div style="text-align:center;margin-top:2.5rem">
            <a href="{{ route('web.routes') }}" class="k-btn-primary" style="display:inline-flex">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/></svg>
                Explore All Routes
            </a>
        </div>
    </section>

    <!-- CTA -->
    <section class="k-cta" id="download">
        <div class="k-cta-inner">
            <h3>Ready to Explore Nepal?</h3>
            <p>Join 50,000+ travelers and locals who are already discovering Nepal smarter. Download the app today — it's free!</p>
            <div class="k-cta-btns">
                <a href="{{ env('APP_STORE_URL', '#') }}" class="k-store-btn">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.81-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M13 3.5c.73-.83 1.21-1.96 1.07-3.11-1.05.05-2.31.72-3.06 1.64-.68.83-1.27 2.15-1.11 3.22 1.18.09 2.38-.74 3.1-1.75z"/></svg>
                    <div><span>Download on the</span><strong>App Store</strong></div>
                </a>
                <a href="{{ env('PLAY_STORE_URL', '#') }}" class="k-store-btn">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M3,20.5V3.5C3,2.91 3.34,2.39 3.84,2.15L13.69,12L3.84,21.85C3.34,21.6 3,21.09 3,20.5M16.81,15.12L6.05,21.34L14.54,12.85L16.81,15.12M20.16,10.81C20.5,11.08 20.75,11.5 20.75,12C20.75,12.5 20.53,12.9 20.18,13.18L17.89,14.5L15.39,12L17.89,9.5L20.16,10.81M6.05,2.66L16.81,8.88L14.54,11.15L6.05,2.66Z"/></svg>
                    <div><span>Get it on</span><strong>Google Play</strong></div>
                </a>
            </div>
        </div>
    </section>

    <!-- FOOTER -->
    <footer class="k-footer">
        <div class="k-footer-inner">
            <div class="k-footer-brand">
                <h4>Nepal Smart Travel</h4>
                <p>Your local intelligence platform for exploring Nepal. Real-time data, community-driven, and built for the mountains.</p>
            </div>
            <div class="k-footer-col">
                <h5>Services</h5>
                <ul>
                    <li><a href="{{ route('web.places') }}">Places & Trails</a></li>
                    <li><a href="{{ route('web.category', 'hotels') }}">Hotels & Stays</a></li>
                    <li><a href="{{ route('web.category', 'restaurants') }}">Restaurants</a></li>
                    <li><a href="{{ route('web.routes') }}">Curated Routes</a></li>
                </ul>
            </div>
            <div class="k-footer-col">
                <h5>Company</h5>
                <ul>
                    <li><a href="#">About Us</a></li>
                    <li><a href="{{ route('partner.register') }}">Partner With Us</a></li>
                    <li><a href="#">Careers</a></li>
                    <li><a href="#">Blog</a></li>
                </ul>
            </div>
            <div class="k-footer-col">
                <h5>Support</h5>
                <ul>
                    <li><a href="#">Help Center</a></li>
                    <li><a href="#">Safety</a></li>
                    <li><a href="#">Terms of Service</a></li>
                    <li><a href="#">Privacy Policy</a></li>
                </ul>
            </div>
        </div>
        <div class="k-footer-bottom">
            &copy; {{ date('Y') }} Nepal Smart Travel. All rights reserved. Made with <span style="color:#ea580c">&hearts;</span> in Nepal.
        </div>
    </footer>

    <script>
        // Animated counters
        const counters = document.querySelectorAll('[data-target]');
        counters.forEach(counter => {
            const target = parseFloat(counter.getAttribute('data-target'));
            const isDecimal = target % 1 !== 0;
            const duration = 2500;
            const start = performance.now();

            function update(currentTime) {
                const elapsed = currentTime - start;
                const progress = Math.min(elapsed / duration, 1);
                const easeOut = 1 - Math.pow(1 - progress, 3);
                const current = target * easeOut;

                if (isDecimal) counter.textContent = current.toFixed(1);
                else counter.textContent = Math.floor(current);

                if (progress < 1) requestAnimationFrame(update);
            }
            setTimeout(() => requestAnimationFrame(update), 300);
        });

        // App detection cookie
        (function () {
            if (new URLSearchParams(window.location.search).has('app')) {
                document.cookie = 'nst_app=1; path=/; max-age=31536000';
            }
        })();
    </script>
</body>
</html>
