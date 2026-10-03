{{-- Landing header/footer design system — identical to web/home.blade.php.
     Include INSIDE a <style> block. --}}
:root {
    --primary: #49C5B6;
    --primary-dark: #015048;
    --primary-deep: #002824;
    --primary-light: #EBF7F5;
    --primary-border: rgba(73, 197, 182, 0.25);
    --white: #ffffff;
    --gray-50: #F7FAF9;
    --gray-100: #EDF3F1;
    --gray-200: #D8E4E1;
    --gray-600: #516360;
    --gray-800: #1C2B29;
    --font-main: 'Manrope', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    --header-height: 80px;
    --shadow-md: 0 10px 30px rgba(1, 80, 72, 0.08);
    --shadow-glow: 0 6px 24px rgba(73, 197, 182, 0.35);
    --radius-full: 9999px;
}

/* ===== HEADER & NAVIGATION ===== */
.site-header {
    font-family: var(--font-main);
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

.site-header .container {
    width: 100%;
    max-width: 1240px;
    margin: 0 auto;
    padding: 0 24px;
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
    margin: 0;
    padding: 0;
}

.nav-link {
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--primary-dark);
    position: relative;
    padding: 6px 0;
    text-decoration: none;
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
    text-decoration: none;
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

/* ===== FOOTER ===== */
.site-footer {
    font-family: var(--font-main);
    background-color: var(--primary-deep);
    color: var(--white);
    padding: 80px 0 40px 0;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.site-footer .container {
    width: 100%;
    max-width: 1240px;
    margin: 0 auto;
    padding: 0 24px;
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
    line-height: 1.7;
    margin-bottom: 24px;
}

.footer-heading {
    color: var(--white);
    font-size: 1.1rem;
    font-weight: 800;
    margin-bottom: 20px;
    position: relative;
}

.footer-links {
    list-style: none;
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin: 0;
    padding: 0;
}

.footer-links a {
    color: rgba(255, 255, 255, 0.75);
    font-size: 0.95rem;
    text-decoration: none;
    transition: all 0.25s ease;
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

/* ===== RESPONSIVE ===== */
@media (max-width: 1024px) {
    .site-footer { padding: 60px 0 32px 0; }
    .footer-top { grid-template-columns: 1fr 1fr; gap: 40px; }
    .footer-bottom { justify-content: center; text-align: center; }
}

@media (max-width: 992px) {
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
}

@media (max-width: 576px) {
    .footer-top { grid-template-columns: 1fr; }
}

@media (max-width: 480px) {
    .brand-logo img { height: 26px; }
    .btn-cta-placeholder { padding: 9px 15px; font-size: 0.85rem; }
}
