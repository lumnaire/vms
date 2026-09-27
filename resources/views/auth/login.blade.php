<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Virac Public Market</title>
    <link rel="icon" href="{{ asset('logo.png') }}" type="image/png">

    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&amp;display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    @vite(['resources/css/app.css'])

    <style>
        /* Full-bleed market photo with a navy scrim, matching the sidebar
           gradient so the login screen belongs to the same product. */
        .auth-bg {
            background-image:
                linear-gradient(rgb(10 31 60 / 0.82), rgb(10 31 60 / 0.9)),
                url('{{ asset('bg.jpg') }}');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
        }
    </style>
</head>

<body class="auth-bg min-h-screen flex items-center justify-center px-4 py-12">

    <div class="w-full max-w-md">

        {{-- Brand --}}
        <div class="text-center mb-8">
            <img src="{{ asset('logo.png') }}" alt="Virac Public Market Logo"
                 class="w-20 h-20 object-contain mx-auto drop-shadow-lg mb-4">
            <h1 class="text-white text-3xl font-extrabold tracking-tight">Virac Public Market</h1>
            <p class="text-brand-300 text-[11px] mt-2 font-bold uppercase tracking-[0.2em]">
                Commodity Supply &amp; Price Monitoring
            </p>
        </div>

        {{-- Card --}}
        <div class="vpm-card vpm-card-padded">

            <div class="mb-7">
                <h2 class="text-slate-900 text-2xl font-bold">Welcome back</h2>
                <p class="text-slate-500 text-sm mt-1">Please enter your details to sign in.</p>
            </div>

            <x-alert variant="error" :message="session('error')" class="!mb-6" />

            <form method="POST" action="{{ route('login.post') }}" class="space-y-5">
                @csrf

                <x-form-input
                    name="username"
                    label="Username"
                    icon="bi-person"
                    autocomplete="username"
                    autofocus
                    placeholder="yourname123"
                    :value="old('username')"
                    :required="true" />

                <x-form-input
                    name="password"
                    label="Password"
                    type="password"
                    icon="bi-lock"
                    autocomplete="current-password"
                    placeholder="••••••••" />

                <label class="flex items-center gap-2 cursor-pointer group">
                    <input type="checkbox" id="remember" name="remember"
                           class="vpm-checkbox">
                    <span class="text-sm text-slate-500 group-hover:text-slate-700 transition-colors">Remember me</span>
                </label>

                <x-btn type="submit" class="w-full !py-3.5 !rounded-xl tracking-wide">
                    <x-icon name="bi-box-arrow-in-right" />
                    Sign In
                </x-btn>

            </form>
        </div>

        <p class="text-center text-white/45 text-xs mt-6">
            Virac Public Market &middot; Catanduanes State University
        </p>

    </div>

</body>

</html>
