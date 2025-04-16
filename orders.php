<?php
/**
 * Klasa obsługi zamówień MovaKid
 *
 * Zarządza procesem zamówienia, od koszyka po finalizację
 */

require_once 'database.php';
require_once 'helpers.php';

class Order {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Tworzy nowe zamówienie na podstawie danych z koszyka i formularza
     */
    public function createOrder($customerData, $cartItems) {
        // Sprawdzenie, czy koszyk nie jest pusty
        if (empty($cartItems)) {
            return ['success' => false, 'message' => 'Koszyk jest pusty'];
        }

        // Walidacja danych klienta
        $requiredFields = ['firstname', 'lastname', 'email', 'phone', 'address', 'postal_code', 'city'];
        foreach ($requiredFields as $field) {
            if (empty($customerData[$field])) {
                return ['success' => false, 'message' => 'Brakuje wymaganych danych: ' . $field];
            }
        }

        try {
            $this->db->beginTransaction();

            // Tworzenie lub aktualizacja klienta
            $customerId = $this->saveCustomer($customerData);

            // Obliczanie wartości zamówienia
            $orderTotal = $this->calculateOrderTotal($cartItems);

            // Generowanie numeru zamówienia
            $orderNumber = 'MK' . date('ymd') . rand(1000, 9999);

            // Tworzenie zamówienia
            $orderId = $this->db->insert('orders', [
                'order_number' => $orderNumber,
                'customer_id' => $customerId,
                'status' => 'new',
                'payment_status' => 'pending',
                'shipping_method' => $customerData['shipping_method'] ?? 'standard',
                'shipping_cost' => $orderTotal['shipping'],
                'subtotal' => $orderTotal['subtotal'],
                'tax' => $orderTotal['tax'],
                'total' => $orderTotal['total'],
                'notes' => $customerData['notes'] ?? '',
                'shipping_address' => $this->formatAddress($customerData),
                'billing_address' => $customerData['billing_same_as_shipping'] ? $this->formatAddress($customerData) : $this->formatAddress($customerData, 'billing_')
            ]);

            // Dodawanie pozycji zamówienia
            foreach ($cartItems as $item) {
                // Sprawdzenie dostępności produktu
                $product = $this->db->fetchRow("SELECT * FROM products WHERE id = ?", [$item['id']]);

                if (!$product) {
                    throw new Exception("Produkt o ID {$item['id']} nie istnieje");
                }

                if ($product['stock'] < $item['quantity']) {
                    throw new Exception("Niewystarczająca liczba sztuk produktu: {$product['name']}");
                }

                // Dodanie pozycji zamówienia
                $this->db->insert('order_items', [
                    'order_id' => $orderId,
                    'product_id' => $product['id'],
                    'product_sku' => $product['sku'],
                    'product_name' => $product['name'],
                    'price' => $product['price'],
                    'quantity' => $item['quantity'],
                    'subtotal' => $product['price'] * $item['quantity']
                ]);

                // Aktualizacja stanu magazynowego
                $this->db->update(
                    'products',
                    ['stock' => $product['stock'] - $item['quantity']],
                    'id = ?',
                    [$product['id']]
                );
            }

            $this->db->commit();

            // Wysłanie maila z potwierdzeniem zamówienia
            $this->sendOrderConfirmation($orderId);

            return [
                'success' => true,
                'message' => 'Zamówienie zostało przyjęte',
                'order_id' => $orderId,
                'order_number' => $orderNumber
            ];

        } catch (Exception $e) {
            $this->db->rollback();

            if (DEBUG_MODE) {
                return ['success' => false, 'message' => $e->getMessage()];
            } else {
                error_log("Error creating order: " . $e->getMessage());
                return ['success' => false, 'message' => 'Wystąpił błąd podczas przetwarzania zamówienia. Prosimy spróbować ponownie.'];
            }
        }
    }

    /**
     * Zapisuje dane klienta i zwraca ID
     */
    private function saveCustomer($customerData) {
        // Sprawdzenie, czy klient już istnieje
        $existingCustomer = $this->db->fetchRow(
            "SELECT id FROM customers WHERE email = ?",
            [$customerData['email']]
        );

        if ($existingCustomer) {
            // Aktualizacja danych klienta
            $this->db->update(
                'customers',
                [
                    'firstname' => $customerData['firstname'],
                    'lastname' => $customerData['lastname'],
                    'phone' => $customerData['phone'],
                    'address' => $customerData['address'],
                    'postal_code' => $customerData['postal_code'],
                    'city' => $customerData['city'],
                    'country' => $customerData['country'] ?? 'Polska'
                ],
                'id = ?',
                [$existingCustomer['id']]
            );

            return $existingCustomer['id'];
        } else {
            // Utworzenie nowego klienta
            return $this->db->insert('customers', [
                'email' => $customerData['email'],
                'firstname' => $customerData['firstname'],
                'lastname' => $customerData['lastname'],
                'phone' => $customerData['phone'],
                'address' => $customerData['address'],
                'postal_code' => $customerData['postal_code'],
                'city' => $customerData['city'],
                'country' => $customerData['country'] ?? 'Polska'
            ]);
        }
    }

    /**
     * Oblicza sumę zamówienia, podatek i koszty wysyłki
     */
    private function calculateOrderTotal($cartItems) {
        $subtotal = 0;

        // Obliczanie wartości produktów
        foreach ($cartItems as $item) {
            $product = $this->db->fetchRow("SELECT price FROM products WHERE id = ?", [$item['id']]);
            if ($product) {
                $subtotal += $product['price'] * $item['quantity'];
            }
        }

        // Obliczanie kosztów wysyłki
        $shipping = $subtotal >= FREE_SHIPPING_THRESHOLD ? 0 : SHIPPING_COST;

        // Obliczanie podatku
        $tax = $subtotal * VAT_RATE;

        // Suma całkowita
        $total = $subtotal + $tax + $shipping;

        return [
            'subtotal' => $subtotal,
            'tax' => $tax,
            'shipping' => $shipping,
            'total' => $total
        ];
    }

