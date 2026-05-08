<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - MinC Computer Parts</title>

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Font Awesome -->
    <script src="https://kit.fontawesome.com/ca30ddfff9.js" crossorigin="anonymous"></script>

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        .hero-gradient {
            background: linear-gradient(135deg, #08415c 0%, #0a5273 50%, #08415c 100%);
        }

        .btn-primary-custom {
            background: linear-gradient(135deg, #08415c 0%, #0a5273 100%);
            transition: all 0.3s ease;
        }

        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(8, 65, 92, 0.4);
        }

        .cart-item {
            transition: all 0.3s ease;
        }

        .cart-item:hover {
            background-color: #f9fafb;
        }

        .quantity-input {
            width: 60px;
            text-align: center;
        }

        #loader {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.9);
            z-index: 9999;
        }

        #loader.active {
            display: flex;
        }
    </style>
</head>

<body class="bg-gray-50">

    <!-- Loader -->
    <div id="loader" class="flex items-center justify-center">
        <div class="text-center">
            <div class="animate-spin rounded-full h-16 w-16 border-b-2 border-[#08415c] mx-auto mb-4"></div>
            <p class="text-[#08415c] font-semibold">Loading cart...</p>
        </div>
    </div>

    <!-- Navigation Component -->
    <?php include 'components/navbar.php'; ?>

    <!-- Page Header -->
    <section class="hero-gradient mt-20 py-12 px-4">
        <div class="max-w-7xl mx-auto">
            <nav class="text-white mb-4">
                <a href="../index.php" class="hover:text-blue-100 transition">Home</a>
                <span class="mx-2">/</span>
                <span>Shopping Cart</span>
            </nav>
            <h1 class="text-4xl md:text-5xl font-bold text-white mb-2">Shopping Cart</h1>
            <p class="text-blue-100 text-lg">Review your items before checkout</p>
        </div>
    </section>

    <!-- Cart Content -->
    <div class="max-w-7xl mx-auto px-4 py-16">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Cart Items -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h2 class="text-2xl font-bold text-[#08415c] mb-6">Cart Items</h2>
                    
                    <!-- Cart Items Container -->
                    <div id="cartItemsContainer">
                        <!-- Items will be loaded here -->
                    </div>

                    <!-- Empty Cart Message -->
                    <div id="emptyCart" class="hidden text-center py-12">
                        <i class="fas fa-shopping-cart text-6xl text-gray-300 mb-4"></i>
                        <p class="text-xl text-gray-600 font-medium mb-4">Your cart is empty</p>
                        <a href="product.php" class="btn-primary-custom text-white px-6 py-3 rounded-lg font-semibold inline-block">
                            <i class="fas fa-arrow-left mr-2"></i>Continue Shopping
                        </a>
                    </div>
                </div>
            </div>

            <!-- Order Summary -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-xl shadow-lg p-6 sticky top-24">
                    <h2 class="text-2xl font-bold text-[#08415c] mb-6">Order Summary</h2>
                    
                    <div class="space-y-4 mb-6">
                        <div class="flex justify-between text-gray-700">
<span>Subtotal:</span>
<span class="font-semibold">₱<span id="summarySubtotal">0.00</span></span>
</div>
<div class="flex justify-between text-gray-700">
<span>Shipping:</span>
<span class="font-semibold">₱<span id="summaryShipping">0.00</span></span>
</div>
<hr class="border-gray-200">
<div class="flex justify-between text-lg font-bold text-[#08415c]">
<span>Total:</span>
<span>₱<span id="summaryTotal">0.00</span></span>
</div>
</div>
<button onclick="proceedToCheckout()" id="checkoutBtn" class="w-full btn-primary-custom text-white py-3 rounded-lg font-semibold mb-3">
                    <i class="fas fa-lock mr-2"></i>Proceed to Checkout
                </button>

                <a href="product.php" class="block w-full text-center bg-gray-100 text-[#08415c] py-3 rounded-lg font-semibold hover:bg-gray-200 transition">
                    <i class="fas fa-arrow-left mr-2"></i>Continue Shopping
                </a>

                <!-- Shipping Info -->
                <div class="mt-6 p-4 bg-blue-50 rounded-lg">
                    <p class="text-sm text-gray-600">
                        <i class="fas fa-truck mr-2 text-[#08415c]"></i>
                        Free shipping on orders over ₱1,000 (otherwise ₱150 shipping fee)
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Footer Component -->
<?php include 'components/footer.php'; ?>

