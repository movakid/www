<?php
/**
 * Pomocnicze funkcje dla systemu MovaKid
 */

require_once 'config.php';

/**
 * Formatuje cenę zgodnie z ustawieniami
 */
function formatPrice($price, $includeCurrency = true) {
    $formattedPrice = number_format($price, 2, ',', ' ');
    return $includeCurrency ? $formattedPrice . ' ' . CURRENCY_SYMBOL : $formattedPrice;
}

/**
 * Wysyła email z użyciem PHPMailer
 */
function sendEmail($to, $subject, $message, $attachments = []) {
    // Wymagamy biblioteki PHPMailer
    require_once 'vendor/autoload.php';

    $mail = new PHPMailer\PHPMailer\PHPMailer();

    try {
        // Ustawienia serwera
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = 'tls';
        $mail->Port = SMTP_PORT;
        $mail->CharSet = 'UTF-8';

        // Nadawca i odbiorca
        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($to);

        // Treść wiadomości
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;
        $mail->AltBody = strip_tags($message);

        // Dodawanie załączników
        foreach ($attachments as $attachment) {
            $mail->addAttachment($attachment);
        }

        // Wysyłanie emaila
        return $mail->send();

    } catch (Exception $e) {
        error_log('Błąd wysyłania emaila: ' . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Generuje token CSRF
 */
function generateCsrfToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Weryfikuje token CSRF
 */
function verifyCsrfToken($token) {
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        return false;
    }
    return true;
}

/**
 * Czyści dane wejściowe
 */
function sanitizeInput($input) {
    if (is_array($input)) {
        foreach ($input as $key => $value) {
            $input[$key] = sanitizeInput($value);
        }
    } else {
        $input = trim($input);
        $input = stripslashes($input);
        $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    }
    return $input;
}

/**
 * Generuje unikalny identyfikator
 */
function generateUniqueId($prefix = '') {
    return uniqid($prefix, true);
}

/**
 * Przekierowuje do podanego URL
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Tworzy komunikat flash
 */
function setFlashMessage($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Pobiera i czyści komunikat flash
 */
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    return null;
}

/**
 * Sprawdza, czy użytkownik jest zalogowany
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Sprawdza, czy administrator jest zalogowany
 */
function isAdminLoggedIn() {
    return isset($_SESSION['admin_id']);
}

/**
 * Wymaga zalogowania administratora, w przeciwnym razie przekierowuje
 */
function requireAdmin() {
    if (!isAdminLoggedIn()) {
        setFlashMessage('error', 'Musisz być zalogowany, aby uzyskać dostęp do tej strony.');
        redirect(ADMIN_URL . '/login.php');
    }
}

/**
 * Wymaga zalogowania użytkownika, w przeciwnym razie przekierowuje
 */
function requireLogin() {
    if (!isLoggedIn()) {
        setFlashMessage('error', 'Musisz być zalogowany, aby uzyskać dostęp do tej strony.');
        redirect(BASE_URL . '/login.php');
    }
}

/**
 * Generuje losowe hasło
 */
function generateRandomPassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()-_=+';
    $password = '';

    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[rand(0, strlen($chars) - 1)];
    }

    return $password;
}

/**
 * Sprawdza, czy tablica jest tablicą asocjacyjną
 */
function isAssocArray($arr) {
    if (!is_array($arr)) {
        return false;
    }
    return array_keys($arr) !== range(0, count($arr) - 1);
}

/**
 * Pobiera nazwę kraju na podstawie kodu
 */
function getCountryName($countryCode) {
    $countries = [
        'PL' => 'Polska',
        'DE' => 'Niemcy',
        'GB' => 'Wielka Brytania',
        'FR' => 'Francja',
        'IT' => 'Włochy',
        'ES' => 'Hiszpania',
        'CZ' => 'Czechy',
        'SK' => 'Słowacja',
        'AT' => 'Austria',
        'NL' => 'Holandia',
        'BE' => 'Belgia',
        'US' => 'Stany Zjednoczone',
        'CA' => 'Kanada'
    ];

    return isset($countries[$countryCode]) ? $countries[$countryCode] : $countryCode;
}

/**
 * Sprawdza, czy string jest prawidłowym adresem email
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Loguje wiadomość do pliku
 */
function logMessage($message, $type = 'info') {
    $logFile = __DIR__ . '/logs/app_' . date('Y-m-d') . '.log';
    $logDir = dirname($logFile);

    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $timestamp = date('Y-m-d H:i:s');
    $formattedMessage = "[$timestamp][$type] $message" . PHP_EOL;

    file_put_contents($logFile, $formattedMessage, FILE_APPEND);
}