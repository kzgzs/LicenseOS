@extends('layouts.app')
@section('title', __('timelog.title'))
@section('content')
<div class="max-w-4xl mx-auto">
    <a href="{{ route('projects.contracts.show', $contract->project) }}" class="text-sm text-indigo-600 hover:underline">&larr; {{ __('timelog.back_to_contract') }}</a>

    <h1 class="text-2xl font-bold text-gray-900 mt-4 mb-2">{{ __('timelog.title') }}</h1>
    <p class="text-sm text-gray-500 mb-6">{{ $contract->project->title }}</p>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow-sm p-4">
            <p class="text-xs text-gray-500">{{ __('timelog.total_hours') }}</p>
            <p class="text-xl font-bold text-gray-900">{{ floor($totalMinutes / 60) }}h {{ $totalMinutes % 60 }}m</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm p-4">
            <p class="text-xs text-gray-500">{{ __('timelog.total_amount') }}</p>
            <p class="text-xl font-bold text-indigo-600">¥{{ number_format($totalAmount, 2) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm p-4">
            <p class="text-xs text-gray-500">{{ __('timelog.entries') }}</p>
            <p class="text-xl font-bold text-gray-900">{{ $timeLogs->total() }}</p>
        </div>
    </div>

    {{-- Log Time Form (developer only) --}}
    @if($isDeveloper && $contract->isActive())
        <div class="bg-white rounded-lg shadow-sm p-5 mb-6">
            <h3 class="font-semibold text-gray-800 mb-3">{{ __('timelog.add_entry') }}</h3>
            <form method="POST" action="{{ route('projects.time-logs.store', $contract) }}" class="grid grid-cols-2 md:grid-cols-4 gap-3">
                @csrf
                <div>
                    <label class="block text-xs text-gray-500 mb-1">{{ __('timelog.worked_date') }} *</label>
                    <input type="date" name="worked_date" value="{{ old('worked_date', date('Y-m-d')) }}" required class="w-full border rounded px-2 py-1.5 text-sm">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">{{ __('timelog.hours') }} *</label>
                    <input type="number" name="hours" step="0.25" min="0.25" max="24" value="{{ old('hours', '1') }}" required class="w-full border rounded px-2 py-1.5 text-sm">
                </div>
                <div class="col-span-2">
                    <label class="block text-xs text-gray-500 mb-1">{{ __('timelog.description') }}</label>
                    <input type="text" name="description" value="{{ old('description') }}" maxlength="2000" class="w-full border rounded px-2 py-1.5 text-sm" placeholder="{{ __('timelog.description_ph') }}">
                </div>
                <div class="col-span-2 md:col-span-4">
                    <button type="submit" class="px-4 py-2 text-sm bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">{{ __('timelog.submit') }}</button>
                </div>
            </form>
        </div>
    @endif

    {{-- Time Log List --}}
    @if($timeLogs->isEmpty())
        <div class="bg-white rounded-lg shadow-sm p-8 text-center">
            <p class="text-gray-500">{{ __('timelog.empty') }}</p>
        </div>
    @else
        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="text-left px-4 py-2 text-gray-600 font-medium">{{ __('timelog.date') }}</th>
                        <th class="text-left px-4 py-2 text-gray-600 font-medium">{{ __('timelog.hours') }}</th>
                        <th class="text-left px-4 py-2 text-gray-600 font-medium">{{ __('timelog.description') }}</th>
                        <th class="text-left px-4 py-2 text-gray-600 font-medium">{{ __('timelog.amount') }}</th>
                        <th class="text-left px-4 py-2 text-gray-600 font-medium">{{ __('timelog.status') }}</th>
                        @if(!$isDeveloper)
                            <th class="text-right px-4 py-2 text-gray-600 font-medium">{{ __('actions') }}</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach($timeLogs as $log)
                        <tr>
                            <td class="px-4 py-3">{{ $log->started_at?->format('Y-m-d') }}</td>
                            <td class="px-4 py-3 font-medium">{{ $log->hours_formatted }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ Str::limit($log->description, 60) }}</td>
                            <td class="px-4 py-3">¥{{ number_format($log->amount, 2) }}</td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 text-xs rounded-full {{ $log->isApproved() ? 'bg-green-100 text-green-700' : ($log->isRejected() ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700') }}">
                                    {{ __('timelog.status_' . $log->status) }}
                                </span>
                            </td>
                            @if(!$isDeveloper)
                                <td class="px-4 py-3 text-right">
                                    @if($log->isPending())
                                        <form method="POST" action="{{ route('projects.time-logs.approve', $log) }}" class="inline">
                                            @csrf
                                            <button class="text-xs text-green-600 hover:underline">{{ __('timelog.approve') }}</button>
                                        </form>
                                        <form method="POST" action="{{ route('projects.time-logs.reject', $log) }}" class="inline ml-2">
                                            @csrf
                                            <button class="text-xs text-red-600 hover:underline">{{ __('timelog.reject') }}</button>
                                        </form>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $timeLogs->links() }}</div>
    @endif
</div>
@endsection
