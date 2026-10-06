@extends('site.layout')

@section('eyebrow', 'About SchoolHub')
@section('heading', 'SchoolHub Team')
@section('lead', 'The people at '.$contact['company'].' who build SchoolHub and support Ugandan schools.')

@php
    $silhouette = '<svg viewBox="0 0 120 120" aria-hidden="true"><circle cx="60" cy="46" r="22" fill="currentColor"/><path d="M18 120c0-25 19-42 42-42s42 17 42 42Z" fill="currentColor"/></svg>';
    $icons = [
        'linkedin' => ['LinkedIn', '<path d="M20.45 20.45h-3.55v-5.57c0-1.33-.03-3.04-1.85-3.04-1.86 0-2.14 1.45-2.14 2.94v5.67H9.36V9h3.41v1.56h.05c.48-.9 1.64-1.85 3.37-1.85 3.6 0 4.27 2.37 4.27 5.46v6.28ZM5.34 7.43a2.06 2.06 0 1 1 0-4.12 2.06 2.06 0 0 1 0 4.12ZM7.12 20.45H3.56V9h3.56v11.45ZM22.22 0H1.77C.79 0 0 .77 0 1.73v20.54C0 23.23.79 24 1.77 24h20.45c.98 0 1.78-.77 1.78-1.73V1.73C24 .77 23.2 0 22.22 0Z"/>'],
        'facebook' => ['Facebook', '<path d="M24 12.07C24 5.41 18.63 0 12 0S0 5.4 0 12.07C0 18.1 4.39 23.1 10.13 24v-8.44H7.08v-3.49h3.04V9.41c0-3.02 1.8-4.7 4.54-4.7 1.31 0 2.68.24 2.68.24v2.97h-1.5c-1.5 0-1.96.93-1.96 1.89v2.26h3.32l-.53 3.5h-2.8V24C19.62 23.1 24 18.1 24 12.07Z"/>'],
        'x' => ['X', '<path d="M18.9 1.15h3.68l-8.04 9.19L24 22.85h-7.4l-5.8-7.58-6.63 7.58H.48l8.6-9.83L0 1.15h7.59l5.24 6.93 6.07-6.93Zm-1.29 19.5h2.04L6.48 3.24H4.3L17.61 20.65Z"/>'],
    ];
@endphp

@section('content')
    <section class="section">
        <div class="container">
            <div class="team-grid">
                @foreach ($contact['team'] as $member)
                    <article class="team-card">
                        <div class="team-photo">
                            @if ($member['photo'])
                                <img src="{{ asset('images/team/'.$member['photo']) }}" alt="{{ $member['name'] }}" width="200" height="200" loading="lazy">
                            @else
                                {!! $silhouette !!}
                            @endif
                        </div>
                        <h2>{{ $member['name'] }}</h2>
                        <p class="team-role">{{ $member['role'] }}</p>
                        <div class="team-links">
                            @foreach ($icons as $key => [$label, $path])
                                @if ($member[$key] ?? null)
                                    <a href="{{ $member[$key] }}" target="_blank" rel="noopener" aria-label="{{ $member['name'] }} on {{ $label }}"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">{!! $path !!}</svg></a>
                                @endif
                            @endforeach
                            @if ($member['email'] ?? null)
                                <a href="mailto:{{ $member['email'] }}" aria-label="Email {{ $member['name'] }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3.5 6.5 8.5 6.5 8.5-6.5"/></svg></a>
                            @endif
                        </div>
                    </article>
                @endforeach

                <article class="team-card team-card-join">
                    <div class="team-photo">{!! $silhouette !!}</div>
                    <h2>You — Join us</h2>
                    <p class="team-role">Help us bring SchoolHub to every school in Uganda.</p>
                    <div class="team-links">
                        <a href="mailto:{{ $contact['email'] }}?subject={{ rawurlencode('Joining the SchoolHub team') }}" class="btn btn-secondary btn-sm">Get in touch</a>
                    </div>
                </article>
            </div>
        </div>
    </section>
@endsection
