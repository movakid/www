<?php
/**
 * Strona koszyka MovaKid
 *
 * Wyświetla zawartość koszyka i umożliwia jego modyfikację
 */

session_start();
require_once 'config.php';
require_once 'database.php';
require_once 'products.php';
require_once 'cart.php';
require_once 'helpers.php';

// Inicjalizacja klas
$cart = new Cart();

// Pobieranie zawartości koszyka
$cartItems = $cart->getCart();
$cartSummary = $cart->getCartTotalWithDiscount();

// Pobieranie informacji o zastosowanym rabacie
$discount = $cart->getAppliedDiscount();

// Obsługa dodawania kodu rabatowego
$discountMessage = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_discount'])) {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $discountMessage = [
            'type' => 'error',
            'text' => 'Błąd weryfikacji formularza. Spróbuj ponownie.'
        ];
    } else {
        $discountCode = sanitizeInput($_POST['discount_code']);

        $result = $cart->applyDiscountCode($discountCode);

        $discountMessage = [
            'type' => $result['success'] ? 'success' : 'error',
            'text' => $result['message']
        ];

        // Odświeżenie podsumowania koszyka z nowym rabatem
        if ($result['success']) {
            $cartSummary = $cart->getCartTotalWithDiscount();
            $discount = $cart->getAppliedDiscount();
        }
    }
}

// Obsługa usuwania kodu rabatowego
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_discount'])) {
    if (isset($_POST['csrf_token']) && verifyCsrfToken($_POST['csrf_token'])) {
        $cart->removeDiscountCode();
        $cartSummary = $cart->getCartSummary();
        $discount = null;
    }
}

// Sprawdzenie, czy istnieje komunikat flash
$flashMessage = getFlashMessage();
if ($flashMessage) {
    $message = [
        'type' => $flashMessage['type'],
        'text' => $flashMessage['message']
    ];
}

// Tytuł strony
$pageTitle = 'Koszyk - MovaKid';

// Generowanie tokenu CSRF
$csrfToken = generateCsrfToken();

?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="styles.css">
    <link rel="icon" href="favicon.ico">
</head>
<body>
<header>
    <div class="container">
        <div class="logo"><a href="index.php">Mova<span>Kid</span></a></div>
        <div class="tagline">Inteligentne urządzenia, które ożywiają zabawki</div>
    </div>
</header>

<nav class="main-nav">
    <div class="container">
        <div class="nav-links">
            <a href="index.php#how-it-works">Jak to działa</a>
            <a href="index.php#products">Produkty</a>
            <a href="index.php#faq">FAQ</a>
            <a href="kontakt.php">Kontakt</a>
        </div>
        <div class="nav-cart">
            <a href="koszyk.php" class="cart-icon active">
                Koszyk <span class="cart-count"><?php echo count($cartItems); ?></span>
            </a>
        </div>
    </div>
</nav>

<?php if (isset($message)): ?>
    <div class="message message-<?php echo $message['type']; ?>">
        <div class="container">
            <?php echo $message['text']; ?>
        </div>
    </div>
<?php endif; ?>

