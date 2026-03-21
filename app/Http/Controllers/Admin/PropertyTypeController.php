<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MeasurementUnit;
use App\Models\PropertyType;
use Illuminate\Http\Request;

class PropertyTypeController extends Controller
{
    public function index()
    {
        $propertyTypes = PropertyType::with('measurementUnit')
            ->orderBy('name')
            ->get();

        $units = MeasurementUnit::orderBy('name')->get();

        return view('admin.property-types.index', compact('propertyTypes', 'units'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100|unique:property_types,name',
            'measurement_unit_id' => 'nullable|exists:measurement_units,id',
            'description' => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean',
        ]);

        $data['is_active'] = $request->boolean('is_active', true);

        PropertyType::create($data);

        return back()->with('success', 'Property type created successfully.');
    }

    public function destroy(PropertyType $propertyType)
    {
        $propertyType->delete();

        return back()->with('success', 'Property type deleted successfully.');
    }
}

