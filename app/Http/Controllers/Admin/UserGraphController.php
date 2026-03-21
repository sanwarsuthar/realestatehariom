<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Sale;
use Illuminate\Http\Request;

class UserGraphController extends Controller
{
    /**
     * Show the MLM network graph
     */
    public function index()
    {
        // Get admin user
        $adminUser = User::where('user_type', 'admin')->first();
        
        // Get all broker users with their relationships and parent
        $users = User::where('user_type', 'broker')
            ->with(['slab', 'wallet', 'referredBy'])
            ->get();
        
        // Precompute sold volume per user (sum of plot size for confirmed sales)
        $volumeByUser = Sale::where('sales.status', 'confirmed')
            ->join('plots', 'sales.plot_id', '=', 'plots.id')
            ->selectRaw('sales.sold_by_user_id, coalesce(sum(plots.size), 0) as volume')
            ->groupBy('sales.sold_by_user_id')
            ->pluck('volume', 'sold_by_user_id');
        
        $ownVolumeByUserId = [];
        $teamVolumeByUserId = [];
        foreach ($users as $user) {
            $ownVolumeByUserId[$user->id] = (float)($volumeByUser[$user->id] ?? 0);
            $downlineIds = $user->getDownlineUserIds();
            $teamVolumeByUserId[$user->id] = (float)$volumeByUser->only($downlineIds)->sum();
        }
        \View::share('ownVolumeByUserId', $ownVolumeByUserId);
        \View::share('teamVolumeByUserId', $teamVolumeByUserId);
        
        // Get root users (users with no referrer or referrer is admin)
        $adminId = $adminUser ? $adminUser->id : 0;
        $rootUsers = $users->filter(function($user) use ($adminId) {
            return !$user->referred_by_user_id || $user->referred_by_user_id == $adminId;
        });
        
        // Share a global array to track rendered users (prevents duplicates in recursive rendering)
        \View::share('renderedUserIds', []);
        
        return view('admin.users.graph', compact('users', 'rootUsers', 'adminUser'));
    }

