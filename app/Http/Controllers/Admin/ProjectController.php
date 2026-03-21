<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Plot;
use App\Models\PropertyType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $query = Project::query();

        // Show only non-deleted projects by default
        if (!$request->has('show_deleted')) {
            $query->whereNull('deleted_at');
        } elseif ($request->show_deleted == 'only') {
            // Show only deleted projects
            $query->whereNotNull('deleted_at');
        }
        // If show_deleted=all, show both (no filter)

        // Apply filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('city')) {
            $query->where('city', 'like', '%' . $request->city . '%');
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('location', 'like', '%' . $search . '%')
                  ->orWhere('description', 'like', '%' . $search . '%');
            });
        }

        $projects = $query->withCount('plots')->orderBy('created_at', 'desc')->paginate(10);

        return view('admin.projects.index', compact('projects'));
    }

    public function show(Project $project)
    {
        $project->load(['plots' => function($query) {
            $query->orderBy('grid_batch_id')->orderBy('plot_number')
                  ->with(['sales' => function($q) {
                      $q->with('customer')->orderBy('created_at', 'desc');
                  }]);
        }]);

        $propertyTypes = PropertyType::where('is_active', true)
            ->with('measurementUnit')
            ->orderBy('name')
            ->get();

        // Group plots by grid_batch_id
        $gridBatches = $project->plots->groupBy('grid_batch_id')->map(function($plots, $batchId) {
            return [
                'batch_id' => $batchId,
                'batch_name' => $plots->first()->grid_batch_name ?? 'Unnamed Grid',
                'plots' => $plots,
                'count' => $plots->count(),
            ];
        })->values();

        return view('admin.projects.show', compact('project', 'propertyTypes', 'gridBatches'));
    }

    public function view(Project $project)
    {
        // Load project with all relationships
        $project->load([
            'plots' => function($query) {
                $query->orderBy('grid_batch_id')->orderBy('plot_number');
            },
            'sales' => function($query) {
                $query->with(['plot', 'soldByUser', 'customer'])->orderBy('created_at', 'desc');
            }
        ]);

        // Get booked properties (plots with status booked/sold or with sales)
        $bookedProperties = $project->plots()
            ->whereIn('status', ['booked', 'sold'])
            ->with(['sales' => function($query) {
                $query->with(['soldByUser', 'customer'])->orderBy('created_at', 'desc');
            }])
            ->orderBy('grid_batch_id')
            ->orderBy('plot_number')
            ->get();

        // Get unique booked users (users who have booked properties in this project)
        $bookedUserIds = $project->sales()->pluck('customer_id')->unique()->filter();
        $bookedUsers = \App\Models\User::whereIn('id', $bookedUserIds)
            ->with(['customerSales.plot'])
            ->get()
            ->map(function($user) use ($project) {
                // Filter sales to only include those from this project
                $user->project_sales = $user->customerSales->filter(function($sale) use ($project) {
                    return $sale->plot && $sale->plot->project_id == $project->id;
                });
                return $user;
            });

        // Statistics
        $totalPlots = $project->plots()->count();
        $availablePlots = $project->plots()->where('status', 'available')->count();
        $bookedPlots = $project->plots()->where('status', 'booked')->count();
        $soldPlots = $project->plots()->where('status', 'sold')->count();
        $totalSales = $project->sales()->count();
        $totalRevenue = $project->sales()->sum('sale_price');
        $totalBookingAmount = $project->sales()->sum('booking_amount');
        $totalCommission = $project->sales()->sum('commission_amount');

        // Group booked properties by grid batch
        $bookedPropertiesByBatch = $bookedProperties->groupBy('grid_batch_id')->map(function($plots, $batchId) {
            return [
                'batch_id' => $batchId,
                'batch_name' => $plots->first()->grid_batch_name ?? 'Unnamed Grid',
                'plots' => $plots,
                'count' => $plots->count(),
            ];
        })->values();

        return view('admin.projects.view', compact(
            'project',
            'bookedProperties',
            'bookedPropertiesByBatch',
            'bookedUsers',
            'totalPlots',
            'availablePlots',
            'bookedPlots',
            'soldPlots',
            'totalSales',
            'totalRevenue',
            'totalBookingAmount',
            'totalCommission'
        ));
    }

    public function create()
    {
        $propertyTypes = PropertyType::where('is_active', true)
            ->with('measurementUnit')
            ->orderBy('name')
            ->get();
        
        return view('admin.projects.create', compact('propertyTypes'));
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'required|string',
                'location' => 'required|string|max:500',
                'pincode' => 'required|string|max:10',
                'type' => 'required|in:residential,commercial,mixed',
                'status' => 'required|in:available,upcoming,sold_out',
                'minimum_booking_amount' => 'required|numeric|min:0',
                'allocated_amount' => 'nullable|numeric|min:0',
                'images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:10240', // 10MB max per image
                'floor_plan_pdf' => 'nullable|file|mimes:pdf|max:20480', // 20MB max for PDF
            ], [
                'images.*.image' => 'The file must be an image.',
                'images.*.mimes' => 'The image must be a file of type: jpeg, png, jpg, gif, webp.',
                'images.*.max' => 'The image may not be greater than 10MB. Please compress or resize your image.',
                'images.0.image' => 'The first image must be a valid image file.',
                'images.0.mimes' => 'The first image must be a file of type: jpeg, png, jpg, gif, webp.',
                'images.0.max' => 'The first image may not be greater than 10MB. Please compress or resize your image.',
                'floor_plan_pdf.max' => 'The PDF file may not be greater than 20MB. Please compress your PDF file.',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $errors = $e->errors();
            $fileInfo = [];
            
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $index => $file) {
                    $fileInfo[$index] = [
                        'name' => $file->getClientOriginalName(),
                        'size' => $file->getSize(),
                        'size_mb' => round($file->getSize() / 1024 / 1024, 2),
                        'mime' => $file->getMimeType(),
                        'isValid' => $file->isValid(),
                        'error' => $file->getError(),
                        'error_message' => $file->getErrorMessage(),
                    ];
                    
                    // Add helpful error messages for common issues
                    if (!$file->isValid()) {
                        $errorCode = $file->getError();
                        $errorMessages = [
                            UPLOAD_ERR_INI_SIZE => 'File exceeds PHP upload_max_filesize (' . ini_get('upload_max_filesize') . ')',
                            UPLOAD_ERR_FORM_SIZE => 'File exceeds form MAX_FILE_SIZE limit',
                            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                            UPLOAD_ERR_EXTENSION => 'PHP extension stopped the file upload',
                        ];
                        $fileInfo[$index]['detailed_error'] = $errorMessages[$errorCode] ?? 'Unknown upload error';
                    }
                    
                    // Check file size
                    if ($file->getSize() > 10240 * 1024) {
                        $fileInfo[$index]['size_error'] = 'File size (' . round($file->getSize() / 1024 / 1024, 2) . ' MB) exceeds 10MB limit';
                    }
                }
            }
            
            \Log::error('Project creation validation error', [
                'errors' => $errors,
                'request_files' => $fileInfo,
                'php_upload_max_filesize' => ini_get('upload_max_filesize'),
                'php_post_max_size' => ini_get('post_max_size'),
                'php_max_file_uploads' => ini_get('max_file_uploads'),
                'has_images' => $request->hasFile('images'),
                'images_count' => $request->hasFile('images') ? count($request->file('images')) : 0,
            ]);
            
            // Return JSON for AJAX requests
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $errors,
                    'file_info' => $fileInfo,
                ], 422);
            }
            
            return redirect()->back()
                ->withInput()
                ->withErrors($errors);
        }

        // Only get validated and safe fields - don't use $request->all() to prevent unwanted fields
        $data = [
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'location' => $request->input('location'),
            'pincode' => $request->input('pincode'),
            'type' => $request->input('type'),
            'status' => $request->input('status'),
            'latitude' => $request->input('latitude'),
            'longitude' => $request->input('longitude'),
            'minimum_booking_amount' => $request->input('minimum_booking_amount'),
            'allocated_amount' => $request->input('allocated_amount') ?? 0,
            'allocated_amount_config' => $this->normalizeAllocatedAmountConfig($request->input('allocated_amount_config', [])),
            'price_per_sqft' => $request->input('price_per_sqft'),
            'plot_size' => $request->input('plot_size'),
            'commission_per_slab' => $request->input('commission_per_slab'),
        ];
        
        // Parse location to extract city and state (for backward compatibility)
        $locationParts = array_map('trim', explode(',', $data['location']));
        $data['city'] = '';
        $data['state'] = '';
        
        // Try to find city and state from location string
        if (count($locationParts) >= 2) {
            $lastPart = trim(end($locationParts));
            $indianStates = ['Rajasthan', 'Maharashtra', 'Gujarat', 'Karnataka', 'Tamil Nadu', 'Delhi', 'Punjab', 'Haryana', 'Uttar Pradesh', 'Madhya Pradesh', 'West Bengal', 'Bihar', 'Odisha', 'Andhra Pradesh', 'Telangana', 'Kerala', 'Assam', 'Jharkhand', 'Chhattisgarh', 'Himachal Pradesh', 'Uttarakhand', 'Goa', 'Manipur', 'Meghalaya', 'Mizoram', 'Nagaland', 'Tripura', 'Sikkim', 'Arunachal Pradesh'];
            if (in_array($lastPart, $indianStates)) {
                $data['state'] = $lastPart;
                if (count($locationParts) >= 3) {
                    $data['city'] = trim($locationParts[count($locationParts) - 2]);
                }
            } else {
                $data['city'] = $lastPart;
            }
        }
        
        // Set defaults if not extracted
        if (empty($data['city'])) {
            $data['city'] = 'Jaipur';
        }
        if (empty($data['state'])) {
            $data['state'] = 'Rajasthan';
        }
        
        // Handle image uploads
        $imageUrls = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $index => $image) {
                if (!$image->isValid()) {
                    $uploadError = $image->getError();
                    $errorMessage = $image->getErrorMessage();
                    
                    // Check for PHP upload errors
                    $phpUploadErrors = [
                        UPLOAD_ERR_INI_SIZE => 'The image exceeds PHP upload_max_filesize limit (currently ' . ini_get('upload_max_filesize') . '). Please reduce image size or increase PHP upload limit.',
                        UPLOAD_ERR_FORM_SIZE => 'The image exceeds the form MAX_FILE_SIZE limit.',
                        UPLOAD_ERR_PARTIAL => 'The image was only partially uploaded.',
                        UPLOAD_ERR_NO_FILE => 'No image was uploaded.',
                        UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
                        UPLOAD_ERR_CANT_WRITE => 'Failed to write image to disk.',
                        UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the image upload.',
                    ];
                    
                    $customError = $phpUploadErrors[$uploadError] ?? $errorMessage;
                    
                    \Log::error('Invalid image file', [
                        'index' => $index,
                        'error_code' => $uploadError,
                        'error_message' => $customError,
                        'name' => $image->getClientOriginalName(),
                        'size' => $image->getSize(),
                        'mime' => $image->getMimeType(),
                        'php_upload_max_filesize' => ini_get('upload_max_filesize'),
                        'php_post_max_size' => ini_get('post_max_size'),
                    ]);
                    
                    // For AJAX requests, return specific error
                    if ($request->ajax() || $request->wantsJson()) {
                        return response()->json([
                            'success' => false,
                            'message' => "Image upload failed: {$customError}",
                            'errors' => ['images.' . $index => [$customError]]
                        ], 422);
                    }
                    
                    continue; // Skip invalid files
                }
                
                try {
                    // Store image in storage/app/public/projects
                    $imagePath = $image->store('projects', 'public');
                    if (!$imagePath) {
                        throw new \Exception('Failed to store image file');
                    }
                    // Store relative path (/storage/...) instead of full URL
                    // This allows the API to construct correct URLs for both local and production
                    $imageUrls[] = Storage::url($imagePath); // Returns /storage/projects/filename.jpg
                } catch (\Exception $e) {
                    \Log::error('Failed to store image', [
                        'index' => $index,
                        'error' => $e->getMessage(),
                        'name' => $image->getClientOriginalName(),
                    ]);
                    // Continue with other images even if one fails
                }
            }
        }
        $data['images'] = $imageUrls;
        
        // Handle floor plan PDF upload
        if ($request->hasFile('floor_plan_pdf')) {
            $pdfFile = $request->file('floor_plan_pdf');
            if ($pdfFile->isValid()) {
                $pdfPath = $pdfFile->store('projects/floor-plans', 'public');
                // Store relative path instead of full URL (like images)
                $data['floor_plan_pdf'] = '/storage/' . str_replace('public/', '', $pdfPath);
            }
        }
        
        // Process facilities and videos (convert comma-separated strings to arrays)
        if ($request->has('facilities')) {
            $facilities = $request->input('facilities');
            if (is_string($facilities) && !empty($facilities)) {
                $data['facilities'] = array_map('trim', explode(',', $facilities));
            } elseif (is_array($facilities)) {
                $data['facilities'] = $facilities;
            } else {
                $data['facilities'] = [];
            }
        } else {
            $data['facilities'] = [];
        }
        
        if ($request->has('videos')) {
            $videos = $request->input('videos');
            if (is_string($videos) && !empty($videos)) {
                $data['videos'] = array_map('trim', explode(',', $videos));
            } elseif (is_array($videos)) {
                $data['videos'] = $videos;
            } else {
                $data['videos'] = [];
            }
        } else {
            $data['videos'] = [];
        }
        
        // Set default values for nullable fields
        $data['is_active'] = true;
        
        // Ensure nullable fields are set properly
        if (!isset($data['latitude']) || $data['latitude'] === '') {
            $data['latitude'] = null;
        }
        if (!isset($data['longitude']) || $data['longitude'] === '') {
            $data['longitude'] = null;
        }
        if (!isset($data['minimum_booking_amount']) || $data['minimum_booking_amount'] === '') {
            $data['minimum_booking_amount'] = 0;
        }
        if (!isset($data['price_per_sqft']) || $data['price_per_sqft'] === '') {
            $data['price_per_sqft'] = null;
        }
        if (!isset($data['plot_size']) || $data['plot_size'] === '') {
            $data['plot_size'] = null;
        }
        
        // Handle commission_per_slab - ensure it's an array or null
        if (isset($data['commission_per_slab'])) {
            if (is_string($data['commission_per_slab']) && !empty($data['commission_per_slab'])) {
                // Try to decode if it's JSON string
                $decoded = json_decode($data['commission_per_slab'], true);
                $data['commission_per_slab'] = $decoded !== null ? $decoded : [];
            } elseif (!is_array($data['commission_per_slab'])) {
                $data['commission_per_slab'] = null;
            }
        } else {
            $data['commission_per_slab'] = null;
        }

        try {
            // Ensure arrays are properly set (empty arrays instead of null for required array fields)
            if (!isset($data['images']) || !is_array($data['images'])) {
                $data['images'] = [];
            }
            if (!isset($data['facilities']) || !is_array($data['facilities'])) {
                $data['facilities'] = [];
            }
            if (!isset($data['videos']) || !is_array($data['videos'])) {
                $data['videos'] = [];
            }
            
            \Log::info('Creating project', [
                'data_keys' => array_keys($data),
                'images_count' => count($data['images'] ?? []),
                'facilities_count' => count($data['facilities'] ?? []),
                'videos_count' => count($data['videos'] ?? [])
            ]);
            
            $project = Project::create($data);

            // Return JSON for AJAX requests
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Project created successfully.',
                    'redirect' => route('admin.projects.show', $project)
                ]);
            }

            return redirect()->route('admin.projects.show', $project)
                ->with('success', 'Project created successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Project creation validation error', [
                'errors' => $e->errors(),
                'data' => $data
            ]);
            
            // Return JSON for AJAX requests
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $e->errors()
                ], 422);
            }
            
            return redirect()->back()
                ->withInput()
                ->withErrors($e->errors());
        } catch (\Exception $e) {
            \Log::error('Project creation error: ' . $e->getMessage(), [
                'data' => $data,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $errorMessage = 'Failed to create project. Error: ' . $e->getMessage();
            
            // Return JSON for AJAX requests
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $errorMessage,
                    'errors' => ['error' => [$errorMessage]]
                ], 500);
            }
            
            return redirect()->back()
                ->withInput()
                ->withErrors(['error' => $errorMessage]);
        }
    }

    public function edit(Project $project)
    {
        $project->load(['plots' => function($query) {
            $query->orderBy('grid_batch_id')->orderBy('plot_number')
                  ->with(['sales' => function($q) {
                      $q->with('customer')->orderBy('created_at', 'desc');
                  }]);
        }]);

        $propertyTypes = PropertyType::where('is_active', true)
            ->with('measurementUnit')
            ->orderBy('name')
            ->get();

        // Prepare existing plots data for JavaScript
        $existingPlots = $project->plots->map(function($plot) {
            return [
                'plot_number' => $plot->plot_number,
                'type' => $plot->type,
                'size' => (float)$plot->size,
                'price_per_unit' => (float)($plot->price_per_unit ?? 0),
                'minimum_booking_amount' => (float)($plot->minimum_booking_amount ?? 0),
            ];
        })->values()->all();

        // Group plots by grid batch
        $gridBatches = $project->plots->groupBy('grid_batch_id')->map(function($plots, $batchId) {
            return [
                'batch_id' => $batchId,
                'batch_name' => $plots->first()->grid_batch_name ?? 'Unnamed Grid',
                'plots' => $plots,
                'count' => $plots->count(),
            ];
        })->values();

        return view('admin.projects.edit', compact('project', 'propertyTypes', 'existingPlots', 'gridBatches'));
    }

    public function update(Request $request, $id)
    {
        // Get project - ensure it exists and is not deleted
        $project = Project::where('id', $id)->whereNull('deleted_at')->first();
        
        if (!$project) {
            $errorMsg = 'Project not found or has been deleted.';
            \Log::error('Project update failed - not found', ['project_id' => $id]);
            
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $errorMsg
                ], 404);
            }
            
            return redirect()->route('admin.projects')
                ->withErrors(['error' => $errorMsg]);
        }
        
        // Store project ID for verification
        $projectId = $project->id;
        
        try {
            // Validate input
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'required|string',
                'location' => 'required|string|max:500',
                'pincode' => 'required|string|max:10',
                'type' => 'required|in:residential,commercial,mixed',
                'status' => 'required|in:available,upcoming,sold_out',
                'minimum_booking_amount' => 'required|numeric|min:0',
                'allocated_amount' => 'nullable|numeric|min:0',
                'images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:10240', // 10MB max per image
                'floor_plan_pdf' => 'nullable|file|mimes:pdf|max:20480', // 20MB max for PDF
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $e->errors()
                ], 422);
            }
            return redirect()->back()->withInput()->withErrors($e->errors());
        }

        // Parse location to extract city and state
        $locationParts = array_map('trim', explode(',', $validated['location']));
        $city = $project->city ?? 'Jaipur';
        $state = $project->state ?? 'Rajasthan';
        
        if (count($locationParts) >= 2) {
            $lastPart = trim(end($locationParts));
            $indianStates = ['Rajasthan', 'Maharashtra', 'Gujarat', 'Karnataka', 'Tamil Nadu', 'Delhi', 'Punjab', 'Haryana', 'Uttar Pradesh', 'Madhya Pradesh', 'West Bengal', 'Bihar', 'Odisha', 'Andhra Pradesh', 'Telangana', 'Kerala', 'Assam', 'Jharkhand', 'Chhattisgarh', 'Himachal Pradesh', 'Uttarakhand', 'Goa', 'Manipur', 'Meghalaya', 'Mizoram', 'Nagaland', 'Tripura', 'Sikkim', 'Arunachal Pradesh'];
            if (in_array($lastPart, $indianStates)) {
                $state = $lastPart;
                if (count($locationParts) >= 3) {
                    $city = trim($locationParts[count($locationParts) - 2]);
                }
            } else {
                $city = $lastPart;
            }
        }
        
        // Handle images
        $imageUrls = [];
        if ($request->has('existing_images') && is_array($request->existing_images)) {
            $imageUrls = $request->existing_images;
        }
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                if ($image->isValid()) {
                    $imagePath = $image->store('projects', 'public');
                    // Store relative path (/storage/...) instead of full URL
                    // This allows the API to construct correct URLs for both local and production
                    $imageUrls[] = Storage::url($imagePath); // Returns /storage/projects/filename.jpg
                }
            }
        }
        if (empty($imageUrls)) {
            $imageUrls = $project->images ?? [];
        }
        
        // Handle floor plan PDF
        $floorPlanPdf = $project->floor_plan_pdf;
        if ($request->hasFile('floor_plan_pdf')) {
            $pdfFile = $request->file('floor_plan_pdf');
            if ($pdfFile->isValid()) {
                if ($project->floor_plan_pdf) {
                    $oldPath = str_replace('/storage/', 'public/', $project->floor_plan_pdf);
                    if (Storage::exists($oldPath)) {
                        Storage::delete($oldPath);
                    }
                }
                $pdfPath = $pdfFile->store('projects/floor-plans', 'public');
                $floorPlanPdf = '/storage/' . str_replace('public/', '', $pdfPath);
            }
        }
        
        // Handle facilities and videos
        $facilities = $project->facilities ?? [];
        if ($request->has('facilities')) {
            $facilitiesInput = $request->input('facilities');
            if (is_string($facilitiesInput) && !empty($facilitiesInput)) {
                $facilities = array_map('trim', explode(',', $facilitiesInput));
            } elseif (is_array($facilitiesInput)) {
                $facilities = $facilitiesInput;
            }
        }
        
        $videos = $project->videos ?? [];
        if ($request->has('videos')) {
            $videosInput = $request->input('videos');
            if (is_string($videosInput) && !empty($videosInput)) {
                $videos = array_map('trim', explode(',', $videosInput));
            } elseif (is_array($videosInput)) {
                $videos = $videosInput;
            }
        }
        
        // Build safe update array - ONLY update allowed fields, NEVER touch deleted_at
        $updateData = [
            'name' => $validated['name'],
            'description' => $validated['description'],
            'location' => $validated['location'],
            'pincode' => $validated['pincode'],
            'type' => $validated['type'],
            'status' => $validated['status'],
            'city' => $city,
            'state' => $state,
            'images' => $imageUrls,
            'facilities' => $facilities,
            'videos' => $videos,
            'floor_plan_pdf' => $floorPlanPdf,
            'latitude' => $request->input('latitude') ?: null,
            'longitude' => $request->input('longitude') ?: null,
            'price_per_sqft' => $request->input('price_per_sqft') ?: null,
            'plot_size' => $request->input('plot_size') ?: null,
            'minimum_booking_amount' => $validated['minimum_booking_amount'] ?? 0,
            'allocated_amount' => $validated['allocated_amount'] ?? ($request->input('allocated_amount') ?? 0),
            'allocated_amount_config' => $this->normalizeAllocatedAmountConfig($request->input('allocated_amount_config', $project->allocated_amount_config ?? [])),
            'is_active' => $project->is_active ?? true,
        ];
        
        if ($request->has('commission_per_slab')) {
            $commissionInput = $request->input('commission_per_slab');
            if (is_string($commissionInput) && !empty($commissionInput)) {
                $decoded = json_decode($commissionInput, true);
                $updateData['commission_per_slab'] = $decoded !== null ? $decoded : null;
            } elseif (is_array($commissionInput)) {
                $updateData['commission_per_slab'] = $commissionInput;
            }
        }
        
        try {
            // Update project safely using Eloquent - NEVER touches deleted_at
            $project->fill($updateData);
            $project->save();
            
            // Verify project still exists and wasn't deleted
            $project->refresh();
            if ($project->trashed() || $project->deleted_at !== null) {
                \Log::error('Project was deleted during update!', ['project_id' => $projectId]);
                $project->deleted_at = null;
                $project->save();
                
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Project was temporarily marked as deleted but has been restored.'
                    ], 500);
                }
                return redirect()->route('admin.projects.show', $project)
                    ->with('error', 'Project was temporarily marked as deleted but has been restored.');
            }
            
            \Log::info('Project updated successfully', ['project_id' => $projectId]);
            
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Project updated successfully.',
                    'redirect' => route('admin.projects.show', $project)
                ]);
            }
            
            return redirect()->route('admin.projects.show', $project)
                ->with('success', 'Project updated successfully.');
                
        } catch (\Exception $e) {
            \Log::error('Project update error: ' . $e->getMessage(), [
                'project_id' => $projectId,
                'trace' => $e->getTraceAsString()
            ]);
            
            $errorMsg = 'Failed to update project: ' . $e->getMessage();
            
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $errorMsg
                ], 500);
            }
            
            return redirect()->back()
                ->withInput()
                ->withErrors(['error' => $errorMsg]);
        }
    }

    public function destroy(Project $project)
    {
        // Soft delete - project can be recovered
        $project->delete();

        return redirect()->route('admin.projects')
            ->with('success', 'Project deleted successfully. It can be recovered from the trash.');
    }

    /**
     * Restore a soft-deleted project
     */
    public function restore($id)
    {
        $project = Project::withTrashed()->findOrFail($id);
        $project->restore();

        return redirect()->route('admin.projects')
            ->with('success', 'Project restored successfully.');
    }

    public function storePlots(Request $request, Project $project)
    {
        $grid = $request->input('grid');
        if (is_string($grid)) {
            $decoded = json_decode($grid, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $grid = $decoded;
            }
        }

        $request->merge(['grid' => $grid]);

        $validated = $request->validate([
            'grid' => 'required|array',
            'grid_batch_name' => 'nullable|string|max:255',
            'editing_grid_batch_id' => 'nullable|string',
            'grid.*.plot_number' => 'required|string',
            'grid.*.type' => 'required|string|max:100',
            'grid.*.size' => 'nullable|numeric|min:0',
            'grid.*.price_per_unit' => 'nullable|numeric|min:0',
            'grid.*.minimum_booking_amount' => 'nullable|numeric|min:0',
            'grid.*.id' => 'nullable|integer|exists:plots,id', // Plot ID when editing
        ]);

        $isEditing = $request->has('editing_grid_batch_id') && !empty($request->input('editing_grid_batch_id'));
        $gridBatchId = $isEditing ? $request->input('editing_grid_batch_id') : 'grid_' . time() . '_' . uniqid();
        $gridBatchName = $request->input('grid_batch_name', 'Grid ' . date('Y-m-d H:i:s'));

        // Use project's booking amount for all plots
        $projectBookingAmount = $project->minimum_booking_amount ?? 0;
        
        // If editing, get existing plot IDs to track what should remain
        $existingPlotIds = [];
        if ($isEditing) {
            $existingPlotIds = Plot::where('project_id', $project->id)
                ->where('grid_batch_id', $gridBatchId)
                ->pluck('id')
                ->toArray();
        }
        
        $updatedPlotIds = [];
        
        \Log::info('Grid update request', [
            'isEditing' => $isEditing,
            'gridBatchId' => $gridBatchId,
            'gridCount' => count($validated['grid']),
            'firstCell' => $validated['grid'][0] ?? null,
        ]);
        
        foreach ((array) $validated['grid'] as $cell) {
            // If editing and plot ID is provided, update existing plot
            if ($isEditing && isset($cell['id']) && !empty($cell['id'])) {
                $plotId = (int) $cell['id']; // Ensure it's an integer
                $plot = Plot::find($plotId);
                
                \Log::info('Attempting to update plot', [
                    'plotId' => $plotId,
                    'plotFound' => $plot ? true : false,
                    'cellData' => $cell,
                ]);
                
                // Verify plot belongs to this project and batch
                if ($plot && $plot->project_id == $project->id && $plot->grid_batch_id == $gridBatchId) {
                    $oldSize = $plot->size;
                    $plot->update([
                        'plot_number' => $cell['plot_number'],
                        'grid_batch_name' => $gridBatchName,
                        'type' => $cell['type'],
                        'size' => $cell['size'] ?? 0,
                        'price_per_unit' => $cell['price_per_unit'] ?? 0,
                        'minimum_booking_amount' => $projectBookingAmount,
                        // Don't update status if plot has sales; preserve pending_booking if there's a pending payment request
                        'status' => $plot->sales()->exists() ? $plot->status : (
                            \App\Models\PaymentRequest::where('plot_id', $plot->id)->where('status', 'pending')->exists() ? 'pending_booking' : 'available'
                        ),
                    ]);
                    
                    \Log::info('Plot updated successfully', [
                        'plotId' => $plot->id,
                        'oldSize' => $oldSize,
                        'newSize' => $plot->size,
                        'sizeChanged' => $oldSize != $plot->size,
                    ]);
                    
                    $updatedPlotIds[] = $plot->id;
                } else {
                    \Log::warning('Plot ID mismatch or not found', [
                        'plotId' => $plotId,
                        'plotExists' => $plot ? true : false,
                        'plotProjectId' => $plot ? $plot->project_id : null,
                        'plotBatchId' => $plot ? $plot->grid_batch_id : null,
                        'expectedProjectId' => $project->id,
                        'expectedBatchId' => $gridBatchId,
                    ]);
                    
                    // Plot ID doesn't match, create new plot
                    $plot = Plot::create([
                        'project_id' => $project->id,
                        'grid_batch_id' => $gridBatchId,
                        'plot_number' => $cell['plot_number'],
                        'grid_batch_name' => $gridBatchName,
                        'type' => $cell['type'],
                        'size' => $cell['size'] ?? 0,
                        'price_per_unit' => $cell['price_per_unit'] ?? 0,
                        'minimum_booking_amount' => $projectBookingAmount,
                        'status' => 'available',
                    ]);
                    $updatedPlotIds[] = $plot->id;
                }
            } else {
                \Log::info('Creating new plot (no ID or not editing)', [
                    'isEditing' => $isEditing,
                    'hasId' => isset($cell['id']),
                    'idValue' => $cell['id'] ?? null,
                ]);
                
                // Creating new plot or no ID provided
                $plot = Plot::updateOrCreate(
                    [
                        'project_id' => $project->id,
                        'grid_batch_id' => $gridBatchId,
                        'plot_number' => $cell['plot_number']
                    ],
                    [
                        'grid_batch_name' => $gridBatchName,
                        'type' => $cell['type'],
                        'size' => $cell['size'] ?? 0,
                        'price_per_unit' => $cell['price_per_unit'] ?? 0,
                        'minimum_booking_amount' => $projectBookingAmount,
                        'status' => 'available',
                    ]
                );
                $updatedPlotIds[] = $plot->id;
            }
        }
        
        // If editing, remove plots that were deleted from the grid
        if ($isEditing) {
            $plotsToDelete = array_diff($existingPlotIds, $updatedPlotIds);
            if (!empty($plotsToDelete)) {
                // Check if any plots have sales before deleting
                $hasSales = Plot::whereIn('id', $plotsToDelete)
                    ->whereHas('sales')
                    ->exists();
                
                if ($hasSales) {
                    return back()->with('error', 'Cannot delete plots that have associated sales.');
                }
                
                Plot::whereIn('id', $plotsToDelete)->delete();
            }
        }

        $message = $isEditing ? 'Grid updated successfully.' : 'Grid saved successfully. You can create another grid below.';
        
        // Return JSON for AJAX requests
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'updated_plot_ids' => $updatedPlotIds,
            ]);
        }
        
        return back()->with('success', $message);
    }

    /**
     * Update grid batch name
     */
    public function updateGridBatch(Request $request, Project $project, string $gridBatchId)
    {
        $validated = $request->validate([
            'grid_batch_name' => 'required|string|max:255',
        ]);

        $updated = Plot::where('project_id', $project->id)
            ->where('grid_batch_id', $gridBatchId)
            ->update(['grid_batch_name' => $validated['grid_batch_name']]);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Grid name updated successfully.',
                'grid_batch_name' => $validated['grid_batch_name']
            ]);
        }

        return back()->with('success', 'Grid name updated successfully.');
    }

    /**
     * Delete a grid batch and all its plots
     */
    public function deleteGridBatch(Project $project, string $gridBatchId)
    {
        $plotsCount = Plot::where('project_id', $project->id)
            ->where('grid_batch_id', $gridBatchId)
            ->count();

        // Check if any plots in this batch have sales
        $hasSales = Plot::where('project_id', $project->id)
            ->where('grid_batch_id', $gridBatchId)
            ->whereHas('sales')
            ->exists();

        if ($hasSales) {
            return back()->with('error', 'Cannot delete this grid because it has associated sales. Please delete the sales first.');
        }

        Plot::where('project_id', $project->id)
            ->where('grid_batch_id', $gridBatchId)
            ->delete();

        return back()->with('success', "Grid deleted successfully. {$plotsCount} unit(s) removed.");
    }

    /**
     * Export a project with all its plots/flats as raw data (no bookings)
     */
    public function export(Project $project)
    {
        // Load project with all plots
        $project->load('plots');

        // Prepare project data (exclude booking-related fields)
        $projectData = [
            'name' => $project->name,
            'description' => $project->description,
            'location' => $project->location,
            'city' => $project->city,
            'state' => $project->state,
            'pincode' => $project->pincode,
            'type' => $project->type,
            'commission_per_slab' => $project->commission_per_slab,
            'commission_config' => $project->commission_config,
            'minimum_booking_amount' => $project->minimum_booking_amount,
            'price_per_sqft' => $project->price_per_sqft,
            'plot_size' => $project->plot_size,
            'facilities' => $project->facilities,
            'images' => $project->images,
            'videos' => $project->videos,
            'floor_plan_pdf' => $project->floor_plan_pdf,
            'status' => $project->status,
            'latitude' => $project->latitude,
            'longitude' => $project->longitude,
            'is_active' => $project->is_active,
            'exported_at' => now()->toDateTimeString(),
            'plots' => []
        ];

        // Group plots by grid batch
        $plotsByBatch = $project->plots->groupBy('grid_batch_id');

        foreach ($plotsByBatch as $batchId => $plots) {
            $batchData = [
                'grid_batch_id' => $batchId,
                'grid_batch_name' => $plots->first()->grid_batch_name ?? 'Grid ' . $batchId,
                'plots' => []
            ];

            foreach ($plots as $plot) {
                // Export plot as raw data (status = available, no booking info)
                $plotData = [
                    'plot_number' => $plot->plot_number,
                    'type' => $plot->type,
                    'size' => $plot->size,
                    'price_per_unit' => $plot->price_per_unit,
                    'minimum_booking_amount' => $plot->minimum_booking_amount,
                    'status' => 'available', // Always export as available (raw data)
                    'amenities' => $plot->amenities,
                    'images' => $plot->images,
                    'description' => $plot->description,
                    'bedrooms' => $plot->bedrooms,
                    'bathrooms' => $plot->bathrooms,
                    'carpet_area' => $plot->carpet_area,
                    'is_active' => $plot->is_active ?? true,
                ];

                $batchData['plots'][] = $plotData;
            }

            $projectData['plots'][] = $batchData;
        }

        // Convert to JSON
        $json = json_encode($projectData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // Generate filename
        $filename = 'project_' . str_replace(' ', '_', strtolower($project->name)) . '_' . date('Y-m-d_His') . '.json';

        // Return JSON download
        return response($json)
            ->header('Content-Type', 'application/json')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    /**
     * Show import form
     */
    public function showImport()
    {
        return view('admin.projects.import');
    }

    /**
     * Import a project from exported JSON file
     */
    public function import(Request $request)
    {
        $request->validate([
            'import_file' => 'required|file|mimes:json|max:10240', // 10MB max
        ]);

        try {
            $file = $request->file('import_file');
            $jsonContent = file_get_contents($file->getRealPath());
            $projectData = json_decode($jsonContent, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return back()->with('error', 'Invalid JSON file. Please check the file format.');
            }

            // Validate required fields
            if (!isset($projectData['name']) || empty($projectData['name'])) {
                return back()->with('error', 'Project name is required in the import file.');
            }

            // Check if project with same name already exists
            $existingProject = Project::where('name', $projectData['name'])
                ->whereNull('deleted_at')
                ->first();

            if ($existingProject && !$request->has('overwrite')) {
                return back()->with('error', 'A project with the name "' . $projectData['name'] . '" already exists. Check "Overwrite existing project" to replace it.');
            }

            DB::beginTransaction();

            try {
                // Prepare project data (exclude plots and export metadata)
                $projectFields = [
                    'name', 'description', 'location', 'city', 'state', 'pincode', 'type',
                    'commission_per_slab', 'commission_config', 'minimum_booking_amount',
                    'price_per_sqft', 'plot_size', 'facilities', 'images', 'videos',
                    'floor_plan_pdf', 'status', 'latitude', 'longitude', 'is_active'
                ];

                $projectInsertData = [];
                foreach ($projectFields as $field) {
                    if (isset($projectData[$field])) {
                        $projectInsertData[$field] = $projectData[$field];
                    }
                }

                // Set defaults
                $projectInsertData['status'] = $projectInsertData['status'] ?? 'available';
                $projectInsertData['is_active'] = $projectInsertData['is_active'] ?? true;

                // Create or update project
                if ($existingProject && $request->has('overwrite')) {
                    // Delete existing plots first
                    Plot::where('project_id', $existingProject->id)->delete();
                    $project = $existingProject;
                    $project->fill($projectInsertData);
                    $project->save();
                } else {
                    $project = Project::create($projectInsertData);
                }

                // Import plots
                if (isset($projectData['plots']) && is_array($projectData['plots'])) {
                    foreach ($projectData['plots'] as $batchData) {
                        $gridBatchId = $batchData['grid_batch_id'] ?? uniqid('grid_');
                        $gridBatchName = $batchData['grid_batch_name'] ?? 'Grid ' . $gridBatchId;

                        if (isset($batchData['plots']) && is_array($batchData['plots'])) {
                            foreach ($batchData['plots'] as $plotData) {
                                $plotInsertData = [
                                    'project_id' => $project->id,
                                    'grid_batch_id' => $gridBatchId,
                                    'grid_batch_name' => $gridBatchName,
                                    'plot_number' => $plotData['plot_number'] ?? null,
                                    'type' => $plotData['type'] ?? 'Plot',
                                    'size' => $plotData['size'] ?? null,
                                    'price_per_unit' => $plotData['price_per_unit'] ?? null,
                                    'minimum_booking_amount' => $plotData['minimum_booking_amount'] ?? $project->minimum_booking_amount,
                                    'status' => 'available', // Always import as available
                                    'amenities' => $plotData['amenities'] ?? [],
                                    'images' => $plotData['images'] ?? [],
                                    'description' => $plotData['description'] ?? null,
                                    'bedrooms' => $plotData['bedrooms'] ?? null,
                                    'bathrooms' => $plotData['bathrooms'] ?? null,
                                    'carpet_area' => $plotData['carpet_area'] ?? null,
                                    'is_active' => $plotData['is_active'] ?? true,
                                ];

                                Plot::create($plotInsertData);
                            }
                        }
                    }
                }

                DB::commit();

                $action = $existingProject && $request->has('overwrite') ? 'updated' : 'imported';
                return redirect()->route('admin.projects.show', $project)
                    ->with('success', "Project '{$project->name}' has been {$action} successfully with " . ($project->plots()->count() ?? 0) . " unit(s).");

            } catch (\Exception $e) {
                DB::rollBack();
                \Log::error('Project import error: ' . $e->getMessage());
                return back()->with('error', 'Error importing project: ' . $e->getMessage());
            }

        } catch (\Exception $e) {
            \Log::error('Project import file error: ' . $e->getMessage());
            return back()->with('error', 'Error reading import file: ' . $e->getMessage());
        }
    }

    /**
     * Normalize allocated amount config to ensure all entries use fixed type
     * 
     * @param array $config
     * @return array
     */
    private function normalizeAllocatedAmountConfig(array $config): array
    {
        $normalized = [];
        
        foreach ($config as $propertyType => $settings) {
            if (is_array($settings)) {
                // Always set type to fixed
                $normalized[$propertyType] = [
                    'type' => 'fixed',
                    'value' => (float)($settings['value'] ?? 0),
                ];
            }
        }
        
        return $normalized;
    }
}