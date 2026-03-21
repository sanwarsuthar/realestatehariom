@extends('admin.layouts.app')

@section('title', 'Users Graph - MLM Network')
@section('page-title', 'Users Graph - MLM Network')

@section('content')
<div class="admin-page-content space-y-6 min-w-0">
    <!-- Controls -->
    <div class="bg-white rounded-2xl shadow-3d p-6">
        <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
            <div>
                <h3 class="text-lg font-semibold text-gray-800">MLM Network Tree</h3>
                <p class="text-sm text-gray-500">Click on any user node to view their details</p>
                @if(isset($users))
                    <div id="graph-stats" class="mt-2 text-xs text-gray-600">
                        <span class="font-semibold">Total Users:</span> {{ $users->count() }}
                    </div>
                @endif
            </div>
            <div class="flex flex-wrap gap-2">
                <button onclick="zoomTree('in')" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors text-sm">
                    <i class="fas fa-search-plus mr-2"></i>Zoom In
                </button>
                <button onclick="zoomTree('out')" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors text-sm">
                    <i class="fas fa-search-minus mr-2"></i>Zoom Out
                </button>
                <button onclick="zoomTree('reset')" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors text-sm">
                    <i class="fas fa-redo mr-2"></i>Reset Zoom
                </button>
                <button onclick="toggleFullscreen()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors text-sm">
                    <i class="fas fa-expand mr-2"></i>Fullscreen
                </button>
            </div>
        </div>
    </div>

    <!-- Graph Container -->
    <div class="bg-white rounded-2xl shadow-3d p-6">
        <div id="treeContainer" style="overflow: auto; padding: 20px; background: white; border-radius: 10px; min-height: 300px; max-height: 800px; position: relative; border: 2px solid #ddd;">
            @if(isset($users) && $users->count() > 0)
                @php
                    $adminId = $adminUser ? $adminUser->id : 0;
                    // Get root users (users with no referrer or referrer is admin)
                    $rootUsers = $users->filter(function($user) use ($adminId) {
                        return !$user->referred_by_user_id || $user->referred_by_user_id == $adminId;
                    });
                    // Reset rendered users for this tree
                    \View::share('renderedUserIds', []);
                @endphp
                @if($rootUsers->count() > 0 || $adminUser)
                    <div id="mlmTree" style="display: flex; flex-direction: column; align-items: center; transform-origin: center top; transition: transform 0.3s ease;">
                        @if($adminUser)
                            @include('admin.users.partials.tree-node', ['user' => $adminUser, 'level' => 0, 'isAdmin' => true, 'allUsers' => $users])
                        @endif
                        @foreach($rootUsers as $root)
                            @include('admin.users.partials.tree-node', ['user' => $root, 'level' => 1, 'isAdmin' => false, 'allUsers' => $users])
                        @endforeach
                    </div>
                @else
                    <div class="flex items-center justify-center h-full text-gray-500 p-8">
                        <div class="text-center">
                            <i class="fas fa-users text-4xl mb-4"></i>
                            <p class="font-bold mb-2">No root users found</p>
                            <p class="text-sm">All users are connected to referrers</p>
                        </div>
                    </div>
                @endif
            @else
                <div class="flex items-center justify-center h-full text-gray-500 p-8">
                <div class="text-center">
                        <i class="fas fa-users text-4xl mb-4"></i>
                        <p class="font-bold mb-2">No users found</p>
                        <p class="text-sm">Please create users to see the MLM tree</p>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Level Color Legend -->
    <div class="bg-white rounded-2xl shadow-3d p-6">
        <h4 class="text-md font-semibold text-gray-800 mb-4">Tree Structure Info</h4>
        <div class="mt-6 p-4 bg-gradient-to-r from-primary-50 to-purple-50 rounded-lg border border-primary-200">
            <div class="flex items-start space-x-3">
                <i class="fas fa-info-circle text-primary-600 mt-1"></i>
                <div>
                    <p class="text-sm font-semibold text-gray-800 mb-2">MLM Network Structure</p>
                    <ul class="text-xs text-gray-600 space-y-1 list-disc list-inside">
                        <li><strong>Superadmin (Red):</strong> Root node - All users branch from here</li>
                        <li><strong>User Nodes:</strong> Each user shows their name, broker ID, slab, sold volume, downline count, and balance</li>
                        <li><strong>Click any user node</strong> to view their complete profile and transaction history</li>
                        <li><strong>Tree Structure:</strong> Shows the complete referral hierarchy with all levels</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    let currentZoom = 0.5;
    const zoomStep = 0.2;
    const minZoom = 0.3;
    const maxZoom = 3;

    function zoomTree(direction) {
        const tree = document.getElementById('mlmTree');
        const fullscreenTree = document.getElementById('fullscreenTree');
        
        if (direction === 'in') {
            currentZoom = Math.min(currentZoom + zoomStep, maxZoom);
        } else if (direction === 'out') {
            currentZoom = Math.max(currentZoom - zoomStep, minZoom);
        } else if (direction === 'reset') {
            currentZoom = 1;
            }
            
        if (tree) {
            tree.style.transform = `scale(${currentZoom})`;
        }
        if (fullscreenTree) {
            fullscreenTree.style.transform = `scale(${currentZoom})`;
                    }
    }

    function toggleFullscreen() {
        const modal = document.getElementById('fullscreenModal');
        if (modal && modal.style.display === 'none') {
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        } else {
            closeFullscreen();
                }
    }

    function closeFullscreen() {
        const modal = document.getElementById('fullscreenModal');
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
    }

    // Close fullscreen on ESC key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeFullscreen();
                }
            });

    // Initialize zoom for fullscreen tree
    document.addEventListener('DOMContentLoaded', function() {
        const fullscreenTree = document.getElementById('fullscreenTree');
        if (fullscreenTree) {
            fullscreenTree.style.transform = `scale(${currentZoom})`;
        }
    });
