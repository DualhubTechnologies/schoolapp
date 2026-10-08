@php
    $jobs = $this->jobs();
@endphp

<x-filament-widgets::widget>
    @if ($jobs)
        @if ($this->hidden())
            <div class="wg-collapsed">
                <x-filament::icon icon="heroicon-o-light-bulb" class="wg-collapsed-icon" />
                <span>Not sure where to start?</span>
                <button type="button" wire:click="showTips" class="wg-link">Show the step-by-step guide</button>
            </div>
        @else
            <x-filament::section>
                <x-slot name="heading">
                    {{ $this->isNewUser() ? 'Welcome! Here is your work, step by step' : 'Your work, step by step' }}
                </x-slot>
                <x-slot name="description">
                    {{ $this->isNewUser()
                        ? 'These are the jobs your login is set up for. Start with step 1 and click a step to open it. You can always come back here from Dashboard on the left.'
                        : 'The jobs your login is set up for, in the order they are done. Click a step to open it.' }}
                </x-slot>
                <x-slot name="afterHeader">
                    <x-filament::button wire:click="hideTips" color="gray" size="sm" outlined icon="heroicon-m-eye-slash">
                        Hide guide
                    </x-filament::button>
                </x-slot>

                <div class="wg-jobs">
                    @foreach ($jobs as $job)
                        <div class="wg-job">
                            <div class="wg-job-head">
                                <x-filament::icon :icon="$job['icon']" class="wg-job-icon" />
                                {{ $job['title'] }}
                            </div>
                            <ol class="wg-steps">
                                @foreach ($job['steps'] as $step)
                                    <li>
                                        <a href="{{ $step['url'] }}" @class(['wg-step', 'is-done' => $step['done'] === true, 'is-due' => $step['done'] === false])>
                                            <span class="wg-mark">
                                                @if ($step['done'] === true)
                                                    <x-filament::icon icon="heroicon-m-check" />
                                                @else
                                                    {{ $loop->iteration }}
                                                @endif
                                            </span>
                                            <span class="wg-body">
                                                <strong>
                                                    {{ $step['title'] }}
                                                    @if ($step['done'] === true)
                                                        <em class="wg-tag is-done">Done</em>
                                                    @elseif ($step['done'] === false)
                                                        <em class="wg-tag is-due">To do</em>
                                                    @endif
                                                </strong>
                                                <span>{{ $step['text'] }}</span>
                                            </span>
                                            <x-filament::icon icon="heroicon-m-chevron-right" class="wg-chevron" />
                                        </a>
                                    </li>
                                @endforeach
                            </ol>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        @endif
    @endif

    <style>
        .wg-collapsed { display: flex; align-items: center; gap: .5rem; flex-wrap: wrap; padding: .7rem 1rem; border: 1px dashed #cbd5e1; border-radius: .75rem; font-size: .875rem; color: #475569; }
        .wg-collapsed-icon { width: 1.1rem; height: 1.1rem; color: #f59e0b; }
        .wg-link { color: #1a5fa8; font-weight: 600; text-decoration: underline; }
        .wg-jobs { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(min(100%, 19rem), 1fr)); }
        .wg-job-head { display: flex; align-items: center; gap: .45rem; font-weight: 700; color: #13294b; margin-bottom: .5rem; font-size: .95rem; }
        .wg-job-icon { width: 1.2rem; height: 1.2rem; color: #1a5fa8; }
        .wg-steps { display: grid; gap: .45rem; }
        .wg-step { display: flex; align-items: center; gap: .7rem; padding: .65rem .75rem; border: 1px solid #e3e9f2; border-radius: .7rem; transition: border-color .15s, background .15s; }
        .wg-step:hover { border-color: #93c5fd; background: #f5f9ff; }
        .wg-step.is-due { border-color: #fcd34d; background: #fffbeb; }
        .wg-step.is-done { opacity: .7; }
        .wg-mark { flex: none; display: grid; place-items: center; width: 1.7rem; height: 1.7rem; border-radius: 50%; background: #eef2f7; color: #475569; font-size: .8rem; font-weight: 700; }
        .wg-step.is-done .wg-mark { background: #dcfce7; color: #166534; }
        .wg-step.is-due .wg-mark { background: #f59e0b; color: #fff; }
        .wg-mark svg { width: 1rem; height: 1rem; }
        .wg-body { flex: 1; min-width: 0; }
        .wg-body strong { display: flex; align-items: center; gap: .4rem; flex-wrap: wrap; font-size: .9rem; color: #13294b; }
        .wg-body > span { display: block; font-size: .8rem; color: #64748b; line-height: 1.35; margin-top: .1rem; }
        .wg-tag { font-style: normal; font-size: .68rem; font-weight: 700; padding: .05rem .45rem; border-radius: 99px; }
        .wg-tag.is-done { background: #dcfce7; color: #166534; }
        .wg-tag.is-due { background: #fef3c7; color: #92400e; }
        .wg-chevron { flex: none; width: 1rem; height: 1rem; color: #94a3b8; }
        .dark .wg-step { border-color: rgb(255 255 255 / .1); }
        .dark .wg-step:hover { background: rgb(255 255 255 / .04); }
        .dark .wg-step.is-due { background: rgb(245 158 11 / .08); border-color: rgb(245 158 11 / .4); }
        .dark .wg-job-head, .dark .wg-body strong { color: #e2e8f0; }
        .dark .wg-body > span, .dark .wg-collapsed { color: #94a3b8; }
        .dark .wg-mark { background: rgb(255 255 255 / .08); color: #cbd5e1; }
    </style>
</x-filament-widgets::widget>
