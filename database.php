<?php
/**
 * Klasa obsługi bazy danych dla systemu MovaKid
 *
 * Obsługuje połączenie z bazą danych i podstawowe operacje CRUD
 */

require_once 'config.php';

class Database {
    private $connection;
    private static $instance = null;

    /**
     * Prywatny konstruktor (Singleton pattern)
     */
    private function __construct() {
        try {
            $this->connection = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            if (DEBUG_MODE) {
                die("Błąd połączenia z bazą danych: " . $e->getMessage());
            } else {
                die("Wystąpił problem z połączeniem do bazy danych. Prosimy spróbować później.");
            }
        }
    }

    /**
     * Implementacja wzorca Singleton
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Wykonuje zapytanie SQL z parametrami
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            if (DEBUG_MODE) {
                die("Błąd zapytania SQL: " . $e->getMessage() . "<br>Zapytanie: " . $sql);
            } else {
                $this->logError($e->getMessage(), $sql, $params);
                die("Wystąpił problem z bazą danych. Prosimy spróbować później.");
            }
        }
    }

    /**
     * Pobiera pojedynczy wiersz
     */
    public function fetchRow($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }

    /**
     * Pobiera wszystkie wiersze
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Pobiera pojedynczą wartość
     */
    public function fetchValue($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchColumn();
    }

    /**
     * Wstawia rekord do tabeli
     */
    public function insert($table, $data) {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";
        $this->query($sql, array_values($data));

        return $this->connection->lastInsertId();
    }

    /**
     * Aktualizuje rekord w tabeli
     */
    public function update($table, $data, $where, $whereParams = []) {
        $set = [];
        foreach (array_keys($data) as $column) {
            $set[] = "$column = ?";
        }
        $setString = implode(', ', $set);

        $sql = "UPDATE $table SET $setString WHERE $where";
        $params = array_merge(array_values($data), $whereParams);

        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * Usuwa rekord z tabeli
     */
    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM $table WHERE $where";
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * Zaczyna transakcję
     */
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }

    /**
     * Zatwierdza transakcję
     */
    public function commit() {
        return $this->connection->commit();
    }

    /**
     * Cofa transakcję
     */
    public function rollback() {
        return $this->connection->rollBack();
    }

    /**
     * Loguje błędy SQL
     */
    private function logError($message, $sql, $params) {
        $logFile = __DIR__ . '/logs/db_errors.log';
        $logDir = dirname($logFile);

        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $timestamp = date('Y-m-d H:i:s');
        $paramString = json_encode($params);
        $log = "[$timestamp] Error: $message\nSQL: $sql\nParams: $paramString\n\n";

        file_put_contents($logFile, $log, FILE_APPEND);
    }

    /**
     * Zapobiega klonowaniu obiektu
     */
    private function __clone() {}

    /**
     * Zapobiega deserializacji obiektu
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

/**
 * Funkcja skrótowa do uzyskania instancji bazy danych
 */
function db() {
    return Database::getInstance();
}

/**
 * Tworzenie tabel jeśli nie istnieją
 */
function initDatabase() {
    $db = Database::getInstance();

    // Tabela produktów
    $db->query("
        CREATE TABLE IF NOT EXISTS products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sku VARCHAR(50) NOT NULL UNIQUE,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            price DECIMAL(10, 2) NOT NULL,
            stock INT NOT NULL DEFAULT 0,
            image VARCHAR(255),
            type ENUM('sphere', 'dualsphere') NOT NULL,
            status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Tabela klientów
    $db->query("
        CREATE TABLE IF NOT EXISTS customers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL UNIQUE,
            password VARCHAR(255),
            firstname VARCHAR(100) NOT NULL,
            lastname VARCHAR(100) NOT NULL,
            phone VARCHAR(50),
            address TEXT,
            postal_code VARCHAR(20),
            city VARCHAR(100),
            country VARCHAR(100) DEFAULT 'Polska',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Tabela zamówień
    $db->query("
        CREATE TABLE IF NOT EXISTS orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_number VARCHAR(50) NOT NULL UNIQUE,
            customer_id INT,
            status ENUM('new', 'paid', 'processing', 'shipped', 'completed', 'cancelled') NOT NULL DEFAULT 'new',
            payment_status ENUM('pending', 'paid', 'failed', 'refunded') NOT NULL DEFAULT 'pending',
            payment_method VARCHAR(50),
            payment_id VARCHAR(255),
            shipping_method VARCHAR(50),
            shipping_cost DECIMAL(10, 2) NOT NULL DEFAULT 0,
            subtotal DECIMAL(10, 2) NOT NULL,
            tax DECIMAL(10, 2) NOT NULL,
            total DECIMAL(10, 2) NOT NULL,
            notes TEXT,
            shipping_address TEXT,
            billing_address TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Tabela pozycji zamówienia
    $db->query("
        CREATE TABLE IF NOT EXISTS order_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NOT NULL,
            product_id INT,
            product_sku VARCHAR(50) NOT NULL,
            product_name VARCHAR(255) NOT NULL,
            price DECIMAL(10, 2) NOT NULL,
            quantity INT NOT NULL,
            subtotal DECIMAL(10, 2) NOT NULL,
            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Tabela subskrybentów newslettera
    $db->query("
        CREATE TABLE IF NOT EXISTS subscribers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL UNIQUE,
            firstname VARCHAR(100),
            product_interest VARCHAR(50),
            status ENUM('active', 'unsubscribed') NOT NULL DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Tabela administratorów
    $db->query("
        CREATE TABLE IF NOT EXISTS admins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(255) NOT NULL UNIQUE,
            role ENUM('admin', 'editor') NOT NULL DEFAULT 'editor',
            last_login TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Dodawanie przykładowych produktów jeśli tabela jest pusta
    $productCount = $db->fetchValue("SELECT COUNT(*) FROM products");

    if ($productCount == 0) {
        $products = [
            [
                'sku' => 'MOVASPHERE-01',
                'name' => 'MovaKid Sphere',
                'description' => 'Inteligentny naszyjnik z półsferą o średnicy 70mm zawierającą głośnik, mikrofon i Raspberry Pi Zero 2.',
                'price' => 59.99,
                'stock' => SPHERE_LIMIT,
                'image' => 'images/products/sphere.jpg',
                'type' => 'sphere'
            ],
            [
                'sku' => 'MOVADUALSPHERE-01',
                'name' => 'MovaKid DualSphere',
                'description' => 'Składane półsfery o średnicy 70mm każda, połączone elastycznym pałąkiem. Po złożeniu tworzą pełną sferę.',
                'price' => 79.99,
                'stock' => DUALSPHERE_LIMIT,
                'image' => 'images/products/dualsphere.jpg',
                'type' => 'dualsphere'
            ]
        ];

        foreach ($products as $product) {
            $db->insert('products', $product);
        }
    }

    // Dodawanie przykładowego administratora jeśli tabela jest pusta
    $adminCount = $db->fetchValue("SELECT COUNT(*) FROM admins");

    if ($adminCount == 0) {
        $db->insert('admins', [
            'username' => 'admin',
            'password' => password_hash('zmien_to_haslo', PASSWORD_DEFAULT),
            'name' => 'Administrator',
            'email' => 'admin@movakid.com',
            'role' => 'admin'
        ]);
    }
}

// Automatyczna inicjalizacja bazy danych przy pierwszym wywołaniu
initDatabase();