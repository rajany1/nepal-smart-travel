@extends('admin.layout')
@section('title', 'Settings')

@section('content')
    <div class="space-y-6">
        <!-- XP Settings -->
        <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-6 max-w-3xl">
            <h2 class="text-xl font-semibold text-slate-900 mb-4">XP Settings</h2>

            <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-6">
                @csrf

                <div>
                    <label class="block text-sm font-medium text-slate-700">Report Approval XP</label>
                    <input type="number" name="report_approval_xp" value="{{ old('report_approval_xp', $settings['report_approval_xp']) }}" min="0" max="1000" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500" />
                    @error('report_approval_xp')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700">Alert Post XP</label>
                    <input type="number" name="alert_post_xp" value="{{ old('alert_post_xp', $settings['alert_post_xp']) }}" min="0" max="1000" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500" />
                    @error('alert_post_xp')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700">Review XP</label>
                    <input type="number" name="review_xp" value="{{ old('review_xp', $settings['review_xp']) }}" min="0" max="1000" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500" />
                    @error('review_xp')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>

                <div class="flex justify-end gap-3">
                    <a href="{{ route('admin.dashboard') }}" class="inline-flex items-center px-4 py-2 border border-slate-300 text-sm font-medium rounded-xl text-slate-700 bg-white hover:bg-slate-50">Cancel</a>
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-sm font-medium text-white rounded-xl hover:bg-indigo-700">Save settings</button>
                </div>
            </form>
        </div>
    </div>
@endsection
