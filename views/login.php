<?php
// Login page
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $masterPassword = $_POST['master_password'] ?? '';
    
    if (empty($email) || empty($password) || empty($masterPassword)) {
        $error = 'All fields are required';
    } else {
        // Store master password for this session
        $_SESSION[SESSION_MASTER_KEY] = $masterPassword;
        
        // Attempt login
        $db = new Database($masterPassword);
        $auth = new Auth($db);
        $result = $auth->login($email, $password);
        
        if ($result['success']) {
            header('Location: ?page=dashboard');
            exit;
        } else {
            $error = $result['error'];
            unset($_SESSION[SESSION_MASTER_KEY]);
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
        <p class="text-gray-500 mt-1">Sign in to your account</p>
    </div>
    
    <?php if ($error): ?>
        <div class="bg-red-50 text-red-700 p-4 rounded-lg mb-6">
            <?php echo e($error); ?>
        </div>
    <?php endif; ?>
    
    <form method="POST" class="space-y-5">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
            <input type="email" name="email" required autofocus
                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-black focus:border-transparent outline-none transition"
                placeholder="john@example.com"
                value="<?php echo e($_POST['email'] ?? ''); ?>">
        </div>
        
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
            <input type="password" name="password" required
                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-black focus:border-transparent outline-none transition"
                placeholder="••••••••">
        </div>
        
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Master Password</label>
            <input type="password" name="master_password" required
                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-black focus:border-transparent outline-none transition"
                placeholder="••••••••">
            <p class="text-xs text-gray-500 mt-1">Your data encryption key</p>
        </div>
        
        <div class="flex items-center">
            <input type="checkbox" name="remember" id="remember" 
                class="w-4 h-4 border-gray-300 rounded text-black focus:ring-black">
            <label for="remember" class="ml-2 text-sm text-gray-600">Remember me</label>
        </div>
        
        <button type="submit"
            class="w-full py-2.5 bg-black text-white rounded-lg font-medium hover:bg-gray-800 transition">
            Sign In
        </button>
    </form>
</div>

<p class="text-center text-sm text-gray-500 mt-6">
    Version <?php echo APP_VERSION; ?>
</p>
