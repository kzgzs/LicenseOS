@extends('layouts.app')
@section('title', __('skill.my_skills'))
@section('content')
<div class="max-w-3xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">{{ __('skill.my_skills') }}</h1>

    @if(session('success'))
        <div class="mb-4 p-3 bg-green-50 text-green-700 text-sm rounded-lg">{{ session('success') }}</div>
    @endif

    {{-- Current Skills --}}
    @if($skills->isNotEmpty())
        <div class="bg-white rounded-lg shadow-sm p-5 mb-6">
            <h3 class="font-semibold text-gray-800 mb-3">{{ __('skill.current_skills') }}</h3>
            <div class="flex flex-wrap gap-2">
                @foreach($skills as $skill)
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm {{ $skill->verified ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700' }}">
                        {{ $skill->skill }}
                        <span class="text-xs opacity-70">{{ __('skill.level_' . $skill->level) }}</span>
                        @if($skill->verified)
                            <span title="{{ __('skill.verified') }}">&#10003;</span>
                        @endif
                    </span>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Edit Skills Form --}}
    <div class="bg-white rounded-lg shadow-sm p-5">
        <h3 class="font-semibold text-gray-800 mb-3">{{ __('skill.edit_skills') }}</h3>
        <p class="text-xs text-gray-500 mb-4">{{ __('skill.edit_hint') }}</p>

        <form method="POST" action="{{ route('projects.skills.update') }}" id="skills-form">
            @csrf
            <div id="skills-container" class="space-y-3">
                {{-- Skill rows added via JS --}}
            </div>
            <div class="mt-4 flex items-center gap-3">
                <button type="button" id="add-skill-btn" class="px-3 py-1.5 text-sm bg-indigo-50 text-indigo-700 rounded-lg hover:bg-indigo-100">
                    + {{ __('skill.add') }}
                </button>
                <button type="submit" class="px-5 py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700">
                    {{ __('skill.save') }}
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    const LEVELS = {
        @foreach($levels as $key => $label)
        {{ $key }}: '{{ __($label) }}',
        @endforeach
    };
    const container = document.getElementById('skills-container');
    let idx = 0;

    function addSkill(name, level) {
        name = name || '';
        level = level || 3;
        const i = idx++;
        const div = document.createElement('div');
        div.className = 'flex items-center gap-3 skill-row';
        div.innerHTML =
            '<input type="text" name="skills[' + i + '][name]" value="' + name + '" class="flex-1 border rounded px-3 py-2 text-sm" placeholder="{{ __('skill.name_ph') }}" maxlength="50" required>' +
            '<select name="skills[' + i + '][level]" class="border rounded px-2 py-2 text-sm">' +
                Object.entries(LEVELS).map(function(e) {
                    return '<option value="' + e[0] + '"' + (level == e[0] ? ' selected' : '') + '>' + e[1] + '</option>';
                }).join('') +
            '</select>' +
            '<button type="button" class="text-red-500 hover:text-red-700 text-sm remove-skill">&times;</button>';
        container.appendChild(div);
        div.querySelector('.remove-skill').addEventListener('click', function() { div.remove(); });
    }

    document.getElementById('add-skill-btn').addEventListener('click', function() { addSkill(); });

    // Pre-populate existing skills
    @foreach($skills as $skill)
    addSkill('{{ addslashes($skill->skill) }}', {{ $skill->level }});
    @endforeach

    // If empty, add one row
    if (container.children.length === 0) addSkill();
})();
</script>
@endsection
