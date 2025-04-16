<?php
/**
 * Klasa obsługi koszyka MovaKid
 *
 * Zarządza koszykiem zakupowym opartym na sesji
 */

require_once 'database.php';
require_once 'products.php';

class Cart {
    private $db;
    private $product;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->product = new Product();

        // Inicjalizacja koszyka w sesji
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
    }

    /**
     * Dodaje produkt do koszyka
     */
    public function addToCart($productId, $quantity = 1) {
        // Walidacja ilości
        if ($quantity <= 0) {
            return [
                'success' => false,
                'message' => 'Nieprawidłowa ilość produktu'
            ];
        }

        // Sprawdzenie, czy produkt istnieje
        $product = $this->product->getProductById($productId);
        if (!$product) {
            return [
                'success' => false,
                'message' => 'Produkt nie istnieje'
            ];
        }

        // Sprawdzenie dostępności
        if (!$this->product->checkAvailability($productId, $quantity)) {
            return [
                'success' => false,
                'message' => 'Produkt jest niedostępny w żądanej ilości'
            ];
        }

        // Dodanie do koszyka lub aktualizacja ilości
        $found = false;
        foreach ($_SESSION['cart'] as &$item) {
            if ($item['id'] == $productId) {
                // Sprawdzenie, czy łączna ilość nie przekroczy dostępnego stanu
                $newQuantity = $item['quantity'] + $quantity;
                if (!$this->product->checkAvailability($productId, $newQuantity)) {
                    return [
                        'success' => false,
                        'message' => 'Nie możesz dodać więcej sztuk tego produktu'
                    ];
                }

                $item['quantity'] = $newQuantity;
                $found = true;
                break;
            }
        }

        if (!$found) {
            $_SESSION['cart'][] = [
                'id' => $productId,
                'name' => $product['name'],
                'price' => $product['price'],
                'quantity' => $quantity,
                'image' => $product['image'],
                'type' => $product['type']
            ];
        }

        return [
            'success' => true,
            'message' => 'Produkt został dodany do koszyka',
            'cart_count' => $this->getCartCount()
        ];
    }

    /**
     * Aktualizuje ilość produktu w koszyku
     */
    public function updateQuantity($productId, $quantity) {
        // Walidacja ilości
        if ($quantity <= 0) {
            return [
                'success' => false,
                'message' => 'Nieprawidłowa ilość produktu'
            ];
        }

        // Sprawdzenie dostępności
        if (!$this->product->checkAvailability($productId, $quantity)) {
            return [
                'success' => false,
                'message' => 'Produkt jest niedostępny w żądanej ilości'
            ];
        }

        // Aktualizacja ilości
        foreach ($_SESSION['cart'] as &$item) {
            if ($item['id'] == $productId) {
                $item['quantity'] = $quantity;
                break;
            }
        }

        return [
            'success' => true,
            'message' => 'Ilość została zaktualizowana',
            'cart_total' => $this->getCartTotal()
        ];
    }

    /**
     * Usuwa produkt z koszyka
     */
    public function removeFromCart($productId) {
        foreach ($_SESSION['cart'] as $key => $item) {
            if ($item['id'] == $productId) {
                unset($_SESSION['cart'][$key]);
                // Reindeksowanie tablicy
                $_SESSION['cart'] = array_values($_SESSION['cart']);
                break;
            }
        }

        return [
            'success' => true,
            'message' => 'Produkt został usunięty z koszyka',
            'cart_count' => $this->getCartCount()
        ];
    }

    /**
     * Czyści koszyk
     */
    public function clearCart() {
        $_SESSION['cart'] = [];

        return [
            'success' => true,
            'message' => 'Koszyk został wyczyszczony'
        ];
    }

    /**
     * Pobiera zawartość koszyka
     */
    public function getCart() {
        return $_SESSION['cart'];
    }

    /**
     * Pobiera liczbę produktów w koszyku
     */
    public function getCartCount() {
        $count = 0;
        foreach ($_SESSION['cart'] as $item) {
            $count += $item['quantity'];
        }
        return $count;
    }

    /**
     * Pobiera wartość koszyka
     */
    public function getCartTotal() {
        $total = 0;
        foreach ($_SESSION['cart'] as $item) {
            $total += $item['price'] * $item['quantity'];
        }
        return $total;
    }

    /**
     * Pobiera pełne podsumowanie koszyka
     */
    public function getCartSummary() {
        $subtotal = $this->getCartTotal();
        $tax = $subtotal * VAT_RATE;

        $shipping = $subtotal >= FREE_SHIPPING_THRESHOLD ? 0 : SHIPPING_COST;
        $total = $subtotal + $tax + $shipping;

        return [
            'items' => $this->getCart(),
            'item_count' => $this->getCartCount(),
            'subtotal' => $subtotal,
            'tax' => $tax,
            'shipping