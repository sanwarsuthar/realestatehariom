<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Admin Login - Shree Hari Om</title>
    
    <!-- Admin styles (local, no CDN) -->
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="min-h-screen gradient-bg flex items-center justify-center p-4">
    <!-- Background Elements -->
    <div class="absolute inset-0 overflow-hidden">
        <!-- Floating Circles -->
        <div class="absolute top-20 left-20 w-32 h-32 bg-white bg-opacity-10 rounded-full floating"></div>
        <div class="absolute top-40 right-32 w-24 h-24 bg-white bg-opacity-10 rounded-full floating" style="animation-delay: -2s;"></div>
        <div class="absolute bottom-32 left-32 w-40 h-40 bg-white bg-opacity-10 rounded-full floating" style="animation-delay: -4s;"></div>
        <div class="absolute bottom-20 right-20 w-28 h-28 bg-white bg-opacity-10 rounded-full floating" style="animation-delay: -1s;"></div>
        
        <!-- Grid Pattern -->
        <div class="absolute inset-0 opacity-5">
            <div class="w-full h-full" style="background-image: radial-gradient(circle, #8b5cf6 1px, transparent 1px); background-size: 50px 50px;"></div>
        </div>
    </div>
    
    <!-- Login Card -->
    <div class="relative z-10 w-full max-w-md">
        <!-- Card -->
        <div class="card-3d bg-white rounded-3xl shadow-3d-lg p-8 animate-slide-up">
            <!-- Logo Section -->
            <div class="text-center mb-8">
                <div class="w-20 h-20 bg-gradient-to-br from-primary-500 to-primary-700 rounded-2xl flex items-center justify-center shadow-glow mx-auto mb-4 pulse-glow">
                    <i class="fas fa-building text-white text-3xl"></i>
                </div>
                <h1 class="text-3xl font-bold text-gray-800 mb-2">Shree Hari Om</h1>
                <p class="text-gray-600">Real Estate MLM Admin Panel</p>
            </div>
            
            <!-- Login Form -->
            <form method="POST" action="{{ route('admin.login.submit') }}" class="space-y-6" autocomplete="off">
                @csrf
                
                <!-- Email Field -->
                <div class="space-y-2">
                    <label for="email" class="block text-sm font-medium text-gray-700">Email Address</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-envelope text-gray-400"></i>
                        </div>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            value="{{ old('email', 'admin@shreehariom.com') }}"
                            autocomplete="username"
                            class="input-focus w-full pl-10 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-transparent @error('email') border-red-500 @enderror" 
                            placeholder="admin@shreehariom.com"
                            required
                        >
                    </div>
                    @error('email')
                        <p class="text-red-500 text-sm">{{ $message }}</p>
                    @enderror
                </div>
                
                <!-- Password Field -->
                <div class="space-y-2">
                    <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-lock text-gray-400"></i>
                        </div>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            autocomplete="off"
                            data-lpignore="true"
                            data-form-type="other"
                            class="input-focus w-full pl-10 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-transparent @error('password') border-red-500 @enderror" 
                            placeholder="Enter your password"
                            required
                        >
                    </div>
                    @error('password')
                        <p class="text-red-500 text-sm">{{ $message }}</p>
                    @enderror
                </div>
                
                <!-- Remember Me -->
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <input 
                            type="checkbox" 
                            id="remember" 
                            name="remember" 
                            class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded"
                        >
                        <label for="remember" class="ml-2 block text-sm text-gray-700">Remember me</label>
                    </div>
                    <a href="#" class="text-sm text-primary-600 hover:text-primary-500 transition-colors">Forgot password?</a>
                </div>
                
                <!-- Login Button -->
                <button 
                    type="submit" 
                    class="w-full bg-gradient-to-r from-primary-500 to-primary-700 text-white py-3 px-4 rounded-xl font-medium hover:from-primary-600 hover:to-primary-800 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 transform hover:scale-105 transition-all duration-200 shadow-lg hover:shadow-xl"
                >
                    <i class="fas fa-sign-in-alt mr-2"></i>
                    Sign In to Admin Panel
                </button>
                
                <!-- Development Quick Login -->
                <div class="mt-4">
                    <button 
                        type="button" 
                        onclick="quickLogin()"
                        class="w-full bg-gradient-to-r from-green-500 to-green-700 text-white py-2 px-4 rounded-xl font-medium hover:from-green-600 hover:to-green-800 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transform hover:scale-105 transition-all duration-200 shadow-lg hover:shadow-xl text-sm"
                    >
                        <i class="fas fa-rocket mr-2"></i>
                        Quick Login (Development)
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Footer -->
        <div class="text-center mt-8 text-white text-sm">
            <p>&copy; 2024 Shree Hari Om Group. All rights reserved.</p>
         </div>
    </div>
    
    <!-- Scripts -->
    <script>
        // Quick login function for development
        function quickLogin() {
            const form = document.querySelector('form');
            const button = document.querySelector('button[onclick="quickLogin()"]');
            
            // Show loading state
            button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Logging In...';
            button.disabled = true;
            
            // Submit form
            form.submit();
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            // Add floating animation to background elements
            const floatingElements = document.querySelectorAll('.floating');
            floatingElements.forEach((element, index) => {
                element.style.animationDelay = `${index * 1.5}s`;
            });
            
            // Add focus effects to inputs
            const inputs = document.querySelectorAll('.input-focus');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.classList.add('scale-105');
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.classList.remove('scale-105');
                });
            });
            
            // Add loading state to form submission
            const form = document.querySelector('form');
            form.addEventListener('submit', function() {
                const button = this.querySelector('button[type="submit"]');
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Signing In...';
                button.disabled = true;
            });
            
            // Auto-focus on email field
            document.getElementById('email').focus();
        });
    </script>
</body>
</html>
