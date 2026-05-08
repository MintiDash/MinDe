<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Details - MinC Computer Parts</title>

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

        .nav-link-custom {
            position: relative;
            transition: color 0.3s ease;
        }

        .nav-link-custom:hover {
            color: #08415c;
        }

        .nav-link-custom::after {
            content: '';
            position: absolute;
            bottom: -4px;
            left: 0;
            width: 0;
            height: 2px;
            background: #08415c;
            transition: width 0.3s ease;
        }

        .nav-link-custom:hover::after {
            width: 100%;
        }

        .btn-primary-custom {
            background: linear-gradient(135deg, #08415c 0%, #0a5273 100%);
            transition: all 0.3s ease;
        }

        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(8, 65, 92, 0.4);
        }

        .product-card {
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .product-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(8, 65, 92, 0.3);
            border-color: #08415c;
        }

        .category-badge {
            background: linear-gradient(135deg, #08415c 0%, #0a5273 100%);
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

        .quantity-btn {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #e5e7eb;
            transition: all 0.3s ease;
        }

        .quantity-btn:hover {
            border-color: #08415c;
            background: #08415c;
            color: white;
        }

        .main-image {
            max-height: 500px;
            object-fit: contain;
        }

        .rating-summary-link {
            color: #0a5273;
            font-weight: 600;
            transition: color 0.2s ease;
        }

        .rating-summary-link:hover {
            color: #08415c;
        }

        .review-shell {
            background:
                radial-gradient(circle at top right, rgba(8, 65, 92, 0.08), transparent 30%),
                linear-gradient(180deg, #ffffff 0%, #f8fbfc 100%);
        }

        .review-score-card {
            border: 1px solid rgba(8, 65, 92, 0.12);
            background: linear-gradient(180deg, rgba(8, 65, 92, 0.04), rgba(255, 255, 255, 1));
        }

        .review-bar-track {
            height: 10px;
            border-radius: 9999px;
            background: #e5e7eb;
            overflow: hidden;
        }

        .review-bar-fill {
            height: 100%;
            border-radius: 9999px;
            background: linear-gradient(90deg, #0a5273 0%, #20a44a 100%);
        }

        .review-star-button {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            border: 1px solid #d1d5db;
            background: #fff;
            color: #cbd5e1;
            transition: all 0.2s ease;
        }

        .review-star-button:hover,
        .review-star-button.active {
            border-color: #0a5273;
            background: rgba(8, 65, 92, 0.08);
            color: #f59e0b;
            transform: translateY(-1px);
        }

        .review-card {
            border: 1px solid rgba(8, 65, 92, 0.12);
            background: #ffffff;
        }

        .review-pill {
            border: 1px solid rgba(8, 65, 92, 0.12);
            background: rgba(8, 65, 92, 0.05);
            color: #08415c;
        }
    </style>
</head>

<body class="bg-gray-50">

    <!-- Loader -->
    <div id="loader" class="flex items-center justify-center">
        <div class="text-center">
            <div class="animate-spin rounded-full h-16 w-16 border-b-2 border-[#08415c] mx-auto mb-4"></div>
            <p class="text-[#08415c] font-semibold">Loading product...</p>
        </div>
    </div>

    <!-- Navigation Component -->
    <?php include 'components/navbar.php'; ?>

    <!-- Breadcrumb -->
    <section class="bg-gray-100 mt-20 py-6 px-4">
        <div class="max-w-7xl mx-auto">
            <nav class="text-gray-600 text-sm" id="breadcrumb">
                <a href="../index.php" class="hover:text-[#08415c] transition">Home</a>
                <span class="mx-2">/</span>
                <a href="product.php" class="hover:text-[#08415c] transition">Products</a>
                <span class="mx-2">/</span>
                <span class="text-gray-800" id="breadcrumb-product">Loading...</span>
            </nav>
        </div>
    </section>

    <!-- Product Detail Section -->
    <section class="py-16 px-4">
        <div class="max-w-7xl mx-auto">
            <div id="product-container" class="grid md:grid-cols-2 gap-12 mb-16">
                <!-- Content will be loaded dynamically -->
            </div>

            <div id="reviews-section" class="mb-16">
                <!-- Reviews will be loaded dynamically -->
            </div>

            <!-- Related Products -->
            <div id="related-products-section" class="hidden">
                <h2 class="text-3xl font-bold text-[#08415c] mb-8">Related Products</h2>
                <div id="related-products-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
                    <!-- Related products will be loaded here -->
                </div>
            </div>
        </div>
    </section>

    <!-- Footer Component -->
    <?php include 'components/footer.php'; ?>

    <!-- Login Modal (Same as product.php) -->
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
    const IS_ROLE1_ADMIN = <?php echo (isset($_SESSION['user_level_id']) && (int)$_SESSION['user_level_id'] === 1) ? 'true' : 'false'; ?>;
    const IS_LOGGED_IN = <?php echo isset($_SESSION['user_id']) ? 'true' : 'false'; ?>;

    // Global variables
    let currentProduct = null;
    let quantity = 1;
    let productReviewsState = null;
    let activeReviewEditId = null;

    // Cart initialization
    function initializeCart() {
        fetch('../backend/cart/cart_get.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateCartCount(data.cart_count);
                }
            })
            .catch(error => console.error('Error loading cart:', error));
    }

    // Get URL parameter
    function getUrlParameter(name) {
        name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
        const regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
        const results = regex.exec(location.search);
        return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
    }

    // Format currency to Philippine Peso
    function formatPeso(amount) {
        return '₱' + parseFloat(amount).toLocaleString('en-PH', {
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

    function renderStarIcons(rating, sizeClass = 'text-base') {
        const safeRating = Math.max(0, Math.min(5, Number(rating) || 0));
        let markup = '';

        for (let star = 1; star <= 5; star++) {
            if (safeRating >= star) {
                markup += `<i class="fas fa-star ${sizeClass}"></i>`;
            } else if (safeRating >= (star - 0.5)) {
                markup += `<i class="fas fa-star-half-alt ${sizeClass}"></i>`;
            } else {
                markup += `<i class="far fa-star ${sizeClass} text-amber-300"></i>`;
            }
        }

        return markup;
    }

    function renderProductHeroRating(product) {
        const reviewCount = Number(product.review_count || 0);
        const averageRating = Number(product.average_rating || 0);

        if (reviewCount <= 0) {
            return `
                <div class="flex items-center gap-3 text-sm text-gray-500 mb-4">
                    <div class="flex items-center gap-0.5 text-amber-400">
                        ${renderStarIcons(0)}
                    </div>
                    <span>No ratings yet</span>
                    <a href="#reviews-section" onclick="scrollToReviews(event)" class="rating-summary-link">Be the first to review</a>
                </div>
            `;
        }

        return `
            <div class="flex flex-wrap items-center gap-3 mb-4">
                <span class="text-lg font-bold text-[#08415c]">${averageRating.toFixed(1)}</span>
                <div class="flex items-center gap-0.5 text-amber-400">
                    ${renderStarIcons(averageRating)}
                </div>
                <a href="#reviews-section" onclick="scrollToReviews(event)" class="rating-summary-link">
                    ${reviewCount.toLocaleString()} review${reviewCount === 1 ? '' : 's'}
                </a>
            </div>
        `;
    }

    function renderCompactRating(product, sizeClass = 'text-sm') {
        const reviewCount = Number(product.review_count || 0);
        const averageRating = Number(product.average_rating || 0);

        if (reviewCount <= 0) {
            return `<div class="text-sm text-gray-400">No reviews yet</div>`;
        }

        return `
            <div class="flex items-center gap-2">
                <span class="text-sm font-semibold text-[#08415c]">${averageRating.toFixed(1)}</span>
                <div class="flex items-center gap-0.5 text-amber-400">
                    ${renderStarIcons(averageRating, sizeClass)}
                </div>
                <span class="text-sm text-gray-500">(${reviewCount.toLocaleString()})</span>
            </div>
        `;
    }

    function scrollToReviews(event) {
        if (event) {
            event.preventDefault();
        }

        const section = document.getElementById('reviews-section');
        if (!section) {
            return;
        }

        section.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    }

    function formatReviewDate(dateString) {
        if (!dateString) {
            return 'Recently';
        }

        const date = new Date(dateString);
        if (Number.isNaN(date.getTime())) {
            return 'Recently';
        }

        return date.toLocaleDateString('en-PH', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    }

    // Load product details
    async function loadProductDetail() {
        showLoader();
        
        const productId = getUrlParameter('id');

        if (!productId) {
            hideLoader();
            showError('No product specified');
            return;
        }

        try {
            const response = await fetch(`../backend/get_product_detail.php?product_id=${productId}`);
            const data = await response.json();

            if (data.success) {
                currentProduct = data.product;
                displayProductDetail(data.product);
                displayRelatedProducts(data.related_products);
                updateBreadcrumb(data.product);
                renderReviewLoadingState();
                await loadProductReviews(data.product.product_id);
            } else {
                showError(data.message || 'Product not found');
            }
        } catch (error) {
            console.error('Error loading product:', error);
            showError('An error occurred while loading product details');
        } finally {
            hideLoader();
        }
    }

    // Display product details
    function displayProductDetail(product) {
        const imagePath = product.product_image 
            ? `../Assets/images/products/${product.product_image}` 
            : '../Assets/images/website-images/placeholder.svg';
        const adminImageUploadControls = IS_ROLE1_ADMIN
            ? `
                <div class="mt-6 border-t pt-4">
                    <input type="file" id="adminProductImageInput" accept=".jpg,.jpeg,.png,.webp,.gif" class="hidden" onchange="handleProductImageChange(event)">
                    <button type="button" onclick="openAdminImagePicker()" class="w-full bg-[#08415c] hover:bg-[#0a5273] text-white py-3 rounded-lg font-semibold transition">
                        <i class="fas fa-upload mr-2"></i>Upload New Product Image
                    </button>
                    <p class="text-xs text-gray-500 mt-2">Allowed: JPG, PNG, WEBP, GIF (max 2MB)</p>
                </div>
            `
            : '';

        const stockBadge = product.stock_quantity > 0 
            ? `<span class="text-green-600 font-semibold"><i class="fas fa-check-circle mr-1"></i>In Stock (${product.stock_quantity} available)</span>`
            : `<span class="text-red-600 font-semibold"><i class="fas fa-times-circle mr-1"></i>Out of Stock</span>`;

        const container = document.getElementById('product-container');
        container.innerHTML = `
            <!-- Product Image -->
            <div class="bg-white rounded-2xl shadow-lg p-8">
                <img src="${imagePath}" 
                     id="product-main-image"
                     alt="${escapeHtml(product.product_name)}" 
                     class="w-full main-image rounded-lg"
                     onerror="this.src='../Assets/images/website-images/placeholder.svg'">
                ${adminImageUploadControls}
            </div>

            <!-- Product Info -->
            <div>
                <div class="mb-4">
                    <span class="category-badge text-white px-4 py-2 rounded-full text-sm font-semibold inline-block mb-4">
                        ${escapeHtml(product.product_line_name)}
                    </span>
                    ${product.is_featured ? '<span class="bg-yellow-500 text-white px-4 py-2 rounded-full text-sm font-semibold inline-block mb-4 ml-2"><i class="fas fa-star mr-1"></i>Featured</span>' : ''}
                </div>

                <h1 class="text-4xl font-bold text-[#08415c] mb-4">${escapeHtml(product.product_name)}</h1>
                
                ${product.product_code ? `<p class="text-gray-600 mb-4">Product Code: <span class="font-semibold">${escapeHtml(product.product_code)}</span></p>` : ''}

                <div id="product-rating-summary">
                    ${renderProductHeroRating(product)}
                </div>

                <div class="mb-6">
                    ${stockBadge}
                </div>

                <div class="mb-6">
                    <span class="text-5xl font-bold text-[#08415c]">${formatPeso(product.price)}</span>
                </div>

                <div class="bg-gray-50 rounded-lg p-6 mb-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-3">Product Description</h3>
                    <p class="text-gray-700 leading-relaxed">
                        ${escapeHtml(product.product_description || 'High-quality auto part designed for optimal performance and durability.')}
                    </p>
                </div>

                <!-- Quantity Selector -->
                <div class="mb-6">
                    <label class="block text-gray-700 font-semibold mb-3">Quantity</label>
                    <div class="flex items-center space-x-4">
                        <button onclick="decreaseQuantity()" class="quantity-btn rounded-lg font-bold text-xl">
                            <i class="fas fa-minus"></i>
                        </button>
                        <input type="number" id="quantity" value="1" min="1" max="${product.stock_quantity}" 
                               class="w-20 text-center text-xl font-bold border-2 border-gray-300 rounded-lg py-2"
                               onchange="updateQuantity(this.value)">
                        <button onclick="increaseQuantity(${product.stock_quantity})" class="quantity-btn rounded-lg font-bold text-xl">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="space-y-4">
                    ${product.stock_quantity > 0 ? `
                    <button onclick="addToCart()" class="w-full btn-primary-custom text-white py-4 rounded-lg font-bold text-lg">
                        <i class="fas fa-shopping-cart mr-2"></i>Add to Cart
                    </button>
                    <button onclick="buyNow()" class="w-full bg-green-600 hover:bg-green-700 text-white py-4 rounded-lg font-bold text-lg transition">
                        <i class="fas fa-bolt mr-2"></i>Buy Now
                    </button>
                    ` : `
                    <button disabled class="w-full bg-gray-400 text-white py-4 rounded-lg font-bold text-lg cursor-not-allowed">
                        <i class="fas fa-times-circle mr-2"></i>Out of Stock
                    </button>
                    `}
                </div>

                <!-- Additional Info -->
                <div class="mt-8 border-t pt-6">
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div class="flex items-center text-gray-600">
                            <i class="fas fa-shield-alt text-[#08415c] mr-2"></i>
                            Quality Guaranteed
                        </div>
                        <div class="flex items-center text-gray-600">
                            <i class="fas fa-shipping-fast text-[#08415c] mr-2"></i>
                            Fast Shipping
                        </div>
                        <div class="flex items-center text-gray-600">
                            <i class="fas fa-undo text-[#08415c] mr-2"></i>
                            Easy Returns
                        </div>
                        <div class="flex items-center text-gray-600">
                            <i class="fas fa-headset text-[#08415c] mr-2"></i>
                            24/7 Support
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    function openAdminImagePicker() {
        if (!IS_ROLE1_ADMIN) return;
        const input = document.getElementById('adminProductImageInput');
        if (input) {
            input.click();
        }
    }

    async function handleProductImageChange(event) {
        if (!IS_ROLE1_ADMIN || !currentProduct) return;

        const fileInput = event.target;
        const file = fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
        if (!file) return;

        if (file.size > 2097152) {
            Swal.fire({
                icon: 'error',
                title: 'File Too Large',
                text: 'Please upload an image up to 2MB only.',
                confirmButtonColor: '#08415c'
            });
            fileInput.value = '';
            return;
        }

        const formData = new FormData();
        formData.append('product_id', currentProduct.product_id);
        formData.append('product_image', file);

        Swal.fire({
            title: 'Uploading image...',
            text: 'Please wait while we update the product image.',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        try {
            const response = await fetch('../backend/products/upload_product_image.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (!data.success) {
                throw new Error(data.message || 'Failed to upload product image.');
            }

            currentProduct.product_image = data.product_image || currentProduct.product_image;
            const mainImage = document.getElementById('product-main-image');
            if (mainImage && data.image_url) {
                mainImage.src = `${data.image_url}?t=${Date.now()}`;
            }

            Swal.fire({
                icon: 'success',
                title: 'Image Updated',
                text: 'The product image was updated successfully.',
                confirmButtonColor: '#08415c'
            });
        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: 'Upload Failed',
                text: error.message || 'Could not upload the image.',
                confirmButtonColor: '#08415c'
            });
        } finally {
            fileInput.value = '';
        }
    }

    // Display related products
    function displayRelatedProducts(products) {
        if (!products || products.length === 0) {
            return;
        }

        const section = document.getElementById('related-products-section');
        const grid = document.getElementById('related-products-grid');
        
        section.classList.remove('hidden');
        grid.innerHTML = '';

        products.forEach(product => {
            const imagePath = product.product_image 
                ? `../Assets/images/products/${product.product_image}` 
                : '../Assets/images/website-images/placeholder.svg';

            const card = document.createElement('div');
            card.className = 'bg-white rounded-xl shadow-lg overflow-hidden product-card cursor-pointer';
            card.onclick = () => window.location.href = `product_detail.php?id=${product.product_id}`;
            card.innerHTML = `
                <div class="relative h-48 bg-gray-100 overflow-hidden">
                    <img src="${imagePath}" 
                         alt="${escapeHtml(product.product_name)}" 
                         class="w-full h-full object-cover"
                         onerror="this.src='../Assets/images/website-images/placeholder.svg'">
                </div>
                <div class="p-4">
                    <h4 class="text-lg font-bold text-[#08415c] mb-2 line-clamp-2">${escapeHtml(product.product_name)}</h4>
                    <div class="mb-3">
                        ${renderCompactRating(product)}
                    </div>
                    <p class="text-2xl font-bold text-[#08415c]">${formatPeso(product.price)}</p>
                </div>
            `;
            grid.appendChild(card);
        });
    }

    function renderReviewLoadingState() {
        const section = document.getElementById('reviews-section');
        if (!section) {
            return;
        }

        section.innerHTML = `
            <div class="review-shell rounded-[28px] shadow-xl p-8 animate-pulse">
                <div class="grid lg:grid-cols-[320px,1fr] gap-8">
                    <div class="space-y-4">
                        <div class="h-8 w-40 bg-gray-200 rounded-full"></div>
                        <div class="h-14 w-28 bg-gray-200 rounded-2xl"></div>
                        <div class="h-4 w-52 bg-gray-200 rounded-full"></div>
                        <div class="space-y-3 pt-4">
                            <div class="h-3 bg-gray-200 rounded-full"></div>
                            <div class="h-3 bg-gray-200 rounded-full"></div>
                            <div class="h-3 bg-gray-200 rounded-full"></div>
                            <div class="h-3 bg-gray-200 rounded-full"></div>
                            <div class="h-3 bg-gray-200 rounded-full"></div>
                        </div>
                    </div>
                    <div class="space-y-4">
                        <div class="h-10 w-56 bg-gray-200 rounded-full"></div>
                        <div class="h-28 bg-gray-200 rounded-3xl"></div>
                        <div class="h-40 bg-gray-200 rounded-3xl"></div>
                    </div>
                </div>
            </div>
        `;
    }

    function getReviewInitials(name) {
        const parts = String(name || 'Customer').trim().split(/\s+/).filter(Boolean);
        if (!parts.length) {
            return 'CU';
        }

        return parts.slice(0, 2).map(part => part.charAt(0).toUpperCase()).join('');
    }

    function updateProductRatingSummary(summary) {
        if (!currentProduct || !summary) {
            return;
        }

        currentProduct.average_rating = Number(summary.average_rating || 0);
        currentProduct.review_count = Number(summary.review_count || 0);

        const container = document.getElementById('product-rating-summary');
        if (container) {
            container.innerHTML = renderProductHeroRating(currentProduct);
        }
    }

    function updateReviewStarSelection(ratingValue) {
        const selectedRating = Number(ratingValue || 0);
        document.querySelectorAll('[data-review-rating]').forEach((button) => {
            const buttonRating = Number(button.getAttribute('data-review-rating') || 0);
            if (buttonRating <= selectedRating) {
                button.classList.add('active');
            } else {
                button.classList.remove('active');
            }
        });
    }

    function setReviewRating(value) {
        const ratingInput = document.getElementById('review_rating');
        if (!ratingInput) {
            return;
        }

        ratingInput.value = String(value || 0);
        updateReviewStarSelection(value);
    }

    function updateReviewTextCounter(value = '') {
        const counter = document.getElementById('review_text_counter');
        if (!counter) {
            return;
        }

        const length = String(value || '').length;
        counter.textContent = `${length}/500 characters`;
        counter.className = `text-xs mt-2 ${length > 500 ? 'text-red-500 font-semibold' : 'text-gray-500'}`;
    }

    function getReviewStateById(reviewId) {
        if (!productReviewsState || !Array.isArray(productReviewsState.reviews)) {
            return null;
        }

        const normalizedReviewId = Number(reviewId || 0);
        return productReviewsState.reviews.find((review) => Number(review.review_id || 0) === normalizedReviewId) || null;
    }

    function scrollToReviewComposer() {
        const form = document.getElementById('product-review-form');
        if (!form) {
            return;
        }

        form.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    }

    function startReviewEdit(reviewId) {
        const review = getReviewStateById(reviewId);
        if (!review || !review.can_edit) {
            return;
        }

        activeReviewEditId = review.is_current_user_review ? null : review.review_id;
        renderReviewSection(productReviewsState);
        scrollToReviewComposer();
    }

    function cancelReviewEdit() {
        activeReviewEditId = null;
        renderReviewSection(productReviewsState);
    }

    async function confirmReviewAction(message, title) {
        if (typeof showConfirmModal === 'function') {
            return await showConfirmModal(message, title);
        }

        if (typeof Swal !== 'undefined') {
            const result = await Swal.fire({
                title: title || 'Please Confirm',
                text: message,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#08415c',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes'
            });
            return !!result.isConfirmed;
        }

        return window.confirm(message);
    }

    async function submitProductReview(event) {
        event.preventDefault();

        if (!currentProduct) {
            return;
        }

        const ratingInput = document.getElementById('review_rating');
        const reviewIdInput = document.getElementById('review_id');
        const reviewTitleInput = document.getElementById('review_title');
        const reviewTextInput = document.getElementById('review_text');
        const reviewId = Number(reviewIdInput ? reviewIdInput.value : 0);
        const rating = Number(ratingInput ? ratingInput.value : 0);
        const reviewTitle = reviewTitleInput ? reviewTitleInput.value.trim() : '';
        const reviewText = reviewTextInput ? reviewTextInput.value.trim() : '';
        const submitButton = document.getElementById('review_submit_button');

        if (rating < 1 || rating > 5) {
            showAlertModal('Please select a rating from 1 to 5 stars.', 'warning', 'Missing Rating');
            return;
        }

        if (reviewText.length < 20) {
            showAlertModal('Please write at least 20 characters for your review.', 'warning', 'Review Too Short');
            return;
        }

        if (reviewText.length > 500) {
            showAlertModal('Review text must be 500 characters or fewer.', 'warning', 'Review Too Long');
            return;
        }

        if (submitButton) {
            submitButton.disabled = true;
            submitButton.classList.add('opacity-70', 'cursor-not-allowed');
        }

        try {
            const response = await fetch('../backend/product-reviews/save_product_review.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    review_id: reviewId,
                    product_id: currentProduct.product_id,
                    rating,
                    review_title: reviewTitle,
                    review_text: reviewText
                })
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                if (response.status === 401) {
                    if (typeof openLoginModal === 'function') {
                        openLoginModal();
                    }
                    throw new Error(data.message || 'Please login to submit a review.');
                }

                throw new Error(data.message || 'Failed to save your review.');
            }

            productReviewsState = {
                summary: data.summary,
                reviews: data.reviews,
                current_user_review: data.current_user_review,
                is_logged_in: data.is_logged_in,
                can_manage_all_reviews: data.can_manage_all_reviews
            };
            activeReviewEditId = null;

            renderReviewSection(productReviewsState);

            if (typeof window.showAppToast === 'function') {
                window.showAppToast(data.message || 'Your review has been saved.', 'success');
            } else {
                Swal.fire({
                    icon: 'success',
                    title: data.message || 'Your review has been saved.',
                    confirmButtonColor: '#08415c'
                });
            }
        } catch (error) {
            showAlertModal(error.message || 'Failed to save your review.', 'error', 'Review Error');
        } finally {
            if (submitButton) {
                submitButton.disabled = false;
                submitButton.classList.remove('opacity-70', 'cursor-not-allowed');
            }
        }
    }

    async function deleteProductReview(reviewId) {
        const review = getReviewStateById(reviewId);
        if (!review || !review.can_delete) {
            return;
        }

        const isConfirmed = await confirmReviewAction(
            'Are you sure you want to delete this review? This action cannot be undone.',
            'Delete Review'
        );

        if (!isConfirmed) {
            return;
        }

        try {
            const response = await fetch('../backend/product-reviews/delete_product_review.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    review_id: review.review_id
                })
            });
            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Failed to delete review.');
            }

            if (Number(activeReviewEditId || 0) === Number(review.review_id || 0)) {
                activeReviewEditId = null;
            }

            productReviewsState = {
                summary: data.summary,
                reviews: data.reviews,
                current_user_review: data.current_user_review,
                is_logged_in: data.is_logged_in,
                can_manage_all_reviews: data.can_manage_all_reviews
            };

            renderReviewSection(productReviewsState);

            if (typeof window.showAppToast === 'function') {
                window.showAppToast(data.message || 'Review deleted successfully.', 'success');
            } else {
                showAlertModal(data.message || 'Review deleted successfully.', 'success', 'Review Deleted');
            }
        } catch (error) {
            showAlertModal(error.message || 'Failed to delete review.', 'error', 'Delete Review Error');
        }
    }

    async function reportProductReview(reviewId) {
        const review = getReviewStateById(reviewId);
        if (!review || !review.can_report || review.is_reported_by_current_user) {
            return;
        }

        let reportPayload = null;

        if (typeof Swal !== 'undefined') {
            const result = await Swal.fire({
                title: 'Report Review',
                html: `
                    <div class="text-left space-y-4">
                        <div>
                            <label for="reviewReportReason" class="block text-sm font-semibold text-gray-700 mb-2">Reason</label>
                            <select id="reviewReportReason" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#08415c]">
                                <option value="">Select a reason</option>
                                <option value="spam">Spam</option>
                                <option value="abuse">Abuse or harassment</option>
                                <option value="false_information">False information</option>
                                <option value="off_topic">Off-topic</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div>
                            <label for="reviewReportDetails" class="block text-sm font-semibold text-gray-700 mb-2">Details (optional)</label>
                            <textarea id="reviewReportDetails" maxlength="500" rows="4" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#08415c] resize-y" placeholder="Briefly explain why you are reporting this review."></textarea>
                        </div>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Submit Report',
                confirmButtonColor: '#08415c',
                focusConfirm: false,
                preConfirm: () => {
                    const reason = document.getElementById('reviewReportReason').value;
                    const details = document.getElementById('reviewReportDetails').value.trim();

                    if (!reason) {
                        Swal.showValidationMessage('Please select a report reason.');
                        return false;
                    }

                    if (details.length > 500) {
                        Swal.showValidationMessage('Report details must be 500 characters or fewer.');
                        return false;
                    }

                    return { reason, details };
                }
            });

            if (!result.isConfirmed || !result.value) {
                return;
            }

            reportPayload = {
                report_reason: result.value.reason,
                report_details: result.value.details
            };
        } else {
            const reason = window.prompt('Enter a report reason: spam, abuse, false_information, off_topic, or other', 'spam');
            if (!reason) {
                return;
            }

            reportPayload = {
                report_reason: reason.trim(),
                report_details: ''
            };
        }

        try {
            const response = await fetch('../backend/product-reviews/report_product_review.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    review_id: review.review_id,
                    report_reason: reportPayload.report_reason,
                    report_details: reportPayload.report_details
                })
            });
            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Failed to report review.');
            }

            await loadProductReviews(currentProduct.product_id);

            if (typeof window.showAppToast === 'function') {
                window.showAppToast(data.message || 'Review reported successfully.', 'success');
            } else {
                showAlertModal(data.message || 'Review reported successfully.', 'success', 'Report Submitted');
            }
        } catch (error) {
            showAlertModal(error.message || 'Failed to report review.', 'error', 'Report Review Error');
        }
    }

    async function loadProductReviews(productId) {
        try {
            const response = await fetch(`../backend/product-reviews/get_product_reviews.php?product_id=${productId}`);
            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Failed to load reviews.');
            }

            productReviewsState = data;
            renderReviewSection(data);
        } catch (error) {
            const section = document.getElementById('reviews-section');
            if (section) {
                section.innerHTML = `
                    <div class="review-shell rounded-[28px] shadow-xl p-8">
                        <div class="text-center py-8">
                            <i class="fas fa-comment-slash text-4xl text-gray-300 mb-4"></i>
                            <p class="text-lg font-semibold text-[#08415c] mb-2">Unable to load reviews right now</p>
                            <p class="text-gray-500">${escapeHtml(error.message || 'Please try again later.')}</p>
                        </div>
                    </div>
                `;
            }
        }
    }

    function renderReviewSection(payload) {
        const section = document.getElementById('reviews-section');
        if (!section) {
            return;
        }

        const summary = payload && payload.summary
            ? payload.summary
            : {
                average_rating: Number(currentProduct && currentProduct.average_rating ? currentProduct.average_rating : 0),
                review_count: Number(currentProduct && currentProduct.review_count ? currentProduct.review_count : 0),
                rating_breakdown: { '5': 0, '4': 0, '3': 0, '2': 0, '1': 0 }
            };
        const reviews = payload && Array.isArray(payload.reviews) ? payload.reviews : [];
        const currentUserReview = payload && payload.current_user_review ? payload.current_user_review : null;
        const isLoggedIn = !!(payload && payload.is_logged_in);
        const canManageAllReviews = !!(payload && payload.can_manage_all_reviews);
        const reviewCount = Number(summary.review_count || 0);
        const averageRating = Number(summary.average_rating || 0);

        let editableReview = currentUserReview;
        if (activeReviewEditId) {
            const selectedReview = reviews.find((review) => Number(review.review_id || 0) === Number(activeReviewEditId));
            if (selectedReview && selectedReview.can_edit) {
                editableReview = selectedReview;
            } else {
                activeReviewEditId = null;
            }
        }

        const isEditingSelectedReview = !!(activeReviewEditId && editableReview);
        const reviewFormTitle = editableReview
            ? (editableReview.is_current_user_review
                ? 'Update your review'
                : `Edit review by ${escapeHtml(editableReview.reviewer_name)}`)
            : 'Write a review';
        const reviewFormDescription = editableReview
            ? (editableReview.is_current_user_review
                ? 'Update your feedback for this product.'
                : 'You are editing this review as an admin.')
            : 'Tell other drivers how this product performed for you.';

        updateProductRatingSummary(summary);

        const breakdownMarkup = [5, 4, 3, 2, 1].map((star) => {
            const count = Number(summary.rating_breakdown && summary.rating_breakdown[String(star)] ? summary.rating_breakdown[String(star)] : 0);
            const percentage = reviewCount > 0 ? (count / reviewCount) * 100 : 0;

            return `
                <div class="grid grid-cols-[52px,1fr,38px] items-center gap-3 text-sm">
                    <span class="font-medium text-gray-600">${star} star</span>
                    <div class="review-bar-track">
                        <div class="review-bar-fill" style="width: ${percentage}%;"></div>
                    </div>
                    <span class="text-right text-gray-500">${count}</span>
                </div>
            `;
        }).join('');

        const reviewFormMarkup = isLoggedIn
            ? `
                <div class="review-card rounded-[24px] p-6 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
                        <div>
                            <h3 class="text-2xl font-bold text-[#08415c]">${reviewFormTitle}</h3>
                            <p class="text-sm text-gray-500">${reviewFormDescription}</p>
                        </div>
                        ${editableReview
                            ? `<span class="review-pill px-3 py-2 rounded-full text-xs font-semibold">${editableReview.is_current_user_review ? 'Editing your review' : 'Admin edit mode'}</span>`
                            : ''}
                    </div>

                    <form id="product-review-form" onsubmit="submitProductReview(event)" class="space-y-5">
                        <input type="hidden" id="review_id" value="${editableReview ? Number(editableReview.review_id || 0) : 0}">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-3">Overall rating</label>
                            <div class="flex flex-wrap gap-2">
                                ${[1, 2, 3, 4, 5].map((star) => `
                                    <button
                                        type="button"
                                        class="review-star-button"
                                        data-review-rating="${star}"
                                        onclick="setReviewRating(${star})"
                                        aria-label="Rate ${star} star${star === 1 ? '' : 's'}"
                                    >
                                        <i class="fas fa-star text-xl"></i>
                                    </button>
                                `).join('')}
                            </div>
                            <input type="hidden" id="review_rating" value="${editableReview ? Number(editableReview.rating || 0) : 0}">
                        </div>

                        <div>
                            <label for="review_title" class="block text-sm font-semibold text-gray-700 mb-2">Review headline</label>
                            <input
                                type="text"
                                id="review_title"
                                maxlength="255"
                                value="${escapeHtml(editableReview && editableReview.review_title ? editableReview.review_title : '')}"
                                placeholder="Example: Great fit and solid finish"
                                class="w-full px-4 py-3 border border-gray-300 rounded-2xl focus:outline-none focus:ring-2 focus:ring-[#08415c]"
                            >
                        </div>

                        <div>
                            <label for="review_text" class="block text-sm font-semibold text-gray-700 mb-2">Written review</label>
                            <textarea
                                id="review_text"
                                rows="6"
                                maxlength="500"
                                oninput="updateReviewTextCounter(this.value)"
                                placeholder="Share what you liked, how it fit, quality, durability, and anything another buyer should know."
                                class="w-full px-4 py-3 border border-gray-300 rounded-2xl focus:outline-none focus:ring-2 focus:ring-[#08415c] resize-y"
                            >${escapeHtml(editableReview && editableReview.review_text ? editableReview.review_text : '')}</textarea>
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-xs text-gray-500 mt-2">Minimum 20 characters. Maximum 500. One review per product per account.</p>
                                <p id="review_text_counter" class="text-xs text-gray-500 mt-2">0/500 characters</p>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <button
                                type="submit"
                                id="review_submit_button"
                                class="btn-primary-custom text-white px-6 py-3 rounded-2xl font-semibold"
                            >
                                ${editableReview ? 'Save Changes' : 'Submit Review'}
                            </button>
                            ${isEditingSelectedReview ? `
                                <button
                                    type="button"
                                    onclick="cancelReviewEdit()"
                                    class="bg-gray-100 text-[#08415c] px-6 py-3 rounded-2xl font-semibold hover:bg-gray-200 transition"
                                >
                                    Cancel
                                </button>
                            ` : ''}
                            <span class="text-sm text-gray-500">${editableReview && !editableReview.is_current_user_review ? 'Admin changes apply directly to this review.' : 'Your rating contributes to the product score shown across the catalog.'}</span>
                        </div>
                    </form>
                </div>
            `
            : `
                <div class="review-card rounded-[24px] p-6 shadow-sm">
                    <h3 class="text-2xl font-bold text-[#08415c] mb-2">Write a review</h3>
                    <p class="text-gray-600 mb-5">Login to rate this product and share your experience with other customers.</p>
                    <button onclick="openLoginModal()" class="btn-primary-custom text-white px-6 py-3 rounded-2xl font-semibold">
                        Login to Review
                    </button>
                </div>
            `;

        const reviewsMarkup = reviews.length > 0
            ? reviews.map((review) => `
                <article class="review-card rounded-[24px] p-6 shadow-sm">
                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 rounded-full bg-[#08415c] text-white flex items-center justify-center font-bold text-sm shrink-0">
                            ${escapeHtml(getReviewInitials(review.reviewer_name))}
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-2 mb-2">
                                <h4 class="text-lg font-bold text-gray-900">${escapeHtml(review.reviewer_name)}</h4>
                                ${review.is_current_user_review ? '<span class="review-pill px-3 py-1 rounded-full text-xs font-semibold">Your review</span>' : ''}
                                ${review.is_verified_purchase ? '<span class="bg-green-50 text-green-700 border border-green-200 px-3 py-1 rounded-full text-xs font-semibold">Verified Purchase</span>' : ''}
                                ${canManageAllReviews && Number(review.report_count || 0) > 0 ? `<span class="bg-red-50 text-red-700 border border-red-200 px-3 py-1 rounded-full text-xs font-semibold">${Number(review.report_count || 0)} report${Number(review.report_count || 0) === 1 ? '' : 's'}</span>` : ''}
                            </div>
                            <div class="flex flex-wrap items-center gap-3 mb-3">
                                <div class="flex items-center gap-0.5 text-amber-400">
                                    ${renderStarIcons(review.rating)}
                                </div>
                                <span class="text-sm text-gray-500">${formatReviewDate(review.updated_at || review.created_at)}</span>
                            </div>
                            ${review.review_title ? `<h5 class="text-lg font-semibold text-[#08415c] mb-2">${escapeHtml(review.review_title)}</h5>` : ''}
                            <p class="text-gray-700 leading-7 whitespace-pre-line">${escapeHtml(review.review_text)}</p>
                            ${(review.can_edit || review.can_delete || review.can_report) ? `
                                <div class="flex flex-wrap items-center gap-3 mt-5 pt-4 border-t border-gray-100">
                                    ${review.can_edit ? `
                                        <button
                                            type="button"
                                            onclick="startReviewEdit(${Number(review.review_id || 0)})"
                                            class="text-sm font-semibold text-[#08415c] hover:text-[#0a5273] transition"
                                        >
                                            <i class="fas fa-pen mr-2"></i>Edit
                                        </button>
                                    ` : ''}
                                    ${review.can_delete ? `
                                        <button
                                            type="button"
                                            onclick="deleteProductReview(${Number(review.review_id || 0)})"
                                            class="text-sm font-semibold text-red-600 hover:text-red-700 transition"
                                        >
                                            <i class="fas fa-trash-alt mr-2"></i>Delete
                                        </button>
                                    ` : ''}
                                    ${review.can_report ? `
                                        <button
                                            type="button"
                                            onclick="reportProductReview(${Number(review.review_id || 0)})"
                                            class="text-sm font-semibold ${review.is_reported_by_current_user ? 'text-gray-400 cursor-not-allowed' : 'text-amber-600 hover:text-amber-700'} transition"
                                            ${review.is_reported_by_current_user ? 'disabled' : ''}
                                        >
                                            <i class="fas fa-flag mr-2"></i>${review.is_reported_by_current_user ? 'Reported' : 'Report'}
                                        </button>
                                    ` : ''}
                                </div>
                            ` : ''}
                        </div>
                    </div>
                </article>
            `).join('')
            : `
                <div class="review-card rounded-[24px] p-10 text-center shadow-sm">
                    <i class="far fa-star text-4xl text-amber-300 mb-4"></i>
                    <h3 class="text-2xl font-bold text-[#08415c] mb-2">No reviews yet</h3>
                    <p class="text-gray-500">Be the first customer to share feedback on this product.</p>
                </div>
            `;

        section.innerHTML = `
            <div class="review-shell rounded-[28px] shadow-xl p-6 md:p-8">
                <div class="flex flex-wrap items-end justify-between gap-4 mb-8">
                    <div>
                        <p class="text-sm uppercase tracking-[0.22em] text-[#0a5273] font-semibold mb-2">Customer Feedback</p>
                        <h2 class="text-3xl font-bold text-[#08415c]">Ratings & Reviews</h2>
                    </div>
                    <p class="text-sm text-gray-500">Real customer ratings for fit, finish, and overall quality.</p>
                </div>

                <div class="grid lg:grid-cols-[320px,1fr] gap-8">
                    <aside class="review-score-card rounded-[24px] p-6 h-fit">
                        <p class="text-sm font-semibold uppercase tracking-[0.18em] text-[#0a5273] mb-3">Overall Rating</p>
                        <div class="flex items-end gap-3 mb-3">
                            <span class="text-5xl font-extrabold text-[#08415c]">${reviewCount > 0 ? averageRating.toFixed(1) : '0.0'}</span>
                            <span class="text-sm text-gray-500 mb-1">out of 5</span>
                        </div>
                        <div class="flex items-center gap-1 text-amber-400 mb-3">
                            ${renderStarIcons(averageRating, 'text-lg')}
                        </div>
                        <p class="text-sm text-gray-500 mb-6">${reviewCount.toLocaleString()} global rating${reviewCount === 1 ? '' : 's'}</p>
                        <div class="space-y-3">
                            ${breakdownMarkup}
                        </div>
                    </aside>

                    <div class="space-y-6">
                        ${reviewFormMarkup}
                        <div class="space-y-4">
                            ${reviewsMarkup}
                        </div>
                    </div>
                </div>
            </div>
        `;

        updateReviewStarSelection(editableReview ? Number(editableReview.rating || 0) : 0);
        updateReviewTextCounter(editableReview && editableReview.review_text ? editableReview.review_text : '');
    }

    // Update breadcrumb
    function updateBreadcrumb(product) {
        const breadcrumb = document.getElementById('breadcrumb');
        breadcrumb.innerHTML = `
            <a href="../index.php" class="hover:text-[#08415c] transition">Home</a>
            <span class="mx-2">/</span>
            <a href="product.php" class="hover:text-[#08415c] transition">Products</a>
            <span class="mx-2">/</span>
            <a href="product.php?id=${product.category_id}" class="hover:text-[#08415c] transition">${escapeHtml(product.category_name)}</a>
            <span class="mx-2">/</span>
            <span class="text-gray-800">${escapeHtml(product.product_name)}</span>
        `;
    }

    // Quantity controls
    function increaseQuantity(max) {
        if (quantity < max) {
            quantity++;
            document.getElementById('quantity').value = quantity;
        }
    }

    function decreaseQuantity() {
        if (quantity > 1) {
            quantity--;
            document.getElementById('quantity').value = quantity;
        }
    }

    function updateQuantity(value) {
        const val = parseInt(value);
        if (val > 0 && val <= currentProduct.stock_quantity) {
            quantity = val;
        } else {
            document.getElementById('quantity').value = quantity;
        }
    }

    // Add to cart
    async function addToCart(suppressSuccessToast = false) {
        if (!currentProduct) return false;

        try {
            const response = await fetch('../backend/cart/cart_add.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    product_id: currentProduct.product_id,
                    quantity: quantity
                })
            });

            const data = await response.json();

            if (data.success) {
                updateCartCount(data.cart_count);
                if (!suppressSuccessToast) {
                    const toastMessage = `${quantity} x ${currentProduct.product_name} added to cart!`;
                    if (typeof window.showAppToast === 'function') {
                        window.showAppToast(toastMessage, 'success', {
                            href: 'user-cart.php',
                            timer: 4200
                        });
                    } else {
                        Swal.fire({
                            icon: 'success',
                            title: toastMessage,
                            toast: true,
                            position: 'top',
                            href: 'user-cart.php',
                            showConfirmButton: false,
                            timer: 4200,
                            timerProgressBar: true
                        });
                    }
                }
                return true;
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message || 'Failed to add item to cart',
                    confirmButtonColor: '#08415c'
                });
                return false;
            }
        } catch (error) {
            console.error('Error adding to cart:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'An error occurred while adding to cart',
                confirmButtonColor: '#08415c'
            });
            return false;
        }
    }

    // Buy now (checkout only this product/quantity)
    function buyNow() {
        if (!currentProduct) return;

        const selectedQty = Math.max(1, parseInt(quantity, 10) || 1);
        const maxStock = parseInt(currentProduct.stock_quantity, 10) || 0;

        if (maxStock <= 0) {
            if (typeof window.showAppToast === 'function') {
                window.showAppToast('This item is out of stock.', 'error');
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Out of Stock',
                    text: 'This item is currently unavailable.',
                    confirmButtonColor: '#08415c'
                });
            }
            return;
        }

        if (selectedQty > maxStock) {
            if (typeof window.showAppToast === 'function') {
                window.showAppToast(`Only ${maxStock} item(s) available.`, 'warning');
            } else {
                Swal.fire({
                    icon: 'warning',
                    title: 'Invalid Quantity',
                    text: `Only ${maxStock} item(s) available.`,
                    confirmButtonColor: '#08415c'
                });
            }
            return;
        }

        const checkoutUrl = `checkout.php?buy_now=1&product_id=${encodeURIComponent(currentProduct.product_id)}&quantity=${encodeURIComponent(selectedQty)}`;
        if (!IS_LOGGED_IN) {
            if (typeof window.setPostLoginRedirect === 'function') {
                window.setPostLoginRedirect(checkoutUrl);
            }
            openLoginModal();
            if (typeof window.showAppToast === 'function') {
                window.showAppToast('Sign in first to continue to checkout.', 'info');
            }
            return;
        }

        window.location.href = checkoutUrl;
    }

    // Update cart count
    function updateCartCount(count) {
        document.querySelectorAll('.cart-count').forEach(el => el.textContent = count || 0);
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
        const container = document.getElementById('product-container');
        container.innerHTML = `
            <div class="col-span-2 text-center py-12">
                <i class="fas fa-exclamation-triangle text-6xl text-red-400 mb-4"></i>
                <p class="text-xl text-gray-600 font-medium mb-4">${escapeHtml(message)}</p>
                <a href="product.php" class="btn-primary-custom text-white px-6 py-3 rounded-lg font-semibold inline-block">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Products
                </a>
            </div>
        `;
    }

    // Mobile menu toggle
    function toggleMobileMenu() {
        const menu = document.getElementById('mobileMenu');
        menu.classList.toggle('hidden');
    }

    // Modal functions
    function openLoginModal() {
        if (typeof window.setPostLoginRedirect === 'function') {
            window.setPostLoginRedirect(window.location.href);
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
        loadProductDetail();
        initializeCart();
    });
</script>
<!-- Chat Bubble Component -->
<?php include 'components/chat_bubble.php'; ?>
</body>
</html>
