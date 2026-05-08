<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - MinC Computer Parts</title>

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Font Awesome -->
    <script src="https://kit.fontawesome.com/ca30ddfff9.js" crossorigin="anonymous"></script>

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">

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

        .filter-btn {
            transition: all 0.3s ease;
            border: 2px solid #e5e7eb;
        }

        .filter-btn.active {
            background: linear-gradient(135deg, #08415c 0%, #0a5273 100%);
            color: white;
            border-color: #08415c;
        }

        .filter-btn:hover {
            border-color: #08415c;
        }

        .filter-panel-scroll {
            -webkit-overflow-scrolling: touch;
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
            <p class="text-[#08415c] font-semibold">Loading products...</p>
        </div>
    </div>

    <!-- Navigation Component -->
    <?php include 'components/navbar.php'; ?>

    <!-- Breadcrumb & Page Header -->
    <section class="hero-gradient mt-20 py-12 px-4">
        <div class="max-w-7xl mx-auto">
            <nav class="text-white mb-4">
                <a href="../index.php" class="hover:text-blue-100 transition">Home</a>
                <span class="mx-2">/</span>
                <a href="product.php" class="hover:text-blue-100 transition">All Products</a>
                <span class="mx-2" id="breadcrumb-separator" style="display:none;">/</span>
                <span id="breadcrumb-category"></span>
            </nav>
            <h1 class="text-4xl md:text-5xl font-bold text-white mb-2" id="page-title">Our Products</h1>
            <p class="text-blue-100 text-lg" id="page-description">Browse our extensive collection of quality auto parts
            </p>
        </div>
    </section>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 py-16">
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
            <!-- Sidebar Filters -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h3 class="text-xl font-bold text-[#08415c] mb-6">Filters</h3>

                    <!-- Product Line Filter -->
                    <div class="mb-8">
                        <!-- <h4 class="font-semibold text-gray-800 mb-4">Product Lines</h4> -->
                        <div id="productline-filters" class="space-y-3">
                            <!-- Populated by JavaScript -->
                        </div>
                    </div>

                    <!-- Price Filter -->
                    <div class="mb-8">
                        <h4 class="font-semibold text-gray-800 mb-4">Price Range</h4>
                        <div class="space-y-3">
                            <label class="flex items-center cursor-pointer hover:text-[#08415c] transition">
                                <input type="checkbox" class="price-filter w-4 h-4 text-[#08415c] rounded"
                                    value="0-1000">
                                <span class="ml-3 text-gray-700">₱0 - ₱1,000</span>
                            </label>
                            <label class="flex items-center cursor-pointer hover:text-[#08415c] transition">
                                <input type="checkbox" class="price-filter w-4 h-4 text-[#08415c] rounded"
                                    value="1000-5000">
                                <span class="ml-3 text-gray-700">₱1,000 - ₱5,000</span>
                            </label>
                            <label class="flex items-center cursor-pointer hover:text-[#08415c] transition">
                                <input type="checkbox" class="price-filter w-4 h-4 text-[#08415c] rounded"
                                    value="5000-10000">
                                <span class="ml-3 text-gray-700">₱5,000 - ₱10,000</span>
                            </label>
                            <label class="flex items-center cursor-pointer hover:text-[#08415c] transition">
                                <input type="checkbox" class="price-filter w-4 h-4 text-[#08415c] rounded"
                                    value="10000-999999">
                                <span class="ml-3 text-gray-700">₱10,000+</span>
                            </label>
                        </div>
                    </div>

                    <!-- Clear Filters -->
                    <button onclick="clearFilters()"
                        class="w-full bg-gray-100 text-[#08415c] py-2 rounded-lg font-semibold hover:bg-gray-200 transition">
                        Clear Filters
                    </button>
                </div>
            </div>

            <!-- Products Grid -->
            <div class="lg:col-span-3">
                <!-- Search and Sorting -->
                <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4 mb-8">
                    <div class="flex-1 max-w-xl">
                        <div class="relative">
                            <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                            <input type="text" id="product-search"
                                placeholder="Search products by name, description, or category..."
                                class="w-full pl-12 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#08415c]">
                        </div>
                    </div>
                    <div class="flex items-center justify-between md:justify-end gap-4">
                        <p id="product-count" class="text-gray-700 font-medium">Loading products...</p>
                        <select id="sort-select" onchange="sortProducts()"
                            class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#08415c]">
                            <option value="default">Sort By</option>
                            <option value="price-low">Price: Low to High</option>
                            <option value="price-high">Price: High to Low</option>
                            <option value="name">Name: A to Z</option>
                        </select>
                    </div>
                </div>

                <!-- Products Container -->
                <div id="products-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
                    <!-- Products will be populated by JavaScript -->
                </div>

                <!-- No Products Message -->
                <div id="no-products" class="hidden col-span-full text-center py-12">
                    <i class="fas fa-inbox text-6xl text-gray-300 mb-4"></i>
                    <p class="text-xl text-gray-600 font-medium">No products found</p>
                    <p class="text-gray-500 mt-2">Try adjusting your filters or browse other categories</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer Component -->
    <?php include 'components/footer.php'; ?>



    <script>
        // Global variables
        let allProducts = [];
        let filteredProducts = [];
        let currentCategoryId = null;
        let currentProductLineId = null;
        let categoryData = null;
        let currentPage = 1;
        const itemsPerPage = 12;

        // Cart initialization
        function initializeCart() {
            fetch('../backend/cart/cart_get.php')  // CHANGED
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateCartCount(data.cart_count);
                    }
                })
                .catch(error => console.error('Error loading cart:', error));
        }
        // Get URL parameters
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

        function renderStarIcons(rating, sizeClass = 'text-sm') {
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

        function renderProductRating(product) {
            const reviewCount = Number(product.review_count || 0);
            const averageRating = Number(product.average_rating || 0);

            if (reviewCount <= 0) {
                return `
                    <div class="flex items-center gap-2 mb-3">
                        <span class="text-sm font-medium text-gray-400">No reviews yet</span>
                    </div>
                `;
            }

            return `
                <div class="flex items-center gap-2 mb-3">
                    <span class="text-sm font-semibold text-[#08415c]">${averageRating.toFixed(1)}</span>
                    <div class="flex items-center gap-0.5 text-amber-400">
                        ${renderStarIcons(averageRating)}
                    </div>
                    <span class="text-sm text-gray-500">(${reviewCount.toLocaleString()})</span>
                </div>
            `;
        }

        // Escape HTML to prevent XSS
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

        // Load products from backend
        // Load products from backend
        async function loadProducts() {
            showLoader();

            currentCategoryId = getUrlParameter('id');
            currentProductLineId = getUrlParameter('c_id');

            try {
                // Build URL - allow no parameters for "All Products"
                let url = '../backend/get_products.php';
                const params = new URLSearchParams();

                if (currentCategoryId) {
                    params.append('category_id', currentCategoryId);
                }
                if (currentProductLineId) {
                    params.append('product_line_id', currentProductLineId);
                }

                if (params.toString()) {
                    url += '?' + params.toString();
                }

                const response = await fetch(url);
                const data = await response.json();

                if (data.success) {
                    categoryData = data.category;
                    allProducts = data.products || [];
                    filteredProducts = [...allProducts];

                    updatePageHeader();
                    initializeFilters();
                    displayProducts();
                } else {
                    showError(data.message || 'Failed to load products');
                }
            } catch (error) {
                console.error('Error loading products:', error);
                showError('An error occurred while loading products');
            } finally {
                hideLoader();
            }
        }

        // Update page header with category/product line info
        // Update page header with category/product line info
        function updatePageHeader() {
            if (!categoryData) return;

            const breadcrumb = document.getElementById('breadcrumb-category');
            const pageTitle = document.getElementById('page-title');
            const pageDescription = document.getElementById('page-description');

            if (currentProductLineId && categoryData.product_line_name) {
                // Viewing specific product line
                breadcrumb.innerHTML = `
                    <a href="product.php?id=${currentCategoryId}" class="hover:text-blue-100 transition">${escapeHtml(categoryData.category_name)}</a>
                    <span class="mx-2">/</span>
                    <span>${escapeHtml(categoryData.product_line_name)}</span>
                `;
                pageTitle.textContent = categoryData.product_line_name;
                pageDescription.textContent = categoryData.product_line_description || 'Browse our quality auto parts';
            } else if (currentCategoryId && categoryData.category_id > 0) {
                // Viewing specific category
                breadcrumb.textContent = categoryData.category_name;
                pageTitle.textContent = categoryData.category_name;
                pageDescription.textContent = categoryData.category_description || 'Browse our extensive collection';
            } else {
                // Viewing all products
                breadcrumb.textContent = '';
                pageTitle.textContent = 'All Products';
                pageDescription.textContent = 'Browse our complete collection of quality auto parts and accessories';
            }
        }

        // Initialize filter checkboxes
        function initializeFilters() {
            const filterContainer = document.getElementById('productline-filters');
            const searchInput = document.getElementById('product-search');
            filterContainer.innerHTML = '';

            if (searchInput) {
                searchInput.oninput = applyFilters;
            }

            // If viewing a specific product line, don't show product line filter
            if (currentProductLineId) {
                filterContainer.innerHTML = '<p class="text-gray-500 text-sm">Viewing specific product line</p>';
            } else {
                // Get unique categories (when viewing all products)
                const categoriesMap = new Map();
                const productLinesMap = new Map();

                allProducts.forEach(p => {
                    if (p.category_id && p.category_name) {
                        categoriesMap.set(p.category_id, {
                            id: p.category_id,
                            name: p.category_name
                        });
                    }
                    if (p.product_line_id && p.product_line_name) {
                        productLinesMap.set(p.product_line_id, {
                            id: p.product_line_id,
                            name: p.product_line_name
                        });
                    }
                });

                const categories = Array.from(categoriesMap.values());
                const productLines = Array.from(productLinesMap.values());

                // Show category filter if viewing all products (no category specified)
                if (!currentCategoryId && categories.length > 1) {
                    const categoryHeader = document.createElement('h4');
                    categoryHeader.className = 'font-semibold text-gray-800 mb-3';
                    categoryHeader.textContent = 'Categories';
                    filterContainer.appendChild(categoryHeader);

                    categories.forEach(cat => {
                        const label = document.createElement('label');
                        label.className = 'flex items-center cursor-pointer hover:text-[#08415c] transition mb-3';
                        label.innerHTML = `
                            <input type="checkbox" class="category-filter w-4 h-4 text-[#08415c] rounded" value="${cat.id}">
                            <span class="ml-3 text-gray-700">${escapeHtml(cat.name)}</span>
                        `;
                        label.querySelector('input').addEventListener('change', applyFilters);
                        filterContainer.appendChild(label);
                    });

                    // Add separator
                    const separator = document.createElement('hr');
                    separator.className = 'my-4 border-gray-200';
                    filterContainer.appendChild(separator);
                }

                // Show product line filter
                if (productLines.length > 0) {
                    const lineHeader = document.createElement('h4');
                    lineHeader.className = 'font-semibold text-gray-800 mb-3';
                    lineHeader.textContent = currentCategoryId ? 'Product Lines' : 'Product Lines';
                    filterContainer.appendChild(lineHeader);

                    productLines.forEach(line => {
                        const label = document.createElement('label');
                        label.className = 'flex items-center cursor-pointer hover:text-[#08415c] transition mb-3';
                        label.innerHTML = `
                            <input type="checkbox" class="productline-filter w-4 h-4 text-[#08415c] rounded" value="${line.id}">
                            <span class="ml-3 text-gray-700">${escapeHtml(line.name)}</span>
                        `;
                        label.querySelector('input').addEventListener('change', applyFilters);
                        filterContainer.appendChild(label);
                    });
                }

                if (categories.length === 0 && productLines.length === 0) {
                    filterContainer.innerHTML = '<p class="text-gray-500 text-sm">No filters available</p>';
                }
            }

            // Add event listeners to price filters
            document.querySelectorAll('.price-filter').forEach(el => {
                el.addEventListener('change', applyFilters);
            });
        }

        // Apply filters
        function applyFilters() {
            currentPage = 1;
            const selectedCategories = Array.from(document.querySelectorAll('.category-filter:checked')).map(el => parseInt(el.value));
            const selectedProductLines = Array.from(document.querySelectorAll('.productline-filter:checked')).map(el => parseInt(el.value));
            const selectedPrices = Array.from(document.querySelectorAll('.price-filter:checked')).map(el => el.value);
            const searchTerm = (document.getElementById('product-search')?.value || '').trim().toLowerCase();

            filteredProducts = allProducts.filter(product => {
                const categoryMatch = selectedCategories.length === 0 || selectedCategories.includes(product.category_id);
                const productLineMatch = selectedProductLines.length === 0 || selectedProductLines.includes(product.product_line_id);
                let priceMatch = selectedPrices.length === 0;
                const searchMatch = !searchTerm ||
                    (product.product_name || '').toLowerCase().includes(searchTerm) ||
                    (product.product_description || '').toLowerCase().includes(searchTerm) ||
                    (product.product_line_name || '').toLowerCase().includes(searchTerm) ||
                    (product.category_name || '').toLowerCase().includes(searchTerm) ||
                    (product.product_code || '').toLowerCase().includes(searchTerm);

                if (!priceMatch) {
                    priceMatch = selectedPrices.some(range => {
                        const [min, max] = range.split('-').map(Number);
                        return product.price >= min && product.price <= max;
                    });
                }

                return categoryMatch && productLineMatch && priceMatch && searchMatch;
            });

            displayProducts();
        }

        // Display products in grid
        // Display products in grid
        function displayProducts() {
            const grid = document.getElementById('products-grid');
            const noProducts = document.getElementById('no-products');
            const count = document.getElementById('product-count');

            grid.innerHTML = '';

            const oldPagination = document.getElementById('pagination-container');
            if (oldPagination) {
                oldPagination.remove();
            }

            if (filteredProducts.length === 0) {
                grid.classList.add('hidden');
                noProducts.classList.remove('hidden');
                count.textContent = 'No products found';
            } else {
                grid.classList.remove('hidden');
                noProducts.classList.add('hidden');

                const totalPages = Math.ceil(filteredProducts.length / itemsPerPage);
                const startIndex = (currentPage - 1) * itemsPerPage;
                const endIndex = startIndex + itemsPerPage;
                const productsToShow = filteredProducts.slice(startIndex, endIndex);

                productsToShow.forEach(product => {
                    const imagePath = product.product_image
                        ? `../Assets/images/products/${product.product_image}`
                        : '../Assets/images/website-images/placeholder.svg';

                    const card = document.createElement('div');
                    card.className = 'bg-white rounded-xl shadow-lg overflow-hidden product-card cursor-pointer flex flex-col';
                    card.innerHTML = `
                        <div onclick="viewProduct(${product.product_id})" class="flex flex-col h-full">
                            <div class="relative h-48 bg-gray-100 overflow-hidden shrink-0">
                                <img src="${imagePath}" 
                                     alt="${escapeHtml(product.product_name)}" 
                                     class="w-full h-full object-cover hover:scale-110 transition duration-300"
                                     onerror="this.src='../Assets/images/website-images/placeholder.svg'">
                            </div>
                             <div class="p-6 flex flex-col flex-1">
                                 <div class="mb-3">
                                     <span class="inline-block category-badge px-3 py-1 rounded-full text-white text-xs font-semibold">
                                         ${escapeHtml(product.product_line_name)}
                                     </span>
                                 </div>
                                 <h3 class="text-xl font-bold text-[#08415c] mb-2">${escapeHtml(product.product_name)}</h3>
                                 ${renderProductRating(product)}
                                 <p class="text-gray-600 text-sm mb-4 line-clamp-2 flex-1">${escapeHtml(product.product_description || 'High-quality auto part for optimal performance')}</p>
                                 <div class="flex justify-between items-center mt-auto">
                                     <span class="text-2xl font-bold text-[#08415c]">${formatPeso(product.price)}</span>
                                    <button onclick="event.stopPropagation(); addToCart(${product.product_id}, '${escapeHtml(product.product_name).replace(/'/g, "\\'")}', ${product.price})" 
                                            class="btn-primary-custom text-white px-4 py-2 rounded-lg font-semibold">
                                        <i class="fas fa-shopping-cart mr-2"></i>Add
                                    </button>
                                </div>
                                ${product.stock_quantity && product.stock_quantity < 10 ?
                            `<p class="text-red-500 text-sm mt-3"><i class="fas fa-exclamation-circle mr-1"></i>Only ${product.stock_quantity} left in stock!</p>`
                            : ''}
                            </div>
                        </div>
                    `;
                    grid.appendChild(card);
                });

                count.textContent = `Showing ${startIndex + 1}-${Math.min(endIndex, filteredProducts.length)} of ${filteredProducts.length} product${filteredProducts.length !== 1 ? 's' : ''}`;

                if (totalPages > 1) {
                    renderPagination(totalPages);
                }
            }
        }

        function renderPagination(totalPages) {
            const paginationContainer = document.createElement('div');
            paginationContainer.id = 'pagination-container';
            paginationContainer.className = 'col-span-full flex justify-center items-center mt-12 gap-2 pb-8';

            const prevBtn = document.createElement('button');
            prevBtn.className = `px-4 py-2 rounded-lg font-medium transition ${currentPage === 1 ? 'bg-gray-200 text-gray-500 cursor-not-allowed' : 'bg-white text-[#08415c] border border-gray-300 hover:bg-gray-50'}`;
            prevBtn.innerHTML = '<i class="fas fa-chevron-left"></i>';
            prevBtn.disabled = currentPage === 1;
            prevBtn.onclick = () => { if (currentPage > 1) { currentPage--; displayProducts(); window.scrollTo({ top: document.getElementById('products-grid').offsetTop - 100, behavior: 'smooth' }); } };
            paginationContainer.appendChild(prevBtn);

            for (let i = 1; i <= totalPages; i++) {
                if (i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
                    const pageBtn = document.createElement('button');
                    pageBtn.className = `w-10 h-10 rounded-lg font-medium transition ${currentPage === i ? 'category-badge text-white shadow-md' : 'bg-white text-[#08415c] border border-gray-300 hover:bg-gray-50'}`;
                    pageBtn.textContent = i;
                    pageBtn.onclick = () => { currentPage = i; displayProducts(); window.scrollTo({ top: document.getElementById('products-grid').offsetTop - 100, behavior: 'smooth' }); };
                    paginationContainer.appendChild(pageBtn);
                } else if (i === currentPage - 2 || i === currentPage + 2) {
                    const ellipsis = document.createElement('span');
                    ellipsis.className = 'px-2 text-gray-500';
                    ellipsis.textContent = '...';
                    paginationContainer.appendChild(ellipsis);
                }
            }

            const nextBtn = document.createElement('button');
            nextBtn.className = `px-4 py-2 rounded-lg font-medium transition ${currentPage === totalPages ? 'bg-gray-200 text-gray-500 cursor-not-allowed' : 'bg-white text-[#08415c] border border-gray-300 hover:bg-gray-50'}`;
            nextBtn.innerHTML = '<i class="fas fa-chevron-right"></i>';
            nextBtn.disabled = currentPage === totalPages;
            nextBtn.onclick = () => { if (currentPage < totalPages) { currentPage++; displayProducts(); window.scrollTo({ top: document.getElementById('products-grid').offsetTop - 100, behavior: 'smooth' }); } };
            paginationContainer.appendChild(nextBtn);

            document.getElementById('products-grid').insertAdjacentElement('afterend', paginationContainer);
        }

        // Navigate to product detail page
        function viewProduct(productId) {
            window.location.href = `product_detail.php?id=${productId}`;
        }

        // Sort products
        function sortProducts() {
            currentPage = 1;
            const sortValue = document.getElementById('sort-select').value;

            switch (sortValue) {
                case 'price-low':
                    filteredProducts.sort((a, b) => parseFloat(a.price) - parseFloat(b.price));
                    break;
                case 'price-high':
                    filteredProducts.sort((a, b) => parseFloat(b.price) - parseFloat(a.price));
                    break;
                case 'name':
                    filteredProducts.sort((a, b) => a.product_name.localeCompare(b.product_name));
                    break;
                default:
                    filteredProducts = [...allProducts];
                    applyFilters();
                    return;
            }
            displayProducts();
        }

        // Clear all filters
        function clearFilters() {
            currentPage = 1;
            document.querySelectorAll('.category-filter, .productline-filter, .price-filter').forEach(el => el.checked = false);
            const searchInput = document.getElementById('product-search');
            if (searchInput) searchInput.value = '';
            filteredProducts = [...allProducts];
            document.getElementById('sort-select').value = 'default';
            displayProducts();
        }

        // Add to cart
        async function addToCart(productId, productName, price) {
            try {
                const response = await fetch('../backend/cart/cart_add.php', {  // CHANGED
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        product_id: productId,
                        quantity: 1
                    })
                });

                const data = await response.json();

                if (data.success) {
                    updateCartCount(data.cart_count);
                    showNotification(`${productName} added to cart!`, 'user-cart.php');
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'Failed to add item to cart',
                        confirmButtonColor: '#08415c'
                    });
                }
            } catch (error) {
                console.error('Error adding to cart:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'An error occurred while adding to cart',
                    confirmButtonColor: '#08415c'
                });
            }
        }

        // Update cart count
        function updateCartCount(count) {
            document.querySelectorAll('.cart-count').forEach(el => el.textContent = count || 0);
        }

        // Show notification
        function showNotification(message, href = '') {
            if (typeof window.showAppToast === 'function') {
                window.showAppToast(message, 'success', {
                    href,
                    timer: 4200
                });
                return;
            }

            Swal.fire({
                icon: 'success',
                title: message,
                toast: true,
                position: 'top',
                href,
                showConfirmButton: false,
                timer: 4200,
                timerProgressBar: true
            });
        }

        // Loader functions
        function showLoader() {
            document.getElementById('loader').classList.add('active');
        }

        function hideLoader() {
            document.getElementById('loader').classList.remove('active');
        }

        // Show error message
        function showError(message) {
            const grid = document.getElementById('products-grid');
            grid.innerHTML = `
                <div class="col-span-full text-center py-12">
                    <i class="fas fa-exclamation-triangle text-6xl text-red-400 mb-4"></i>
                    <p class="text-xl text-gray-600 font-medium mb-4">${escapeHtml(message)}</p>
                    <button onclick="loadProducts()" class="btn-primary-custom text-white px-6 py-3 rounded-lg font-semibold">
                        <i class="fas fa-redo mr-2"></i>Try Again
                    </button>
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
            e.preventDefault(); // <-- THIS PREVENTS THE PAGE RELOAD BUG
            if (typeof window.globalHandleLogin === 'function') {
                return window.globalHandleLogin(e);
            } else {
                console.error("Login script is missing from navbar!");
            }
        }

        // Handle register
        async function handleRegister(e) {
            e.preventDefault(); // <-- ADD IT HERE TOO
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
        document.addEventListener('click', function (event) {
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
            loadProducts();
            initializeCart();
        });
    </script>
    <!-- Chat Bubble Component -->
    <?php include 'components/chat_bubble.php'; ?>
</body>

</html>