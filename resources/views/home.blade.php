@extends('layouts.app')

@section('title', 'Data Entry')

@section('content')
    <div class="mb-8">
        <h1 class="text-2xl font-semibold text-gray-900">GMR Data Entry</h1>
        <p class="mt-1 text-sm text-gray-500">Use the single data entry form and choose AMR or PMR inside it.</p>
    </div>

    <a href="{{ route('records.create') }}" class="block max-w-xl rounded-lg border border-gray-200 bg-white p-6 shadow-sm transition hover:border-blue-300 hover:shadow-md">
        <h2 class="text-lg font-semibold text-gray-900">Open Data Entry Form</h2>
        <p class="mt-1 text-sm text-gray-500">Select AMR or PMR after opening the form.</p>
    </a>
@endsection
