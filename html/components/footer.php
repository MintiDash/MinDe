<?php
// Shared Footer Component for MinC
// Include this file at the bottom of your PHP pages
$current_page = basename($_SERVER['PHP_SELF']);
$is_in_html = in_array($current_page, ['product.php', 'product_detail.php', 'user-cart.php', 'checkout.php', 'order-success.php', 'profile.php', 'my-orders.php', 'reset_password.php', 'blog.php']);
$base_path = $is_in_html ? '../' : './';
$product_path = $is_in_html ? 'product.php' : 'html/product.php';
?>

<!-- Footer -->
<footer id="contact-us" class="bg-[#08415c] text-white py-16">
    <div class="max-w-7xl mx-auto px-4">
        <div class="grid md:grid-cols-4 gap-12">
            <div>
                <h3 class="text-2xl font-bold mb-6">MinC</h3>
                <p class="text-blue-200 mb-4">Your trusted partner for quality auto parts and accessories.</p>
            </div>

            <div>
                <h4 class="text-lg font-bold mb-4">Contact Us</h4>
                <ul class="space-y-3 text-blue-200">
                    <li><i class="fas fa-map-marker-alt mr-2"></i> Angeles City, Pampanga</li>
                    <li><i class="fas fa-phone mr-2"></i> 0921-949-8978</li>
                    <li><i class="fas fa-envelope mr-2"></i> <a href="mailto:MinC@gmail.com" class="hover:text-white transition">MinC@gmail.com</a></li>
                </ul>
            </div>

            <div>
                <h4 class="text-lg font-bold mb-4">Quick Links</h4>
                <ul class="space-y-3 text-blue-200">
                    <li><a href="<?php echo $base_path; ?>index.php#about-us" class="hover:text-white transition">About Us</a></li>
                    <li><a href="<?php echo $base_path; ?>index.php#categories" class="hover:text-white transition">Categories</a></li>
                    <li><a href="<?php echo $product_path; ?>" class="hover:text-white transition">Products</a></li>
                </ul>
            </div>

            <div>
                <h4 class="text-lg font-bold mb-4">Follow Us</h4>
                <div class="flex space-x-4">
                    <a href="https://www.facebook.com/ritzmoncar.autoparts?rdid=GbXdvmSnoK5FnqUs&share_url=https%3A%2F%2Fwww.facebook.com%2Fshare%2F1AR2ZrWwrF#" target="_blank" rel="noopener noreferrer" class="bg-[#0a5273] w-10 h-10 rounded-full flex items-center justify-center hover:bg-white hover:text-[#08415c] transition">
                        <i class="fab fa-facebook-f"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="border-t border-blue-900 mt-12 pt-8 text-center text-blue-200">
            <p>&copy; 2025-2026 MinC Computer Parts. All rights reserved.</p>
        </div>
    </div>
</footer>
