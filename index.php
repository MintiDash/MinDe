<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MinC - Auto Parts Store</title>

<!-- Tailwind CSS CDN -->
<script src="https://cdn.tailwindcss.com"></script>

<!-- Font Awesome -->
<script src="https://kit.fontawesome.com/ca30ddfff9.js" crossorigin="anonymous"></script>

<!-- Google Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

<!-- SweetAlert2 for beautiful alerts -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
body {
    font-family: 'Inter', sans-serif;
}

.hero-gradient {
    background: linear-gradient(135deg, #08415c 0%, #0a5273 50%, #08415c 100%);
    position: relative;
    overflow: hidden;
}

.hero-gradient::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: radial-gradient(circle at 30% 50%, rgba(255,255,255,0.1) 0%, transparent 50%);
}

.card-hover {
    transition: all 0.3s ease;
    border: 2px solid transparent;
}

.card-hover:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 40px rgba(8, 65, 92, 0.3);
    border-color: #08415c;
}

.category-badge {
    background: linear-gradient(135deg, #08415c 0%, #0a5273 100%);
}

.feature-icon {
    background: linear-gradient(135deg, #08415c 0%, #0a5273 100%);
}

</style>
</head>
<body class="bg-gray-50">

<!-- Navigation Component -->
<?php include 'html/components/navbar.php'; ?>

<!-- Hero Section -->
<section class="hero-gradient mt-20 py-20 px-4">
<div class="max-w-7xl mx-auto">
<div class="grid md:grid-cols-2 gap-12 items-center">
<div class="text-white relative z-10">
<h1 class="text-5xl md:text-6xl font-bold mb-6 leading-tight">
Premium Auto Parts at Your Fingertips
</h1>
<p class="text-xl mb-8 text-blue-100">
Quality parts, unbeatable prices, and fast delivery for your vehicle needs
</p>
<div class="flex flex-wrap gap-4">
<a href="html/product.php" class="bg-white text-[#08415c] px-8 py-3 rounded-lg font-semibold hover:shadow-xl transition transform hover:scale-105 inline-block text-center">
Shop Now
</a>
<a href="#about-us" class="border-2 border-white text-white px-8 py-3 rounded-lg font-semibold hover:bg-white hover:text-[#08415c] transition inline-block text-center">
Learn More
</a>
</div>
</div>

<div class="hidden md:block relative z-10">
<img src="Assets/images/website-images/slider-1.webp" alt="Auto Parts" class="w-full rounded-2xl shadow-2xl transform hover:scale-105 transition duration-300" onerror="this.onerror=null;this.src='Assets/images/website-images/placeholder.svg';">
</div>
</div>
</div>
</section>

<!-- Features -->
<section class="py-16 bg-white">
<div class="max-w-7xl mx-auto px-4">
<div class="grid md:grid-cols-3 gap-8">
<div class="text-center p-8 rounded-xl hover:shadow-xl transition border-2 border-transparent hover:border-[#08415c]">
<div class="feature-icon w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
<i class="fas fa-star text-white text-2xl"></i>
</div>
<h3 class="text-xl font-bold mb-2 text-[#08415c]">High Quality</h3>
<p class="text-gray-600">Premium products from trusted manufacturers</p>
</div>

<div class="text-center p-8 rounded-xl hover:shadow-xl transition border-2 border-transparent hover:border-[#08415c]">
<div class="feature-icon w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
<i class="fas fa-shipping-fast text-white text-2xl"></i>
</div>
<h3 class="text-xl font-bold mb-2 text-[#08415c]">Fast Delivery</h3>
<p class="text-gray-600">Quick shipping to your doorstep</p>
</div>

<div class="text-center p-8 rounded-xl hover:shadow-xl transition border-2 border-transparent hover:border-[#08415c]">
<div class="feature-icon w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
<i class="fas fa-dollar-sign text-white text-2xl"></i>
</div>
<h3 class="text-xl font-bold mb-2 text-[#08415c]">Best Prices</h3>
<p class="text-gray-600">Competitive rates on all products</p>
</div>
</div>
</div>
</section>

<!-- About Section -->
<section id="about-us" class="py-20 bg-gradient-to-br from-gray-50 to-blue-50">
<div class="max-w-7xl mx-auto px-4">
<div class="grid md:grid-cols-2 gap-12 items-center">
<div>
<h2 class="text-4xl font-bold mb-6 text-[#08415c]">About MinC</h2>
<p class="text-lg text-gray-700 mb-6 leading-relaxed">
At MinC we offer an extensive selection of auto parts, truck parts and automotive accessories, so you can easily find the quality parts you need at the lowest price.
</p>
<p class="text-lg text-gray-700 leading-relaxed">
Explore our wide inventory to find both brand new car parts and second-hand parts for your vehicle.
</p>
</div>

<div class="hidden md:block">
<img src="Assets/images/website-images/about.png" alt="About MinC" class="w-full rounded-2xl shadow-xl transform hover:scale-105 transition duration-300" onerror="this.onerror=null;this.src='Assets/images/website-images/placeholder.svg';">
</div>
</div>
</div>
</section>

<!-- Categories Section -->
<section id="categories" class="py-20 bg-white">
<div class="max-w-7xl mx-auto px-4">
<div class="text-center mb-16">
<h2 class="text-4xl font-bold text-[#08415c] mb-4">Shop by Category</h2>
<p class="text-xl text-gray-600">Browse our extensive collection of auto parts</p>
</div>

<!-- Dynamic Categories Container -->
<div id="categoriesContainer">
<!-- Loading State -->
<div id="loadingState" class="text-center py-12">
<i class="fas fa-spinner fa-spin text-4xl text-[#08415c] mb-4"></i>
<p class="text-gray-600">Loading categories...</p>
</div>
</div>
</div>
</section>

<!-- Footer Component -->
<?php include 'html/components/footer.php'; ?>

<script>
// Run homepage initialization once
function initializePage() {
    checkSession();
    loadCategories();
    initializeCart();

    // Check for URL parameters
    const urlParams = new URLSearchParams(window.location.search);
    const error = urlParams.get('error');

    if (error === 'unauthorized') {
        Swal.fire({
            icon: 'warning',
            title: 'Unauthorized Access',
            text: 'Please login to access that page.',
            confirmButtonColor: '#08415c'
        });
    } else if (error === 'session_expired') {
        Swal.fire({
            icon: 'info',
            title: 'Session Expired',
            text: 'Your session has expired. Please login again.',
            confirmButtonColor: '#08415c'
        });
    } else if (error === 'access_denied') {
        Swal.fire({
            icon: 'error',
            title: 'Access Denied',
            text: 'You do not have permission to access that area.',
            confirmButtonColor: '#08415c'
        });
    }
}

function checkSession() {
    fetch('backend/auth.php?api=status')
    .then(response => response.json())
    .then(data => {
        if (data.logged_in) {
            updateUIForLoggedInUser(data.user);
        }
    })
    .catch(error => console.error('Session check error:', error));
}

// Cart initialization
function initializeCart() {
    fetch('backend/cart/cart_get.php')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateCartCount(data.cart_count);
        }
    })
    .catch(error => console.error('Error loading cart:', error));
}

