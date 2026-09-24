@extends('layouts.app')

@section('title', 'Edit Trial')

@section('content')
    <div class="mx-auto max-w-xl">
        <h1 class="text-2xl font-semibold text-gray-900">Edit {{ strtoupper($formType) }} Trial {{ $trial->trial_number }}</h1>
        <p class="mt-1 text-sm text-gray-500">{{ $trial->warehouse_name }} — Pile {{ $trial->pile_number }}</p>

        @if ($errors->any())
            <div class="mt-6 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('records.update', [$formType, $trial->id]) }}" class="mt-6 space-y-5 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            @csrf
            @method('PATCH')

            @if ($formType === 'amr')
                <div>
                    <label for="rice_millers" class="block text-sm font-medium text-gray-700">Rice Miller</label>
                    <input type="text" name="rice_millers" id="rice_millers" value="{{ old('rice_millers', $trial->rice_millers) }}"
                           class="mt-1 block w-full rounded-md border-gray-300 px-3 py-2 text-sm shadow-sm">
                </div>
            @endif

            <div>
                <label for="palay_input" class="block text-sm font-medium text-gray-700">Palay Input (kg)</label>
                <input type="number" name="palay_input" id="palay_input" value="{{ old('palay_input', $trial->palay_input_kg) }}" min="0" step="any" required
                       class="mt-1 block w-full rounded-md border-gray-300 px-3 py-2 text-sm shadow-sm">
            </div>

            <div>
                <label for="rice_recovery" class="block text-sm font-medium text-gray-700">Rice Output (kg)</label>
                <input type="number" name="rice_recovery" id="rice_recovery" value="{{ old('rice_recovery', $trial->rice_recovery_kg) }}" min="0" step="any" required
                       class="mt-1 block w-full rounded-md border-gray-300 px-3 py-2 text-sm shadow-sm">
            </div>

            <div class="flex gap-3">
                <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save Changes</button>
                <a href="{{ route($formType === 'amr' ? 'amr.index' : 'pmr.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</a>
            </div>
        </form>
    </div>
@endsection
