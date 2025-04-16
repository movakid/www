<?php
/**
 * Konfiguracja systemu MovaKid
 *
 * Plik zawiera podstawowe ustawienia systemu, w tym:
 * - Połączenie z bazą danych
 * - Stałe systemowe
 * - Konfiguracja SMTP do maili
 * - Ustawienia API płatności
 */

// Tryb debugowania (true w środowisku dev, false na produkcji)
define('DEBUG_MODE', true);

if (DEBUG_MODE) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    error_reporting(0);
}

// Dane do połączenia z bazą danych
define('DB_HOST', 'localhost');
define('DB_NAME', 'movakid_db');
define('DB_USER', 'movakid_user');
define('DB_PASS', 'strong_password_here'); // Zmień na silne hasło

// Ustawienia URL
define('BASE_URL', 'https://movakid.com');
define('ADMIN_URL', BASE_URL . '/admin');

// Ustawienia SMTP
define('SMTP_HOST', 'smtp.example.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'noreply@movakid.com');
define('SMTP_PASS', 'email_password_here');
define('MAIL_FROM', 'kontakt@movakid.com');
define('MAIL_FROM_NAME', 'MovaKid');

// Ustawienia sklepu
define('CURRENCY', 'EUR');
define('CURRENCY_SYMBOL', '€');
define('VAT_RATE', 0.23); // 23% VAT
define('SHIPPING_COST', 9.99);
define('FREE_SHIPPING_THRESHOLD', 100);

// Ustawienia API płatności
define('PAYMENT_MODE', 'sandbox'); // sandbox lub production
define('STRIPE_PUBLIC_KEY', 'pk_test_your_key_here');
define('STRIPE_SECRET_KEY', 'sk_test_your_key_here');
define('PAYPAL_CLIENT_ID', 'your_client_id_here');
define('PAYPAL_SECRET', 'your_secret_here');

// Klucz zabezpieczający do tokenów (sessions, CSRF)
define('SECURITY_KEY', 'generate_random_strong_key_here');

// Ustawienia sesji
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 1); // Wyłącz dla localhost

// Strefa czasowa
date_default_timezone_set('Europe/Warsaw');

// Limity produktów
define('SPHERE_LIMIT', 100);
define('DUALSPHERE_LIMIT', 50);

/**
 * Funkcja do bezpiecznego ładowania wartości z konfiguracji
 */
function config($key, $default = null) {
    $constant = strtoupper($key);
    if (defined($constant)) {
        return constant($constant);
    }
    return $default;
}