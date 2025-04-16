<?php
/**
 * Handler operacji AJAX dla MovaKid
 *
 * Obsługuje asynchroniczne żądania ze strony klienta
 */

session_start();
require_once 'config.php';
require_once 'database.php';
require_once 'products.php';
require_once 'cart.php';
require_once 'helpers.php';

// Ustawienie nagłówków odpowiedzi
header('Content-Type: application/json');

// Sprawdzenie, czy żądanie jest typu POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Nieprawidłowa metoda żądania']);
    exit;
}

// Pobieranie danych z żądania
$postData = json_decode(file_get_contents('php://input'), true);
if (!$postData) {
    $postData = $_POST;
}

// Obsługa różnych akcji
$action = isset($postData['action']) ? $postData['action'] : '';

switch ($action) {
    // Dodawanie produktu do koszyka
    case 'add_to_cart':
        if (!isset($postData['product_id']) || !isset($postData['quantity'])) {
            echo json_encode(['success' => false, 'message' => 'Brak wymaganych parametrów']);
            exit;
        }

        $productId = (int)$postData['product_id'];
        $quantity = (int)$postData['quantity'];

        $cart = new Cart();
        $result = $cart->addToCart($productId, $quantity);

        echo json_encode($result);
        break;

    // Aktualizacja ilości produktu w koszyku
    case 'update_cart_quantity':
        if (!isset($postData['product_id']) || !isset($postData['quantity'])) {
            echo json_encode(['success' => false, 'message' => 'Brak wymaganych parametrów']);
            exit;
        }

        $productId = (int)$postData['product_id'];
        $quantity = (int)$postData['quantity'];

        $cart = new Cart();
        $result = $cart->updateQuantity($productId, $quantity);

        // Dodanie zaktualizowanego podsumowania koszyka
        if ($result['success']) {
            $result['cart_summary'] = $cart->getCartTotalWithDiscount();
        }

        echo json_encode($result);
        break;

    // Usuwanie produktu z koszyka
    case 'remove_from_cart':
        if (!isset($postData['product_id'])) {
            echo json_encode(['success' => false, 'message' => 'Brak wymaganych parametrów']);
            exit;
        }

        $productId = (int)$postData['product_id'];

        $cart = new Cart();
        $result = $cart->removeFromCart($productId);

        // Dodanie zaktualizowanego podsumowania koszyka
        if ($result['success']) {
            $result['cart_summary'] = $cart->getCartTotalWithDiscount();
        }

        echo json_encode($result);
        break;

    // Czyszczenie koszyka
    case 'clear_cart':
        $cart = new Cart();
        $result = $cart->clearCart();

        echo json_encode($result);
        break;

    // Sprawdzanie dostępności produktów
    case 'check_availability':
        if (!isset($postData['product_id'])) {
            echo json_encode(['success' => false, 'message' => 'Brak wymaganych parametrów']);
            exit;
        }

        $productId = (int)$postData['product_id'];
        $quantity = isset($postData['quantity']) ? (int)$postData['quantity'] : 1;

        $product = new Product();
        $available = $product->checkAvailability($productId, $quantity);

        echo json_encode([
            'success' => true,
            'available' => $available
        ]);
        break;

    // Zastosowanie kodu rabatowego
    case 'apply_discount_code':
        if (!isset($postData['discount_code'])) {
            echo json_encode(['success' => false, 'message' => 'Brak wymaganych parametrów']);
            exit;
        }

        $discountCode = $postData['discount_code'];

        $cart = new Cart();
        $result = $cart->applyDiscountCode($discountCode);

        // Dodanie zaktualizowanego podsumowania koszyka
        if ($result['success']) {
            $result['cart_summary'] = $cart->getCartTotalWithDiscount();
        }

        echo json_encode($result);
        break;

    // Usunięcie kodu rabatowego
    case 'remove_discount_code':
        $cart = new Cart();
        $result = $cart->removeDiscountCode();

        // Dodanie zaktualizowanego podsumowania koszyka
        if ($result['success']) {
            $result['cart_summary'] = $cart->getCartSummary();
        }

        echo json_encode($result);
        break;

    // Pobieranie informacji o dostępności produktów
    case 'get_availability_info':
        $product = new Product();
        $availabilityInfo = $product->getAvailabilityInfo();

        echo json_encode([
            'success' => true,
            'availability' => $availabilityInfo
        ]);
        break;

    // Zapisanie subskrypcji newslettera
    case 'subscribe_newsletter':
        if (!isset($postData['email']) || !filter_var($postData['email'], FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Nieprawidłowy adres email']);
            exit;
        }

        $db = Database::getInstance();

        // Sprawdzenie, czy email już istnieje w bazie
        $existingSubscriber = $db->fetchRow(
            "SELECT id, status FROM subscribers WHERE email = ?",
            [$postData['email']]
        );

        if ($existingSubscriber) {
            if ($existingSubscriber['status'] == 'unsubscribed') {
                // Reaktywacja subskrypcji
                $db->update(
                    'subscribers',
                    ['status' => 'active', 'product_interest' => $postData['product_interest'] ?? null],
                    'id = ?',
                    [$existingSubscriber['id']]
                );

                echo json_encode([
                    'success' => true,
                    'message' => 'Twoja subskrypcja została wznowiona'
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'message' => 'Ten adres email jest już zapisany do newslettera'
                ]);
            }
        } else {
            // Dodanie nowego subskrybenta
            $db->insert('subscribers', [
                'email' => $postData['email'],
                'firstname' => $postData['firstname'] ?? null,
                'product_interest' => $postData['product_interest'] ?? null
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Dziękujemy za zapisanie się do newslettera!'
            ]);
        }
        break;

    // Nieznana akcja
    default:
        echo json_encode(['success' => false, 'message' => 'Nieznana akcja']);
        break;
}