<section class="cart-section">
    <div class="container">
        <h1>Twój koszyk</h1>

        <?php if (empty($cartItems)): ?>
            <div class="empty-cart">
                <p>Twój koszyk jest pusty.</p>
                <a href="index.php#products" class="btn">Przejdź do sklepu</a>
            </div>
        <?php else: ?>
            <div class="cart-grid">
                <div class="cart-items">
                    <table class="cart-table">
                        <thead>
                        <tr>
                            <th>Produkt</th>
                            <th>Cena</th>
                            <th>Ilość</th>
                            <th>Suma</th>
                            <th>Akcje</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($cartItems as $index => $item): ?>
                            <tr class="cart-item" data-id="<?php echo $item['id']; ?>">
                                <td class="cart-product">
                                    <div class="cart-product-image">
                                        <img src="images/products/<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                                    </div>
                                    <div class="cart-product-info">
                                        <h3><?php echo htmlspecialchars($item['name']); ?></h3>
                                        <p class="cart-product-type">Typ: <?php echo $item['type'] == 'sphere' ? 'Sphere' : 'DualSphere'; ?></p>
                                    </div>
                                </td>
                                <td class="cart-price"><?php echo formatPrice($item['price']); ?></td>
                                <td class="cart-quantity">
                                    <div class="quantity-control">
                                        <button class="quantity-btn quantity-decrease" data-id="<?php echo $item['id']; ?>">-</button>
                                        <input type="number" value="<?php echo $item['quantity']; ?>" min="1" max="10" class="quantity-input" data-id="<?php echo $item['id']; ?>">
                                        <button class="quantity-btn quantity-increase" data-id="<?php echo $item['id']; ?>">+</button>
                                    </div>
                                </td>
                                <td class="cart-subtotal"><?php echo formatPrice($item['price'] * $item['quantity']); ?></td>
                                <td class="cart-actions">
                                    <button class="remove-item-btn" data-id="<?php echo $item['id']; ?>">Usuń</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div class="cart-actions-bottom">
                        <a href="index.php#products" class="btn btn-outline">Kontynuuj zakupy</a>
                        <button id="clear-cart-btn" class="btn btn-outline">Wyczyść koszyk</button>
                    </div>
                </div>

                <div class="cart-summary">
                    <h2>Podsumowanie</h2>

                    <div class="cart-totals">
                        <div class="cart-total-row">
                            <span>Wartość koszyka:</span>
                            <span id="cart-subtotal"><?php echo formatPrice($cartSummary['subtotal']); ?></span>
                        </div>

                        <?php if (isset($cartSummary['discount_amount'])): ?>
                            <div class="cart-total-row discount">
                                <span><?php echo $cartSummary['discount_description']; ?>:</span>
                                <span id="cart-discount">-<?php echo formatPrice($cartSummary['discount_amount']); ?></span>
                            </div>
                        <?php endif; ?>

                        <div class="cart-total-row">
                            <span>Podatek VAT (<?php echo VAT_RATE * 100; ?>%):</span>
                            <span id="cart-tax"><?php echo formatPrice($cartSummary['tax']); ?></span>
                        </div>

                        <div class="cart-total-row">
                            <span>Koszty wysyłki:</span>
                            <span id="cart-shipping"><?php echo formatPrice($cartSummary['shipping']); ?></span>
                        </div>

                        <?php if ($cartSummary['subtotal'] < FREE_SHIPPING_THRESHOLD): ?>
                            <div class="free-shipping-notice">
                                Dodaj produkty za <?php echo formatPrice(FREE_SHIPPING_THRESHOLD - $cartSummary['subtotal']); ?>, aby otrzymać darmową dostawę!
                            </div>
                        <?php else: ?>
                            <div class="free-shipping-notice success">
                                Gratulacje! Kwalifikujesz się do darmowej dostawy.
                            </div>
                        <?php endif; ?>

                        <div class="cart-total-row grand-total">
                            <span>Do zapłaty:</span>
                            <span id="cart-total"><?php echo formatPrice($cartSummary['total']); ?></span>
                        </div>
                    </div>

                    <!-- Kod rabatowy -->
                    <div class="discount-code-section">
                        <h3>Kod rabatowy</h3>

                        <?php if ($discount): ?>
                            <div class="applied-discount">
                                <p>Zastosowany kod: <strong><?php echo $discount['code']; ?></strong></p>
                                <form method="post" action="">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                    <input type="hidden" name="remove_discount" value="1">
                                    <button type="submit" class="btn btn-small">Usuń</button>
                                </form>
                            </div>
                        <?php else: ?>
                            <form method="post" action="" class="discount-form">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                <div class="form-group">
                                    <input type="text" name="discount_code" placeholder="Wpisz kod rabatowy" required>
                                    <button type="submit" name="apply_discount" class="btn">Zastosuj</button>
                                </div>
                                <?php if ($discountMessage): ?>
                                    <p class="discount-message <?php echo $discountMessage['type']; ?>"><?php echo $discountMessage['text']; ?></p>
                                <?php endif; ?>
                            </form>
                        <?php endif; ?>
                    </div>

                    <a href="checkout.php" class="btn btn-large checkout-btn">Przejdź do kasy</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<footer>
    <div class="container">
        <div class="footer-grid">
            <div class="footer-about">
                <div class="footer-logo">Mova<span>Kid</span></div>
                <p>Innowacyjne rozwiązania audio dla zabawek dzieci, tworzone z pasją i troską o rozwój najmłodszych.</p>
            </div>

            <div class="footer-links">
                <h4>Przydatne linki</h4>
                <ul>
                    <li><a href="index.php#how-it-works">Jak to działa</a></li>
                    <li><a href="index.php#products">Produkty</a></li>
                    <li><a href="o-nas.php">O nas</a></li>
                    <li><a href="kontakt.php">Kontakt</a></li>
                </ul>
            </div>

            <div class="footer-contact">
                <h4>Kontakt</h4>
                <p>Email: info@movakid.com</p>
                <p>Tel: +48 123 456 789</p>
                <div class="social-icons">