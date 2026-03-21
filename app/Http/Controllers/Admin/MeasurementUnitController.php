<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MeasurementUnit;
use Illuminate\Http\Request;

class MeasurementUnitController extends Controller
{
    public function index()
    {
        $units = MeasurementUnit::orderBy('name')->get();
        return view('admin.measurement-units.index', compact('units'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100|unique:measurement_units,name',
            'symbol' => 'nullable|string|max:10',
            'description' => 'nullable|string|max:500',
        ]);

        MeasurementUnit::create($data);

        return back()->with('success', 'Measurement unit created successfully.');
    }

    public function update(Request $request, MeasurementUnit $measurementUnit)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100|unique:measurement_units,name,' . $measurementUnit->id,
            'symbol' => 'nullable|string|max:10',
            'description' => 'nullable|string|max:500',
        ]);

        $measurementUnit->update($data);

        return back()->with('success', 'Measurement unit updated successfully.');
    }

    public function destroy(MeasurementUnit $measurementUnit)
    {
        // Check if measurement unit is being used by slabs
        if ($measurementUnit->slabs()->count() > 0) {
            return back()->with('error', 'Cannot delete this measurement unit because it is being used by slabs.');
        }

        // Check if measurement unit is being used by property types
        if ($measurementUnit->propertyTypes()->count() > 0) {
            return back()->with('error', 'Cannot delete this measurement unit because it is being used by property types.');
        }

        $measurementUnit->delete();

        return back()->with('success', 'Measurement unit deleted successfully.');
    }
}

