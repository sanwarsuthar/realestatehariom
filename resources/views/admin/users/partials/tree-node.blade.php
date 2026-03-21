@php
    // Get the shared rendered users array
    $renderedUserIds = \View::shared('renderedUserIds', []);
    
    // Skip if this user was already rendered
    if (in_array($user->id, $renderedUserIds)) {
        return;
    }
    
    // Mark this user as rendered
    $renderedUserIds[] = $user->id;
    \View::share('renderedUserIds', $renderedUserIds);
    
    // Get children of this user (users who were referred by this user)
    // Filter out already rendered users to prevent duplicates
    $children = $allUsers->filter(function($u) use ($user) {
        $rendered = \View::shared('renderedUserIds', []);
        return $u->referred_by_user_id == $user->id && !in_array($u->id, $rendered);
    });
    
    $slab = $user->slab ?? null;
    $balance = $user->wallet ? $user->wallet->balance : 0;
    $totalDownline = $user->total_downline_count ?? 0;
    
    // Calculate sold volume if available
    $soldVolume = $user->sold_volume ?? 0;
    
    // Parent (sponsor) and sales volume for display inside box
    $parent = $user->referredBy;
    $parentName = $parent ? $parent->name : '—';
    $parentId = $user->referred_by_user_id ?? '—';
    $ownVolumeByUserId = \View::shared('ownVolumeByUserId', []);
    $teamVolumeByUserId = \View::shared('teamVolumeByUserId', []);
    $ownVolume = $ownVolumeByUserId[$user->id] ?? 0;
    $teamVolume = $teamVolumeByUserId[$user->id] ?? 0;
@endphp

<div class="tree-node-wrapper" style="display: flex; flex-direction: column; align-items: center; margin: 10px; position: relative;">
    <div class="node-box" style="background: {{ $isAdmin ? 'linear-gradient(135deg, #dc2626 0%, #b91c1c 100%)' : 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)' }}; color: white; padding: 15px 20px; border-radius: 10px; min-width: 200px; text-align: center; box-shadow: 0 4px 10px rgba(0,0,0,0.2); position: relative; z-index: 2; cursor: pointer;" onclick="window.location.href='/admin/users/{{ $user->id }}'">
        <div style="font-weight: bold; font-size: 16px; margin-bottom: 5px;">{{ $user->name }}</div>
        <div style="font-size: 12px; opacity: 0.9;">{{ $isAdmin ? 'Superadmin' : ($user->broker_id ?? 'ID: ' . $user->id) }}</div>
        @if(!$isAdmin)
            <div style="font-size: 11px; margin-top: 5px;">
                @if($slab)
                    <span class="slab-badge" style="background: rgba(255,255,255,0.3); padding: 3px 8px; border-radius: 12px; font-size: 10px;">
                        {{ $slab->name }}
                    </span>
                @else
                    <span style="font-size: 10px; opacity: 0.8;">No Slab</span>
                @endif
            </div>
            @if($soldVolume > 0)
            <div style="font-size: 11px; margin-top: 5px;">
                Sold: {{ number_format($soldVolume, 1) }} sq yds
            </div>
            @endif
            @if($totalDownline > 0)
            <div style="font-size: 10px; margin-top: 3px; color: #4caf50; font-weight: 600;">
                Downline: {{ $totalDownline }}
            </div>
            @endif
            @if($balance > 0)
            <div style="font-size: 10px; margin-top: 3px; opacity: 0.8;">
                Balance: ₹{{ number_format($balance, 0) }}
            </div>
            @endif
            {{-- Parent, Parent ID, Own sale volume, Team sale volume — inside box --}}
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 6px; margin-top: 10px; text-align: center;">
                <div style="background: rgba(255,255,255,0.2); border-radius: 6px; padding: 5px 6px;">
                    <div style="font-size: 9px; opacity: 0.9;">Parent</div>
                    <div style="font-size: 11px; font-weight: 500;">{{ $parentName }}</div>
                </div>
                <div style="background: rgba(255,255,255,0.2); border-radius: 6px; padding: 5px 6px;">
                    <div style="font-size: 9px; opacity: 0.9;">Parent ID</div>
                    <div style="font-size: 11px; font-weight: 500;">{{ $parentId }}</div>
                </div>
                <div style="background: rgba(255,255,255,0.2); border-radius: 6px; padding: 5px 6px;">
                    <div style="font-size: 9px; opacity: 0.9;">Own Sale</div>
                    <div style="font-size: 11px; font-weight: 500;">{{ number_format($ownVolume, 1) }} <span style="font-size: 10px;">sq yd</span></div>
                </div>
                <div style="background: rgba(255,255,255,0.2); border-radius: 6px; padding: 5px 6px;">
                    <div style="font-size: 9px; opacity: 0.9;">Team Sale</div>
                    <div style="font-size: 11px; font-weight: 500;">{{ number_format($teamVolume, 1) }} <span style="font-size: 10px;">sq yd</span></div>
                </div>
            </div>
        @endif
    </div>
    
    @if($children->count() > 0)
        <div class="children-container" style="display: flex; flex-direction: row; justify-content: center; margin-top: 30px; position: relative; flex-wrap: wrap;">
            @if($level > 0)
                <div style="position: absolute; top: -30px; left: 50%; width: 2px; height: 30px; background: {{ $isAdmin ? '#dc2626' : '#667eea' }}; transform: translateX(-50%);"></div>
            @endif
            
            @foreach($children as $index => $child)
                <div style="position: relative; margin: 0 10px;">
                    @if($index > 0)
                        <div style="position: absolute; top: -30px; left: -10px; width: 20px; height: 2px; background: {{ $isAdmin ? '#dc2626' : '#667eea' }};"></div>
                    @endif
                    @include('admin.users.partials.tree-node', ['user' => $child, 'level' => $level + 1, 'isAdmin' => false, 'allUsers' => $allUsers])
                </div>
            @endforeach
        </div>
    @endif
</div>

<style>
.tree-node-wrapper {
    margin: 10px;
}
.node-box:hover {
    transform: scale(1.05);
    transition: transform 0.2s;
}
</style>