<!-- Login Modal -->
<div id="loginModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-8 relative">
        <button onclick="closeLoginModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600">
            <i class="fas fa-times text-2xl"></i>
        </button>

        <div id="loginForm">
            <h2 class="text-3xl font-bold mb-6 text-[#08415c]">Welcome Back</h2>
            <form onsubmit="handleLogin(event)">
                <div class="mb-4">
                    <label class="block text-gray-700 font-medium mb-2">Email</label>
                    <input type="email" id="loginEmail" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#08415c]">
                </div>
                <div class="mb-6">
                    <label class="block text-gray-700 font-medium mb-2">Password</label>
                    <input type="password" id="loginPassword" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#08415c]">
                </div>
                <button type="submit" class="w-full btn-primary-custom text-white py-3 rounded-lg font-semibold">
                    Login
                </button>
            </form>
            <p class="text-center mt-6 text-gray-600">
                Don't have an account? 
                <button onclick="showRegister()" class="text-[#08415c] font-semibold hover:text-[#0a5273]">Register</button>
            </p>
        </div>

        <div id="registerForm" class="hidden">
            <h2 class="text-3xl font-bold mb-6 text-[#08415c]">Create Account</h2>
            <form onsubmit="handleRegister(event)">
                <div class="mb-4">
                    <label class="block text-gray-700 font-medium mb-2">First Name</label>
                    <input type="text" id="registerFname" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#08415c]">
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 font-medium mb-2">Last Name</label>
                    <input type="text" id="registerLname" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#08415c]">
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 font-medium mb-2">Email</label>
                    <input type="email" id="registerEmail" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#08415c]">
                </div>
                <div class="mb-6">
                    <label class="block text-gray-700 font-medium mb-2">Password</label>
                    <input type="password" id="registerPassword" required minlength="6" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#08415c]">
                </div>
                <button type="submit" class="w-full btn-primary-custom text-white py-3 rounded-lg font-semibold">
                    Register
                </button>
            </form>
            <p class="text-center mt-6 text-gray-600">
                Already have an account? 
                <button onclick="showLogin()" class="text-[#08415c] font-semibold hover:text-[#0a5273]">Login</button>
            </p>
        </div>
    </div>
</div>

