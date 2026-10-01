{{--
    First run of the Windows app (DesktopSetupController): the school and
    its administrator. Offline: no web fonts or scripts, only local files.
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
        body { margin: 0; font-family: "Segoe UI", system-ui, -apple-system, Roboto, Arial, sans-serif; background: #f1f5f9; color: #0f172a; }
        .wrap { max-width: 44rem; margin: 0 auto; padding: 2.5rem 1.25rem 3rem; }
        .brand { display: flex; justify-content: center; margin-bottom: 1.5rem; }
        .brand img { height: 2.75rem; }
        .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 2rem; box-shadow: 0 10px 30px -18px rgba(15, 23, 42, .35); }
        h1 { margin: 0; font-size: 1.5rem; font-weight: 700; letter-spacing: -.01em; }
        .lead { margin: .5rem 0 1.5rem; color: #475569; }
        fieldset { border: 0; margin: 0 0 1.25rem; padding: 0; }
        legend { font-weight: 700; font-size: .95rem; margin-bottom: .75rem; color: #1e3a5f; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: .9rem 1rem; }
        .full { grid-column: 1 / -1; }
        label { display: block; font-size: .85rem; font-weight: 600; margin-bottom: .3rem; color: #334155; }
        input, select { width: 100%; padding: .6rem .75rem; border: 1px solid #cbd5e1; border-radius: 8px; font: inherit; background: #fff; }
        input:focus, select:focus { outline: 2px solid #93c5fd; border-color: #2563eb; }
        .has-error input, .has-error select { border-color: #dc2626; }
        .err { display: block; margin-top: .3rem; color: #b91c1c; font-size: .8rem; }
        .hint { margin-top: .3rem; color: #64748b; font-size: .8rem; }
        .alert { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; border-radius: 8px; padding: .7rem .9rem; margin-bottom: 1.25rem; font-size: .9rem; }
        button { width: 100%; padding: .8rem 1rem; border: 0; border-radius: 10px; background: #2563eb; color: #fff; font: inherit; font-weight: 700; font-size: 1rem; cursor: pointer; }
        button:hover { background: #1d4ed8; }
        .note { margin-top: 1rem; text-align: center; color: #64748b; font-size: .8rem; }
        @media (max-width: 560px) { .grid { grid-template-columns: 1fr; } .card { padding: 1.25rem; } }
    </style>
</head>
<body>
<div class="wrap">
    <div class="brand"><img src="{{ asset('images/schoolhub-logo-light-cropped.svg') }}" alt="SchoolHub"></div>

    <div class="card">
        <h1>Welcome to SchoolHub</h1>
        <p class="lead">Set up your school on this computer. It takes a minute, and you will be signed in as the school administrator straight away.</p>

        @if ($errors->any())
            <div class="alert" role="alert">Please check the highlighted fields and try again.</div>
        @endif

        <form method="POST" action="{{ route('desktop.setup.store') }}" novalidate>
            @csrf

            <fieldset>
                <legend>Your school</legend>
                <div class="grid">
                    <div @class(['full', 'has-error' => $errors->has('school_name')])>
                        <label for="school_name">School name</label>
                        <input id="school_name" name="school_name" value="{{ old('school_name') }}" required maxlength="150" autofocus>
                        @error('school_name')<span class="err">{{ $message }}</span>@enderror
                        <div class="hint">As it should appear on receipts, report cards and ID cards.</div>
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
                    <div @class(['has-error' => $errors->has('city')])>
                        <label for="city">Town or district</label>
                        <input id="city" name="city" value="{{ old('city') }}" required maxlength="100" placeholder="e.g. Wakiso">
                        @error('city')<span class="err">{{ $message }}</span>@enderror
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
                    <div @class(['full', 'has-error' => $errors->has('email')])>
                        <label for="email">Email</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email">
                        @error('email')<span class="err">{{ $message }}</span>@enderror
                        <div class="hint">You sign in with this email and password.</div>
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
    </div>

    <p class="note">Everything is kept on this computer. Back it up from Backups in the menu, onto a flash disk.</p>
</div>
</body>
</html>
