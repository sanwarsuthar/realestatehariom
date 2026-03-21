@extends('admin.layouts.app')

@section('title', 'Project Management')
@section('page-title', 'Projects')

@section('content')
<div class="admin-page-content space-y-4 min-w-0">
    <div class="flex items-center justify-between">
        <form method="GET" class="flex flex-wrap gap-2">
            <input name="search" value="{{ request('search') }}" placeholder="Search projects..." class="px-3 py-2 border rounded-lg">
            <select name="status" class="px-3 py-2 border rounded-lg">
                <option value="">All Status</option>
                <option value="available" @selected(request('status')==='available')>Available</option>
                <option value="upcoming" @selected(request('status')==='upcoming')>Upcoming</option>
                <option value="sold_out" @selected(request('status')==='sold_out')>Sold Out</option>
            </select>
            <select name="show_deleted" class="px-3 py-2 border rounded-lg">
                <option value="">Active Projects</option>
                <option value="only" @selected(request('show_deleted')==='only')>Deleted Projects</option>
                <option value="all" @selected(request('show_deleted')==='all')>All Projects</option>
            </select>
            <button class="px-4 py-2 bg-gray-100 rounded-lg">Filter</button>
        </form>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.projects.import') }}" role="button" class="inline-flex items-center px-4 py-2 rounded-lg bg-green-600 text-white hover:bg-green-700 relative z-10 pointer-events-auto"><i class="fas fa-upload mr-2"></i>Import Project</a>
            <a href="{{ route('admin.projects.create') }}" role="button" class="inline-flex items-center px-4 py-2 rounded-lg bg-primary-600 text-white hover:bg-primary-700 relative z-10 pointer-events-auto"><i class="fas fa-plus mr-2"></i>New Project</a>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        @forelse($projects as $project)
            <div class="bg-white rounded-2xl shadow-3d p-5 flex flex-col {{ $project->deleted_at ? 'opacity-60 border-2 border-red-300' : '' }}">
                <div class="flex items-start justify-between mb-3">
                    <h3 class="text-lg font-semibold text-gray-800">
                        {{ $project->name }}
                        @if($project->deleted_at)
                            <span class="text-xs text-red-600 font-normal">(Deleted)</span>
                        @endif
                    </h3>
                    @php($badge = ['available'=>'bg-green-100 text-green-700','upcoming'=>'bg-yellow-100 text-yellow-700','sold_out'=>'bg-red-100 text-red-700'][$project->status] ?? 'bg-gray-100 text-gray-700')
                    <span class="px-2 py-1 text-xs rounded {{ $badge }} capitalize">{{ str_replace('_',' ', $project->status) }}</span>
                </div>
                <p class="text-sm text-gray-600 mb-3">{{ $project->location }}, {{ $project->city }}</p>
                <p class="text-sm text-gray-500 mb-4">Price: ₹{{ number_format($project->price_range_min ?? 0,0) }} - ₹{{ number_format($project->price_range_max ?? 0,0) }}</p>
                <div class="mt-auto flex items-center justify-between">
                    <span class="text-xs text-gray-500">{{ $project->plots_count }} units</span>
                    <div class="space-x-2">
                        @if($project->deleted_at)
                            <form action="{{ route('admin.projects.restore', ['project' => $project->id]) }}" method="POST" class="inline">
                                @csrf
                                @method('POST')
                                <button type="submit" onclick="return confirm('Restore this project?')" class="px-3 py-1 text-sm rounded bg-green-600 text-white">Restore</button>
                            </form>
                        @else
                            <a href="{{ route('admin.projects.export', ['project' => $project->id]) }}" class="px-3 py-1 text-sm rounded bg-purple-600 text-white hover:bg-purple-700" title="Export Project"><i class="fas fa-download"></i></a>
                            <a href="{{ route('admin.projects.view', ['project' => $project->id]) }}" class="px-3 py-1 text-sm rounded bg-blue-600 text-white hover:bg-blue-700">View</a>
                            <a href="{{ route('admin.projects.show', ['project' => $project->id]) }}" class="px-3 py-1 text-sm rounded bg-gray-100">Open</a>
                            <a href="{{ route('admin.projects.edit', ['project' => $project->id]) }}" class="px-3 py-1 text-sm rounded bg-gray-100">Edit</a>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full text-gray-500">No projects found.</div>
        @endforelse
    </div>

    <div>
        {{ $projects->withQueryString()->links() }}
    </div>
</div>
@endsection
