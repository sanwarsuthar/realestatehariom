@extends('admin.layouts.app')

@section('title', 'Reports')

@section('content')
<div class="admin-page-content container-fluid min-w-0">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <h4 class="page-title">Reports & Analytics</h4>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Business Reports</h5>
                    <p class="text-muted">View detailed reports on sales, users, and business performance.</p>
                    
                    <div class="text-center py-5">
                        <i class="fas fa-chart-bar text-6xl text-purple-200 mb-4"></i>
                        <h4 class="text-gray-600">Reports Coming Soon</h4>
                        <p class="text-gray-500">This feature is under development and will be available soon.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