    /**
     * Get tree data for the graph - Complete MLM structure with unlimited levels
     */
    public function getTreeData(Request $request)
    {
        try {
            // Get admin ID first (cache it)
            $adminUser = User::where('user_type', 'admin')->first();
            $adminId = $adminUser ? $adminUser->id : 0;
            
            if ($adminId == 0) {
                return response()->json([
                    'error' => 'Admin user not found'
                ], 404);
            }

            // Limit levels and nodes for performance (max 10 levels, max 1000 nodes)
            $maxLevel = $request->input('max_level', 10);
            $maxNodes = $request->input('max_nodes', 1000);
            
            // Get all brokers with their relationships
            $users = User::where('user_type', 'broker')
                ->with(['slab', 'wallet'])
                ->get();

            // Build tree structure
            $nodes = [];
            $edges = [];
            $userMap = [];
            $levelCache = []; // Cache levels to avoid recalculating

        // Create Superadmin node at the top
        $nodes[] = [
            'id' => $adminId,
            'label' => 'Superadmin',
            'title' => 'Superadmin\nRoot Node\nAll Users Branch From Here',
            'color' => [
                'background' => '#dc2626', // Red color for superadmin
                'border' => '#dc2626',
                'highlight' => [
                    'background' => '#ef4444',
                    'border' => '#000000'
                ]
            ],
            'font' => ['color' => '#ffffff', 'size' => 20, 'bold' => true],
            'shape' => 'circle',
            'size' => 40,
            'level' => 0,
        ];

        // Define colors for each level (0 = Superadmin, 1-10 = User levels)
        $levelColors = [
            0 => '#dc2626', // Red - Superadmin
            1 => '#2563eb', // Blue - Level 1
            2 => '#16a34a', // Green - Level 2
            3 => '#ea580c', // Orange - Level 3
            4 => '#9333ea', // Purple - Level 4
            5 => '#0891b2', // Cyan - Level 5
            6 => '#e11d48', // Rose - Level 6
            7 => '#ca8a04', // Yellow/Amber - Level 7
            8 => '#be123c', // Pink - Level 8
            9 => '#059669', // Emerald - Level 9
            10 => '#7c3aed', // Indigo - Level 10
        ];

        // Pre-calculate all levels efficiently
        $this->calculateAllLevels($users, $adminId, $levelCache);

        // Filter users by max level and limit nodes
        $filteredUsers = [];
        $nodeCount = 0;
        
        // First, add admin node (counts as 1)
        $nodeCount++;
        
        // Add users up to max level and max nodes
        foreach ($users as $user) {
            $level = $levelCache[$user->id] ?? 1;
            
            // Only include users within the level limit and node limit
            if ($level > 0 && $level <= $maxLevel && $nodeCount < $maxNodes) {
                $filteredUsers[] = $user;
                $nodeCount++;
            }
        }
        
        // Log for debugging
        \Log::info('Graph data preparation', [
            'total_users' => $users->count(),
            'filtered_users' => count($filteredUsers),
            'max_level' => $maxLevel,
            'max_nodes' => $maxNodes,
            'levels_distribution' => array_count_values(array_values($levelCache))
        ]);

        // Build user map for quick lookup (only filtered users)
        foreach ($filteredUsers as $user) {
            $balance = $user->wallet ? $user->wallet->balance : 0;
            $slabName = $user->slab ? $user->slab->name : 'Slab1';
            
            // Get level from cache
            $level = $levelCache[$user->id] ?? 1;
            
            // Get color based on level (cap at 10 for color array)
            $levelKey = min($level, 10);
            $nodeColor = $levelColors[$levelKey] ?? '#8b5cf6';
            
            $label = $user->name . "\n" . ($user->broker_id ?? 'N/A');
            
            $nodes[] = [
                'id' => $user->id,
                'label' => $label,
                'title' => sprintf(
                    "%s\nBroker ID: %s\nReferral Code: %s\nSlab: %s\nBalance: ₹%s\nDownline: %d\nLevel: %d",
                    $user->name,
                    $user->broker_id ?? 'N/A',
                    $user->referral_code ?? 'N/A',
                    $slabName,
                    number_format($balance, 2),
                    $user->total_downline_count ?? 0,
                    $level
                ),
                'color' => [
                    'background' => $nodeColor,
                    'border' => $nodeColor,
                    'highlight' => [
                        'background' => $nodeColor,
                        'border' => '#000000'
                    ]
                ],
                'font' => ['color' => '#ffffff', 'size' => 13],
                'shape' => 'circle',
                'size' => 30,
                'level' => $level,
                'data' => [
                    'broker_id' => $user->broker_id,
                    'referral_code' => $user->referral_code,
                    'slab' => $slabName,
                    'balance' => $balance,
                    'downline_count' => $user->total_downline_count ?? 0,
                ]
            ];

            $userMap[$user->id] = $user;
        }

        // Build edges - connect each user to their referrer (only for filtered users)
        foreach ($filteredUsers as $user) {
            $referrerId = null;
            
            if ($user->referred_by_user_id) {
                // Check if referrer is admin
                if ($user->referred_by_user_id == $adminId) {
                    $referrerId = $adminId;
                } else {
                    // Check if referrer exists in our filtered user list
                    foreach ($filteredUsers as $filteredUser) {
                        if ($filteredUser->id == $user->referred_by_user_id) {
                            $referrerId = $filteredUser->id;
                            break;
                        }
                    }
                    
                    // If referrer not in filtered list, connect to admin
                    if (!$referrerId) {
                        $referrerId = $adminId;
                    }
                }
            } else {
                // No referrer - connect directly to Superadmin
                $referrerId = $adminId;
            }
            
            // Create edge
            $edges[] = [
                'from' => $referrerId,
                'to' => $user->id,
                'arrows' => 'to',
                'color' => ['color' => '#8b5cf6', 'highlight' => '#6d28d9'],
                'width' => 2,
                'smooth' => [
                    'type' => 'cubicBezier',
                    'forceDirection' => 'vertical',
                    'roundness' => 0.5
                ]
            ];
        }

            $totalUsers = $users->count();
            $displayedUsers = count($filteredUsers);
            
            return response()->json([
                'nodes' => $nodes,
                'edges' => $edges,
                'stats' => [
                    'total_users' => $totalUsers,
                    'displayed_users' => $displayedUsers,
                    'max_level' => $maxLevel,
                    'max_nodes' => $maxNodes,
                    'has_more' => $totalUsers > $displayedUsers || $maxLevel < 15
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Error generating graph data: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Failed to generate graph data',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Efficiently calculate all user levels using BFS approach
     */
    private function calculateAllLevels($users, $adminId, &$levelCache)
    {
        // Initialize all users with a default level
        foreach ($users as $user) {
            $levelCache[$user->id] = 999; // High default to identify unprocessed
        }
        
        // BFS: Start from admin and calculate levels
        $queue = [];
        $visited = [];
        
        // Find all direct children of admin (Level 1)
        foreach ($users as $user) {
            if ($user->referred_by_user_id == $adminId || (!$user->referred_by_user_id)) {
                $levelCache[$user->id] = 1;
                $queue[] = ['user' => $user, 'level' => 1];
                $visited[$user->id] = true;
            }
        }
        
        // Process queue using BFS
        while (!empty($queue)) {
            $current = array_shift($queue);
            $currentUser = $current['user'];
            $currentLevel = $current['level'];
            
            // Find all children of current user
            foreach ($users as $child) {
                if ($child->referred_by_user_id == $currentUser->id && !isset($visited[$child->id])) {
                    $newLevel = $currentLevel + 1;
                    $levelCache[$child->id] = $newLevel;
                    $queue[] = ['user' => $child, 'level' => $newLevel];
                    $visited[$child->id] = true;
                }
            }
        }
        
        // For any remaining users (orphaned or circular references), calculate individually
        foreach ($users as $user) {
            if (!isset($visited[$user->id]) || $levelCache[$user->id] == 999) {
                $calculatedLevel = $this->calculateUserLevel($user, $users, $adminId);
                $levelCache[$user->id] = $calculatedLevel;
            }
        }
    }

    /**
     * Calculate the level/depth of a user in the MLM tree (fallback method)
     */
    private function calculateUserLevel($user, $allUsers, $adminId)
    {
        $level = 1;
        $currentUser = $user;
        $visited = [];
        
        // Traverse up the referral chain until we reach admin
        while ($currentUser && $currentUser->referred_by_user_id && !isset($visited[$currentUser->id])) {
            $visited[$currentUser->id] = true;
            
            // Check if referred by admin
            if ($currentUser->referred_by_user_id == $adminId) {
                return $level;
            }
            
            // Find referrer in user list
            $referrer = $allUsers->where('id', $currentUser->referred_by_user_id)->first();
            
            if ($referrer) {
                $level++;
                $currentUser = $referrer;
            } else {
                return $level;
            }
            
            // Safety check to prevent infinite loops
            if ($level > 100) {
                break;
            }
        }
        
        return $level;
    }
}

