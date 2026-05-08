<?php
// Shared Navigation Component for MinC
$current_page = basename($_SERVER['PHP_SELF']);

// Determine base paths for navigation
$is_in_html = in_array($current_page, ['product.php', 'product_detail.php', 'user-cart.php', 'checkout.php', 'order-success.php', 'profile.php', 'my-orders.php']);
$base_path = $is_in_html ? '../' : './';
$html_path = $is_in_html ? '' : 'html/';
?>

<!-- Navigation -->
<nav class="bg-white shadow-md fixed w-full top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-20">
            <!-- Logo -->
            <div class="flex-shrink-0">
                <a href="<?php echo $base_path; ?>index.php" onclick="scrollToTop(event)"
                    class="text-3xl font-bold text-gray-900">MinC</a>
            </div>

            <!-- Desktop Menu -->
            <div class="hidden md:flex items-center space-x-8">
                <a href="<?php echo $base_path; ?>index.php#about-us"
                    class="nav-link-custom text-gray-700 font-medium">About Us</a>
                <a href="<?php echo $html_path; ?>product.php"
                    class="nav-link-custom text-gray-700 font-medium">Products</a>
                <a href="<?php echo $base_path; ?>index.php#categories"
                    class="nav-link-custom text-gray-700 font-medium">Categories</a>
                <a href="<?php echo $base_path; ?>index.php#contact-us"
                    class="nav-link-custom text-gray-700 font-medium">Contact</a>
                <a id="dashboardLink" href="<?php echo $base_path; ?>app/frontend/dashboard.php"
                    class="nav-link-custom text-gray-700 font-medium flex items-center hidden"><i
                        class="fas fa-chart-line mr-2"></i>Dashboard</a>
                <a id="profileLink" href="<?php echo $html_path; ?>profile.php"
                    class="nav-link-custom text-gray-700 font-medium flex items-center hidden"><i
                        class="fas fa-user-circle mr-2"></i>Profile</a>
                <a id="cartLink" href="<?php echo $html_path; ?>user-cart.php"
                    class="nav-link-custom text-gray-700 font-medium flex items-center"><i
                        class="fas fa-shopping-cart mr-2"></i>Cart</a>
                <a id="orderLink" href="<?php echo $html_path; ?>my-orders.php"
                    class="nav-link-custom text-gray-700 font-medium flex items-center hidden"><i
                        class="fas fa-shopping-bag mr-2"></i>My Orders</a>
                <button id="loginBtn" class="btn-primary-custom text-white px-4 py-2 rounded-lg font-medium ml-4"
                    onclick="openLoginModal()">Login</button>
                <button id="logoutBtn"
                    class="hidden btn-primary-custom text-white px-4 py-2 rounded-lg font-medium ml-4"
                    onclick="handleLogout()">Logout</button>
            </div>

            <!-- Mobile Menu Button -->
            <button class="md:hidden text-gray-700" onclick="toggleMobileMenu()">
                <i class="fas fa-bars text-2xl"></i>
            </button>
        </div>
    </div>

    <!-- Mobile Menu -->
    <div id="mobileMenu" class="hidden md:hidden bg-white border-t">
        <div class="px-4 py-4 space-y-3">
            <a href="<?php echo $base_path; ?>index.php#about-us" class="block text-gray-700 font-medium py-2">About
                Us</a>
            <a href="<?php echo $html_path; ?>product.php" class="block text-gray-700 font-medium py-2">Products</a>
            <a href="<?php echo $base_path; ?>index.php#categories"
                class="block text-gray-700 font-medium py-2">Categories</a>
            <a href="<?php echo $base_path; ?>index.php#contact-us"
                class="block text-gray-700 font-medium py-2">Contact</a>
            <a id="dashboardLinkMobile" href="<?php echo $base_path; ?>app/frontend/dashboard.php"
                class="block text-gray-700 font-medium py-2 flex items-center hidden"><i
                    class="fas fa-chart-line mr-2"></i>Dashboard</a>
            <a id="profileLinkMobile" href="<?php echo $html_path; ?>profile.php"
                class="block text-gray-700 font-medium py-2 flex items-center hidden"><i
                    class="fas fa-user-circle mr-2"></i>Profile</a>
            <a id="cartLinkMobile" href="<?php echo $html_path; ?>user-cart.php"
                class="block text-gray-700 font-medium py-2 flex items-center"><i
                    class="fas fa-shopping-cart mr-2"></i>Cart</a>
            <a id="orderLinkMobile" href="<?php echo $html_path; ?>my-orders.php"
                class="block text-gray-700 font-medium py-2 flex items-center hidden"><i
                    class="fas fa-shopping-bag mr-2"></i>My Orders</a>
            <button id="loginBtnMobile"
                class="w-full btn-primary-custom text-white px-4 py-2 rounded-lg font-medium mt-4"
                onclick="openLoginModal()">Login</button>
            <button id="logoutBtnMobile"
                class="hidden w-full btn-primary-custom text-white px-4 py-2 rounded-lg font-medium mt-4"
                onclick="handleLogout()">Logout</button>
        </div>
    </div>

    <!-- Login Modal -->
    <div id="loginModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-xl w-full max-h-[92vh] overflow-y-auto p-6 sm:p-8 relative">
            <button onclick="closeLoginModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-2xl"></i>
            </button>

            <div id="loginForm">
                <h2 class="text-3xl font-bold mb-6 text-[#08415c]">Welcome Back</h2>
                <form id="loginFormElement" onsubmit="handleLogin(event)">
                    <div class="mb-4">
                        <label class="block text-gray-700 font-medium mb-2">Email</label>
                        <input type="email" id="loginEmail" required
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#08415c]">
                    </div>
                    <div class="mb-6">
                        <label class="block text-gray-700 font-medium mb-2">Password</label>
                        <div class="relative">
                            <input type="password" id="loginPassword" required
                                class="w-full px-4 py-3 pr-24 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#08415c]">
                            <button type="button" id="toggleLoginPassword"
                                onclick="togglePasswordVisibility('loginPassword', 'toggleLoginPassword')"
                                class="absolute inset-y-0 right-0 px-3 text-sm text-gray-600 hover:text-[#08415c]">Show</button>
                        </div>
                    </div>
                    <button type="submit" class="w-full btn-primary-custom text-white py-3 rounded-lg font-semibold">
                        Login
                    </button>
                </form>
                <p class="text-center mt-3">
                    <button type="button" onclick="showForgotPassword()"
                        class="text-sm text-[#08415c] font-semibold hover:text-[#0a5273]">Forgot password?</button>
                </p>
                <p class="text-center mt-6 text-gray-600">
                    Don't have an account?
                    <button type="button" onclick="showRegister()"
                        class="text-[#08415c] font-semibold hover:text-[#0a5273]">Register</button>
                </p>
            </div>

            <div id="registerForm" class="hidden">
                <h2 class="text-3xl font-bold mb-6 text-[#08415c]">Create Account</h2>

                <form id="registerFormElement" onsubmit="handleRegister(event)">
                    <p class="text-sm text-gray-600 mb-4">Required fields: first name, last name, email, contact number,
                        and default shipping address.</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">First Name <span class="text-red-500">*</span></label>
                            <input type="text" id="registerFname" required
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#08415c]">
                        </div>
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">Last Name <span class="text-red-500">*</span></label>
                            <input type="text" id="registerLname" required
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#08415c]">
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 font-medium mb-2">Email <span class="text-red-500">*</span></label>
                        <input type="email" id="registerEmail" required
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#08415c]">
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-[2fr_1fr] gap-4 mb-4">
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">Contact Number <span class="text-red-500">*</span></label>
                            <input type="tel" id="registerContact" required maxlength="13"
                                placeholder="09XXXXXXXXX or +63XXXXXXXXXX"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#08415c]">
                        </div>
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">Postal Code <span class="text-red-500">*</span></label>
                            <input type="text" id="registerPostalCode" inputmode="numeric" maxlength="4" required
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#08415c]"
                                placeholder="2019">
                        </div>
                    </div>
                    <div class="mb-6">
                        <label class="block text-gray-700 font-medium mb-2">Default Shipping Address <span class="text-red-500">*</span></label>
                        <textarea id="registerAddress" required rows="3"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#08415c]"
                            placeholder="House/Unit No., Street, Barangay, Angeles City, Pampanga"></textarea>
                        <p class="mt-2 text-xs text-gray-500">Type your address or choose from the location options
                            below. Your address will update automatically.</p>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">Barangay</label>
                            <select id="registerBarangay"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#08415c] bg-white">
                                <option value="">Select barangay</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">City</label>
                            <select id="registerCity"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#08415c] bg-white">
                                <option value="">Select city</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">Province</label>
                            <select id="registerProvince"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#08415c] bg-white">
                                <option value="">Select province</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="w-full btn-primary-custom text-white py-3 rounded-lg font-semibold">
                        Continue
                    </button>
                </form>

                <div id="otpStep" class="hidden mt-6">
                    <h3 class="text-xl font-bold text-[#08415c] mb-2">Enter Verification Code</h3>
                    <p class="text-sm text-gray-600 mb-4">Enter the 6-digit OTP sent to your email.</p>
                    <form id="otpFormElement" onsubmit="handleVerifyOtp(event)">
                        <div class="mb-3">
                            <input type="text" id="otpCode" maxlength="6" inputmode="numeric" pattern="\d{6}"
                                placeholder="000000"
                                class="w-full tracking-[0.4em] text-center text-xl px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#08415c]">
                        </div>
                        <button type="submit"
                            class="w-full btn-primary-custom text-white py-3 rounded-lg font-semibold">
                            Verify OTP
                        </button>
                    </form>
                    <button type="button" onclick="handleResendOtp()"
                        class="w-full mt-3 text-[#08415c] font-semibold hover:text-[#0a5273]">
                        Resend code
                    </button>
                </div>

                <div id="passwordStep" class="hidden mt-6">
                    <h3 class="text-xl font-bold text-[#08415c] mb-2">Create Password</h3>
                    <p class="text-sm text-gray-600 mb-4">Set your account password after OTP verification.</p>
                    <form id="passwordFormElement" onsubmit="handleSetPassword(event)">
                        <div class="mb-2">
                            <label class="block text-gray-700 font-medium mb-2">Password</label>
                            <div class="relative">
                                <input type="password" id="registerPassword" required minlength="8"
                                    class="w-full px-4 py-3 pr-24 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#08415c]">
                                <button type="button" id="toggleRegisterPassword"
                                    onclick="togglePasswordVisibility('registerPassword', 'toggleRegisterPassword')"
                                    class="absolute inset-y-0 right-0 px-3 text-sm text-gray-600 hover:text-[#08415c]">Show</button>
                            </div>
                            <p class="mt-2 text-xs text-gray-500">Password must be at least 8 characters long and
                                include a letter, number, and special character.</p>
                        </div>
                        <button type="submit"
                            class="w-full btn-primary-custom text-white py-3 rounded-lg font-semibold mt-2">
                            Submit
                        </button>
                    </form>
                </div>

                <p class="text-center mt-6 text-gray-600">
                    Already have an account?
                    <button type="button" onclick="showLogin()"
                        class="text-[#08415c] font-semibold hover:text-[#0a5273]">Login</button>
                </p>
            </div>
        </div>
    </div>

    <!-- Forgot Password Modal -->
    <div id="forgotPasswordModal"
        class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-8 relative">
            <button onclick="closeForgotPasswordModal()"
                class="absolute top-4 right-4 text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-2xl"></i>
            </button>
            <h2 class="text-3xl font-bold mb-3 text-[#08415c]">Forgot Password</h2>
            <p class="text-sm text-gray-600 mb-5">Enter your email and we will send a recovery link.</p>
            <form id="forgotPasswordFormElement" onsubmit="handleForgotPassword(event)">
                <div class="mb-5">
                    <label class="block text-gray-700 font-medium mb-2">Email</label>
                    <input type="email" id="forgotEmail" required
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#08415c]">
                </div>
                <button type="submit" class="w-full btn-primary-custom text-white py-3 rounded-lg font-semibold">
                    Send Recovery Link
                </button>
            </form>
            <p class="text-center mt-6 text-gray-600">
                <button type="button" onclick="backToLoginFromForgot()"
                    class="text-[#08415c] font-semibold hover:text-[#0a5273]">Back to Login</button>
            </p>
        </div>
    </div>
