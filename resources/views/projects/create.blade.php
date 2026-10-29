@extends('layouts.app')
@section('title', __('project.post_project'))
@section('content')
<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('projects.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800">&larr; {{ __('project.back_to_marketplace') }}</a>
    </div>

    <h1 class="text-2xl font-bold text-gray-900 mb-6">{{ __('project.post_project') }}</h1>

    @if($errors->any())
        <div class="mb-4 p-3 bg-red-50 text-red-700 text-sm rounded-lg">
            @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('projects.store') }}" class="bg-white rounded-lg shadow-sm p-6 space-y-5">
        @csrf

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('project.title_label') }} *</label>
            <input type="text" name="title" value="{{ old('title') }}" required maxlength="200"
                   class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                   placeholder="{{ __('project.title_placeholder') }}">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('project.description_label') }} *</label>
            <textarea name="description" rows="6" required maxlength="10000"
                      class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                      placeholder="{{ __('project.description_placeholder') }}">{{ old('description') }}</textarea>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('project.category') }} *</label>
                <select name="category" required class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                    @foreach($categories as $key => $label)
                        <option value="{{ $key }}" {{ old('category') === $key ? 'selected' : '' }}>{{ __($label) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('project.duration') }}</label>
                <select name="duration" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="">{{ __('project.select_duration') }}</option>
                    @foreach($durations as $key => $label)
                        <option value="{{ $key }}" {{ old('duration') === $key ? 'selected' : '' }}>{{ __($label) }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('project.skills_label') }}</label>
            <input type="text" name="skills" value="{{ old('skills') }}" maxlength="500"
                   class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                   placeholder="{{ __('project.skills_placeholder') }}">
            <p class="text-xs text-gray-400 mt-1">{{ __('project.skills_hint') }}</p>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('project.budget_min') }}</label>
                <input type="number" name="budget_min" value="{{ old('budget_min') }}" min="0" step="0.01"
                       class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('project.budget_max') }}</label>
                <input type="number" name="budget_max" value="{{ old('budget_max') }}" min="0" step="0.01"
                       class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('project.mode') }}</label>
            <div class="grid grid-cols-3 gap-3">
                @foreach(\App\Models\Project::modeOptions() as $modeKey => $modeLabel)
                    <label class="relative flex items-start p-3 border rounded-lg cursor-pointer hover:bg-gray-50 has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50">
                        <input type="radio" name="mode" value="{{ $modeKey }}" {{ old('mode', 'bidding') === $modeKey ? 'checked' : '' }} class="mt-0.5">
                        <div class="ml-2">
                            <span class="text-sm font-medium text-gray-900">{{ __($modeLabel) }}</span>
                            <p class="text-xs text-gray-500">{{ __('project.mode_' . $modeKey . '_hint') }}</p>
                        </div>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                {{ __('project.publish_project') }}
            </button>
            <a href="{{ route('projects.index') }}" class="text-sm text-gray-500 hover:text-gray-700">{{ __('cancel') }}</a>
        </div>
    </form>
</div>
@endsection
