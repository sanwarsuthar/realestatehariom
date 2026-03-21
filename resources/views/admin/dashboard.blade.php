@extends('admin.layouts.app')

@section('title', 'Dashboard - Shree Hari Om Admin')
@section('page-title', 'Dashboard')

@section('content')
@if(isset($error))
    <!-- Error Alert -->
    <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-6">
        <div class="flex items-center">
            <div class="flex-shrink-0">
                <i class="fas fa-exclamation-triangle text-red-400 text-xl"></i>
            </div>
            <div class="ml-3">
                <h3 class="text-sm font-medium text-red-800">Dashboard Error</h3>
                <div class="mt-2 text-sm text-red-700">
                    <p>{{ $error }}</p>
                </div>
            </div>
        </div>
    </div>
@endif

<div class="admin-page-content space-y-6 min-w-0">
    <!-- Welcome Section -->
    <div class="bg-gradient-to-r from-primary-500 to-primary-700 rounded-2xl p-6 text-white shadow-3d-lg relative overflow-hidden">
        <!-- Background Pattern -->
        <div class="absolute inset-0 opacity-10">
            <div class="w-full h-full bg-[length:10px_10px]" style="background-image: linear-gradient(to right, rgba(255,255,255,0.3) 1px, transparent 1px), linear-gradient(to bottom, rgba(255,255,255,0.3) 1px, transparent 1px);"></div>
        </div>
        
        <div class="relative z-10 flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold mb-2">Welcome back, {{ Auth::user()->name }}!</h1>
                <p class="text-primary-100">Here's what's happening with your Real Estate MLM platform today.</p>
                <div class="mt-4 flex items-center space-x-4">
                    <div class="flex items-center space-x-2">
                        <i class="fas fa-building text-primary-200"></i>
                        <span class="text-sm">Real Estate Platform</span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <i class="fas fa-users text-primary-200"></i>
                        <span class="text-sm">MLM Network</span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <i class="fas fa-chart-line text-primary-200"></i>
                        <span class="text-sm">Growth Analytics</span>
                    </div>
                    <div class="flex items-center space-x-2 bg-white bg-opacity-20 px-3 py-1 rounded-lg">
                        <i class="fas fa-share-alt text-primary-200"></i>
                        <span class="text-sm font-semibold">Admin Referral Code: <span class="font-bold">{{ $adminReferralCode ?? 'N/A' }}</span></span>
                    </div>
                </div>
            </div>
            <div class="w-20 h-20 bg-white bg-opacity-20 rounded-2xl flex items-center justify-center floating">
                <i class="fas fa-building text-3xl"></i>
            </div>
        </div>
        
        <!-- Floating Elements -->
        <div class="absolute top-4 right-4 w-8 h-8 bg-white bg-opacity-20 rounded-full floating" style="animation-delay: -1s;"></div>
        <div class="absolute bottom-4 left-4 w-6 h-6 bg-white bg-opacity-20 rounded-full floating" style="animation-delay: -2s;"></div>
    </div>
    
    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Total Users -->
        <div class="card-3d bg-white rounded-2xl p-6 shadow-lg hover:shadow-xl transition-all duration-300">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Total Users</p>
                    <p class="text-3xl font-bold text-gray-900">{{ number_format($stats['total_users'] ?? 0) }}</p>
                    <p class="text-sm text-green-600 flex items-center mt-1">
                        <i class="fas fa-arrow-up mr-1"></i>
                        +{{ $stats['new_users_today'] ?? 0 }} today
                    </p>
                </div>
                <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl flex items-center justify-center shadow-glow">
                    <i class="fas fa-users text-white text-xl"></i>
                </div>
            </div>
        </div>
        
        <!-- Properties Sold -->
        <div class="card-3d bg-white rounded-2xl p-6 shadow-lg hover:shadow-xl transition-all duration-300">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Properties Sold</p>
                    <p class="text-3xl font-bold text-gray-900">{{ number_format($stats['properties_sold_today'] ?? 0) }}</p>
                    <p class="text-sm text-green-600 flex items-center mt-1">
                        <i class="fas fa-arrow-up mr-1"></i>
                        Today
                    </p>
                </div>
                <div class="w-12 h-12 bg-gradient-to-br from-green-500 to-green-600 rounded-xl flex items-center justify-center shadow-glow">
                    <i class="fas fa-home text-white text-xl"></i>
                </div>
            </div>
        </div>
        
        <!-- Business Volume -->
        <div class="card-3d bg-white rounded-2xl p-6 shadow-lg hover:shadow-xl transition-all duration-300">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Business Volume</p>
                    <p class="text-3xl font-bold text-gray-900">₹{{ number_format($stats['total_business_volume'] ?? 0) }}</p>
                    <p class="text-sm text-primary-600 flex items-center mt-1">
                        <i class="fas fa-rupee-sign mr-1"></i>
                        Total
                    </p>
                </div>
                <div class="w-12 h-12 bg-gradient-to-br from-primary-500 to-primary-600 rounded-xl flex items-center justify-center shadow-glow">
                    <i class="fas fa-chart-bar text-white text-xl"></i>
                </div>
            </div>
        </div>
        
        <!-- Pending KYC -->
        <div class="card-3d bg-white rounded-2xl p-6 shadow-lg hover:shadow-xl transition-all duration-300">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Pending KYC</p>
                    <p class="text-3xl font-bold text-gray-900">{{ number_format($stats['pending_kyc'] ?? 0) }}</p>
                    <p class="text-sm text-orange-600 flex items-center mt-1">
                        <i class="fas fa-clock mr-1"></i>
                        Needs Review
                    </p>
                </div>
                <div class="w-12 h-12 bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl flex items-center justify-center shadow-glow">
                    <i class="fas fa-file-alt text-white text-xl"></i>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Charts and Analytics -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- User Growth Chart -->
        <div class="card-3d bg-white rounded-2xl p-6 shadow-lg">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-800">User Growth</h3>
                <div class="flex space-x-2">
                    <button class="px-3 py-1 text-xs bg-primary-100 text-primary-700 rounded-full">7D</button>
                    <button class="px-3 py-1 text-xs text-gray-500 rounded-full">30D</button>
                    <button class="px-3 py-1 text-xs text-gray-500 rounded-full">90D</button>
                </div>
            </div>
            <div class="h-64">
                <canvas id="userGrowthChart"></canvas>
            </div>
        </div>
        
        <!-- Slab Distribution -->
        <div class="card-3d bg-white rounded-2xl p-6 shadow-lg">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-800">Slab Distribution</h3>
                <i class="fas fa-chart-pie text-primary-500"></i>
            </div>
            <div class="h-64">
                <canvas id="slabDistributionChart"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Recent Activity -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Recent Users -->
        <div class="card-3d bg-white rounded-2xl p-6 shadow-lg">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-800">Recent Users</h3>
                <a href="#" class="text-primary-600 hover:text-primary-700 text-sm font-medium">View All</a>
            </div>
            <div class="space-y-4">
                @forelse($recentUsers as $user)
                <div class="flex items-center space-x-4 p-3 rounded-xl hover:bg-gray-50 transition-colors">
                    <div class="w-10 h-10 bg-gradient-to-br from-primary-400 to-primary-600 rounded-full flex items-center justify-center">
                        <i class="fas fa-user text-white text-sm"></i>
                    </div>
                    <div class="flex-1">
                        <p class="font-medium text-gray-800">{{ $user->name }}</p>
                        <p class="text-sm text-gray-500">{{ $user->broker_id }} • {{ $user->phone_number }}</p>
                    </div>
                    <div class="text-right">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" style="background-color: {{ $user->slab_color }}20; color: {{ $user->slab_color }};">
                            {{ $user->slab_name }}
                        </span>
                        <p class="text-xs text-gray-500 mt-1">{{ $user->created_at->diffForHumans() }}</p>
                    </div>
                </div>
                @empty
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-users text-4xl mb-4"></i>
                    <p>No users found</p>
                </div>
                @endforelse
            </div>
        </div>
        
        <!-- Latest Projects -->
        <div class="card-3d bg-white rounded-2xl p-6 shadow-lg">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-800">Latest Projects</h3>
                <a href="#" class="text-primary-600 hover:text-primary-700 text-sm font-medium">View All</a>
            </div>
            <div class="space-y-4">
                @forelse($latestProjects as $project)
                <div class="flex items-center space-x-4 p-3 rounded-xl hover:bg-gray-50 transition-colors">
                    <div class="w-12 h-12 bg-gradient-to-br from-green-400 to-green-600 rounded-xl flex items-center justify-center">
                        <i class="fas fa-building text-white"></i>
                    </div>
                    <div class="flex-1">
                        <p class="font-medium text-gray-800">{{ $project->name }}</p>
                        <p class="text-sm text-gray-500">{{ $project->location }}</p>
                    </div>
                    <div class="text-right">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                            @if($project->status === 'available') bg-green-100 text-green-800
                            @elseif($project->status === 'upcoming') bg-yellow-100 text-yellow-800
                            @else bg-red-100 text-red-800
                            @endif">
                            {{ ucfirst($project->status) }}
                        </span>
                        <p class="text-xs text-gray-500 mt-1">{{ $project->created_at->diffForHumans() }}</p>
                    </div>
                </div>
                @empty
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-building text-4xl mb-4"></i>
                    <p>No projects found</p>
                </div>
                @endforelse
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="card-3d bg-white rounded-2xl p-6 shadow-lg">
        <h3 class="text-lg font-semibold text-gray-800 mb-6">Quick Actions</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <a href="#" class="flex items-center space-x-3 p-4 rounded-xl bg-primary-50 hover:bg-primary-100 transition-colors group">
                <div class="w-10 h-10 bg-primary-500 rounded-lg flex items-center justify-center group-hover:scale-110 transition-transform">
                    <i class="fas fa-plus text-white"></i>
                </div>
                <div>
                    <p class="font-medium text-gray-800">Add New Project</p>
                    <p class="text-sm text-gray-500">Create a new real estate project</p>
                </div>
            </a>
            
            <a href="#" class="flex items-center space-x-3 p-4 rounded-xl bg-green-50 hover:bg-green-100 transition-colors group">
                <div class="w-10 h-10 bg-green-500 rounded-lg flex items-center justify-center group-hover:scale-110 transition-transform">
                    <i class="fas fa-user-plus text-white"></i>
                </div>
                <div>
                    <p class="font-medium text-gray-800">Add New User</p>
                    <p class="text-sm text-gray-500">Register a new broker</p>
                </div>
            </a>
            
            <a href="#" class="flex items-center space-x-3 p-4 rounded-xl bg-orange-50 hover:bg-orange-100 transition-colors group">
                <div class="w-10 h-10 bg-orange-500 rounded-lg flex items-center justify-center group-hover:scale-110 transition-transform">
                    <i class="fas fa-file-check text-white"></i>
                </div>
                <div>
                    <p class="font-medium text-gray-800">Review KYC</p>
                    <p class="text-sm text-gray-500">Approve pending documents</p>
                </div>
            </a>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
    // User Growth Chart
    const userGrowthEl = document.getElementById('userGrowthChart');
    if (userGrowthEl && typeof Chart !== 'undefined') {
    const userGrowthCtx = userGrowthEl.getContext('2d');
    new Chart(userGrowthCtx, {
        type: 'line',
        data: {
            labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            datasets: [{
                label: 'New Users',
                data: [12, 19, 3, 5, 2, 3, 8],
                borderColor: '#8b5cf6',
                backgroundColor: 'rgba(139, 92, 246, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#8b5cf6',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 6,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
    }
    
    // Slab Distribution Chart
    const slabDistributionEl = document.getElementById('slabDistributionChart');
    if (slabDistributionEl && typeof Chart !== 'undefined') {
    const slabDistributionCtx = slabDistributionEl.getContext('2d');
    new Chart(slabDistributionCtx, {
        type: 'doughnut',
        data: {
            labels: ['Slab1', 'Slab2', 'Slab3', 'Slab4'],
            datasets: [{
                data: [45, 25, 20, 10],
                backgroundColor: [
                    '#CD7F32',
                    '#C0C0C0',
                    '#FFD700',
                    '#8B5CF6'
                ],
                borderWidth: 0,
                cutout: '60%'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 20,
                        usePointStyle: true
                    }
                }
            }
        }
    });
    }
    
    // Add floating animation to cards
    const cards = document.querySelectorAll('.card-3d');
    cards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
    });
    });
</script>
@endpush
