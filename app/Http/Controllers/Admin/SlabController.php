<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MeasurementUnit;
use App\Models\PropertyType;
use App\Models\Slab;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SlabController extends Controller
{
    public function index()
    {
        $slabs = Slab::with(['measurementUnit', 'propertyTypes'])
            ->orderBy('minimum_target')
            ->get();

        $units = MeasurementUnit::orderBy('name')->get();
        $propertyTypes = PropertyType::where('is_active', true)->orderBy('name')->get();

        return view('admin.slabs.index', compact('slabs', 'units', 'propertyTypes'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100|unique:slabs,name',
            'minimum_target' => 'required|numeric|min:0',
            'maximum_target' => 'nullable|numeric|gte:minimum_target',
            'bonus_percentage' => 'nullable|numeric|min:0|max:100',
            'measurement_unit_id' => 'required|exists:measurement_units,id',
            'property_types' => 'required|array|min:1',
            'property_types.*' => 'exists:property_types,id',
            'description' => 'nullable|string|max:500',
            'color_code' => 'nullable|string|max:7',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $data['color_code'] = $data['color_code'] ?? '#8B5CF6';
        $data['bonus_percentage'] = $data['bonus_percentage'] ?? 0;
        $data['sort_order'] = $data['sort_order'] ?? 0;
        // Set a default commission_ratio for backward compatibility (not used in calculations)
        $data['commission_ratio'] = 0; // Not used - commissions are managed in Settings

        $slab = Slab::create($data);
        
        // Attach property types to slab
        $slab->propertyTypes()->sync($request->property_types);

        // Check if this is an initial slab (lowest sort_order) for any property type
        // If so, assign it to all existing users
        $this->assignInitialSlabToAllUsers($slab);

        return back()->with('success', 'Slab created successfully.');
    }

    public function edit(Slab $slab)
    {
        $slab->load('propertyTypes', 'measurementUnit');
        $units = MeasurementUnit::orderBy('name')->get();
        $propertyTypes = PropertyType::where('is_active', true)->orderBy('name')->get();
        
        return view('admin.slabs.edit', compact('slab', 'units', 'propertyTypes'));
    }

    public function update(Request $request, Slab $slab)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100|unique:slabs,name,' . $slab->id,
            'minimum_target' => 'required|numeric|min:0',
            'maximum_target' => 'nullable|numeric|gte:minimum_target',
            'bonus_percentage' => 'nullable|numeric|min:0|max:100',
            'measurement_unit_id' => 'required|exists:measurement_units,id',
            'property_types' => 'required|array|min:1',
            'property_types.*' => 'exists:property_types,id',
            'description' => 'nullable|string|max:500',
            'color_code' => 'nullable|string|max:7',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $data['color_code'] = $data['color_code'] ?? '#8B5CF6';
        $data['bonus_percentage'] = $data['bonus_percentage'] ?? 0;
        $data['sort_order'] = $data['sort_order'] ?? 0;
        // Set a default commission_ratio for backward compatibility (not used in calculations)
        $data['commission_ratio'] = $slab->commission_ratio ?? 0; // Keep existing or default to 0

        $slab->update($data);
        
        // Sync property types
        $slab->propertyTypes()->sync($request->property_types);

        return redirect()->route('admin.slabs.index')->with('success', 'Slab updated successfully.');
    }

    public function destroy(Slab $slab)
    {
        $slab->delete();

        return back()->with('success', 'Slab deleted successfully.');
    }

    /**
     * Check if a slab is an initial slab for any property type and assign it to all users
     */
    private function assignInitialSlabToAllUsers(Slab $newSlab): void
    {
        // Get all property types this slab belongs to
        $propertyTypes = $newSlab->propertyTypes;
        
        foreach ($propertyTypes as $propertyType) {
            // Check if this is the initial slab (lowest sort_order) for this property type
            $lowestSortOrder = Slab::where('is_active', true)
                ->whereHas('propertyTypes', function($query) use ($propertyType) {
                    $query->where('property_types.id', $propertyType->id);
                })
                ->min('sort_order');
            
            // If this slab has the lowest sort_order, it's an initial slab
            if ($newSlab->sort_order == $lowestSortOrder) {
                // Get all broker users
                $users = User::where('user_type', 'broker')->get();
                
                $assignedCount = 0;
                foreach ($users as $user) {
                    // Check if user already has a slab for this property type
                    $existing = DB::table('user_slabs')
                        ->where('user_id', $user->id)
                        ->where('property_type_id', $propertyType->id)
                        ->first();
                    
                    if (!$existing) {
                        // Assign this initial slab to the user
                        DB::table('user_slabs')->insert([
                            'user_id' => $user->id,
                            'property_type_id' => $propertyType->id,
                            'slab_id' => $newSlab->id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        $assignedCount++;
                    }
                }
                
                if ($assignedCount > 0) {
                    \Log::info("Assigned initial slab '{$newSlab->name}' for property type '{$propertyType->name}' to {$assignedCount} users");
                }
            }
        }
    }
}

