<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-white leading-tight">Portfolio</h2>
    </x-slot>

    <div class="p-5 md:p-7">
        <div class="space-y-4">
            <p class="text-sm text-gray-500 px-1">Finished product photos from every job. Add them from the job page under Finished Product Photos.</p>

            <div class="k-card p-4">
                <form method="GET" action="{{ route('portfolio.index') }}" class="flex flex-col sm:flex-row sm:flex-wrap gap-3">
                    <div class="relative flex-1 min-w-[12rem]">
                        <x-icon name="search" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400" />
                        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search job, title or customer..." class="w-full pl-10 text-sm">
                    </div>
                    <select name="department" onchange="this.form.submit()" class="text-sm">
                        <option value="">All departments</option>
                        @foreach ($departments as $key => $dept)
                            <option value="{{ $key }}" @selected(request('department') === $key)>{{ $dept['label'] }}</option>
                        @endforeach
                    </select>
                    <select name="year" onchange="this.form.submit()" class="text-sm">
                        <option value="">All years</option>
                        @foreach ($years as $y)
                            <option value="{{ $y }}" @selected((int) request('year') === $y)>{{ $y }}</option>
                        @endforeach
                    </select>
                    <label class="inline-flex items-center gap-2 text-sm text-gray-600">
                        <input type="checkbox" name="marketing" value="1" @checked(request()->boolean('marketing')) onchange="this.form.submit()" class="rounded border-gray-300 text-[#C2185B]"> Marketing OK only
                    </label>
                    @if ($photos->total() > 0)
                        <a href="{{ route('portfolio.download', request()->query()) }}" class="inline-flex items-center justify-center gap-1.5 text-sm font-semibold px-4 py-2 rounded-xl border border-[#EFE3DE] text-gray-700 bg-white hover:bg-[#FFF7F3]"><x-icon name="download" class="w-4 h-4" /> Download zip</a>
                    @endif
                </form>
            </div>

            @if ($photos->isEmpty())
                <div class="k-card p-10 text-center text-sm text-gray-400">No photos yet. Open a job and add photos under Finished Product Photos.</div>
            @else
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-3">
                    @foreach ($photos as $photo)
                        @php $job = $photo->job; @endphp
                        <div class="k-card overflow-hidden">
                            <a href="{{ route('jobs.photos.show', [$job, $photo]) }}" target="_blank" rel="noopener">
                                <img src="{{ route('jobs.photos.show', [$job, $photo, 'thumb' => 1]) }}" alt="{{ $photo->caption ?: $job->job_type }}" loading="lazy" class="w-full aspect-square object-cover">
                            </a>
                            <a href="{{ route('jobs.show', $job) }}" class="block px-3 py-2 hover:bg-[#FFF7F3]">
                                <div class="text-xs font-semibold text-gray-900 truncate">{{ $job->job_type }}</div>
                                <div class="text-[11px] text-gray-400 truncate">{{ $job->job_id }} · {{ $job->customer?->company ?: $job->customer?->name }}</div>
                                @unless ($photo->marketing_ok)<div class="text-[11px] text-gray-400">Internal only</div>@endunless
                            </a>
                        </div>
                    @endforeach
                </div>
                <div>{{ $photos->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
