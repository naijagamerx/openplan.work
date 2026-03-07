<?php
// First-time setup page
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $masterPassword = $_POST['master_password'] ?? '';
    
    // Validation
    if (empty($name) || empty($email) || empty($password) || empty($masterPassword)) {
        $error = 'All fields are required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match';
    } else {
        // Store master password in session for encryption
        $_SESSION[SESSION_MASTER_KEY] = $masterPassword;
        
        // Create database with master password
        $db = new Database($masterPassword);
        $auth = new Auth($db);
        
        // Register user
        $result = $auth->register($email, $password, $name);
        
        if ($result['success']) {
            // Create initial config
            $db->save('config', [
                'businessName' => '',
                'businessEmail' => $email,
                'currency' => 'USD',
                'taxRate' => 0,
                'groqApiKey' => '',
                'openrouterApiKey' => '',
                'setupComplete' => true,
                'createdAt' => date('c')
            ]);
            
            $success = true;
        } else {
            $error = $result['error'];
        }
    }
}
?>

<div class="bg-white rounded-2xl shadow-xl p-8">
    <!-- Logo -->
    <div class="text-center mb-8">
        <div class="w-16 h-16 bg-black rounded-2xl flex items-center justify-center mx-auto mb-4">
            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
        </div>
        <h1 class="text-2xl font-bold text-gray-900"><?php echo APP_NAME; ?></h1>
        <p class="text-gray-500 mt-1">Welcome! Let's set up your account.</p>
    </div>
    
    <?php if ($success): ?>
        <div class="bg-green-50 text-green-700 p-4 rounded-lg mb-6">
            <p class="font-medium">Setup complete!</p>
            <p class="text-sm mt-1">Redirecting to login...</p>
        </div>
        <script>
            setTimeout(() => {
                window.location.href = '?page=login';
            }, 2000);
        </script>
    <?php else: ?>
        <?php if ($error): ?>
            <div class="bg-red-50 text-red-700 p-4 rounded-lg mb-6">
                <?php echo e($error); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" class="space-y-5">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                <input type="text" name="name" required
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-black focus:border-transparent outline-none transition"
                    placeholder="John Doe"
                    value="<?php echo e($_POST['name'] ?? ''); ?>">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                <input type="email" name="email" required
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-black focus:border-transparent outline-none transition"
                    placeholder="john@example.com"
                    value="<?php echo e($_POST['email'] ?? ''); ?>">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                <input type="password" name="password" required minlength="8"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-black focus:border-transparent outline-none transition"
                    placeholder="••••••••">
                <p class="text-xs text-gray-500 mt-1">Minimum 8 characters</p>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
                <input type="password" name="confirm_password" required
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-black focus:border-transparent outline-none transition"
                    placeholder="••••••••">
            </div>
            
            <div class="pt-2 border-t border-gray-200">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Master Password
                    <span class="font-normal text-gray-500">(for data encryption)</span>
                </label>
                <input type="password" name="master_password" required
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-black focus:border-transparent outline-none transition"
                    placeholder="••••••••">
                <p class="text-xs text-gray-500 mt-1">
                    ⚠️ Keep this safe! Required to decrypt your data.
                </p>
            </div>
            
            <button type="submit"
                class="w-full py-2.5 bg-black text-white rounded-lg font-medium hover:bg-gray-800 transition">
                Complete Setup
            </button>
        </form>
    <?php endif; ?>
</div>
