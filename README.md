# www
www.movakid.com
# Instrukcja wdrożenia plików MovaKid

## Struktura plików

Strona MovaKid została podzielona na trzy główne pliki:

1. `index.html` - struktura strony i zawartość tekstowa
2. `styles.css` - wszystkie style odpowiedzialne za wygląd strony
3. `script.js` - interaktywność strony i funkcje JavaScript

## Jak wdrożyć stronę

### 1. Przygotowanie środowiska

Upewnij się, że posiadasz dostęp do serwera i domeny (najlepiej movakid.com). Możesz wykorzystać:
- Hosting współdzielony (np. nazwa.pl, home.pl, hostinger.pl)
- VPS/Serwer dedykowany
- Usługi hostingowe typu Netlify, Vercel, GitHub Pages

### 2. Uploading plików

#### Dla tradycyjnego hostingu:
1. Połącz się z serwerem przez FTP (możesz użyć programów jak FileZilla, WinSCP)
2. Prześlij wszystkie trzy pliki (`index.html`, `styles.css`, `script.js`) do katalogu głównego
3. Upewnij się, że zachowana jest struktura:
   ```
   public_html/
   ├── index.html
   ├── styles.css
   └── script.js
   ```

#### Dla platform jak Netlify/Vercel:
1. Utwórz repozytorium Git z tymi plikami
2. Połącz repozytorium z platformą (Netlify, Vercel)
3. Skonfiguruj automatyczny deployment

### 3. Obrazy zastępcze

W obecnej implementacji używane są placeholdery obrazów w formacie:
```html
<img src="/api/placeholder/500/400" alt="...">
```

W finalnej wersji należy je zastąpić rzeczywistymi obrazami. Przygotuj:
- Zdjęcia produktów (MovaKid Sphere i DualSphere)
- Ikonki dla sekcji funkcji
- Zdjęcia profilowe dla testimoniali
- Ewentualne zdjęcia tła

### 4. Domena i SSL

1. Skonfiguruj domenę movakid.com aby wskazywała na serwer
2. Aktywuj certyfikat SSL dla zapewnienia bezpiecznego połączenia (https://)
3. Skonfiguruj przekierowanie z http:// na https://

### 5. Integracja systemu płatności

Obecna strona zawiera tylko makiety przycisków "Dodaj do koszyka". Do pełnej funkcjonalności:

1. Zintegruj system koszyka zakupowego
2. Dodaj systemy płatności (np. PayPal, Stripe, Przelewy24)
3. Skonfiguruj powiadomienia email o zamówieniach

```javascript
// Przykład integracji z Stripe (do umieszczenia w script.js)
function initializeStripe() {
  const stripe = Stripe('your_stripe_public_key');
  const elements = stripe.elements();
  
  // Konfiguracja elementów płatności...
}
```

### 6. Konfiguracja Google Analytics

Dodaj śledzenie strony, aby monitorować konwersje i zachowanie użytkowników:

```html
<!-- Umieść to tuż przed zamknięciem tagu </head> -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-XXXXXXXXXX"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', 'G-XXXXXXXXXX');
</script>
```

## Porady i uwagi

### Responsywność
Strona jest responsywna dzięki zaprojektowanym stylom CSS. Przetestuj wyświetlanie na różnych urządzeniach:
- Telefonach komórkowych
- Tabletach
- Monitorach komputerowych

### Optymalizacja wydajności
Po dodaniu rzeczywistych obrazów, upewnij się, że:
- Obrazy są zoptymalizowane (narzędzia jak TinyPNG)
- Używasz formatów WebP tam, gdzie to możliwe
- JavaScript jest minifikowany w wersji produkcyjnej

### Testowanie przed uruchomieniem
1. Sprawdź działanie wszystkich linków
2. Przetestuj formularze i przyciski
3. Zweryfikuj czy timer odliczania działa poprawnie
4. Sprawdź działanie FAQ (rozwijanie/zwijanie)

### Systemy rezerwacji towaru

Jeśli planujesz przedsprzedaż z ograniczoną liczbą produktów:

```javascript
// Dodaj do script.js
function checkInventory(productType) {
  // Połączenie z API sprawdzającym dostępność
  fetch('/api/inventory/' + productType)
    .then(response => response.json())
    .then(data => {
      if (data.available <= 10) {
        // Pokaż komunikat o ograniczonej dostępności
      }
    });
}
```

## Dalszy rozwój

### Potencjalne rozszerzenia strony
1. Dodanie sekcji bloga z poradami
2. Rozbudowa o galerię zdjęć
3. Dodanie filmów prezentacyjnych produktów
4. Sekcja opinii klientów z możliwością dodawania recenzji
5. System rejestracji i logowania użytkowników

### Integracje marketingowe
1. Formularz zapisów do newslettera
2. Integracja z platformami social media
3. System polecania znajomym z kodami rabatowymi
4. Chatbot dla obsługi klienta