<script>
    // Global variables
    const IS_LOGGED_IN = <?php echo isset($_SESSION['user_id']) ? 'true' : 'false'; ?>;
    let cartItems = [];
    let subtotal = 0;
    const SHIPPING_FEE = 150;
    const FREE_SHIPPING_THRESHOLD = 1000;

    // Format currency
    function formatPeso(amount) {
        return parseFloat(amount).toLocaleString('en-PH', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    // Escape HTML
    function escapeHtml(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.toString().replace(/[&<>"']/g, m => map[m]);
    }

    // Load cart
    async function loadCart() {
        showLoader();
        
        try {
            const response = await fetch('../backend/cart/cart_get.php');
            const data = await response.json();

            if (data.success) {
                cartItems = data.cart_items || [];
                subtotal = parseFloat(data.subtotal) || 0;
                
                displayCart();
                updateSummary();
                updateCartCount(data.cart_count);
            } else {
                showError(data.message || 'Failed to load cart');
            }
        } catch (error) {
            console.error('Error loading cart:', error);
            showError('An error occurred while loading cart');
        } finally {
            hideLoader();
        }
    }

    // Display cart
    function displayCart() {
        const container = document.getElementById('cartItemsContainer');
        const emptyCart = document.getElementById('emptyCart');
        const checkoutBtn = document.getElementById('checkoutBtn');

        if (cartItems.length === 0) {
            container.classList.add('hidden');
            emptyCart.classList.remove('hidden');
            checkoutBtn.disabled = true;
            checkoutBtn.classList.add('opacity-50', 'cursor-not-allowed');
            return;
        }

        container.classList.remove('hidden');
        emptyCart.classList.add('hidden');
        checkoutBtn.disabled = false;
        checkoutBtn.classList.remove('opacity-50', 'cursor-not-allowed');

        container.innerHTML = cartItems.map(item => {
            const imagePath = item.product_image 
                ? `../Assets/images/products/${item.product_image}` 
                : '../Assets/images/website-images/placeholder.svg';

            const isOutOfStock = item.stock_status === 'out_of_stock';
            const isLowStock = item.stock_status === 'low_stock';

            return `
                <div class="cart-item border-b border-gray-200 py-6 ${isOutOfStock ? 'opacity-50' : ''}">
                    <div class="flex flex-col md:flex-row gap-4">
                        <!-- Product Image -->
                        <div class="flex-shrink-0">
                            <img src="${imagePath}" 
                                 alt="${escapeHtml(item.product_name)}" 
                                 class="w-24 h-24 object-cover rounded-lg"
                                 onerror="this.src='../Assets/images/website-images/placeholder.svg'">
                        </div>

                        <!-- Product Details -->
                        <div class="flex-grow">
                            <div class="flex justify-between items-start mb-2">
                                <div>
                                    <h3 class="text-lg font-bold text-[#08415c]">${escapeHtml(item.product_name)}</h3>
                                    <p class="text-sm text-gray-500">${escapeHtml(item.product_line_name)}</p>
                                    ${item.product_code ? `<p class="text-xs text-gray-400">Code: ${escapeHtml(item.product_code)}</p>` : ''}
                                </div>
                                <button onclick="removeFromCart(${item.cart_item_id})" 
                                        class="text-red-500 hover:text-red-700 transition">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>

                            <!-- Stock Status -->
                            ${isOutOfStock ? 
                                '<p class="text-red-500 text-sm font-semibold mb-2"><i class="fas fa-exclamation-circle mr-1"></i>Out of Stock</p>' 
                                : isLowStock ? 
                                `<p class="text-orange-500 text-sm mb-2"><i class="fas fa-exclamation-triangle mr-1"></i>Only ${item.stock_quantity} left</p>` 
                                : ''}

                            <!-- Price and Quantity -->
                            <div class="flex flex-wrap items-center gap-4 mt-3">
                                <div class="text-lg font-bold text-[#08415c]">
                                    ₱${formatPeso(item.price)}
                                </div>

                                <!-- Quantity Controls -->
                                <div class="flex items-center border border-gray-300 rounded-lg ${isOutOfStock ? 'opacity-50 pointer-events-none' : ''}">
                                    <button onclick="updateQuantity(${item.cart_item_id}, ${item.quantity - 1}, ${item.stock_quantity})" 
                                            class="px-3 py-1 hover:bg-gray-100 transition"
                                            ${item.quantity <= 1 ? 'disabled' : ''}>
                                        <i class="fas fa-minus text-sm"></i>
                                    </button>
                                    <input type="number" 
                                           value="${item.quantity}" 
                                           min="1" 
                                           max="${item.stock_quantity}"
                                           class="quantity-input border-x border-gray-300 py-1 focus:outline-none"
                                           onchange="updateQuantity(${item.cart_item_id}, this.value, ${item.stock_quantity})"
                                           ${isOutOfStock ? 'disabled' : ''}>
                                    <button onclick="updateQuantity(${item.cart_item_id}, ${item.quantity + 1}, ${item.stock_quantity})" 
                                            class="px-3 py-1 hover:bg-gray-100 transition"
                                            ${item.quantity >= item.stock_quantity ? 'disabled' : ''}>
                                        <i class="fas fa-plus text-sm"></i>
                                    </button>
                                </div>

                                <!-- Item Total -->
                                <div class="ml-auto">
                                    <p class="text-sm text-gray-500">Total:</p>
                                    <p class="text-xl font-bold text-[#08415c]">₱${formatPeso(item.item_total)}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }

    // Update quantity
    async function updateQuantity(cartItemId, newQuantity, maxStock) {
        newQuantity = parseInt(newQuantity);
        
        if (newQuantity < 1 || newQuantity > maxStock) {
            Swal.fire({
                icon: 'warning',
                title: 'Invalid Quantity',
                text: `Quantity must be between 1 and ${maxStock}`,
                confirmButtonColor: '#08415c'
            });
            return;
        }

        try {
            const response = await fetch('../backend/cart/cart_update.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    cart_item_id: cartItemId,
                    quantity: newQuantity
                })
            });

            const data = await response.json();

            if (data.success) {
                loadCart();
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Update Failed',
                    text: data.message,
                    confirmButtonColor: '#08415c'
                });
            }
        } catch (error) {
            console.error('Error updating quantity:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Failed to update quantity',
                confirmButtonColor: '#08415c'
            });
        }
    }

    // Remove from cart
    async function removeFromCart(cartItemId) {
        const result = await Swal.fire({
            title: 'Remove Item?',
            text: 'Are you sure you want to remove this item from cart?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#08415c',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, remove it'
        });

        if (!result.isConfirmed) return;

        try {
            const response = await fetch('../backend/cart/cart_remove.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    cart_item_id: cartItemId
                })
            });

            const data = await response.json();

            if (data.success) {
                if (typeof window.showAppToast === 'function') {
                    window.showAppToast('Item removed from cart', 'success', { timer: 1800 });
                } else {
                    Swal.fire({
                        icon: 'success',
                        title: 'Item removed from cart',
                        toast: true,
                        position: 'top',
                        showConfirmButton: false,
                        timer: 1800,
                        timerProgressBar: true
                    });
                }
                loadCart();
            } else {
                throw new Error(data.message);
            }
        } catch (error) {
            console.error('Error removing item:', error);
            if (typeof window.showAppToast === 'function') {
                window.showAppToast('Failed to remove item', 'error');
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Failed to remove item',
                    toast: true,
                    position: 'top',
                    showConfirmButton: false,
                    timer: 2600,
                    timerProgressBar: true
                });
            }
        }
    }

    // Update summary
    function updateSummary() {
        const shippingFee = subtotal >= FREE_SHIPPING_THRESHOLD ? 0 : SHIPPING_FEE;
        const total = subtotal + shippingFee;

        document.getElementById('summarySubtotal').textContent = formatPeso(subtotal);
        document.getElementById('summaryShipping').textContent = shippingFee === 0 ? 'FREE' : formatPeso(shippingFee);
        document.getElementById('summaryTotal').textContent = formatPeso(total);
    }

    // Update cart count
    function updateCartCount(count) {
        document.querySelectorAll('.cart-count').forEach(el => el.textContent = count || 0);
    }

    // Proceed to checkout
    async function proceedToCheckout() {
        try {
            // Revalidate cart state so checkout is based on fresh server data.
            const response = await fetch('../backend/cart/cart_get.php', { cache: 'no-store' });
            const data = await response.json();
            const latestItems = data.success && Array.isArray(data.cart_items) ? data.cart_items : cartItems;

            if (!latestItems || latestItems.length === 0) {
                if (typeof window.showAppToast === 'function') {
                    window.showAppToast('Please add items to cart before checkout', 'warning');
                } else {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Empty Cart',
                        text: 'Please add items to cart before checkout',
                        confirmButtonColor: '#08415c'
                    });
                }
                return;
            }

            const outOfStockItems = latestItems.filter(item =>
                item.stock_status === 'out_of_stock' || Number(item.stock_quantity) <= 0
            );

            if (outOfStockItems.length > 0) {
                if (typeof window.showAppToast === 'function') {
                    window.showAppToast('Please remove out of stock items before checkout', 'warning');
                } else {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Out of Stock Items',
                        text: 'Please remove out of stock items before checkout',
                        confirmButtonColor: '#08415c'
                    });
                }
                return;
            }

            if (!IS_LOGGED_IN) {
                if (typeof window.setPostLoginRedirect === 'function') {
                    window.setPostLoginRedirect('checkout.php');
                }
                openLoginModal();
                if (typeof window.showAppToast === 'function') {
                    window.showAppToast('Please sign in or register before ordering.', 'info');
                }
                return;
            }

            window.location.href = 'checkout.php';
        } catch (error) {
            console.error('Checkout validation failed:', error);
            Swal.fire({
                icon: 'error',
                title: 'Checkout Error',
                text: 'Unable to continue checkout right now. Please try again.',
                confirmButtonColor: '#08415c'
            });
        }
    }

    // Loader functions
    function showLoader() {
        document.getElementById('loader').classList.add('active');
    }

    function hideLoader() {
        document.getElementById('loader').classList.remove('active');
    }

    // Show error
    function showError(message) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: message,
            confirmButtonColor: '#08415c'
        });
    }

    // Mobile menu toggle
    function toggleMobileMenu() {
        document.getElementById('mobileMenu').classList.toggle('hidden');
    }

    // Modal functions
    function openLoginModal() {
        if (typeof window.setPostLoginRedirect === 'function') {
            window.setPostLoginRedirect('checkout.php');
        }
        document.getElementById('loginModal').classList.remove('hidden');
    }

    function closeLoginModal() {
        document.getElementById('loginModal').classList.add('hidden');
    }

    function showRegister() {
        document.getElementById('loginForm').classList.add('hidden');
        document.getElementById('registerForm').classList.remove('hidden');
    }

    function showLogin() {
        document.getElementById('registerForm').classList.add('hidden');
        document.getElementById('loginForm').classList.remove('hidden');
    }

    // Handle login
    async function handleLogin(e) {
        if (typeof window.globalHandleLogin === 'function') {
            return window.globalHandleLogin(e);
        }
    }

    // Handle register
    async function handleRegister(e) {
        if (typeof window.globalHandleRegister === 'function') {
            return window.globalHandleRegister(e);
        }
    }

    // Check session
    function checkSession() {
        fetch('../backend/auth.php?api=status')
            .then(response => response.json())
            .then(data => {
                if (data.logged_in) {
                    updateUIForLoggedInUser(data.user);
                }
            })
            .catch(error => console.error('Session check error:', error));
    }

    // Update UI for logged in user
    function updateUIForLoggedInUser(user) {
        const userSection = document.getElementById('userSection');
        if (!userSection) return;
        userSection.innerHTML = `
            <div class="relative">
                <button id="userMenuButton" onclick="toggleUserMenu()" class="flex items-center space-x-2 text-gray-700 hover:text-[#08415c] transition">
                    <i class="fas fa-user-circle text-2xl"></i>
                    <span class="font-medium">${escapeHtml(user.name)}</span>
                    <i class="fas fa-chevron-down text-sm"></i>
                </button>
                <div id="userDropdown" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-xl py-2 z-50">
                    <a href="#" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">
                        <i class="fas fa-user mr-2"></i>Profile
                    </a>
                    <a href="#" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">
                        <i class="fas fa-shopping-bag mr-2"></i>Orders
                    </a>
                    ${user.user_level_id <= 2 ? `
                    <a href="../app/frontend/dashboard.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">
                        <i class="fas fa-tachometer-alt mr-2"></i>Dashboard
                    </a>` : ''}
                    <hr class="my-2">
                    <button onclick="handleLogout()" class="w-full text-left px-4 py-2 text-red-600 hover:bg-gray-100">
                        <i class="fas fa-sign-out-alt mr-2"></i>Logout
                    </button>
                </div>
            </div>
        `;
    }

    // Toggle user menu
    function toggleUserMenu() {
        const dropdown = document.getElementById('userDropdown');
        dropdown.classList.toggle('hidden');
    }

    // Handle logout
    async function handleLogout() {
        if (typeof window.globalHandleLogout === 'function') {
            return window.globalHandleLogout();
        }
    }

    // Close dropdown when clicking outside
    document.addEventListener('click', function(event) {
        const userMenuButton = document.getElementById('userMenuButton');
        const userDropdown = document.getElementById('userDropdown');
        
        if (userMenuButton && userDropdown) {
            const isClickInside = userMenuButton.contains(event.target) || userDropdown.contains(event.target);
            
            if (!isClickInside && !userDropdown.classList.contains('hidden')) {
                userDropdown.classList.add('hidden');
            }
        }
    });

    // Initialize page
    document.addEventListener('DOMContentLoaded', () => {
        checkSession();
        loadCart();
    });
</script>
<!-- Chat Bubble Component -->
<?php include 'components/chat_bubble.php'; ?>
</body>
</html>
