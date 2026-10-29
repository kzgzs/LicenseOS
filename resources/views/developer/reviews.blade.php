@extends('layouts.app')
@section('title', 'Reviews Management')
@section('content')
<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-bold dark:text-white">Reviews Management</h1>
</div>

{{-- Review Stats Summary --}}
<div class="grid md:grid-cols-4 gap-6 mb-8">
    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm">
        <p class="text-sm text-gray-500 dark:text-gray-400">Total Reviews</p>
        <p class="text-2xl font-bold dark:text-gray-200">{{ $reviews->total() }}</p>
    </div>
    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm">
        <p class="text-sm text-gray-500 dark:text-gray-400">Approved</p>
        <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $reviews->where('approved', true)->count() }}</p>
    </div>
    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm">
        <p class="text-sm text-gray-500 dark:text-gray-400">Pending</p>
        <p class="text-2xl font-bold text-yellow-600 dark:text-yellow-400">{{ $reviews->where('approved', false)->count() }}</p>
    </div>
    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm">
        <p class="text-sm text-gray-500 dark:text-gray-400">Average Rating</p>
        <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">
            @if($reviews->count() > 0)
                {{ number_format($reviews->avg('rating'), 1) }} &#9733;
            @else
                - &#9733;
            @endif
        </p>
    </div>
</div>

{{-- Reviews Table --}}
<div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
    <div class="p-4 border-b dark:border-gray-700">
        <h2 class="text-lg font-semibold dark:text-white">All Reviews</h2>
    </div>
    <table class="w-full">
        <thead class="bg-gray-50 dark:bg-gray-700">
            <tr>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-300">ID</th>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-300">Product</th>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-300">Reviewer</th>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-300">Rating</th>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-300">Comment</th>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-300">Status</th>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-300">Date</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody>
            @forelse($reviews as $review)
            <tr class="border-t dark:border-gray-700">
                <td class="px-4 py-3 text-sm dark:text-gray-200">#{{ $review->id }}</td>
                <td class="px-4 py-3 text-sm font-medium dark:text-gray-200">{{ $review->product->name ?? '-' }}</td>
                <td class="px-4 py-3 text-sm dark:text-gray-200">{{ $review->user->name ?? '-' }}</td>
                <td class="px-4 py-3">
                    <span class="text-yellow-500">
                        @for($i = 1; $i <= 5; $i++)
                            @if($i <= $review->rating)&#9733;@else;&#9734;@endif
                        @endfor
                    </span>
                </td>
                <td class="px-4 py-3 text-sm max-w-xs truncate dark:text-gray-200">{{ $review->comment }}</td>
                <td class="px-4 py-3">
                    <span class="px-2 py-1 text-xs rounded-full
                        {{ $review->approved ? 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300' : 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300' }}
                    ">{{ $review->approved ? 'Approved' : 'Pending' }}</span>
                </td>
                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $review->created_at->format('Y-m-d') }}</td>
                <td class="px-4 py-3 text-right">
                    @if(!$review->approved)
                    <form method="POST" action="{{ route('developer.reviews.approve', $review) }}" class="inline">
                        @csrf
                        <button type="submit" class="text-green-600 hover:text-green-800 dark:text-green-400 dark:hover:text-green-300 text-sm mr-2">Approve</button>
                    </form>
                    <form method="POST" action="{{ route('developer.reviews.reject', $review) }}" class="inline">
                        @csrf
                        <button type="submit" class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 text-sm" onclick="return confirm('Reject this review?')">Reject</button>
                    </form>
                    @endif
                </td>
            </tr>
            @empty
            <tr><td colspan="8" class="px-4 py-8 text-center text-gray-400 dark:text-gray-500">No reviews yet</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="p-4 border-t dark:border-gray-700">{{ $reviews->links() }}</div>
</div>
@endsection
