<?php
/**
 * Strona główna MovaKid
 *
 * Pokazuje produkty i obsługuje podstawową funkcjonalność
 */

session_start();
require_once 'config.php';
require_once 'database.php';
require_once 'products.php';
require_once 'cart.php';
require_once 'helpers.php';

// Inicjalizacja klas
$product = new Product();
$cart = new Cart();

// Pobieranie produktów
$products = $product->getAllProducts();

// Pobieranie informacji o dostępności
$availabilityInfo = $product->getAvailabilityInfo();

// Obsługa dodawania do koszyka
$message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $message = [
            'type' => 'error',
            'text' => 'Błąd weryfikacji formularza. Spróbuj ponownie.'
        ];
    } else {
        $productId = (int)$_POST['product_id'];
        $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;

        $result = $cart->addToCart($productId, $quantity);

        $message = [
            'type' => $result['success'] ? 'success' : 'error',
            'text' => $result['message']
        ];
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
$pageTitle = 'MovaKid - Inteligentne urządzenia dla zabawek';

// Generowanie tokenu CSRF
$csrfToken = generateCsrfToken();

// Pobieranie liczby przedmiotów w koszyku
$cartCount = $cart->getCartCount();

?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="styles.css">
    <link rel="icon" href="favicon.ico">
    <meta name="description" content="MovaKid - innowacyjne urządzenia audio, które dodają głos i dźwięk do ulubionych zabawek Twojego dziecka.">
</head>
<body>
<header>
    <div class="container">
        <div class="logo">Mova<span>Kid</span></div>
        <div class="tagline">Inteligentne urządzenia, które ożywiają zabawki</div>
    </div>
</header>

<nav class="main-nav">
    <div class="container">
        <div class="nav-links">
            <a href="#how-it-works">Jak to działa</a>
            <a href="#products">Produkty</a>
            <a href="#faq">FAQ</a>
            <a href="kontakt.php">Kontakt</a>
        </div>
        <div class="nav-cart">
            <a href="koszyk.php" class="cart-icon">
                Koszyk <span class="cart-count"><?php echo $cartCount; ?></span>
            </a>
        </div>
    </div>
</nav>

<?php if ($message): ?>
    <div class="message message-<?php echo $message['type']; ?>">
        <div class="container">
            <?php echo $message['text']; ?>
        </div>
    </div>
<?php endif; ?>

<section class="hero">
    <div class="container">
        <div class="hero-content">
            <h1>Pozwól zabawkom Twojego dziecka mówić</h1>
            <p>MovaKid to innowacyjna technologia, która dodaje głos i dźwięk do ulubionych zabawek Twojego dziecka. Nasze inteligentne urządzenia oparte na Raspberry Pi Zero 2 dostarczają wyjątkowych wrażeń dźwiękowych w kompaktowej formie.</p>
            <p><strong>Specjalna oferta przedsprzedaży - tylko przez najbliższe 7 dni!</strong></p>
            <div class="hero-buttons">
                <a href="#products" class="btn">Zobacz produkty</a>
                <a href="#how-it-works" class="btn btn-outline">Jak to działa?</a>
            </div>
        </div>
        <div class="hero-image">
            <img src="images/hero-image.jpg" alt="MovaKid z pluszowym misiem" />
        </div>
    </div>
</section>

<section class="features" id="how-it-works">
    <div class="container">
        <h2 class="section-title">Inteligentna technologia dla zabawek</h2>
        <p class="section-subtitle">MovaKid to więcej niż zwykła zabawka - to platforma do zabawy i nauki</p>

        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">🔊</div>
                <h3>Wysokiej jakości dźwięk</h3>
                <p>Potężne głośniki w kompaktowej formie zapewniają czysty, głośny dźwięk porównywalny z asystentami głosowymi.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">🎙️</div>
                <h3>Zaawansowane mikrofony</h3>
                <p>Czułe mikrofony z redukcją szumów wyłapują głos dziecka nawet z odległości.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">🔋</div>
                <h3>Długi czas pracy</h3>
                <p>Bateria wystarcza na 6-10 godzin ciągłego użytkowania, co przekłada się na wiele dni zabawy.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">🧠</div>
                <h3>Inteligentny procesor</h3>
                <p>Bazując na Raspberry Pi Zero 2, nasze urządzenia oferują zaawansowane funkcje głosowe i możliwość rozbudowy.</p>
            </div>
        </div>
    </div>
</section>

<section class="products" id="products">
    <div class="container">
        <h2 class="section-title">Nasze produkty</h2>
        <p class="section-subtitle">Wybierz idealny model dla zabawek Twojego dziecka</p>

        <div class="product-cards">
            <?php foreach ($products as $product): ?>
                <div class="product-card">
                    <div class="product-image">
                        <img src="images/products/<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" />
                    </div>
                    <div class="product-content">
                        <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                        <p class="product-description"><?php echo htmlspecialchars($product['description']); ?></p>

                        <div class="product-availability">
                            <?php if($availabilityInfo[$product['type']]['low_stock']): ?>
                                <p class="low-stock">Zostało tylko <?php echo $availabilityInfo[$product['type']]['available']; ?> sztuk!</p>
                            <?php else: ?>
                                <p class="in-stock">Dostępność: <?php echo $availabilityInfo[$product['type']]['available']; ?> sztuk</p>
                            <?php endif; ?>

                            <div class="availability-bar">
                                <div class="availability-progress" style="width: <?php echo $availabilityInfo[$product['type']]['percentage']; ?>%"></div>
                            </div>
                            <p class="availability-info">Sprzedano <?php echo $availabilityInfo[$product['type']]['limit'] - $availabilityInfo[$product['type']]['available']; ?> z <?php echo $availabilityInfo[$product['type']]['limit']; ?></p>
                        </div>

                        <div class="product-price">
                            <span class="price-tag"><?php echo formatPrice($product['price']); ?></span>
                            <span class="price-note">Przedsprzedaż: <?php echo formatPrice($product['price'] * 0.8); ?></span>
                        </div>

                        <form action="" method="post" class="add-to-cart-form">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                            <input type="hidden" name="add_to_cart" value="1">

                            <div class="quantity-selector">
                                <label for="quantity-<?php echo $product['id']; ?>">Ilość:</label>
                                <select name="quantity" id="quantity-<?php echo $product['id']; ?>">
                                    <?php for ($i = 1; $i <= min(5, $product['stock']); $i++): ?>
                                        <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>

                            <button type="submit" class="btn product-btn">Dodaj do koszyka</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="testimonials">
    <div class="container">
        <h2 class="section-title">Co mówią rodzice i dzieci</h2>

        <div class="testimonials-slider">
            <div class="testimonial">
                <div class="testimonial-content">
                    <p>"Moja córka jest zachwycona! Teraz jej ulubiona lalka może z nią rozmawiać. To niesamowite jak technologia MovaKid sprawiła, że zabawka stała się interaktywna."</p>
                </div>
                <div class="testimonial-author">
                    <img src="images/testimonials/anna.jpg" alt="Anna K." class="testimonial-avatar" />
                    <div class="testimonial-info">
                        <h4>Anna K.</h4>
                        <p>Mama 5-letniej Zosi</p>
                    </div>
                </div>
            </div>

            <div class="testimonial">
                <div class="testimonial-content">
                    <p>"Jako tata pracujący często poza domem, mogę nagrać opowieści, które mój syn może usłyszeć ze swojego pluszaka. To zbliża nas nawet, gdy jestem daleko."</p>
                </div>
                <div class="testimonial-author">
                    <img src="images/testimonials/marcin.jpg" alt="Marcin T." class="testimonial-avatar" />
                    <div class="testimonial-info">
                        <h4>Marcin T.</h4>
                        <p>Tata 4-letniego Kuby</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="faq" id="faq">
    <div class="container">
        <h2 class="section-title">Najczęściej zadawane pytania</h2>

        <div class="faq-list">
            <div class="faq-item">
                <div class="faq-question">Czy MovaKid jest bezpieczny dla dzieci?</div>
                <div class="faq-answer">
                    <p>Tak, MovaKid spełnia wszystkie europejskie normy bezpieczeństwa dla zabawek. Obudowa wykonana jest z materiałów bezpiecznych dla dzieci, bez ostrych krawędzi. Bateria jest bezpiecznie zamknięta w obudowie, a maksymalna głośność jest ograniczona do bezpiecznego poziomu.</p>
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question">Jak nagrać dźwięki do urządzenia?</div>
                <div class="faq-answer">
                    <p>MovaKid można programować na dwa sposoby: bezpośrednio poprzez przyciski na urządzeniu lub za pomocą aplikacji mobilnej. Aplikacja pozwala na nagrywanie, organizowanie i przesyłanie dźwięków do urządzenia przez Bluetooth.</p>
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question">Ile czasu trwa ładowanie baterii?</div>
                <div class="faq-answer">
                    <p>Pełne ładowanie zajmuje około 2 godzin dla MovaKid Sphere i 2,5 godziny dla MovaKid DualSphere. Ładowanie odbywa się przez standardowy port USB-C.</p>
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question">Czy urządzenie wymaga połączenia z internetem?</div>
                <div class="faq-answer">
                    <p>Nie, MovaKid działa niezależnie i nie wymaga połączenia z internetem do podstawowych funkcji. WiFi i Bluetooth są używane tylko do aktualizacji oprogramowania i zarządzania dźwiękami poprzez aplikację.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="cta">
    <div class="container">
        <h2>Zamów teraz i skorzystaj z promocji przedsprzedażowej!</h2>
        <p>Tylko przez najbliższe 7 dni otrzymasz urządzenie MovaKid w specjalnej cenie. Dodatkowo, pierwsze 100 zamówień otrzyma bezpłatny zestaw kreatywnych dźwięków.</p>

        <div class="countdown">
            <p>Oferta kończy się za:</p>
            <div class="timer">
                <div class="timer-item">
                    <div class="timer-value" id="days">7</div>
                    <div class="timer-label">Dni</div>
                </div>
                <div class="timer-item">
                    <div class="timer-value" id="hours">23</div>
                    <div class="timer-label">Godziny</div>
                </div>
                <div class="timer-item">
                    <div class="timer-value" id="minutes">59</div>
                    <div class="timer-label">Minuty</div>
                </div>
                <div class="timer-item">
                    <div class="timer-value" id="seconds">59</div>
                    <div class="timer-label">Sekundy</div>
                </div>
            </div>
        </div>

        <a href="#products" class="btn btn-large">ZAMÓW TERAZ</a>
    </div>
</section>

<section class="newsletter">
    <div class="container">
        <h2>Zapisz się do newslettera</h2>
        <p>Bądź na bieżąco z nowościami i promocjami MovaKid</p>

        <form id="newsletter-form" class="newsletter-form">
            <input type="email" id="newsletter-email" placeholder="Twój adres e-mail" required>
            <select id="product-interest">
                <option value="">Który produkt Cię interesuje?</option>
                <option value="sphere">MovaKid Sphere</option>
                <option value="dualsphere">MovaKid DualSphere</option>
                <option value="both">Oba produkty</option>
            </select>
            <button type="submit" class="btn">Zapisz się</button>
        </form>
        <div id="newsletter-message"></div>
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
                    <li><a href="#how-it-works">Jak to działa</a></li>
                    <li><a href="#products">Produkty</a></li>
                    <li><a href="o-nas.php">O nas</a></li>
                    <li><a href="kontakt.php">Kontakt</a></li>
                </ul>
            </div>

            <div class="footer-contact">
                <h4>Kontakt</h4>
                <p>Email: info@movakid.com</p>
                <p>Tel: +48 123 456 789</p>
                <div class="social-icons">
                    <a href="#" class="social-icon">FB</a>
                    <a href="#" class="social-icon">IG</a>
                    <a href="#" class="social-icon">YT</a>
                </div>
            </div>
        </div>

        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> MovaKid. Wszystkie prawa zastrzeżone.</p>
            <div class="footer-legal">
                <a href="polityka-prywatnosci.php">Polityka prywatności</a>
                <a href="warunki-uzytkowania.php">Warunki użytkowania</a>
            </div>
        </div>
    </div>
</footer>

<script src="script.js"></script>
<script>
    // Obsługa formularza newslettera
    document.getElementById('newsletter-form').addEventListener('submit', function(e) {
        e.preventDefault();

        const email = document.getElementById('newsletter-email').value;
        const productInterest = document.getElementById('product-interest').value;

        fetch('ajax_handler.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'subscribe_newsletter',
                email: email,
                product_interest: productInterest
            }),
        })
            .then(response => response.json())
            .then(data => {
                const messageElement = document.getElementById('newsletter-message');
                messageElement.textContent = data.message;
                messageElement.className = data.success ? 'success-message' : 'error-message';

                if (data.success) {
                    document.getElementById('newsletter-form').reset();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('newsletter-message').textContent = 'Wystąpił błąd. Spróbuj ponownie później.';
                document.getElementById('newsletter-message').className = 'error-message';
            });
    });
</script>
</body>
</html>