    /**
     * Formatuje adres do zapisu w bazie
     */
    private function formatAddress($data, $prefix = '') {
        $address = [];

        if (!empty($data[$prefix . 'firstname'])) {
            $address[] = $data[$prefix . 'firstname'] . ' ' . $data[$prefix . 'lastname'];
        } else {
            $address[] = $data['firstname'] . ' ' . $data['lastname'];
        }

        if (!empty($data[$prefix . 'address'])) {
            $address[] = $data[$prefix . 'address'];
        } else {
            $address[] = $data['address'];
        }

        if (!empty($data[$prefix . 'postal_code'])) {
            $address[] = $data[$prefix . 'postal_code'] . ' ' . $data[$prefix . 'city'];
        } else {
            $address[] = $data['postal_code'] . ' ' . $data['city'];
        }

        if (!empty($data[$prefix . 'country'])) {
            $address[] = $data[$prefix . 'country'];
        } else {
            $address[] = $data['country'] ?? 'Polska';
        }

        return implode("\n", $address);
    }

    /**
     * Wysyła email z potwierdzeniem zamówienia
     */
    private function sendOrderConfirmation($orderId) {
        $order = $this->getOrderDetails($orderId);

        if (!$order) {
            return false;
        }

        $customer = $this->db->fetchRow(
            "SELECT * FROM customers WHERE id = ?",
            [$order['customer_id']]
        );

        $orderItems = $this->db->fetchAll(
            "SELECT * FROM order_items WHERE order_id = ?",
            [$orderId]
        );

        // Tworzenie treści emaila
        $subject = "Potwierdzenie zamówienia #{$order['order_number']}";

        $message = "<html><body>";
        $message .= "<h1>Dziękujemy za zamówienie!</h1>";
        $message .= "<p>Twoje zamówienie zostało przyjęte do realizacji.</p>";
        $message .= "<h2>Szczegóły zamówienia #{$order['order_number']}</h2>";

        $message .= "<table border='1' cellpadding='5' cellspacing='0' width='100%'>";
        $message .= "<tr><th>Produkt</th><th>Cena</th><th>Ilość</th><th>Suma</th></tr>";

        foreach ($orderItems as $item) {
            $message .= "<tr>";
            $message .= "<td>{$item['product_name']}</td>";
            $message .= "<td>" . formatPrice($item['price']) . "</td>";
            $message .= "<td>{$item['quantity']}</td>";
            $message .= "<td>" . formatPrice($item['subtotal']) . "</td>";
            $message .= "</tr>";
        }

        $message .= "</table>";

        $message .= "<p><strong>Wartość produktów:</strong> " . formatPrice($order['subtotal']) . "</p>";
        $message .= "<p><strong>Podatek VAT:</strong> " . formatPrice($order['tax']) . "</p>";
        $message .= "<p><strong>Koszty wysyłki:</strong> " . formatPrice($order['shipping_cost']) . "</p>";
        $message .= "<p><strong>Łącznie do zapłaty:</strong> " . formatPrice($order['total']) . "</p>";

        $message .= "<h3>Adres dostawy:</h3>";
        $message .= "<p>" . nl2br($order['shipping_address']) . "</p>";

        if ($order['payment_status'] == 'pending') {
            $message .= "<h3>Płatność:</h3>";
            $message .= "<p>Status: Oczekuje na płatność</p>";
            $message .= "<p>Metoda płatności: " . ($order['payment_method'] ?: 'Nie wybrano') . "</p>";

            // Dane do przelewu
            $message .= "<h4>Dane do przelewu:</h4>";
            $message .= "<p>Nazwa odbiorcy: MovaKid Sp. z o.o.</p>";
            $message .= "<p>Numer konta: 00 0000 0000 0000 0000 0000 0000</p>";
            $message .= "<p>Tytuł przelewu: Zamówienie {$order['order_number']}</p>";
            $message .= "<p>Kwota: " . formatPrice($order['total']) . "</p>";
        }

        $message .= "<p>W razie pytań prosimy o kontakt: kontakt@movakid.com</p>";
        $message .= "</body></html>";

        // Wysyłanie emaila
        return sendEmail($customer['email'], $subject, $message);
    }

    /**
     * Pobiera szczegóły zamówienia
     */
    public function getOrderDetails($orderId) {
        return $this->db->fetchRow(
            "SELECT * FROM orders WHERE id = ?",
            [$orderId]
        );
    }

    /**
     * Pobiera zamówienia klienta
     */
    public function getCustomerOrders($customerId) {
        return $this->db->fetchAll(
            "SELECT * FROM orders WHERE customer_id = ? ORDER BY created_at DESC",
            [$customerId]
        );
    }

    /**
     * Pobiera pozycje zamówienia
     */
    public function getOrderItems($orderId) {
        return $this->db->fetchAll(
            "SELECT * FROM order_items WHERE order_id = ?",
            [$orderId]
        );
    }

    /**
     * Aktualizuje status zamówienia
     */
    public function updateOrderStatus($orderId, $status) {
        return $this->db->update(
            'orders',
            ['status' => $status],
            'id = ?',
            [$orderId]
        );
    }

    /**
     * Aktualizuje status płatności
     */
    public function updatePaymentStatus($orderId, $status, $paymentId = null) {
        $data = ['payment_status' => $status];

        if ($paymentId) {
            $data['payment_id'] = $paymentId;
        }

        return $this->db->update(
            'orders',
            $data,
            'id = ?',