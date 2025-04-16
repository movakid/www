<?php
/**
 * Handler płatności dla MovaKid
 *
 * Obsługuje inicjalizację płatności i zwrotne wywołania od dostawców płatności
 */

session_start();
require_once 'config.php';
require_once 'database.php';
require_once 'orders.php';
require_once 'helpers.php';

// Obsługa różnych metod płatności i akcji
$action = isset($_GET['action']) ? $_GET['action'] : '';
$paymentMethod = isset($_GET['method']) ? $_GET['method'] : '';

// Inicjalizacja obiektu zamówienia
$order = new Order();

// Obsługa akcji inicjacji płatności
if ($action === 'init') {
    // Sprawdzenie, czy podano ID zamówienia
    if (!isset($_GET['order_id']) || empty($_GET['order_id'])) {
        setFlashMessage('error', 'Brak identyfikatora zamówienia');
        redirect(BASE_URL);
    }

    $orderId = (int)$_GET['order_id'];

    // Pobieranie danych zamówienia
    $orderDetails = $order->getOrderDetails($orderId);

    if (!$orderDetails) {
        setFlashMessage('error', 'Zamówienie nie istnieje');
        redirect(BASE_URL);
    }

    // Sprawdzenie, czy zamówienie nie zostało już opłacone
    if ($orderDetails['payment_status'] === 'paid') {
        setFlashMessage('info', 'To zamówienie zostało już opłacone');
        redirect(BASE_URL . '/potwierdzenie.php?order=' . $orderDetails['order_number']);
    }

    // Obsługa różnych metod płatności
    switch ($paymentMethod) {
        case 'stripe':
            initStripePayment($orderDetails);
            break;

        case 'paypal':
            initPayPalPayment($orderDetails);
            break;

        case 'przelewy24':
            initPrzelewy24Payment($orderDetails);
            break;

        case 'bank_transfer':
            // Przelew tradycyjny - przekierowanie na stronę z danymi do przelewu
            setFlashMessage('info', 'Wybrano płatność przelewem tradycyjnym. Szczegóły przelewu znajdziesz poniżej.');
            redirect(BASE_URL . '/przelew.php?order=' . $orderDetails['order_number']);
            break;

        default:
            setFlashMessage('error', 'Nieobsługiwana metoda płatności');
            redirect(BASE_URL . '/koszyk.php');
            break;
    }
}

// Obsługa zwrotnego wywołania (callback) od dostawcy płatności
elseif ($action === 'callback') {
    switch ($paymentMethod) {
        case 'stripe':
            handleStripeCallback();
            break;

        case 'paypal':
            handlePayPalCallback();
            break;

        case 'przelewy24':
            handlePrzelewy24Callback();
            break;

        default:
            header('HTTP/1.1 400 Bad Request');
            echo 'Nieobsługiwana metoda płatności';
            exit;
    }
}

// Obsługa powrotu użytkownika po płatności
elseif ($action === 'return') {
    switch ($paymentMethod) {
        case 'stripe':
            handleStripeReturn();
            break;

        case 'paypal':
            handlePayPalReturn();
            break;

        case 'przelewy24':
            handlePrzelewy24Return();
            break;

        default:
            setFlashMessage('error', 'Nieobsługiwana metoda płatności');
            redirect(BASE_URL);
    }
}

// Nieznana akcja
else {
    setFlashMessage('error', 'Nieznana akcja płatności');
    redirect(BASE_URL);
}

/**
 * Inicjuje płatność przez Stripe
 */
function initStripePayment($orderDetails) {
    // Wymaganie biblioteki Stripe
    require_once 'vendor/autoload.php';

    // Konfiguracja Stripe
    \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

    try {
        // Tworzenie sesji płatności
        $session = \Stripe\Checkout\Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [
                [
                    'price_data' => [
                        'currency' => strtolower(CURRENCY),
                        'unit_amount' => round($orderDetails['total'] * 100), // W centach
                        'product_data' => [
                            'name' => 'Zamówienie ' . $orderDetails['order_number'],
                            'description' => 'MovaKid - Innowacyjne urządzenia audio dla zabawek'
                        ],
                    ],
                    'quantity' => 1,
                ],
            ],
            'mode' => 'payment',
            'success_url' => BASE_URL . '/payment_handler.php?action=return&method=stripe&session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => BASE_URL . '/koszyk.php',
            'client_reference_id' => $orderDetails['id'],
            'customer_email' => $orderDetails['customer_email'] ?? null,
            'metadata' => [
                'order_id' => $orderDetails['id'],
                'order_number' => $orderDetails['order_number']
            ]
        ]);

        // Aktualizacja metody płatności w zamówieniu
        $order = new Order();
        $order->updatePaymentMethod($orderDetails['id'], 'stripe');

        // Przekierowanie do formularza płatności Stripe
        header('Location: ' . $session->url);
        exit;

    } catch (Exception $e) {
        setFlashMessage('error', 'Błąd podczas inicjalizacji płatności: ' . $e->getMessage());
        redirect(BASE_URL . '/koszyk.php');
    }
}

/**
 * Obsługuje zwrotne wywołanie od Stripe
 */
function handleStripeCallback() {
    // Pobieranie zawartości żądania
    $payload = @file_get_contents('php://input');
    $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];

    try {
        // Weryfikacja webhooków Stripe
        $event = \Stripe\Webhook::constructEvent(
            $payload, $sig_header, STRIPE_WEBHOOK_SECRET
        );

        // Obsługa różnych zdarzeń
        switch ($event->type) {
            case 'checkout.session.completed':
                $session = $event->data->object;
                $orderId = $session->metadata->order_id;

                // Aktualizacja statusu płatności
                $order = new Order();
                $order->updatePaymentStatus($orderId, 'paid', $session->payment_intent);

                break;

            case 'payment_intent.payment_failed':
                $intent = $event->data->object;
                $orderId = $intent->metadata->order_id;

                // Aktualizacja statusu płatności
                $order = new Order();
                $order->updatePaymentStatus($orderId, 'failed', $intent->id);

                break;
        }

        http_response_code(200);

    } catch(\UnexpectedValueException $e) {
        // Nieprawidłowa zawartość żądania
        http_response_code(400);
        exit;
    } catch(\Stripe\Exception\SignatureVerificationException $e) {
        // Nieprawidłowy podpis
        http_response_code(400);
        exit;
    } catch (Exception $e) {
        // Inny błąd
        http_response_code(500);
        exit;
    }
}

/**
 * Obsługuje powrót użytkownika po płatności Stripe
 */
function handleStripeReturn() {
    if (!isset($_GET['session_id'])) {
        setFlashMessage('error', 'Brak identyfikatora sesji');
        redirect(BASE_URL);
    }

    $sessionId = $_GET['session_id'];

    try {
        // Pobranie informacji o sesji
        $session = \Stripe\Checkout\Session::retrieve($sessionId);

        if (!$session) {
            setFlashMessage('error', 'Nie znaleziono sesji płatności');
            redirect(BASE_URL);
        }

        // Pobranie identyfikatora zamówienia
        $orderId = $session->client_reference_id;
        $orderNumber = $session->metadata->order_number;

        // Przekierowanie na stronę potwierdzenia
        setFlashMessage('success', 'Płatność została zrealizowana pomyślnie');
        redirect(BASE_URL . '/potwierdzenie.php?order=' . $orderNumber);

    } catch (Exception $e) {
        setFlashMessage('