// Update cart count
function updateCartCount(count) {
    document.querySelectorAll('.cart-count').forEach(el => el.textContent = count || 0);
}

function updateUIForLoggedInUser(user) {
    const userSection = document.getElementById('userSection');
    userSection.innerHTML = `
    <div class="relative">
    <button id="userMenuButton" onclick="toggleUserMenu()" class="flex items-center space-x-2 text-gray-700 hover:text-[#08415c] transition">
    <i class="fas fa-user-circle text-2xl"></i>
    <span class="font-medium">${user.name}</span>
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
        <a href="app/frontend/dashboard.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">
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

// Add these NEW functions right after updateUIForLoggedInUser function
function toggleUserMenu() {
    const dropdown = document.getElementById('userDropdown');
    dropdown.classList.toggle('hidden');
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

function showRegister() {
    document.getElementById('loginForm').classList.add('hidden');
    document.getElementById('registerForm').classList.remove('hidden');
    if (typeof resetRegistrationFlow === 'function') resetRegistrationFlow();
}

function showLogin() {
    document.getElementById('registerForm').classList.add('hidden');
    document.getElementById('loginForm').classList.remove('hidden');
    if (typeof resetRegistrationFlow === 'function') resetRegistrationFlow();
}

async function handleLogin(e) {
    if (typeof window.globalHandleLogin === 'function') {
        return window.globalHandleLogin(e);
    }
}

async function handleRegister(e) {
    if (typeof window.globalHandleRegister === 'function') {
        return window.globalHandleRegister(e);
    }
}

async function handleLogout() {
    if (typeof window.globalHandleLogout === 'function') {
        return window.globalHandleLogout();
    }
}

// Smooth scroll for anchor links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            // Close mobile menu if open
            const mobileMenu = document.getElementById('mobileMenu');
            if (!mobileMenu.classList.contains('hidden')) {
                mobileMenu.classList.add('hidden');
            }
        }
    });
});

// Fetch and render categories
async function loadCategories() {
    try {
        const response = await fetch('backend/get_landing_data.php');
        const data = await response.json();

        if (data.success && data.categories) {
            renderCategories(data.categories);
        } else {
            showCategoryError('Failed to load categories');
        }
    } catch (error) {
        console.error('Error loading categories:', error);
        showCategoryError('An error occurred while loading categories');
    }
}

function renderCategories(categories) {
    const container = document.getElementById('categoriesContainer');

    if (!categories || categories.length === 0) {
        container.innerHTML = `
        <div class="text-center py-12">
        <i class="fas fa-box-open text-6xl text-gray-300 mb-4"></i>
        <p class="text-gray-600 text-lg">No categories available at the moment</p>
        </div>
        `;
        return;
    }

    let html = '';

    categories.forEach((category, index) => {
        // Add margin bottom except for last category
        const marginClass = index < categories.length - 1 ? 'mb-16' : '';

    html += `
    <div class="${marginClass}">
    <h3 class="text-3xl font-bold mb-8 text-[#08415c]">${escapeHtml(category.category_name)}</h3>
    <div class="grid md:grid-cols-3 gap-8">
    `;

    // Render product lines for this category
    if (category.product_lines && category.product_lines.length > 0) {
        category.product_lines.forEach(productLine => {
            const imagePath = productLine.product_line_image
            ? `Assets/images/product-lines/${productLine.product_line_image}`
            : 'Assets/images/website-images/placeholder.svg';

        html += `
        <div class="bg-white rounded-xl shadow-lg overflow-hidden card-hover">
        <img src="${imagePath}"
        alt="${escapeHtml(productLine.product_line_name)}"
        class="w-full h-48 object-cover"
        onerror="this.onerror=null;this.src='Assets/images/website-images/placeholder.svg';">
        <div class="p-6">
        <h4 class="text-xl font-bold mb-2 text-[#08415c]">${escapeHtml(productLine.product_line_name)}</h4>
        <p class="text-gray-600 mb-4">${escapeHtml(productLine.product_line_description || 'Quality products for your vehicle')}</p>
        <div class="flex items-center justify-between">
        <span class="text-sm text-gray-500">
        <i class="fas fa-box mr-1"></i>
        ${productLine.product_count || 0} Products
        </span>
        <a href="html/product.php?id=${category.category_id}&c_id=${productLine.product_line_id}"
        class="inline-flex items-center text-[#08415c] font-semibold hover:text-[#0a5273] transition">
        View Products <i class="fas fa-arrow-right ml-2"></i>
        </a>
        </div>
        </div>
        </div>
        `;
        });
    } else {
        html += `
        <div class="col-span-3 text-center py-8 text-gray-500">
        <i class="fas fa-info-circle text-3xl mb-2"></i>
        <p>No product lines available in this category yet</p>
        </div>
        `;
    }

    html += `
    </div>
    </div>
    `;
    });

    container.innerHTML = html;
}

function showCategoryError(message) {
    const container = document.getElementById('categoriesContainer');
    container.innerHTML = `
    <div class="text-center py-12">
    <i class="fas fa-exclamation-triangle text-6xl text-red-400 mb-4"></i>
    <p class="text-gray-600 text-lg mb-4">${escapeHtml(message)}</p>
    <button onclick="loadCategories()" class="btn-primary-custom text-white px-6 py-3 rounded-lg font-semibold">
    <i class="fas fa-redo mr-2"></i>Try Again
    </button>
    </div>
    `;
}

// Helper function to escape HTML
function escapeHtml(text) {
    if (!text) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}

document.addEventListener('DOMContentLoaded', function() {
    initializePage();
});
</script>

<!-- Chat Bubble Component -->
<?php include 'html/components/chat_bubble.php'; ?>
</body>
</html>
