{{--
    First run of the Windows app (DesktopSetupController): a new school and
    its administrator, or a school already on SchoolHub online joining with
    its account. Sized to fit the app's window without scrolling. Offline:
    no web fonts or scripts, only local files.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Set up SchoolHub</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: "Segoe UI", system-ui, -apple-system, Roboto, Arial, sans-serif; background: #f1f5f9; color: #0f172a; font-size: 14px; }
        .wrap { max-width: 56rem; min-height: 100vh; margin: 0 auto; padding: 1.1rem 1.25rem 1rem; display: flex; flex-direction: column; justify-content: center; }
        .brand { display: flex; justify-content: center; margin-bottom: .8rem; }
        .brand img { height: 2rem; }
        .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 1.25rem 1.5rem 1.4rem; box-shadow: 0 10px 30px -18px rgba(15, 23, 42, .35); }
        h1 { margin: 0; font-size: 1.3rem; font-weight: 700; letter-spacing: -.01em; }
        .lead { margin: .25rem 0 .9rem; color: #475569; }
        .tabs { display: grid; grid-template-columns: 1fr 1fr; gap: .4rem; background: #f1f5f9; padding: .3rem; border-radius: 10px; margin-bottom: 1rem; }
        .tabs a { text-align: center; padding: .55rem .5rem; border-radius: 8px; color: #475569; font-weight: 600; text-decoration: none; }
        .tabs a.on { background: #fff; color: #1d4ed8; box-shadow: 0 1px 3px rgba(15, 23, 42, .15); }
        .tabs small { display: block; font-weight: 400; font-size: .75rem; color: #64748b; }
        fieldset { border: 0; margin: 0 0 .8rem; padding: 0; }
        legend { font-weight: 700; font-size: .85rem; margin-bottom: .45rem; color: #1e3a5f; }
        .grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: .65rem .9rem; }
        .span2 { grid-column: span 2; }
        label { display: block; font-size: .8rem; font-weight: 600; margin-bottom: .2rem; color: #334155; }
        input, select { width: 100%; padding: .5rem .65rem; border: 1px solid #cbd5e1; border-radius: 8px; font: inherit; background: #fff; }
        input:focus, select:focus { outline: 2px solid #93c5fd; border-color: #2563eb; }
        .has-error input, .has-error select { border-color: #dc2626; }
        .err { display: block; margin-top: .2rem; color: #b91c1c; font-size: .78rem; }
        .alert { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; border-radius: 8px; padding: .55rem .8rem; margin-bottom: .8rem; font-size: .85rem; }
        .info { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e3a8a; border-radius: 8px; padding: .6rem .8rem; margin-bottom: .9rem; font-size: .85rem; line-height: 1.45; }
        button { width: 100%; padding: .7rem 1rem; border: 0; border-radius: 10px; background: #2563eb; color: #fff; font: inherit; font-weight: 700; font-size: .95rem; cursor: pointer; margin-top: .2rem; }
        button:hover { background: #1d4ed8; }
        .note { margin: .7rem 0 0; text-align: center; color: #64748b; font-size: .78rem; }
        .code { font-family: ui-monospace, Consolas, monospace; letter-spacing: .1em; text-transform: uppercase; }
        @media (max-width: 720px) { .grid { grid-template-columns: 1fr 1fr; } .span2 { grid-column: 1 / -1; } }
        @media (max-width: 480px) { .grid { grid-template-columns: 1fr; } .card { padding: 1rem; } }
    </style>
</head>
<body>
<div class="wrap">
    <div class="brand"><img src="{{ asset('images/schoolhub-logo-light-cropped.svg') }}" alt="SchoolHub"></div>

    <div class="card">
        <h1>Welcome to SchoolHub</h1>
        <p class="lead">Set up your school on this computer. It takes a minute, and you are signed in straight away.</p>

        <nav class="tabs" aria-label="How to set up">
            <a href="{{ route('desktop.setup') }}" @class(['on' => $mode === 'new'])>New school<small>Start fresh on this computer</small></a>
            <a href="{{ route('desktop.setup.connect') }}" @class(['on' => $mode === 'online'])>Already on SchoolHub online<small>Bring your school here and keep it in sync</small></a>
        </nav>

        @if ($errors->any() && $mode === 'new')
            <div class="alert" role="alert">Please check the highlighted fields and try again.</div>
        @endif

        @if ($mode === 'online')
            <div class="info">Sign in with <b>your SchoolHub online email and password</b> and the <b>link code</b> from SchoolHub (or from Settings → Windows app online). Everything comes to this computer and stays in sync, staff sign in here with their online passwords, and your online subscription covers it. Needs internet for this step.</div>

            <form method="POST" action="{{ route('desktop.setup.connect.store') }}" novalidate>
                @csrf
                <div class="grid">
                    <div @class(['has-error' => $errors->has('code')])>
                        <label for="code">Link code</label>
                        <input id="code" name="code" class="code" value="{{ old('code') }}" required maxlength="20" placeholder="XXXX-XXXX" autocomplete="off" autofocus>
                    </div>
                    <div @class(['has-error' => $errors->has('email')])>
                        <label for="email">Online email</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email">
                        @error('email')<span class="err">{{ $message }}</span>@enderror
                    </div>
                    <div @class(['has-error' => $errors->has('password')])>
                        <label for="password">Online password</label>
                        <input id="password" name="password" type="password" required autocomplete="current-password">
                        @error('password')<span class="err">{{ $message }}</span>@enderror
                    </div>
                </div>
                @error('code')<div class="alert" role="alert" style="margin: .7rem 0 0">{{ $message }}</div>@enderror
                <button type="submit" style="margin-top: .9rem">Connect and bring my school here</button>
            </form>
            <p class="note">The first sync can take a few minutes for a big school. Everyone signs in on this computer with the same email and password as online.</p>
        @else
            <form method="POST" action="{{ route('desktop.setup.store') }}" novalidate>
                @csrf

                <fieldset>
                    <legend>Your school</legend>
                    <div class="grid">
                        <div @class(['span2', 'has-error' => $errors->has('school_name')])>
                            <label for="school_name">School name <span style="font-weight: 400; color: #64748b">(as on receipts and report cards)</span></label>
                            <input id="school_name" name="school_name" value="{{ old('school_name') }}" required maxlength="150" autofocus>
                            @error('school_name')<span class="err">{{ $message }}</span>@enderror
                        </div>
                        <div @class(['has-error' => $errors->has('school_type')])>
                            <label for="school_type">School type</label>
                            <select id="school_type" name="school_type" required>
                                <option value="">Choose…</option>
                                @foreach ($types as $value => $label)
                                    <option value="{{ $value }}" @selected(old('school_type') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('school_type')<span class="err">{{ $message }}</span>@enderror
                        </div>
                    </div>
                </fieldset>

                <fieldset>
                    <legend>Your account (the school administrator)</legend>
                    <div class="grid">
                        <div @class(['has-error' => $errors->has('name')])>
                            <label for="name">Your name</label>
                            <input id="name" name="name" value="{{ old('name') }}" required maxlength="150" autocomplete="name">
                            @error('name')<span class="err">{{ $message }}</span>@enderror
                        </div>
                        <div @class(['has-error' => $errors->has('phone')])>
                            <label for="phone">Phone</label>
                            <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" required placeholder="07XX XXX XXX" autocomplete="tel">
                            @error('phone')<span class="err">{{ $message }}</span>@enderror
                        </div>
                        <div @class(['has-error' => $errors->has('city')])>
                            <label for="city">Town or district</label>
                            <input id="city" name="city" value="{{ old('city') }}" required maxlength="100" placeholder="e.g. Wakiso">
                            @error('city')<span class="err">{{ $message }}</span>@enderror
                        </div>
                        <div @class(['has-error' => $errors->has('email')])>
                            <label for="email">Email <span style="font-weight: 400; color: #64748b">(to sign in)</span></label>
                            <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email">
                            @error('email')<span class="err">{{ $message }}</span>@enderror
                        </div>
                        <div @class(['has-error' => $errors->has('password')])>
                            <label for="password">Password</label>
                            <input id="password" name="password" type="password" required autocomplete="new-password">
                            @error('password')<span class="err">{{ $message }}</span>@enderror
                        </div>
                        <div>
                            <label for="password_confirmation">Confirm password</label>
                            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
                        </div>
                    </div>
                </fieldset>

                <button type="submit">Set up my school</button>
            </form>
            <p class="note">Everything is kept on this computer. Back it up from Backups in the menu, onto a flash disk.</p>
        @endif
    </div>
</div>
</body>
</html>
