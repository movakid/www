<?php
/**
 * Klasa obsługi produktów MovaKid
 *
 * Zarządza produktami, ich dostępnością i dodatkowymi funkcjami
 */

require_once 'database.php';

class Product {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Pobiera wszystkie produkty
     */
    public function getAllProducts() {
        return $this->db->fetchAll("SELECT * FROM products WHERE status = 'active' ORDER BY id ASC");
    }

    /**
     * Pobiera produkt na podstawie ID
     */
    public function getProductById($id) {
        return $this->db->fetchRow("SELECT * FROM products WHERE id = ?", [$id]);
    }

    /**
     * Pobiera produkt na podstawie SKU
     */
    public function getProductBySku($sku) {
        return $this->db->fetchRow("SELECT * FROM products WHERE sku = ?", [$sku]);
    }

    /**
     * Pobiera produkty na podstawie typu
     */
    public function getProductsByType($type) {
        return $this->db->fetchAll(
            "SELECT * FROM products WHERE type = ? AND status = 'active'",
            [$type]
        );
    }

    /**
     * Dodaje nowy produkt
     */
    public function addProduct($productData) {
        // Walidacja danych
        $requiredFields = ['sku', 'name', 'price', 'type'];
        foreach ($requiredFields as $field) {
            if (empty($productData[$field])) {
                return ['success' => false, 'message' => "Pole $field jest wymagane"];
            }
        }

        // Sprawdzenie, czy SKU jest unikalny
        $existingProduct = $this->getProductBySku($productData['sku']);
        if ($existingProduct) {
            return ['success' => false, 'message' => 'Produkt o podanym SKU już istnieje'];
        }

        // Zapisanie produktu
        try {
            $productId = $this->db->insert('products', [
                'sku' => $productData['sku'],
                'name' => $productData['name'],
                'description' => $productData['description'] ?? '',
                'price' => $productData['price'],
                'stock' => $productData['stock'] ?? 0,
                'image' => $productData['image'] ?? '',
                'type' => $productData['type'],
                'status' => $productData['status'] ?? 'active'
            ]);

            return [
                'success' => true,
                'message' => 'Produkt został dodany',
                'product_id' => $productId
            ];

        } catch (Exception $e) {
            if (DEBUG_MODE) {
                return ['success' => false, 'message' => $e->getMessage()];
            } else {
                error_log("Error adding product: " . $e->getMessage());
                return ['success' => false, 'message' => 'Błąd podczas dodawania produktu'];
            }
        }
    }

    /**
     * Aktualizuje produkt
     */
    public function updateProduct($id, $productData) {
        // Sprawdzenie, czy produkt istnieje
        $product = $this->getProductById($id);
        if (!$product) {
            return ['success' => false, 'message' => 'Produkt nie istnieje'];
        }

        // Przygotowanie danych do aktualizacji
        $updateData = [];
        $allowedFields = ['name', 'description', 'price', 'stock', 'image', 'status'];

        foreach ($allowedFields as $field) {
            if (isset($productData[$field])) {
                $updateData[$field] = $productData[$field];
            }
        }

        // Sprawdzenie, czy jest co aktualizować
        if (empty($updateData)) {
            return ['success' => false, 'message' => 'Brak danych do aktualizacji'];
        }

        // Aktualizacja produktu
        try {
            $this->db->update('products', $updateData, 'id = ?', [$id]);

            return [
                'success' => true,
                'message' => 'Produkt został zaktualizowany'
            ];

        } catch (Exception $e) {
            if (DEBUG_MODE) {
                return ['success' => false, 'message' => $e->getMessage()];
            } else {
                error_log("Error updating product: " . $e->getMessage());
                return ['success' => false, 'message' => 'Błąd podczas aktualizacji produktu'];
            }
        }
    }

    /**
     * Usuwa produkt (zmienia status na nieaktywny)
     */
    public function deleteProduct($id) {
        // Sprawdzenie, czy produkt istnieje
        $product = $this->getProductById($id);
        if (!$product) {
            return ['success' => false, 'message' => 'Produkt nie istnieje'];
        }

        // Zmiana statusu na nieaktywny zamiast fizycznego usuwania
        try {
            $this->db->update('products', ['status' => 'inactive'], 'id = ?', [$id]);

            return [
                'success' => true,
                'message' => 'Produkt został usunięty'
            ];

        } catch (Exception $e) {
            if (DEBUG_MODE) {
                return ['success' => false, 'message' => $e->getMessage()];
            } else {
                error_log("Error deleting product: " . $e->getMessage());
                return ['success' => false, 'message' => 'Błąd podczas usuwania produktu'];
            }
        }
    }

