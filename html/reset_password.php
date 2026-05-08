<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - MinC</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="min-h-screen bg-gray-100 flex items-center justify-center p-4">
    <div class="bg-white shadow-xl rounded-2xl w-full max-w-md p-8">
        <h1 class="text-2xl font-bold text-[#08415c] mb-2">Create New Password</h1>
        <p class="text-sm text-gray-600 mb-6">Enter a strong password for your account.</p>

        <form id="resetPasswordForm">
            <div class="mb-4">
                <label class="block text-gray-700 font-medium mb-2">New Password</label>
                <input type="password" id="newPassword" required minlength="8" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#08415c]">
            </div>
            <div class="mb-2">
                <label class="block text-gray-700 font-medium mb-2">Confirm Password</label>
                <input type="password" id="confirmPassword" required minlength="8" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#08415c]">
            </div>
            <p class="mt-2 text-xs text-gray-500 mb-6">Password must be at least 8 characters and include a letter, number, and special character.</p>

            <button type="submit" class="w-full bg-[#08415c] hover:bg-[#0a5273] text-white py-3 rounded-lg font-semibold transition">
                Update Password
            </button>
        </form>
    </div>

    <script>
        function isStrongPassword(password) {
            if (password.length < 8) return false;
            if (password === '123456') return false;
            if (!/[A-Za-z]/.test(password)) return false;
            if (!/\d/.test(password)) return false;
            if (!/[^A-Za-z0-9]/.test(password)) return false;
            return true;
        }

        function getTokenFromUrl() {
            const params = new URLSearchParams(window.location.search);
            return params.get('token') || '';
        }

        document.getElementById('resetPasswordForm').addEventListener('submit', async function (e) {
            e.preventDefault();

            const token = getTokenFromUrl();
            const password = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;

            if (!token) {
                Swal.fire({ icon: 'error', title: 'Invalid Link', text: 'Reset link is invalid or missing token.' });
                return;
            }

            if (password !== confirmPassword) {
                Swal.fire({ icon: 'error', title: 'Password Mismatch', text: 'Passwords do not match.' });
                return;
            }

            if (!isStrongPassword(password)) {
                Swal.fire({ icon: 'error', title: 'Weak Password', text: 'Use at least 8 chars with letter, number, and special char.' });
                return;
            }

            try {
                const response = await fetch('../backend/reset_password.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ token, password })
                });

                const data = await response.json();
                if (!data.success) {
                    Swal.fire({ icon: 'error', title: 'Failed', text: data.message || 'Could not reset password.' });
                    return;
                }

                Swal.fire({
                    icon: 'success',
                    title: 'Password Updated',
                    text: data.message || 'You can now login.',
                    confirmButtonColor: '#08415c'
                }).then(() => {
                    window.location.href = '../index.php';
                });
            } catch (error) {
                Swal.fire({ icon: 'error', title: 'Error', text: 'An unexpected error occurred.' });
            }
        });
    </script>
</body>
</html>

