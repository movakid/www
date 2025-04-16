<?php
/**
 * Skrypt przetwarzania zamówienia MovaKid
 *
 * Obsługuje przesłanie formularza zamówienia i zapisuje je w bazie danych
 */

session_start();
require_once 'config.php';
require_once 'database.php';
require_once 'orders.php';
require_once 'cart.php';
require_once 'helpers.php';

// Sprawdzenie, czy formularz został przesłany
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/koszyk.php');
}

// Sprawdzenie tokenu CSRF
if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
    setFlashMessage('error', 'Błąd weryfikacji formularza. Spróbuj ponownie.');
    redirect(BASE_URL . '/koszyk.php');
}

// Inicjalizacja klas
$cart = new Cart();
$order = new Order();

// Sprawdzenie, czy koszyk nie jest pusty
if (count($cart->getCart()) === 0) {
    setFlashMessage('error', 'Twój koszyk jest pusty. Dodaj produkty przed złożeniem zamówienia.');
    redirect(BASE_URL . '/koszyk.php');
}

// Walidacja dostępności produktów
$validation = $cart->validateCartItems();
if (!$validation['valid']) {
    setFlashMessage('error', 'Niektóre produkty w koszyku nie są dostępne: ' . implode(', ', $validation['errors']));
    redirect(BASE_URL . '/koszyk.php');
}

// Sanityzacja danych formularza
$formData = sanitizeInput($_POST);

// Tworzenie danych klienta
$customerData = [
    'firstname' => $formData['firstname'] ?? '',
    'lastname' => $formData['lastname'] ?? '',
    'email' => $formData['email'] ?? '',
    'phone' => $formData['phone'] ?? '',
    'address' => $formData['address'] ?? '',
    'postal_code' => $formData['postal_code'] ?? '',
    'city' => $formData['city'] ?? '',
    'country' => $formData['country'] ?? 'Polska',
    'notes' => $formData['notes'] ?? '',
    'shipping_method' => $formData['shipping_method'] ?? 'standard',
    'payment_method' => $formData['payment_method'] ?? 'bank_transfer',
    'billing_same_as_shipping' => isset($formData['billing_same_as_shipping'])
];

// Jeżeli adres rozliczeniowy jest inny niż adres dostawy
if (!$customerData['billing_same_as_shipping']) {
    $customerData['billing_firstname'] = $formData['billing_firstname'] ?? '';
    $customerData['billing_lastname'] = $formData['billing_lastname'] ?? '';
    $customerData['billing_address'] = $formData['billing_address'] ?? '';
    $customerData['billing_postal_code'] = $formData['billing_postal_code'] ?? '';
    $customerData['billing_city'] = $formData['billing_city'] ?? '';
    $customerData['billing_country'] = $formData['billing_country'] ?? 'Polska';
}

// Zapis zapisu w newsletterze
if (isset($formData['subscribe_newsletter']) && filter_var($customerData['email'], FILTER_VALIDATE_EMAIL)) {
    $db = Database::getInstance();

    // Sprawdzenie, czy email już istnieje w bazie
    $existingSubscriber = $db->fetchRow(
        "SELECT id FROM subscribers WHERE email = ?",
        [$customerData['email']]
    );

    if (!$existingSubscriber) {
        $db->insert('subscribers', [
            'email' => $customerData['email'],
            'firstname' => $customerData['firstname'],
            'product_interest' => $formData['product_interest'] ?? ''
        ]);
    }
}

// Pobieranie koszyka z uwzględnieniem rabatu
$cartSummary = $cart->getCartTotalWithDiscount();

// Informacje o rabacie (jeśli istnieje)
$discount = $cart->getAppliedDiscount();
if ($discount) {
    $customerData['discount_code'] = $discount['code'];
    $customerData['discount_type'] = $discount['type'];
    $customerData['discount_value'] = $discount['value'];
}

// Utworzenie zamówienia
$result = $order->createOrder($customerData, $cart->getCart());

if ($result['success']) {
    // Wyczyszczenie koszyka
    $cart->clearCart();

    // Usunięcie kodu rabatowego
    $cart->removeDiscountCode();

    // Zapisanie danych zamówienia w sesji do wyświetlenia na stronie potwierdzenia
    $_SESSION['last_order'] = [
        'order_id' => $result['order_id'],
        'order_number' => $result['order_number'],
        'total' => $cartSummary['total']
    ];

    // Przekierowanie na stronę potwierdzenia
    redirect(BASE_URL . '/potwierdzenie.php');
} else {
    // Zapisanie błędu i przekierowanie z powrotem do koszyka
    setFlashMessage('error', $result['message']);
    redirect(BASE_URL . '/koszyk.php');
}