    /**
     * Aktualizuje stan magazynowy produktu
     */
    public function updateStock($id, $quantity) {
        // Sprawdzenie, czy produkt istnieje
        $product = $this->getProductById($id);
        if (!$product) {
            return ['success' => false, 'message' => 'Produkt nie istnieje'];
        }

        try {
            $this->db->update('products', ['stock' => $quantity], 'id = ?', [$id]);

            return [
                'success' => true,
                'message' => 'Stan magazynowy został zaktualizowany'
            ];

        } catch (Exception $e) {
            if (DEBUG_MODE) {
                return ['success' => false, 'message' => $e->getMessage()];
            } else {
                error_log("Error updating stock: " . $e->getMessage());
                return ['success' => false, 'message' => 'Błąd podczas aktualizacji stanu magazynowego'];
            }
        }
    }

    /**
     * Sprawdza dostępność produktu
     */
    public function checkAvailability($id, $quantity = 1) {
        $product = $this->getProductById($id);

        if (!$product) {
            return false;
        }

        if ($product['status'] != 'active') {
            return false;
        }

        return $product['stock'] >= $quantity;
    }

    /**
     * Pobiera liczbę dostępnych produktów
     */
    public function getAvailableCount($type) {
        return $this->db->fetchValue(
            "SELECT SUM(stock) FROM products WHERE type = ? AND status = 'active'",
            [$type]
        );
    }

    /**
     * Pobiera informacje o dostępności dla strony głównej
     */
    public function getAvailabilityInfo() {
        $sphereCount = $this->getAvailableCount('sphere');
        $dualSphereCount = $this->getAvailableCount('dualsphere');

        return [
            'sphere' => [
                'available' => $sphereCount,
                'limit' => SPHERE_LIMIT,
                'percentage' => round(($sphereCount / SPHERE_LIMIT) * 100),
                'low_stock' => $sphereCount < (SPHERE_LIMIT * 0.1) // poniżej 10%
            ],
            'dualsphere' => [
                'available' => $dualSphereCount,
                'limit' => DUALSPHERE_LIMIT,
                'percentage' => round(($dualSphereCount / DUALSPHERE_LIMIT) * 100),
                'low_stock' => $dualSphereCount < (DUALSPHERE_LIMIT * 0.1) // poniżej 10%
            ]
        ];
    }

    /**
     * Wyszukuje produkty na podstawie frazy
     */
    public function searchProducts($query) {
        $searchQuery = "%" . $query . "%";
        return $this->db->fetchAll(
            "SELECT * FROM products WHERE 
             (name LIKE ? OR description LIKE ? OR sku LIKE ?) 
             AND status = 'active'",
            [$searchQuery, $searchQuery, $searchQuery]
        );
    }

    /**
     * Pobiera produkty z filtrami i sortowaniem
     */
    public function getFilteredProducts($filters = [], $sort = 'id', $order = 'ASC', $limit = 0, $offset = 0) {
        $query = "SELECT * FROM products WHERE status = 'active'";
        $params = [];

        // Dodanie filtrów
        if (!empty($filters)) {
            foreach ($filters as $key => $value) {
                if ($key == 'type' && !empty($value)) {
                    $query .= " AND type = ?";
                    $params[] = $value;
                }

                if ($key == 'min_price' && !empty($value)) {
                    $query .= " AND price >= ?";
                    $params[] = $value;
                }

                if ($key == 'max_price' && !empty($value)) {
                    $query .= " AND price <= ?";
                    $params[] = $value;
                }

                if ($key == 'in_stock' && $value) {
                    $query .= " AND stock > 0";
                }
            }
        }

        // Dodanie sortowania
        $allowedSortFields = ['id', 'name', 'price', 'stock'];
        $allowedOrders = ['ASC', 'DESC'];

        $sort = in_array($sort, $allowedSortFields) ? $sort : 'id';
        $order = in_array(strtoupper($order), $allowedOrders) ? strtoupper($order) : 'ASC';

        $query .= " ORDER BY $sort $order";

        // Dodanie limitu
        if ($limit > 0) {
            $query .= " LIMIT ?, ?";
            $params[] = (int)$offset;
            $params[] = (int)$limit;
        }

        return $this->db->fetchAll($query, $params);
    }

    /**
     * Pobiera zdjęcie produktu lub wartość domyślną
     */
    public function getProductImage($product, $defaultImage = 'default.jpg') {
        if (empty($product['image']) || !file_exists('uploads/products/' . $product['image'])) {
            return 'uploads/products/' . $defaultImage;
        }

        return 'uploads/products/' . $product['image'];
    }
}