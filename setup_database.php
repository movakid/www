<?php
/**
 * Skrypt konfiguracyjny bazy danych MovaKid
 *
 * Tworzy strukturę bazy danych i dodaje przykładowe dane
 */

require_once 'config.php';

// Ustaw flagę inicjalizacji
$initializeDb = true;

// Sprawdź, czy istnieje plik blokady
$lockFile = __DIR__ . '/db_setup.lock';
if (file_exists($lockFile)) {
    echo "Baza danych została już skonfigurowana. Usuń plik db_setup.lock, aby uruchomić ponownie.<br>";
    $initializeDb = false;
}

if ($initializeDb) {
    // Nawiązanie połączenia z bazą danych
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );

        echo "Połączenie z serwerem bazy danych nawiązane pomyślnie.<br>";

        // Tworzenie bazy danych
        $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE " . DB_NAME);

        echo "Baza danych " . DB_NAME . " utworzona pomyślnie.<br>";

        // Tworzenie tabel
        $tables = [
            // Tabela produktów
            "CREATE TABLE IF NOT EXISTS products (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            // Tabela klientów
            "CREATE TABLE IF NOT EXISTS customers (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            // Tabela zamówień
            "CREATE TABLE IF NOT EXISTS orders (
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
                discount_code VARCHAR(50),
                discount_amount DECIMAL(10, 2),
                notes TEXT,
                shipping_address TEXT,
                billing_address TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            // Tabela pozycji zamówienia
            "CREATE TABLE IF NOT EXISTS order_items (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            // Tabela subskrybentów newslettera
            "CREATE TABLE IF NOT EXISTS subscribers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255) NOT NULL UNIQUE,
                firstname VARCHAR(100),
                product_interest VARCHAR(50),
                status ENUM('active', 'unsubscribed') NOT NULL DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            // Tabela administratorów
            "CREATE TABLE IF NOT EXISTS admins (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(255) NOT NULL UNIQUE,
                role ENUM('admin', 'editor') NOT NULL DEFAULT 'editor',
                last_login TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            // Tabela zapisanych koszyków
            "CREATE TABLE IF NOT EXISTS saved_carts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                cart_data TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES customers(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            // Tabela kodów rabatowych
            "CREATE TABLE IF NOT EXISTS discount_codes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(50) NOT NULL UNIQUE,
                type ENUM('percentage', 'fixed', 'free_shipping') NOT NULL,
                value DECIMAL(10, 2) NOT NULL,
                min_order_value DECIMAL(10, 2) DEFAULT 0,
                max_uses INT DEFAULT NULL,
                uses_count INT DEFAULT 0,
                start_date DATE,
                end_date DATE,
                status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        ];

        // Wykonanie zapytań tworzących tabele
        foreach ($tables as $sql) {
            $pdo->exec($sql);
        }

        echo "Struktura tabel utworzona pomyślnie.<br>";

        // Dodawanie przykładowych produktów
        $products = [
            [
                'sku' => 'MOVASPHERE-01',
                'name' => 'MovaKid Sphere',
                'description' => 'Inteligentny naszyjnik z półsferą o średnicy 70mm zawierającą głośnik, mikrofon i Raspberry Pi Zero 2. Idealne rozwiązanie dla mniejszych zabawek i lalek.',
                'price' => 59.99,
                'stock' => 100,
                'image' => 'sphere.jpg',
                'type' => 'sphere'
            ],
            [
                'sku' => 'MOVADUALSPHERE-01',
                'name' => 'MovaKid DualSphere',
                'description' => 'Składane półsfery o średnicy 70mm każda, połączone elastycznym pałąkiem. Po złożeniu tworzą pełną sferę. Doskonałe rozwiązanie dla większych pluszaków i misiów.',
                'price' => 79.99,
                'stock' => 50,
                'image' => 'dualsphere.jpg',
                'type' => 'dualsphere'
            ]
        ];

        $stmt = $pdo->prepare("INSERT INTO products (sku, name, description, price, stock, image, type) VALUES (?, ?, ?, ?, ?, ?, ?)");

        foreach ($products as $product) {
            try {
                $stmt->execute([
                    $product['sku'],
                    $product['name'],
                    $product['description'],
                    $product['price'],
                    $product['stock'],
                    $product['image'],
                    $product['type']
                ]);
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) { // Kod błędu duplikacji
                    echo "Produkt " . $product['sku'] . " już istnieje.<br>";
                } else {
                    throw $e;
                }
            }
        }

        echo "Produkty zostały dodane.<br>";

        // Dodawanie przykładowego administratora
        $adminUsername = 'admin';
        $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $adminName = 'Administrator';
        $adminEmail = 'admin@movakid.com';

        try {
            $stmt = $pdo->prepare("INSERT INTO admins (username, password, name, email, role) VALUES (?, ?, ?, ?, 'admin')");
            $stmt->execute([$adminUsername, $adminPassword, $adminName, $adminEmail]);
            echo "Administrator został dodany.<br>";
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { // Kod błędu duplikacji
                echo "Administrator już istnieje.<br>";
            } else {
                throw $e;
            }
        }

        // Dodawanie przykładowych kodów rabatowych
        $discountCodes = [
            [
                'code' => 'MOVAKID10',
                'type' => 'percentage',
                'value' => 10,
                'min_order_value' => 0,
                'start_date' => date('Y-m-d'),
                'end_date' => date('Y-m-d', strtotime('+30 days')),
                'status' => 'active'
            ],
            [
                'code' => 'FREESHIP',
                'type' => 'free_shipping',
                'value' => 0,
                'min_order_value' => 50,
                'start_date' => date('Y-m-d'),
                'end_date' => date('Y-m-d', strtotime('+30 days')),
                'status' => 'active'
            ],
            [
                'code' => 'LAUNCH20',
                'type' => 'percentage',
                'value' => 20,
                'min_order_value' => 100,
                'start_date' => date('Y-m-d'),
                'end_date' => date('Y-m-d', strtotime('+7 days')),
                'status' => 'active'
            ]
        ];

        $stmt = $pdo->prepare("INSERT INTO discount_codes (code, type, value, min_order_value, start_date, end_date, status) VALUES (?, ?, ?, ?, ?, ?, ?)");

        foreach ($discountCodes as $code) {
            try {
                $stmt->execute([
                    $code['code'],
                    $code['type'],
                    $code['value'],
                    $code['min_order_value'],
                    $code['start_date'],
                    $code['end_date'],
                    $code['status']
                ]);
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) { // Kod błędu duplikacji
                    echo "Kod rabatowy " . $code['code'] . " już istnieje.<br>";
                } else {
                    throw $e;
                }
            }
        }

        echo "Kody rabatowe zostały dodane.<br>";

        // Tworzenie pliku blokady
        file_put_contents($lockFile, date('Y-m-d H:i:s'));

        echo "<br>Konfiguracja bazy danych została zakończona pomyślnie.<br>";
        echo "Dane logowania do panelu administracyjnego:<br>";
        echo "Login: admin<br>";
        echo "Hasło: admin123<br>";
        echo "<strong>Zmień te dane po pierwszym logowaniu!</strong><br>";

    } catch (PDOException $e) {
        echo "Błąd bazy danych: " . $e->getMessage() . "<br>";
    }
}