</nav>

<style>
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

    .minc-toast-container {
        position: fixed !important;
        inset: 0 !important;
        display: flex !important;
        justify-content: center !important;
        align-items: flex-start !important;
        padding-top: 50px !important;
        pointer-events: none !important;
        z-index: 10000 !important;
    }

    .swal2-container.minc-toast-container>.swal2-popup.minc-toast-popup {
        margin: 0 auto !important;
    }

    .swal2-popup.minc-toast-popup {
        width: min(92vw, 420px) !important;
        min-height: 0 !important;
        padding: 0.55rem 0.8rem !important;
        border-radius: 12px !important;
        background: linear-gradient(135deg, #08415c 0%, #0a5273 100%) !important;
        border: 1px solid rgba(255, 255, 255, 0.2) !important;
        color: #fff !important;
        pointer-events: auto !important;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.22) !important;
    }

    .swal2-popup.minc-toast-popup .swal2-title.minc-toast-title {
        margin: 0 !important;
        font-size: 0.92rem !important;
        font-weight: 600 !important;
        line-height: 1.25 !important;
    }

    .swal2-popup.minc-toast-popup .swal2-close.minc-toast-close {
        color: rgba(255, 255, 255, 0.9) !important;
        font-size: 1.2rem !important;
        width: 1.5rem !important;
        height: 1.5rem !important;
    }

    .swal2-popup.minc-toast-popup .swal2-icon {
        margin: 0 0.55rem 0 0 !important;
        transform: scale(0.78);
    }

    .swal2-popup.minc-toast-popup .swal2-timer-progress-bar {
        background: rgba(255, 255, 255, 0.4) !important;
    }

    .swal2-popup.minc-toast-popup.minc-toast-info,
    .swal2-popup.minc-toast-popup.minc-toast-success,
    .swal2-popup.minc-toast-popup.minc-toast-warning,
    .swal2-popup.minc-toast-popup.minc-toast-error,
    .swal2-popup.minc-toast-popup.minc-toast-question {
        background: linear-gradient(135deg, #08415c 0%, #0a5273 100%) !important;
        color: #fff !important;
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    const BASE_PATH = '<?php echo $base_path; ?>';
    const MINC_DEFAULT_CITY = 'Angeles City';
    const MINC_DEFAULT_PROVINCE = 'Pampanga';
    const MINC_ALLOWED_BARANGAYS = [
        'Agapito del Rosario', 'Amsic', 'Balibago', 'Capaya', 'Claro M. Recto', 'Cuayan',
        'Lourdes North-West', 'Lourdes Sur (South)', 'Lourdes Sur-East', 'Malabanas',
        'Margot', 'Mining', 'Ninoy Aquino', 'Pampang', 'Pandan', 'Pulungbulu',
        'Pulung Cacutud', 'Pulung Maragul', 'Pulungbato', 'Salapungan', 'San Jose',
        'San Nicolas', 'Santa Teresita', 'Santa Trinidad', 'Santo Cristo', 'Santo Domingo',
        'Sapangbato'
    ];

    function normalizeShippingToken(value) {
        const baseValue = String(value || '').trim();
        const normalized = typeof baseValue.normalize === 'function'
            ? baseValue.normalize('NFD').replace(/[\u0300-\u036f]/g, '')
            : baseValue;

        return normalized
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function normalizedTokenExists(haystack, needle) {
        const normalizedHaystack = normalizeShippingToken(haystack);
        const normalizedNeedle = normalizeShippingToken(needle);
        if (!normalizedHaystack || !normalizedNeedle) return false;
        return (` ${normalizedHaystack} `).includes(` ${normalizedNeedle} `);
    }

    function parseShippingAddress(address, fallbackCity = MINC_DEFAULT_CITY, fallbackProvince = MINC_DEFAULT_PROVINCE) {
        const trimmedAddress = String(address || '').trim().replace(/\s+/g, ' ');
        let barangay = '';

        for (const candidate of MINC_ALLOWED_BARANGAYS) {
            if (normalizedTokenExists(trimmedAddress, candidate)) {
                barangay = candidate;
                break;
            }
        }

        return {
            address: trimmedAddress,
            barangay,
            city: trimmedAddress ? fallbackCity : '',
            province: trimmedAddress ? fallbackProvince : '',
            hasValidBarangay: Boolean(barangay)
        };
    }

    function stripShippingLocation(address, options = {}) {
        const removableTokens = new Set(
            [
                MINC_DEFAULT_CITY,
                MINC_DEFAULT_PROVINCE,
                options.city || '',
                options.province || '',
                options.lastCity || '',
                options.lastProvince || ''
            ]
                .map((value) => normalizeShippingToken(value))
                .filter(Boolean)
        );

        return String(address || '')
            .split(',')
            .map((segment) => segment.trim())
            .filter((segment) => {
                if (!segment) return false;

                const normalizedSegment = normalizeShippingToken(segment);
                if (!normalizedSegment) return false;
                if (removableTokens.has(normalizedSegment)) return false;

                return !MINC_ALLOWED_BARANGAYS.some((candidate) => normalizedSegment === normalizeShippingToken(candidate));
            })
            .join(', ');
    }

    function composeShippingAddress(address, options = {}) {
        const streetAddress = stripShippingLocation(address, options);
        const parts = [
            streetAddress,
            String(options.barangay || '').trim(),
            String(options.city || MINC_DEFAULT_CITY).trim(),
            String(options.province || MINC_DEFAULT_PROVINCE).trim()
        ].filter(Boolean);

        return parts.join(', ');
    }

    function escapeShippingOption(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function populateSelectableOptions(targetElement, values, placeholderLabel = '') {
        if (!targetElement) return;

        const uniqueValues = Array.from(new Set((Array.isArray(values) ? values : []).map((value) => String(value || '').trim()).filter(Boolean)));

        if (targetElement.tagName === 'SELECT') {
            const currentValue = String(targetElement.value || '').trim();
            const placeholderOption = placeholderLabel !== ''
                ? `<option value="">${escapeShippingOption(placeholderLabel)}</option>`
                : '';

            targetElement.innerHTML = placeholderOption + uniqueValues
                .map((value) => `<option value="${escapeShippingOption(value)}">${escapeShippingOption(value)}</option>`)
                .join('');

            if (uniqueValues.includes(currentValue)) {
                targetElement.value = currentValue;
            }
            return;
        }

        if (targetElement.tagName === 'DATALIST') {
            targetElement.innerHTML = uniqueValues
                .map((value) => `<option value="${escapeShippingOption(value)}"></option>`)
                .join('');
        }
    }

    function initializeShippingControls(config = {}) {
        const addressInput = document.getElementById(config.addressId || '');
        const barangayInput = document.getElementById(config.barangayId || '');
        const cityInput = document.getElementById(config.cityId || '');
        const provinceInput = document.getElementById(config.provinceId || '');

        if (!addressInput || !barangayInput || !cityInput || !provinceInput) {
            return null;
        }

        const barangayOptionsTarget = barangayInput.tagName === 'SELECT'
            ? barangayInput
            : document.getElementById(config.barangayListId || '');
        const cityOptionsTarget = cityInput.tagName === 'SELECT'
            ? cityInput
            : document.getElementById(config.cityListId || '');
        const provinceOptionsTarget = provinceInput.tagName === 'SELECT'
            ? provinceInput
            : document.getElementById(config.provinceListId || '');

        populateSelectableOptions(barangayOptionsTarget, MINC_ALLOWED_BARANGAYS, config.barangayPlaceholder || 'Select barangay');
        populateSelectableOptions(cityOptionsTarget, [MINC_DEFAULT_CITY], config.cityPlaceholder || 'Select city');
        populateSelectableOptions(provinceOptionsTarget, [MINC_DEFAULT_PROVINCE], config.provincePlaceholder || 'Select province');

        const syncFromAddress = () => {
            const parsedAddress = parseShippingAddress(
                addressInput.value,
                cityInput.value.trim() || MINC_DEFAULT_CITY,
                provinceInput.value.trim() || MINC_DEFAULT_PROVINCE
            );
            const syncedCity = addressInput.value.trim() ? (parsedAddress.city || cityInput.value.trim() || MINC_DEFAULT_CITY) : '';
            const syncedProvince = addressInput.value.trim() ? (parsedAddress.province || provinceInput.value.trim() || MINC_DEFAULT_PROVINCE) : '';

            barangayInput.value = parsedAddress.barangay || '';
            cityInput.value = syncedCity;
            provinceInput.value = syncedProvince;
            addressInput.dataset.mincCity = syncedCity;
            addressInput.dataset.mincProvince = syncedProvince;

            if (typeof config.onSync === 'function') {
                config.onSync(parsedAddress);
            }

            return parsedAddress;
        };

        const applySelectionsToAddress = () => {
            addressInput.value = composeShippingAddress(addressInput.value, {
                barangay: barangayInput.value,
                city: cityInput.value || MINC_DEFAULT_CITY,
                province: provinceInput.value || MINC_DEFAULT_PROVINCE,
                lastCity: addressInput.dataset.mincCity || '',
                lastProvince: addressInput.dataset.mincProvince || ''
            });

            return syncFromAddress();
        };

        if (!cityInput.value.trim()) {
            cityInput.value = MINC_DEFAULT_CITY;
        }
        if (!provinceInput.value.trim()) {
            provinceInput.value = MINC_DEFAULT_PROVINCE;
        }

        addressInput.addEventListener('input', syncFromAddress);
        ['change', 'blur'].forEach((eventName) => {
            barangayInput.addEventListener(eventName, applySelectionsToAddress);
            cityInput.addEventListener(eventName, applySelectionsToAddress);
            provinceInput.addEventListener(eventName, applySelectionsToAddress);
        });

        syncFromAddress();

        return {
            syncFromAddress,
            applySelectionsToAddress
        };
    }

    window.mincParseShippingAddress = parseShippingAddress;
    window.mincComposeShippingAddress = composeShippingAddress;
    window.mincInitializeShippingControls = initializeShippingControls;
    window.MINC_ALLOWED_BARANGAYS = MINC_ALLOWED_BARANGAYS.slice();

    function resolveToastVariant(icon) {
        const value = String(icon || 'info').toLowerCase();
        if (value === 'error' || value === 'success' || value === 'warning' || value === 'question' || value === 'info') {
            return value;
        }
        return 'info';
    }

    function enforceCenteredToastLayout(toastEl) {
        const container = toastEl && toastEl.parentElement ? toastEl.parentElement : null;
        if (!container) return;

        container.style.position = 'fixed';
        container.style.top = '0';
        container.style.right = '0';
        container.style.bottom = '0';
        container.style.left = '0';
        container.style.width = '100vw';
        container.style.maxWidth = '100vw';
        container.style.transform = 'none';
        container.style.margin = '0';
        container.style.display = 'flex';
        container.style.justifyContent = 'center';
        container.style.alignItems = 'flex-start';
        container.style.paddingTop = '50px';
        container.style.pointerEvents = 'none';
        container.style.zIndex = '10000';
        container.classList.remove('swal2-top-start', 'swal2-top-end');
        container.classList.add('swal2-top');

        toastEl.style.margin = '0 auto';
        toastEl.style.pointerEvents = 'auto';
    }

    function attachToastNavigation(toastEl, options = {}) {
        if (!toastEl) return;

        const href = typeof options.href === 'string' ? options.href.trim() : '';
        const onClick = typeof options.onClick === 'function' ? options.onClick : null;

        if (!href && !onClick) {
            toastEl.style.cursor = '';
            toastEl.removeAttribute('role');
            toastEl.removeAttribute('tabindex');
            return;
        }

        const activateToast = (event) => {
            if (event && event.target && typeof event.target.closest === 'function' && event.target.closest('.swal2-close')) {
                return;
            }

            if (onClick) {
                onClick(event);
                return;
            }

            window.location.assign(href);
        };

        toastEl.style.cursor = 'pointer';
        toastEl.setAttribute('role', 'button');
        toastEl.setAttribute('tabindex', '0');
        toastEl.addEventListener('click', activateToast);
        toastEl.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                activateToast(event);
            }
        });
    }

    function showAppToast(message, icon = 'info', options = {}) {
        if (typeof Swal === 'undefined') {
            alert(String(message ?? ''));
            return Promise.resolve();
        }

        const variant = resolveToastVariant(icon || options.icon);
        const popupClass = `minc-toast-popup minc-toast-${variant}`;
        const originalDidOpen = options.didOpen;

        return Swal.fire(Object.assign({}, options, {
            toast: true,
            position: 'top',
            icon: variant,
            title: options.title || String(message ?? ''),
            text: options.text || undefined,
            showConfirmButton: false,
            showCloseButton: true,
            timer: options.timer ?? 3200,
            timerProgressBar: true,
            customClass: Object.assign({}, options.customClass || {}, {
                container: 'minc-toast-container',
                popup: popupClass,
                title: 'minc-toast-title',
                closeButton: 'minc-toast-close'
            }),
            didOpen: (toast) => {
                enforceCenteredToastLayout(toast);
                toast.addEventListener('mouseenter', Swal.stopTimer);
                toast.addEventListener('mouseleave', Swal.resumeTimer);
                attachToastNavigation(toast, options);
                if (typeof originalDidOpen === 'function') {
                    originalDidOpen(toast);
                }
            }
        }));
    }

    function applyGlobalToastDefaults() {
        if (typeof Swal === 'undefined' || Swal.__mincToastPatched) {
            return;
        }

        const originalFire = Swal.fire.bind(Swal);

        Swal.fire = function (...args) {
            if (args.length === 1 && args[0] && typeof args[0] === 'object' && args[0].toast === true) {
                const input = args[0];
                const variant = resolveToastVariant(input.icon);
                const originalDidOpen = input.didOpen;
                const existingPopup = (input.customClass && input.customClass.popup) ? String(input.customClass.popup) : '';
                const popupClass = existingPopup.includes('minc-toast-popup')
                    ? existingPopup
                    : `${existingPopup} minc-toast-popup minc-toast-${variant}`.trim();

                return originalFire(Object.assign({}, input, {
                    position: 'top',
                    showCloseButton: input.showCloseButton ?? true,
                    showConfirmButton: false,
                    timerProgressBar: input.timerProgressBar ?? true,
                    customClass: Object.assign({}, input.customClass || {}, {
                        container: (input.customClass && input.customClass.container) || 'minc-toast-container',
                        popup: popupClass,
                        title: (input.customClass && input.customClass.title) || 'minc-toast-title',
                        closeButton: (input.customClass && input.customClass.closeButton) || 'minc-toast-close'
                    }),
                    didOpen: (toast) => {
                        enforceCenteredToastLayout(toast);
                        toast.addEventListener('mouseenter', Swal.stopTimer);
                        toast.addEventListener('mouseleave', Swal.resumeTimer);
                        attachToastNavigation(toast, input);
                        if (typeof originalDidOpen === 'function') {
                            originalDidOpen(toast);
                        }
                    }
                }));
            }

            return originalFire(...args);
        };

        Swal.__mincToastPatched = true;
    }

    applyGlobalToastDefaults();
    window.showAppToast = showAppToast;

    function showAlertModal(message, icon = 'info', title = 'Notice') {
        if (typeof Swal !== 'undefined') {
            return Swal.fire({
                icon,
                title,
                text: String(message ?? ''),
                confirmButtonColor: '#08415c'
            });
        }
        alert(message);
        return Promise.resolve();
    }

    async function showConfirmModal(message, title = 'Please Confirm') {
        if (typeof Swal !== 'undefined') {
            const result = await Swal.fire({
                icon: 'question',
                title,
                text: String(message ?? ''),
                showCancelButton: true,
                confirmButtonColor: '#08415c',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Confirm'
            });
            return !!result.isConfirmed;
        }
        return confirm(message);
    }
    const HTML_PATH = '<?php echo $html_path; ?>';

    // Check session on navbar load
    function checkNavbarSession() {
        fetch(BASE_PATH + 'backend/auth.php?api=status&t=' + Date.now(), { cache: 'no-store' })
            .then(response => response.json())
            .then(data => {
                if (data.logged_in) {
                    updateNavbarUI(true, data.user.user_level_id);
                } else {
                    updateNavbarUI(false);
                }
            })
            .catch(error => console.error('Session check error:', error));
    }

    function updateNavbarUI(isLoggedIn, userLevelId = null) {
        const loginBtn = document.getElementById('loginBtn');
        const loginBtnMobile = document.getElementById('loginBtnMobile');
        const logoutBtn = document.getElementById('logoutBtn');
        const logoutBtnMobile = document.getElementById('logoutBtnMobile');
        const profileLink = document.getElementById('profileLink');
        const profileLinkMobile = document.getElementById('profileLinkMobile');
        const cartLink = document.getElementById('cartLink');
        const cartLinkMobile = document.getElementById('cartLinkMobile');
        const orderLink = document.getElementById('orderLink');
        const orderLinkMobile = document.getElementById('orderLinkMobile');
        const dashboardLink = document.getElementById('dashboardLink');
        const dashboardLinkMobile = document.getElementById('dashboardLinkMobile');

        if (isLoggedIn) {
            // Show authenticated elements
            if (loginBtn) loginBtn.classList.add('hidden');
            if (loginBtnMobile) loginBtnMobile.classList.add('hidden');
            if (logoutBtn) logoutBtn.classList.remove('hidden');
            if (logoutBtnMobile) logoutBtnMobile.classList.remove('hidden');
            if (profileLink) profileLink.classList.remove('hidden');
            if (profileLinkMobile) profileLinkMobile.classList.remove('hidden');
            if (cartLink) cartLink.classList.remove('hidden');
            if (cartLinkMobile) cartLinkMobile.classList.remove('hidden');
            if (orderLink) orderLink.classList.remove('hidden');
            if (orderLinkMobile) orderLinkMobile.classList.remove('hidden');

            // Show dashboard only for IT Personnel (1) and Owner (2)
            if (userLevelId && userLevelId <= 2) {
                if (dashboardLink) dashboardLink.classList.remove('hidden');
                if (dashboardLinkMobile) dashboardLinkMobile.classList.remove('hidden');
            } else {
                if (dashboardLink) dashboardLink.classList.add('hidden');
                if (dashboardLinkMobile) dashboardLinkMobile.classList.add('hidden');
            }
        } else {
            // Show unauthenticated elements
            if (loginBtn) loginBtn.classList.remove('hidden');
            if (loginBtnMobile) loginBtnMobile.classList.remove('hidden');
            if (logoutBtn) logoutBtn.classList.add('hidden');
            if (logoutBtnMobile) logoutBtnMobile.classList.add('hidden');
            if (profileLink) profileLink.classList.add('hidden');
            if (profileLinkMobile) profileLinkMobile.classList.add('hidden');
            if (orderLink) orderLink.classList.add('hidden');
            if (orderLinkMobile) orderLinkMobile.classList.add('hidden');
            if (dashboardLink) dashboardLink.classList.add('hidden');
            if (dashboardLinkMobile) dashboardLinkMobile.classList.add('hidden');
        }
    }

    function toggleMobileMenu() {
        const menu = document.getElementById('mobileMenu');
        if (menu) {
            menu.classList.toggle('hidden');
        }
    }

    function scrollToTop(event) {
        if (window.location.pathname.includes('index.php') || window.location.pathname.endsWith('/')) {
            event.preventDefault();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    }

    function sanitizePostLoginRedirect(url) {
        try {
            const targetUrl = new URL(url || window.location.href, window.location.href);
            return targetUrl.origin === window.location.origin ? targetUrl.href : '';
        } catch (error) {
            return '';
        }
    }

    function setPostLoginRedirect(url) {
        const safeUrl = sanitizePostLoginRedirect(url);
        if (!safeUrl) {
            return;
        }

        try {
            window.sessionStorage.setItem('post_login_redirect', safeUrl);
        } catch (error) {
            // Ignore storage failures and fall back to backend redirect.
        }
    }

    function getPostLoginRedirect() {
        try {
            return sanitizePostLoginRedirect(window.sessionStorage.getItem('post_login_redirect'));
        } catch (error) {
            return '';
        }
    }

    function clearPostLoginRedirect() {
        try {
            window.sessionStorage.removeItem('post_login_redirect');
        } catch (error) {
            // Ignore storage failures.
        }
    }

    function openLoginModal() {
        setPostLoginRedirect(window.location.href);
        const modal = document.getElementById('loginModal');
        if (modal) modal.classList.remove('hidden');
        showLogin();
    }

    let pendingRegistrationEmail = '';
    let registerShippingControls = null;

    function closeLoginModal() {
        const modal = document.getElementById('loginModal');
        if (modal) modal.classList.add('hidden');
        // Clear form fields
        document.getElementById('loginEmail').value = '';
        document.getElementById('loginPassword').value = '';
        resetRegistrationFlow();
        document.getElementById('loginForm').classList.remove('hidden');
    }

    function openForgotPasswordModal() {
        const modal = document.getElementById('forgotPasswordModal');
        if (modal) modal.classList.remove('hidden');
    }

    function closeForgotPasswordModal() {
        const modal = document.getElementById('forgotPasswordModal');
        if (modal) modal.classList.add('hidden');
        const forgotEmailInput = document.getElementById('forgotEmail');
        if (forgotEmailInput) forgotEmailInput.value = '';
    }

    function togglePasswordVisibility(inputId, buttonId) {
        const input = document.getElementById(inputId);
        const button = document.getElementById(buttonId);
        if (!input || !button) return;

        const isPassword = input.type === 'password';
        input.type = isPassword ? 'text' : 'password';
        button.textContent = isPassword ? 'Hide' : 'Show';
    }

    function isStrongPassword(password) {
        if (password.length < 8) return false;
        if (password === '123456') return false;
        if (!/[A-Za-z]/.test(password)) return false;
        if (!/\d/.test(password)) return false;
        if (!/[^A-Za-z0-9]/.test(password)) return false;
        return true;
    }

    function showRegister() {
        document.getElementById('loginForm').classList.add('hidden');
        document.getElementById('registerForm').classList.remove('hidden');
        resetRegistrationFlow();
    }

    function showLogin() {
        document.getElementById('registerForm').classList.add('hidden');
        document.getElementById('loginForm').classList.remove('hidden');
        resetRegistrationFlow();
    }

    function showForgotPassword() {
        closeLoginModal();
        openForgotPasswordModal();
    }

    function backToLoginFromForgot() {
        const forgotEmail = document.getElementById('forgotEmail').value;
        closeForgotPasswordModal();
        openLoginModal();
        if (forgotEmail) {
            document.getElementById('loginEmail').value = forgotEmail;
        }
    }

    function resetRegistrationFlow() {
        pendingRegistrationEmail = '';
        const registerForm = document.getElementById('registerFormElement');
        const otpStep = document.getElementById('otpStep');
        const passwordStep = document.getElementById('passwordStep');
        const otpCode = document.getElementById('otpCode');
        const registerPassword = document.getElementById('registerPassword');
        const registerFields = [
            'registerFname',
            'registerLname',
            'registerEmail',
            'registerContact',
            'registerAddress',
            'registerBarangay',
            'registerCity',
            'registerProvince',
            'registerPostalCode'
        ];

        if (registerForm) registerForm.classList.remove('hidden');
        if (otpStep) otpStep.classList.add('hidden');
        if (passwordStep) passwordStep.classList.add('hidden');
        if (otpCode) otpCode.value = '';
        if (registerPassword) registerPassword.value = '';

        registerFields.forEach((id) => {
            const field = document.getElementById(id);
            if (field) field.disabled = false;
        });

        if (registerShippingControls && typeof registerShippingControls.syncFromAddress === 'function') {
            registerShippingControls.syncFromAddress();
        }
    }

    async function handleLogin(e) {
        e.preventDefault();

        const email = document.getElementById('loginEmail').value;
        const password = document.getElementById('loginPassword').value;

        try {
            const response = await fetch(BASE_PATH + 'backend/login.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    email: email,
                    password: password
                })
            });

            const data = await response.json();

            if (data.success) {
                closeLoginModal();
                const redirectTarget = getPostLoginRedirect();
                clearPostLoginRedirect();
                if (redirectTarget) {
                    window.location.href = redirectTarget;
                } else {
                    window.location.reload();
                }
            } else {
                showAlertModal('Login failed: ' + data.message, 'error', 'Login Failed');
            }
        } catch (error) {
            console.error('Login error:', error);
            showAlertModal('An error occurred during login', 'error', 'Login Error');
        }
    }

    async function handleRegister(e) {
        e.preventDefault();

        const fname = document.getElementById('registerFname').value;
        const lname = document.getElementById('registerLname').value;
        const email = document.getElementById('registerEmail').value;
        const contact = document.getElementById('registerContact').value.trim();
        const addressInput = document.getElementById('registerAddress');
        const address = addressInput ? addressInput.value.trim() : '';
        const postalCode = (document.getElementById('registerPostalCode').value || '').trim();
        const shippingLocation = parseShippingAddress(address);

        if (!contact) {
            showAlertModal('Contact number is required.', 'warning', 'Missing Contact Number');
            return;
        }

        if (!address) {
            showAlertModal('Default shipping address is required.', 'warning', 'Missing Shipping Address');
            return;
        }

        if (address.length < 10 || address.length > 255) {
            showAlertModal('Shipping address must be between 10 and 255 characters.', 'warning', 'Invalid Shipping Address');
            return;
        }

        if (!shippingLocation.hasValidBarangay) {
            showAlertModal('Include a valid Angeles City barangay in the shipping address.', 'warning', 'Incomplete Shipping Address');
            return;
        }

        if (!/^(09\d{9}|(\+?63)\d{10})$/.test(contact.replace(/[\s\-\(\)]/g, ''))) {
            showAlertModal('Contact number must be 09XXXXXXXXX or +63XXXXXXXXXX.', 'warning', 'Invalid Contact Number');
            return;
        }

        if (postalCode && !/^\d{4}$/.test(postalCode)) {
            showAlertModal('Postal code must be a 4-digit number.', 'warning', 'Invalid Postal Code');
            return;
        }

        try {
            const response = await fetch(BASE_PATH + 'backend/register.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    fname: fname,
                    lname: lname,
                    email: email,
                    contact_num: contact,
                    address: address,
                    postal_code: postalCode
                })
            });

            const data = await response.json();

            if (data.success) {
                pendingRegistrationEmail = email;
                document.getElementById('registerFormElement').classList.add('hidden');
                document.getElementById('otpStep').classList.remove('hidden');
                document.getElementById('passwordStep').classList.add('hidden');
                document.getElementById('otpCode').focus();
                const emailSent = data.email_sent !== false;
                showAlertModal(
                    data.message || (emailSent ? 'OTP sent to your email.' : 'OTP could not be sent. Please try resend.'),
                    emailSent ? 'success' : 'warning',
                    emailSent ? 'OTP Sent' : 'OTP Not Sent'
                );
            } else {
                showAlertModal('Registration failed: ' + data.message, 'error', 'Registration Failed');
            }
        } catch (error) {
            console.error('Register error:', error);
            showAlertModal('An error occurred during registration', 'error', 'Registration Error');
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        registerShippingControls = window.mincInitializeShippingControls({
            addressId: 'registerAddress',
            barangayId: 'registerBarangay',
            cityId: 'registerCity',
            provinceId: 'registerProvince'
        });
    });

    async function handleForgotPassword(e) {
        e.preventDefault();
        const email = document.getElementById('forgotEmail').value;

        try {
            const response = await fetch(BASE_PATH + 'backend/request_password_reset.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ email })
            });

            const data = await response.json();
            showAlertModal(data.message || 'If this email exists, a recovery link has been sent.', 'info', 'Password Recovery');
            closeForgotPasswordModal();
            openLoginModal();
            document.getElementById('loginEmail').value = email;
        } catch (error) {
            console.error('Forgot password error:', error);
            showAlertModal('An error occurred while requesting password reset.', 'error', 'Password Reset Error');
        }
    }

    async function handleVerifyOtp(e) {
        e.preventDefault();

        const email = pendingRegistrationEmail || document.getElementById('registerEmail').value;
        const otp = (document.getElementById('otpCode').value || '').trim();

        if (!/^\d{6}$/.test(otp)) {
            showAlertModal('Please enter a valid 6-digit OTP.', 'warning', 'Invalid OTP');
            return;
        }

        try {
            const response = await fetch(BASE_PATH + 'backend/verify_registration_otp.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ email, otp })
            });

            const data = await response.json();
            if (!data.success) {
                showAlertModal(data.message || 'OTP verification failed.', 'error', 'OTP Verification Failed');
                return;
            }

            document.getElementById('otpStep').classList.add('hidden');
            document.getElementById('passwordStep').classList.remove('hidden');
            document.getElementById('registerPassword').focus();
        } catch (error) {
            console.error('OTP verification error:', error);
            showAlertModal('An error occurred while verifying OTP.', 'error', 'OTP Error');
        }
    }

    async function handleResendOtp() {
        const email = pendingRegistrationEmail || document.getElementById('registerEmail').value;
        if (!email) {
            showAlertModal('Please enter your email first.', 'warning', 'Missing Email');
            return;
        }

        try {
            const response = await fetch(BASE_PATH + 'backend/resend_verification_email.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ email })
            });

            const data = await response.json();
            if (!data.success) {
                showAlertModal(data.message || 'Failed to resend OTP.', 'error', 'Resend Failed');
                return;
            }

            const emailSent = data.email_sent !== false;
            showAlertModal(
                data.message || (emailSent ? 'OTP resent successfully.' : 'OTP could not be sent right now.'),
                emailSent ? 'success' : 'warning',
                emailSent ? 'OTP Resent' : 'OTP Not Sent'
            );
        } catch (error) {
            console.error('Resend OTP error:', error);
            showAlertModal('An error occurred while resending OTP.', 'error', 'Resend Error');
        }
    }

    async function handleSetPassword(e) {
        e.preventDefault();

        const email = pendingRegistrationEmail || document.getElementById('registerEmail').value;
        const password = document.getElementById('registerPassword').value;

        if (!isStrongPassword(password)) {
            showAlertModal('Password must be at least 8 characters long and include a letter, number, and special character.', 'warning', 'Weak Password');
            return;
        }

        try {
            const response = await fetch(BASE_PATH + 'backend/complete_registration.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ email, password })
            });

            const data = await response.json();
            if (!data.success) {
                showAlertModal(data.message || 'Failed to set password.', 'error', 'Password Setup Failed');
                return;
            }

            showAlertModal(data.message || 'Registration complete. You can now login.', 'success', 'Registration Complete');
            showLogin();
            document.getElementById('loginEmail').value = email;
        } catch (error) {
            console.error('Set password error:', error);
            showAlertModal('An error occurred while setting password.', 'error', 'Password Error');
        }
    }

    async function handleLogout() {
        let isConfirmed = false;

        if (typeof Swal !== 'undefined') {
            const result = await Swal.fire({
                title: 'Are you sure?',
                text: 'Do you want to logout?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#08415c',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, logout'
            });
            isConfirmed = !!result.isConfirmed;
        } else {
            isConfirmed = await showConfirmModal('Are you sure you want to logout?', 'Logout');
        }

        if (!isConfirmed) {
            return;
        }

        try {
            const response = await fetch(BASE_PATH + 'backend/logout.php', {
                cache: 'no-store',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });

            let data = {};
            try {
                data = await response.json();
            } catch (e) {
                data = {};
            }

            if (!response.ok || data.success === false) {
                throw new Error(data.message || 'Logout failed');
            }

            updateNavbarUI(false);

            if (typeof Swal !== 'undefined') {
                await Swal.fire({
                    icon: 'success',
                    title: 'Logged Out',
                    text: 'You have been logged out successfully.',
                    confirmButtonColor: '#08415c',
                    timer: 1200
                });
            }

            const currentPath = window.location.pathname.toLowerCase();
            const shouldRedirectHome =
                currentPath.endsWith('/html/profile.php') ||
                currentPath.endsWith('/html/user-cart.php') ||
                currentPath.endsWith('/html/my-orders.php') ||
                currentPath.endsWith('/html/checkout.php');

            if (shouldRedirectHome) {
                window.location.href = BASE_PATH + 'index.php';
                return;
            }

            window.location.reload();
        } catch (error) {
            console.error('Logout error:', error);
            updateNavbarUI(false);
            window.location.reload();
        }
    }

    window.globalHandleLogout = handleLogout;
    window.globalHandleRegister = handleRegister;
    window.globalHandleLogin = handleLogin;
    window.setPostLoginRedirect = setPostLoginRedirect;

    // Check session when navbar loads
    document.addEventListener('DOMContentLoaded', checkNavbarSession);
</script>