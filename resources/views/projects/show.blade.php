@extends('layouts.app')
@section('title', $project->title)
@section('content')
<div class="max-w-4xl mx-auto">
    <div class="mb-4">
        <a href="{{ route('projects.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800">&larr; {{ __('project.back_to_marketplace') }}</a>
    </div>

    @if(session('success'))
        <div class="mb-4 p-3 bg-green-50 text-green-700 text-sm rounded-lg">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-4 p-3 bg-red-50 text-red-700 text-sm rounded-lg">
            @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
        </div>
    @endif

    <div class="grid grid-cols-3 gap-6">
        {{-- Main Content (2/3) --}}
        <div class="col-span-2">
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center gap-2 mb-2">
                    <span class="inline-flex px-2 py-0.5 text-xs bg-indigo-50 text-indigo-600 rounded-full font-medium">
                        {{ __(App\Models\Project::categories()[$project->category] ?? $project->category) }}
                    </span>
                    <span class="inline-block px-2 py-0.5 text-xs rounded-full font-medium
                        @if($project->status === 'open') bg-green-50 text-green-600
                        @elseif($project->status === 'in_progress') bg-yellow-50 text-yellow-600
                        @else bg-gray-100 text-gray-500
                        @endif">{{ __('project.status_' . $project->status) }}</span>
                </div>
                <h1 class="text-xl font-bold text-gray-900 mb-3">{{ $project->title }}</h1>
                <div class="prose prose-sm max-w-none text-gray-700">{!! nl2br(e($project->description)) !!}</div>

                @if($project->skills)
                <div class="flex items-center flex-wrap gap-2 mt-4 pt-4 border-t">
                    @foreach($project->skills as $skill)
                        <span class="inline-flex px-2 py-0.5 text-xs bg-gray-100 text-gray-600 rounded-full">{{ $skill }}</span>
                    @endforeach
                </div>
                @endif
            </div>

            {{-- Bids Section --}}
            @if($project->bids->count() > 0)
            <div class="bg-white rounded-lg shadow-sm p-6 mt-4">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">{{ __('project.bids') }} ({{ $project->bids->count() }})</h2>
                @foreach($project->bids as $bid)
                <div class="border-b last:border-b-0 py-4 flex items-start justify-between gap-4">
                    <div class="flex-1">
                        <div class="flex items-center gap-2">
                            <span class="font-medium text-gray-900">{{ $bid->developer->name }}</span>
                            @if($bid->developer->isVerified())
                                <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 bg-blue-100 text-blue-700 text-xs font-medium rounded" title="{{ __('verification.status_approved') }}">&#127970; {{ __('verification.verified_badge') }}</span>
                            @endif
                            @php $rating = $bidderRatings[$bid->developer_id] ?? ['avg' => 0, 'count' => 0]; @endphp
                            @if($rating['count'] > 0)
                                <span class="text-xs text-yellow-600" title="{{ $rating['avg'] }}/5 ({{ $rating['count'] }})">
                                    &#9733; {{ $rating['avg'] }}
                                    <span class="text-gray-400">({{ $rating['count'] }})</span>
                                </span>
                            @else
                                <span class="text-xs text-gray-400">{{ __('project.no_reviews') }}</span>
                            @endif
                            @if($bid->isAccepted())
                                <span class="px-2 py-0.5 text-xs bg-green-50 text-green-600 rounded-full font-medium">{{ __('project.bid_accepted') }}</span>
                            @endif
                        </div>
                        <p class="text-sm text-gray-500 mt-1">{{ Str::limit($bid->proposal, 200) }}</p>
                        @if($bid->delivery_days)
                            <span class="text-xs text-gray-400">{{ __('project.delivery') }}: {{ $bid->delivery_days }} {{ __('project.days') }}</span>
                        @endif
                    </div>
                    <div class="text-right shrink-0">
                        <div class="font-bold text-gray-900">{{ $bid->currency }} {{ number_format($bid->amount) }}</div>
                        {{-- Client: Accept bid button --}}
                        @auth
                        @if($project->isOpen() && $project->client_id === auth()->id() && $bid->isPending())
                            <form method="POST" action="{{ route('projects.accept-bid', $bid) }}" class="mt-2"
                                  onsubmit="return confirm('{{ __('project.confirm_accept_bid') }}')">
                                @csrf
                                <button type="submit" class="px-3 py-1.5 bg-green-600 text-white text-xs font-medium rounded-lg hover:bg-green-700">
                                    {{ __('project.accept_bid') }}
                                </button>
                            </form>
                        @endif
                        @endauth
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </div>

        {{-- Sidebar (1/3) --}}
        <div>
            <div class="bg-white rounded-lg shadow-sm p-5 space-y-4">
                <div>
                    <span class="text-xs text-gray-400">{{ __('project.budget') }}</span>
                    @if($project->budget_min && $project->budget_max)
                        <div class="text-lg font-bold text-gray-900">{{ $project->currency }} {{ number_format($project->budget_min) }} - {{ number_format($project->budget_max) }}</div>
                    @elseif($project->budget_max)
                        <div class="text-lg font-bold text-gray-900">{{ __('project.up_to') }} {{ $project->currency }} {{ number_format($project->budget_max) }}</div>
                    @else
                        <div class="text-sm text-gray-500">{{ __('project.budget_negotiable') }}</div>
                    @endif
                </div>
                @if($project->duration)
                <div>
                    <span class="text-xs text-gray-400">{{ __('project.duration') }}</span>
                    <div class="text-sm font-medium text-gray-900">{{ __(App\Models\Project::durations()[$project->duration] ?? $project->duration) }}</div>
                </div>
                @endif
                <div>
                    <span class="text-xs text-gray-400">{{ __('project.posted_by') }}</span>
                    <div class="text-sm font-medium text-gray-900 flex items-center gap-1.5">
                        {{ $project->client->name }}
                        @if($project->client->isVerified())
                            <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 bg-blue-100 text-blue-700 text-xs font-medium rounded" title="{{ __('verification.status_approved') }}">&#127970; {{ __('verification.verified_badge') }}</span>
                        @endif
                    </div>
                </div>
                <div>
                    <span class="text-xs text-gray-400">{{ __('project.posted_at') }}</span>
                    <div class="text-sm text-gray-600">{{ $project->created_at->diffForHumans() }}</div>
                </div>
                <div>
                    <span class="text-xs text-gray-400">{{ __('project.bids') }}</span>
                    <div class="text-sm font-medium text-gray-900">{{ $project->bid_count }}</div>
                </div>

                {{-- Bid Form (only for developers on open projects) --}}
                @auth
                @if($project->isOpen() && auth()->user()->isDeveloper() && $project->client_id !== auth()->id())
                    @if($myBid)
                        <div class="p-3 bg-blue-50 rounded-lg text-sm text-blue-700">
                            {{ __('project.your_bid') }}: <strong>{{ $myBid->currency }} {{ number_format($myBid->amount) }}</strong>
                            <span class="block text-xs mt-1 text-blue-500">{{ __('project.bid_status_' . $myBid->status) }}</span>
                        </div>
                    @else
                        <div class="border-t pt-4">
                            <h3 class="text-sm font-semibold text-gray-900 mb-3">{{ __('project.place_bid') }}</h3>
                            <form method="POST" action="{{ route('projects.bid', $project) }}" class="space-y-3">
                                @csrf
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">{{ __('project.your_price') }} *</label>
                                    <input type="number" name="amount" min="1" step="0.01" required
                                           class="w-full border-gray-300 rounded-lg shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">{{ __('project.delivery_days') }}</label>
                                    <input type="number" name="delivery_days" min="1" max="365"
                                           class="w-full border-gray-300 rounded-lg shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">{{ __('project.proposal') }} *</label>
                                    <textarea name="proposal" rows="3" required maxlength="5000"
                                              class="w-full border-gray-300 rounded-lg shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500"
                                              placeholder="{{ __('project.proposal_placeholder') }}"></textarea>
                                </div>
                                <button type="submit" class="w-full px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                                    {{ __('project.submit_bid') }}
                                </button>
                            </form>
                        </div>
                    @endif
                @elseif(!$project->isOpen())
                    <div class="p-3 bg-gray-50 rounded-lg text-sm text-gray-500">{{ __('project.project_closed_for_bids') }}</div>
                @elseif(!auth()->user()->isDeveloper())
                    <div class="p-3 bg-gray-50 rounded-lg text-sm text-gray-500">{{ __('project.developer_only_bid') }}</div>
                @endif

                {{-- Client: Cancel project --}}
                @if($project->isOpen() && $project->client_id === auth()->id())
                <form method="POST" action="{{ route('projects.cancel', $project) }}" class="mt-2"
                      onsubmit="return confirm('{{ __('project.confirm_cancel') }}')">
                    @csrf
                    <button type="submit" class="w-full px-4 py-2 bg-gray-100 text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-200 transition">
                        {{ __('project.cancel_project') }}
                    </button>
                </form>
                @endif
                @endauth

                @guest
                <div class="p-3 bg-gray-50 rounded-lg text-sm text-gray-500 text-center">
                    <a href="{{ route('login') }}" class="text-indigo-600 hover:text-indigo-800">{{ __('project.login_to_bid') }}</a>
                </div>
                @endguest
            </div>
        </div>
    </div>

    {{-- Recommended Technical Experts --}}
    @if($experts && $experts->count() > 0)
    <div class="bg-white rounded-xl shadow-sm p-5">
        <h2 class="font-semibold text-gray-900 mb-3">{{ __('project.recommended_experts') }}</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            @foreach($experts as $expert)
            <div class="flex items-start gap-3 p-3 border rounded-lg">
                @if($expert->avatar)
                    <img src="{{ asset('storage/' . $expert->avatar) }}" class="w-10 h-10 rounded-full object-cover flex-shrink-0" alt="{{ $expert->name }}">
                @else
                    <div class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-700 font-bold flex-shrink-0">
                        {{ mb_substr($expert->name, 0, 1) }}
                    </div>
                @endif
                <div class="min-w-0">
                    <p class="text-sm font-medium text-gray-900 truncate">{{ $expert->name }}</p>
                    <p class="text-xs text-indigo-600">{{ $expert->specialty }}</p>
                    @if($expert->description)
                        <p class="text-xs text-gray-500 mt-0.5 line-clamp-2">{{ Str::limit($expert->description, 60) }}</p>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif
</div>
@endsection
