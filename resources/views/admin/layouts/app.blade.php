<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Shree Hari Om Admin')</title>
    
    <!-- Admin styles (local, no CDN – works when cdn.tailwindcss.com is unreachable) -->
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
    
    <!-- Font Awesome (optional: if this CDN fails, icons will be missing but layout works) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></noscript>
    
    <!-- Chart.js (optional: if this CDN fails, dashboard charts won't render) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js" defer></script>
    
    @yield('styles')
</head>
<body class="bg-gray-100 flex min-h-screen">
        <!-- Sidebar -->
    <aside class="w-64 bg-white shadow-3d-lg border-r border-primary-200 flex flex-col fixed top-0 left-0 h-screen z-50">
        <!-- Logo at top -->
        <div class="flex items-center justify-center h-16 bg-gradient-to-r from-primary-600 to-primary-700 border-b border-primary-500">
            <a href="{{ route('admin.dashboard') }}" class="flex items-center space-x-3 text-white">
                <div class="w-10 h-10 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                    <i class="fas fa-building text-xl"></i>
                    </div>
                    <div>
                    <h1 class="text-lg font-bold">Shree Hari Om</h1>
                    <p class="text-xs text-primary-100">Admin Panel</p>
                    </div>
            </a>
                </div>
                
                <!-- Navigation -->
        <nav class="flex-1 mt-4 overflow-y-auto px-4 pb-4">
            <ul class="space-y-2">
                <li>
                    <a href="{{ route('admin.dashboard') }}" class="sidebar-item flex items-center space-x-3 px-4 py-3 rounded-xl text-gray-700 hover:bg-primary-50 hover:text-primary-700 transition-all duration-300 {{ request()->routeIs('admin.dashboard') ? 'bg-primary-100 text-primary-700 shadow-md' : '' }}">
                        <i class="fas fa-tachometer-alt w-5"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('admin.users') }}" class="sidebar-item flex items-center space-x-3 px-4 py-3 rounded-xl text-gray-700 hover:bg-primary-50 hover:text-primary-700 transition-all duration-300 {{ request()->routeIs('admin.users*') && !request()->routeIs('admin.slab-upgrades') ? 'bg-primary-50 text-primary-700' : '' }}">
                        <i class="fas fa-users w-5"></i>
                        <span>Users</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('admin.slab-upgrades') }}" class="sidebar-item flex items-center space-x-3 px-4 py-3 rounded-xl text-gray-700 hover:bg-primary-50 hover:text-primary-700 transition-all duration-300 {{ request()->routeIs('admin.slab-upgrades') ? 'bg-primary-50 text-primary-700' : '' }}">
                        <i class="fas fa-chart-line w-5"></i>
                        <span>Slab Upgrades</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('admin.projects') }}" class="sidebar-item flex items-center space-x-3 px-4 py-3 rounded-xl text-gray-700 hover:bg-primary-50 hover:text-primary-700 transition-all duration-300 {{ request()->routeIs('admin.projects*') ? 'bg-primary-50 text-primary-700' : '' }}">
                        <i class="fas fa-building w-5"></i>
                        <span>Projects</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('admin.slabs.index') }}" class="sidebar-item flex items-center space-x-3 px-4 py-3 rounded-xl text-gray-700 hover:bg-primary-50 hover:text-primary-700 transition-all duration-300 {{ request()->routeIs('admin.slabs.*') ? 'bg-primary-50 text-primary-700' : '' }}">
                        <i class="fas fa-layer-group w-5"></i>
                        <span>Create Slabs</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('admin.property-types.index') }}" class="sidebar-item flex items-center space-x-3 px-4 py-3 rounded-xl text-gray-700 hover:bg-primary-50 hover:text-primary-700 transition-all duration-300 {{ request()->routeIs('admin.property-types.*') ? 'bg-primary-50 text-primary-700' : '' }}">
                        <i class="fas fa-th-large w-5"></i>
                        <span>Property Types</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('admin.measurement-units.index') }}" class="sidebar-item flex items-center space-x-3 px-4 py-3 rounded-xl text-gray-700 hover:bg-primary-50 hover:text-primary-700 transition-all duration-300 {{ request()->routeIs('admin.measurement-units.*') ? 'bg-primary-50 text-primary-700' : '' }}">
                        <i class="fas fa-ruler w-5"></i>
                        <span>Measurement Units</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('admin.kyc') }}" class="sidebar-item flex items-center space-x-3 px-4 py-3 rounded-xl text-gray-700 hover:bg-primary-50 hover:text-primary-700 transition-all duration-300 {{ request()->routeIs('admin.kyc*') ? 'bg-primary-50 text-primary-700' : '' }}">
                        <i class="fas fa-file-alt w-5"></i>
                        <span>KYC Management</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('admin.contact-inquiries.index') }}" class="sidebar-item flex items-center space-x-3 px-4 py-3 rounded-xl text-gray-700 hover:bg-primary-50 hover:text-primary-700 transition-all duration-300 {{ request()->routeIs('admin.contact-inquiries*') ? 'bg-primary-50 text-primary-700' : '' }}">
                        <i class="fas fa-envelope w-5"></i>
                        <span>Contact Inquiries</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('admin.wallet') }}" class="sidebar-item flex items-center space-x-3 px-4 py-3 rounded-xl text-gray-700 hover:bg-primary-50 hover:text-primary-700 transition-all duration-300 {{ request()->routeIs('admin.wallet*') ? 'bg-primary-50 text-primary-700' : '' }}">
                        <i class="fas fa-wallet w-5"></i>
                        <span>Wallet</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('admin.payment-methods.index') }}" class="sidebar-item flex items-center space-x-3 px-4 py-3 rounded-xl text-gray-700 hover:bg-primary-50 hover:text-primary-700 transition-all duration-300 {{ request()->routeIs('admin.payment-methods*') ? 'bg-primary-50 text-primary-700' : '' }}">
                        <i class="fas fa-credit-card w-5"></i>
                        <span>Payment Methods</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('admin.payment-requests.index') }}" class="sidebar-item flex items-center space-x-3 px-4 py-3 rounded-xl text-gray-700 hover:bg-primary-50 hover:text-primary-700 transition-all duration-300 {{ request()->routeIs('admin.payment-requests*') ? 'bg-primary-50 text-primary-700' : '' }}">
                        <i class="fas fa-money-check-alt w-5"></i>
                        <span>Payment Requests</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('admin.bookings.index') }}" class="sidebar-item flex items-center space-x-3 px-4 py-3 rounded-xl text-gray-700 hover:bg-primary-50 hover:text-primary-700 transition-all duration-300 {{ request()->routeIs('admin.bookings*') ? 'bg-primary-50 text-primary-700' : '' }}">
                        <i class="fas fa-calendar-check w-5"></i>
                        <span>Bookings</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('admin.users.graph') }}" class="sidebar-item flex items-center space-x-3 px-4 py-3 rounded-xl text-gray-700 hover:bg-primary-50 hover:text-primary-700 transition-all duration-300 {{ request()->routeIs('admin.users.graph*') ? 'bg-primary-50 text-primary-700' : '' }}">
                        <i class="fas fa-sitemap w-5"></i>
                        <span>Users Graph</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('admin.reports') }}" class="sidebar-item flex items-center space-x-3 px-4 py-3 rounded-xl text-gray-700 hover:bg-primary-50 hover:text-primary-700 transition-all duration-300 {{ request()->routeIs('admin.reports*') ? 'bg-primary-50 text-primary-700' : '' }}">
                        <i class="fas fa-chart-line w-5"></i>
                        <span>Reports</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('admin.settings') }}" class="sidebar-item flex items-center space-x-3 px-4 py-3 rounded-xl text-gray-700 hover:bg-primary-50 hover:text-primary-700 transition-all duration-300 {{ request()->routeIs('admin.settings*') ? 'bg-primary-50 text-primary-700' : '' }}">
                        <i class="fas fa-cog w-5"></i>
                        <span>Settings</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('admin.content.about-us') }}" class="sidebar-item flex items-center space-x-3 px-4 py-3 rounded-xl text-gray-700 hover:bg-primary-50 hover:text-primary-700 transition-all duration-300 {{ request()->routeIs('admin.content.about-us*') ? 'bg-primary-50 text-primary-700' : '' }}">
                        <i class="fas fa-info-circle w-5"></i>
                        <span>About Us</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('admin.content.contact-us') }}" class="sidebar-item flex items-center space-x-3 px-4 py-3 rounded-xl text-gray-700 hover:bg-primary-50 hover:text-primary-700 transition-all duration-300 {{ request()->routeIs('admin.content.contact-us*') ? 'bg-primary-50 text-primary-700' : '' }}">
                        <i class="fas fa-address-book w-5"></i>
                        <span>Contact Us</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('admin.content.privacy-policy') }}" class="sidebar-item flex items-center space-x-3 px-4 py-3 rounded-xl text-gray-700 hover:bg-primary-50 hover:text-primary-700 transition-all duration-300 {{ request()->routeIs('admin.content.privacy-policy*') ? 'bg-primary-50 text-primary-700' : '' }}">
                        <i class="fas fa-shield-alt w-5"></i>
                        <span>Privacy Policy</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('admin.content.terms-conditions') }}" class="sidebar-item flex items-center space-x-3 px-4 py-3 rounded-xl text-gray-700 hover:bg-primary-50 hover:text-primary-700 transition-all duration-300 {{ request()->routeIs('admin.content.terms-conditions*') ? 'bg-primary-50 text-primary-700' : '' }}">
                        <i class="fas fa-file-contract w-5"></i>
                        <span>Terms & Conditions</span>
                    </a>
                </li>
            </ul>
                </nav>
            
            <!-- User Info -->
        <div class="p-6 border-t border-gray-200 flex-shrink-0 bg-white">
                <div class="space-y-3">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-gradient-to-br from-primary-400 to-primary-600 rounded-full flex items-center justify-center">
                            <i class="fas fa-user text-white"></i>
                        </div>
                        <div class="flex-1">
                            <p class="text-sm font-medium text-gray-800">{{ Auth::user()->name ?? 'Admin' }}</p>
                            <p class="text-xs text-gray-500">Administrator</p>
                        </div>
                        <form method="POST" action="{{ route('admin.logout') }}">
                            @csrf
                            <button type="submit" class="text-gray-400 hover:text-red-500 transition-colors" title="Logout">
                                <i class="fas fa-sign-out-alt"></i>
                            </button>
                        </form>
                    </div>
                    <a href="{{ route('admin.profile.show') }}" class="flex items-center space-x-2 px-3 py-2 text-sm text-gray-700 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                        <i class="fas fa-user-edit text-primary-600"></i>
                        <span>Edit Profile</span>
                    </a>
                </div>
            </div>
    </aside>
        
    <!-- Main Content Area -->
    <main class="flex-1 ml-64 min-w-0" style="margin-left: 16rem;">
        <div class="w-full p-6">
            <!-- Top Bar -->
            <header class="bg-white shadow-md border-b border-gray-200 px-6 py-4 mb-6 rounded-lg">
                <div class="flex items-center justify-between">
                    <h2 class="text-2xl font-bold text-gray-800">@yield('page-title', 'Dashboard')</h2>
                    
                    <div class="flex items-center space-x-4">
                        <!-- Notifications -->
                        <div class="relative">
                            <button class="p-2 text-gray-400 hover:text-primary-600 transition-colors">
                                <i class="fas fa-bell text-xl"></i>
                                <span class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white text-xs rounded-full flex items-center justify-center">3</span>
                            </button>
                        </div>
                        
                        <!-- Search -->
                        <div class="relative">
                            <input type="text" placeholder="Search..." class="w-64 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                            <i class="fas fa-search absolute right-3 top-3 text-gray-400"></i>
                        </div>
                    </div>
                </div>
            </header>
            
            <!-- Page Content (min-w-0 prevents grid/flex collapse on all pages) -->
            <div class="w-full min-w-0">
                @yield('content')
            </div>
        </div>
    </main>
    
    <!-- Scripts -->
    <script>
        // Add some interactive animations
        document.addEventListener('DOMContentLoaded', function() {
            // Add floating animation to cards
            const cards = document.querySelectorAll('.card-3d');
            cards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
            });
            
            // Add hover effects to sidebar items
            const sidebarItems = document.querySelectorAll('.sidebar-item');
            sidebarItems.forEach(item => {
                item.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateX(5px)';
                });
                
                item.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateX(0)';
                });
            });
        });
    </script>
    
    @yield('scripts')
    @stack('scripts')
</body>
</html>
