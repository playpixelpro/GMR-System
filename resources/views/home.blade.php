@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="mb-8">
    <h1 class="text-2xl font-semibold text-base-content">Dashboard</h1>
</div>

<div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
    <div class="card bg-base-100 shadow-sm border border-base-content/10 p-6 flex flex-col justify-between">
        <div>
            <div class="size-10 rounded-lg bg-primary/10 text-primary flex items-center justify-center mb-4">
                <span class="icon-[tabler--edit] size-6"></span>
            </div>
            <h2 class="text-lg font-semibold text-base-content">Data Entry Form</h2>
        </div>
        <div class="mt-6">
            <a href="{{ route('records.create') }}" class="btn btn-primary w-full">Open Data Entry</a>
        </div>
    </div>

    <div class="card bg-base-100 shadow-sm border border-base-content/10 p-6 flex flex-col justify-between">
        <div>
            <div class="size-10 rounded-lg bg-primary/10 text-primary flex items-center justify-center mb-4">
                <span class="icon-[tabler--chart-bar] size-6"></span>
            </div>
            <h2 class="text-lg font-semibold text-base-content">AMR Report</h2>
        </div>
        <div class="mt-6">
            <a href="{{ route('amr.index') }}" class="btn btn-outline btn-primary w-full">View AMR Report</a>
        </div>
    </div>

    <div class="card bg-base-100 shadow-sm border border-base-content/10 p-6 flex flex-col justify-between">
        <div>
            <div class="size-10 rounded-lg bg-secondary/10 text-secondary flex items-center justify-center mb-4">
                <span class="icon-[tabler--chart-dots] size-6"></span>
            </div>
            <h2 class="text-lg font-semibold text-base-content">PMR Report</h2>
        </div>
        <div class="mt-6">
            <a href="{{ route('pmr.index') }}" class="btn btn-outline btn-secondary w-full">View PMR Report</a>
        </div>
    </div>

    <div class="card bg-base-100 shadow-sm border border-base-content/10 p-6 flex flex-col justify-between">
        <div>
            <div class="size-10 rounded-lg bg-accent/10 text-accent flex items-center justify-center mb-4">
                <span class="icon-[tabler--chart-arrows] size-6"></span>
            </div>
            <h2 class="text-lg font-semibold text-base-content">Expected Milling Recovery</h2>
        </div>
        <div class="mt-6">
            <a href="{{ route('emr.index') }}" class="btn btn-outline btn-accent w-full">View EMR Dashboard</a>
        </div>
    </div>
</div>
@endsection
