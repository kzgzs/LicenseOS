@extends('layouts.app')
@section('title', __('project.contract_detail'))
@section('content')
<div class="max-w-4xl mx-auto">
    <div class="mb-4">
        <a href="{{ route('projects.contracts') }}" class="text-sm text-indigo-600 hover:text-indigo-800">&larr; {{ __('project.back_to_contracts') }}</a>
    </div>

    @if(session('success'))
        <div class="mb-4 p-3 bg-green-50 text-green-700 text-sm rounded-lg">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-4 p-3 bg-red-50 text-red-700 text-sm rounded-lg">
            @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
        </div>
    @endif

    {{-- Contract Summary --}}
    <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
            <h1 class="text-xl font-bold text-gray-900">{{ $contract->project->title }}</h1>
            <span class="px-3 py-1 text-sm rounded-full font-medium
                @if($contract->status === 'active') bg-yellow-50 text-yellow-600
                @elseif($contract->status === 'completed') bg-green-50 text-green-600
                @elseif($contract->status === 'disputed') bg-red-50 text-red-600
                @else bg-gray-100 text-gray-500
                @endif">{{ __('project.contract_' . $contract->status) }}</span>
        </div>
        <div class="grid grid-cols-4 gap-4 text-sm">
            <div>
                <span class="text-gray-400">{{ __('project.client') }}</span>
                <div class="font-medium text-gray-900">{{ $contract->client->name }}</div>
            </div>
            <div>
                <span class="text-gray-400">{{ __('project.developer') }}</span>
                <div class="font-medium text-gray-900">{{ $contract->developer->name }}</div>
            </div>
            <div>
                <span class="text-gray-400">{{ __('project.total_amount') }}</span>
                <div class="font-bold text-gray-900">{{ $contract->currency }} {{ number_format($contract->total_amount) }}</div>
            </div>
            <div>
                <span class="text-gray-400">{{ __('project.started') }}</span>
                <div class="text-gray-600">{{ $contract->started_at?->format('Y-m-d') ?? '-' }}</div>
            </div>
        </div>
    </div>

    {{-- Milestones --}}
    <h2 class="text-lg font-semibold text-gray-900 mb-4">{{ __('project.milestones') }}</h2>

    @foreach($contract->milestones as $milestone)
    <div class="bg-white rounded-lg shadow-sm p-6 mb-4">
        <div class="flex items-center justify-between mb-3">
            <h3 class="font-semibold text-gray-900">{{ $milestone->title }}</h3>
            <div class="flex items-center gap-3">
                <span class="font-bold text-gray-900">{{ $contract->currency }} {{ number_format($milestone->amount) }}</span>
                <span class="px-2 py-0.5 text-xs rounded-full font-medium
                    @if($milestone->status === 'pending') bg-gray-100 text-gray-500
                    @elseif($milestone->status === 'funded') bg-blue-50 text-blue-600
                    @elseif($milestone->status === 'submitted') bg-purple-50 text-purple-600
                    @elseif($milestone->status === 'approved' || $milestone->status === 'released') bg-green-50 text-green-600
                    @elseif($milestone->status === 'disputed') bg-red-50 text-red-600
                    @endif">{{ __('project.ms_' . $milestone->status) }}</span>
            </div>
        </div>

        @if($milestone->description)
            <p class="text-sm text-gray-500 mb-3">{{ $milestone->description }}</p>
        @endif

        {{-- Deliverable Note --}}
        @if($milestone->deliverable_note)
        <div class="p-3 bg-purple-50 rounded-lg mb-3">
            <span class="text-xs font-medium text-purple-600">{{ __('project.deliverable') }}:</span>
            <p class="text-sm text-gray-700 mt-1">{!! nl2br(e($milestone->deliverable_note)) !!}</p>
            {{-- Deliverable Files --}}
            @if($milestone->deliverable_files)
                <div class="mt-2 space-y-1">
                    @foreach($milestone->deliverable_files as $file)
                        <a href="{{ asset('storage/' . $file['path']) }}" target="_blank"
                           class="inline-flex items-center gap-1 text-xs text-indigo-600 hover:underline bg-white px-2 py-1 rounded border mr-2">
                            &#128206; {{ $file['name'] }}
                            <span class="text-gray-400">({{ number_format(($file['size'] ?? 0) / 1024, 1) }}KB)</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
        @endif

        {{-- Auto-approve countdown --}}
        @if($milestone->isSubmitted() && $milestone->auto_approve_at && $milestone->auto_approve_at->isFuture())
        <div class="p-2 bg-orange-50 border border-orange-200 rounded-lg mb-3">
            <span class="text-xs text-orange-600">
                &#9200; {{ __('project.auto_approve_countdown') }}: {{ $milestone->auto_approve_at->diffForHumans() }}
                <span class="text-gray-500">({{ $milestone->auto_approve_at->format('Y-m-d H:i') }})</span>
            </span>
        </div>
        @endif

        {{-- Revision Note --}}
        @if($milestone->revision_note)
        <div class="p-3 bg-yellow-50 rounded-lg mb-3">
            <span class="text-xs font-medium text-yellow-600">{{ __('project.revision_request') }}:</span>
            <p class="text-sm text-gray-700 mt-1">{!! nl2br(e($milestone->revision_note)) !!}</p>
        </div>
        @endif

        {{-- Timeline --}}
        <div class="flex items-center gap-4 text-xs text-gray-400 mb-4">
            @if($milestone->funded_at)<span>{{ __('project.funded') }}: {{ $milestone->funded_at->format('Y-m-d H:i') }}</span>@endif
            @if($milestone->submitted_at)<span>{{ __('project.submitted') }}: {{ $milestone->submitted_at->format('Y-m-d H:i') }}</span>@endif
            @if($milestone->approved_at)<span>{{ __('project.approved') }}: {{ $milestone->approved_at->format('Y-m-d H:i') }}</span>@endif
            @if($milestone->released_at)<span>{{ __('project.released') }}: {{ $milestone->released_at->format('Y-m-d H:i') }}</span>@endif
        </div>

        {{-- Actions --}}
        <div class="flex items-center gap-3 flex-wrap">
            {{-- Client: Fund milestone --}}
            @if($isClient && $milestone->isPending())
            <form method="POST" action="{{ route('projects.fund-milestone', $milestone) }}">
                @csrf
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700">
                    {{ __('project.fund_milestone') }}
                </button>
            </form>
            @endif

            {{-- Developer: Submit deliverable --}}
            @if(!$isClient && $milestone->isFunded())
            <form method="POST" action="{{ route('projects.submit-deliverable', $milestone) }}" enctype="multipart/form-data" class="flex-1">
                @csrf
                <textarea name="deliverable_note" rows="2" required maxlength="5000"
                          class="w-full border-gray-300 rounded-lg shadow-sm text-sm mb-2 focus:ring-indigo-500 focus:border-indigo-500"
                          placeholder="{{ __('project.deliverable_placeholder') }}"></textarea>
                <div class="flex items-center gap-2 mb-2">
                    <input type="file" name="deliverable_files[]" id="deliverable-files-{{ $milestone->id }}" class="hidden" multiple accept=".zip,.pdf,.doc,.docx,.jpg,.png,.txt,.sql,.sql.gz,.tar.gz,.rar,.7z,.json,.xml,.csv,.mp4,.mp3">
                    <button type="button" onclick="document.getElementById('deliverable-files-{{ $milestone->id }}').click()" class="px-2 py-1 text-xs bg-gray-100 text-gray-600 rounded hover:bg-gray-200 border">
                        &#128206; {{ __('project.attach_files') }}
                    </button>
                    <span id="deliverable-count-{{ $milestone->id }}" class="text-xs text-gray-400">{{ __('project.max_5_files') }}</span>
                </div>
                <script>
                document.getElementById('deliverable-files-{{ $milestone->id }}').addEventListener('change', function() {
                    document.getElementById('deliverable-count-{{ $milestone->id }}').textContent = this.files.length + ' {{ __('project.files_selected') }}';
                });
                </script>
                <button type="submit" class="px-4 py-2 bg-purple-600 text-white text-sm font-medium rounded-lg hover:bg-purple-700">
                    {{ __('project.submit_delivery') }}
                </button>
            </form>
            @endif

            {{-- Client: Approve or Request Revision --}}
            @if($isClient && $milestone->isSubmitted())
            <form method="POST" action="{{ route('projects.approve-milestone', $milestone) }}">
                @csrf
                <button type="submit" class="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700"
                        onclick="return confirm('{{ __('project.confirm_approve') }}')">
                    {{ __('project.approve') }}
                </button>
            </form>
            <form method="POST" action="{{ route('projects.request-revision', $milestone) }}" class="flex-1">
                @csrf
                <textarea name="revision_note" rows="2" required maxlength="3000"
                          class="w-full border-gray-300 rounded-lg shadow-sm text-sm mb-2 focus:ring-indigo-500 focus:border-indigo-500"
                          placeholder="{{ __('project.revision_placeholder') }}"></textarea>
                <button type="submit" class="px-4 py-2 bg-yellow-600 text-white text-sm font-medium rounded-lg hover:bg-yellow-700">
                    {{ __('project.request_revision') }}
                </button>
            </form>
            @endif
        </div>
    </div>
    @endforeach

    {{-- Phase 2: Quick Links --}}
    <div class="bg-white rounded-lg shadow-sm p-6 mt-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">{{ __('project.tools') }}</h2>
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('projects.time-logs', $contract) }}" class="px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200">
                &#128339; {{ __('timelog.title') }}
            </a>
            <a href="{{ route('projects.badges') }}" class="px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200">
                &#127942; {{ __('badge.my_badges') }}
            </a>
            <a href="{{ route('projects.skills') }}" class="px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200">
                &#128736; {{ __('skill.my_skills') }}
            </a>
            <a href="{{ route('projects.messages', $project) }}" class="px-4 py-2 bg-indigo-50 text-indigo-700 text-sm font-medium rounded-lg hover:bg-indigo-100 border border-indigo-200">
                &#128172; {{ __('message.chat') }}
            </a>

            {{-- File Dispute (only when funded/submitted milestones exist and no active dispute) --}}
            @if($contract->isActive() || $contract->isDisputed())
                @php $hasActiveDispute = $contract->disputes()->whereNotIn('status', ['closed'])->exists(); @endphp
                @if(!$hasActiveDispute)
                    <button type="button" onclick="document.getElementById('dispute-form').classList.toggle('hidden')" class="px-4 py-2 bg-red-50 text-red-700 text-sm font-medium rounded-lg hover:bg-red-100">
                        &#9888; {{ __('dispute.file_dispute') }}
                    </button>
                @else
                    <a href="{{ route('projects.disputes.show', $contract->disputes()->whereNotIn('status', ['closed'])->first()) }}" class="px-4 py-2 bg-red-100 text-red-800 text-sm font-medium rounded-lg">
                        &#9888; {{ __('dispute.view_dispute') }}
                    </a>
                @endif
            @endif
        </div>

        {{-- Inline Dispute Form --}}
        <div id="dispute-form" class="hidden mt-4 p-4 bg-red-50 rounded-lg border border-red-200">
            <h3 class="font-semibold text-red-800 mb-3">{{ __('dispute.file_dispute') }}</h3>
            <form method="POST" action="{{ route('projects.dispute.file', $contract) }}">
                @csrf
                <div class="grid grid-cols-2 gap-3 mb-3">
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">{{ __('dispute.reason') }} *</label>
                        <select name="reason" required class="w-full border rounded px-2 py-1.5 text-sm">
                            @foreach(\App\Models\ProjectDispute::reasons() as $key => $label)
                                <option value="{{ $key }}">{{ __($label) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">{{ __('dispute.related_milestone') }}</label>
                        <select name="milestone_id" class="w-full border rounded px-2 py-1.5 text-sm">
                            <option value="">{{ __('dispute.select_milestone') }}</option>
                            @foreach($contract->milestones as $ms)
                                <option value="{{ $ms->id }}">{{ $ms->title }} (¥{{ number_format($ms->amount, 2) }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="block text-xs text-gray-600 mb-1">{{ __('dispute.description') }} *</label>
                    <textarea name="description" rows="3" required maxlength="5000" class="w-full border rounded px-3 py-2 text-sm" placeholder="{{ __('dispute.description_ph') }}"></textarea>
                </div>
                <button type="submit" class="px-4 py-2 bg-red-600 text-white text-sm rounded-lg hover:bg-red-700"
                        onclick="return confirm('{{ __('dispute.confirm_file') }}')">
                    {{ __('dispute.submit_dispute') }}
                </button>
            </form>
        </div>
    </div>

    {{-- Phase 3: Project Review (when completed) --}}
    @if($contract->isCompleted())
    @php
        $myReview = \App\Models\ProjectReview::where('contract_id', $contract->id)
            ->where('reviewer_id', auth()->id())->first();
    @endphp
    @if(!$myReview)
    <div class="bg-white rounded-lg shadow-sm p-6 mt-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">{{ __('review.title') }}</h2>
        <form method="POST" action="{{ route('projects.review', $contract) }}">
            @csrf
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                <div>
                    <label class="block text-xs text-gray-600 mb-1">{{ __('review.overall') }} *</label>
                    <select name="rating" required class="w-full border rounded px-3 py-2 text-sm">
                        @for($i = 1; $i <= 5; $i++)
                            <option value="{{ $i }}">{{ $i }} &#9733;</option>
                        @endfor
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-600 mb-1">{{ __('review.communication') }} *</label>
                    <select name="communication" required class="w-full border rounded px-3 py-2 text-sm">
                        @for($i = 1; $i <= 5; $i++)
                            <option value="{{ $i }}">{{ $i }}</option>
                        @endfor
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-600 mb-1">{{ __('review.quality') }} *</label>
                    <select name="quality" required class="w-full border rounded px-3 py-2 text-sm">
                        @for($i = 1; $i <= 5; $i++)
                            <option value="{{ $i }}">{{ $i }}</option>
                        @endfor
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-600 mb-1">{{ __('review.timeliness') }} *</label>
                    <select name="timeliness" required class="w-full border rounded px-3 py-2 text-sm">
                        @for($i = 1; $i <= 5; $i++)
                            <option value="{{ $i }}">{{ $i }}</option>
                        @endfor
                    </select>
                </div>
            </div>
            <div class="mb-3">
                <label class="block text-xs text-gray-600 mb-1">{{ __('review.comment') }}</label>
                <textarea name="comment" rows="2" maxlength="2000" class="w-full border rounded px-3 py-2 text-sm" placeholder="{{ __('review.comment_ph') }}"></textarea>
            </div>
            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700">
                {{ __('review.submit') }}
            </button>
        </form>
    </div>
    @else
    <div class="bg-green-50 rounded-lg p-4 mt-6">
        <p class="text-sm text-green-700">{{ __('review.already_reviewed') }}</p>
    </div>
    @endif
    @endif

    {{-- Phase 3: Team Members --}}
    @if($project->max_team_size > 1)
    <div class="bg-white rounded-lg shadow-sm p-6 mt-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">
            {{ __('team.title') }}
            <span class="text-sm text-gray-400 font-normal">({{ count($project->team_members ?? []) }}/{{ $project->max_team_size }})</span>
        </h2>
        @if($project->team_members)
            <div class="space-y-2 mb-4">
                @foreach($project->team_members as $member)
                    <div class="flex items-center gap-2 text-sm">
                        <div class="w-6 h-6 rounded-full bg-indigo-100 flex items-center justify-center text-xs font-bold text-indigo-600">
                            {{ strtoupper(substr(\App\Models\User::find($member['user_id'])?->name ?? '?', 0, 1)) }}
                        </div>
                        <span>{{ \App\Models\User::find($member['user_id'])?->name ?? '-' }}</span>
                        <span class="text-xs text-gray-400">({{ $member['role'] ?? 'member' }})</span>
                    </div>
                @endforeach
            </div>
        @endif
        @auth
            @if(count($project->team_members ?? []) < $project->max_team_size)
                @php $isMember = collect($project->team_members ?? [])->contains('user_id', auth()->id()); @endphp
                @if(!$isMember)
                    <form method="POST" action="{{ route('projects.team.join', $project) }}">
                        @csrf
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700">
                            {{ __('team.join') }}
                        </button>
                    </form>
                @else
                    <form method="POST" action="{{ route('projects.team.leave', $project) }}">
                        @csrf
                        <button type="submit" class="px-4 py-2 bg-gray-200 text-gray-700 text-sm rounded-lg hover:bg-gray-300">
                            {{ __('team.leave') }}
                        </button>
                    </form>
                @endif
            @endif
        @endauth
    </div>
    @endif
</div>
@endsection
