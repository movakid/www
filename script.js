document.addEventListener('DOMContentLoaded', function() {
    // FAQ toggling
    setupFAQ();

    // Countdown timer
    startCountdown();

    // Product buttons
    setupProductButtons();

    // Smooth scrolling for navigation links
    setupSmoothScrolling();
});

/**
 * Sets up the FAQ section's toggle functionality
 */
function setupFAQ() {
    const faqQuestions = document.querySelectorAll('.faq-question');

    faqQuestions.forEach(question => {
        question.addEventListener('click', () => {
            // Toggle active class on the question
            question.classList.toggle('active');

            // Get the associated answer
            const answer = question.nextElementSibling;

            // Toggle display of the answer
            if (answer.style.display === 'block') {
                answer.style.display = 'none';
            } else {
                answer.style.display = 'block';
            }
        });
    });
}

/**
 * Starts the countdown timer for the special offer
 */
function startCountdown() {
    // Elements
    const daysEl = document.getElementById('days');
    const hoursEl = document.getElementById('hours');
    const minutesEl = document.getElementById('minutes');
    const secondsEl = document.getElementById('seconds');

    // Set the end date for the countdown (7 days from now)
    const endDate = new Date();
    endDate.setDate(endDate.getDate() + 7);

    // Update the countdown every second
    const countdownInterval = setInterval(updateCountdown, 1000);

    // Initial update
    updateCountdown();

    function updateCountdown() {
        const now = new Date();
        const timeDifference = endDate - now;

        // Check if countdown has ended
        if (timeDifference <= 0) {
            clearInterval(countdownInterval);
            daysEl.textContent = '0';
            hoursEl.textContent = '0';
            minutesEl.textContent = '0';
            secondsEl.textContent = '0';
            return;
        }

        // Calculate time units
        const days = Math.floor(timeDifference / (1000 * 60 * 60 * 24));
        const hours = Math.floor((timeDifference % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((timeDifference % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((timeDifference % (1000 * 60)) / 1000);

        // Update the DOM
        daysEl.textContent = days;
        hoursEl.textContent = hours < 10 ? `0${hours}` : hours;
        minutesEl.textContent = minutes < 10 ? `0${minutes}` : minutes;
        secondsEl.textContent = seconds < 10 ? `0${seconds}` : seconds;
    }
}

/**
 * Sets up the product buttons with shopping cart functionality
 */
function setupProductButtons() {
    const cartItems = [];
    const productButtons = document.querySelectorAll('.product-btn');

    productButtons.forEach(button => {
        button.addEventListener('click', () => {
            const productType = button.getAttribute('data-product');
            let productName, productPrice;

            // Get product details based on type
            if (productType === 'sphere') {
                productName = 'MovaKid Sphere';
                productPrice = 59.99;
            } else if (productType === 'dualsphere') {
                productName = 'MovaKid DualSphere';
                productPrice = 79.99;
            }

            // Add to cart
            cartItems.push({
                type: productType,
                name: productName,
                price: productPrice
            });

            // Provide feedback to user
            button.textContent = 'Dodano do koszyka ✓';
            button.style.backgroundColor = '#4CAF50';

            // Reset button after a delay
            setTimeout(() => {
                button.textContent = 'Dodaj do koszyka';
                button.style.backgroundColor = '';
            }, 2000);

            // Here you would normally update the cart icon/counter
            console.log('Koszyk:', cartItems);
        });
    });
}

/**
 * Sets up smooth scrolling for navigation links
 */
function setupSmoothScrolling() {
    const links = document.querySelectorAll('a[href^="#"]');

    links.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();

            const targetId = this.getAttribute('href');
            if (targetId === '#') return;

            const targetElement = document.querySelector(targetId);
            if (targetElement) {
                // Smooth scroll to target
                targetElement.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
}