</script>

<!-- Fullscreen Modal -->
<div id="fullscreenModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.95); z-index: 9999; overflow: auto;">
    <div style="position: fixed; top: 20px; left: 20px; right: 20px; z-index: 10000; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <button onclick="zoomTree('in')" style="background: #667eea; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-weight: 600; font-size: 16px;">🔍 Zoom In</button>
            <button onclick="zoomTree('out')" style="background: #667eea; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-weight: 600; font-size: 16px;">🔍 Zoom Out</button>
            <button onclick="zoomTree('reset')" style="background: #666; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-weight: 600; font-size: 16px;">↺ Reset</button>
        </div>
        <button onclick="closeFullscreen()" style="background: #dc3545; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-weight: 600; font-size: 16px;">✕ Close Fullscreen</button>
    </div>
    <div id="fullscreenTreeContainer" style="padding: 80px 20px 20px; width: 100%; height: 100%; overflow: auto;">
        <div id="fullscreenTree" style="display: flex; flex-direction: column; align-items: center; transform-origin: center top; transition: transform 0.3s ease;">
            @if(isset($users) && $users->count() > 0)
                @php
                    $adminId = $adminUser ? $adminUser->id : 0;
                    $rootUsers = $users->filter(function($user) use ($adminId) {
                        return !$user->referred_by_user_id || $user->referred_by_user_id == $adminId;
                    });
                    // Reset rendered users for fullscreen tree
                    \View::share('renderedUserIds', []);
                @endphp
                @if($rootUsers->count() > 0 || $adminUser)
                    @if($adminUser)
                        @include('admin.users.partials.tree-node', ['user' => $adminUser, 'level' => 0, 'isAdmin' => true, 'allUsers' => $users])
                    @endif
                    @foreach($rootUsers as $root)
                        @include('admin.users.partials.tree-node', ['user' => $root, 'level' => 1, 'isAdmin' => false, 'allUsers' => $users])
                    @endforeach
                @endif
            @endif
        </div>
    </div>
</div>
@endsection
