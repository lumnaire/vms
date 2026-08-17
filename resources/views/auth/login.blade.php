<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Virac Public Market</title>
    <link rel="icon" href="{{ asset('logo.png') }}" type="image/png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link
        href="https://fonts.googleapis.com/css2?family=Libre+Baskerville:wght@400;700&family=Inter:wght@300;400;500;600&display=swap"
        rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        .brand-font {
            font-family: 'Libre Baskerville', serif;
        }

        .ocean-bg {
            /* This creates a dark overlay over your background image */
            background: linear-gradient(rgba(15, 23, 42, 0.8), rgba(15, 23, 42, 0.8)),
                url('{{ asset('bg.jpg') }}');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
        }

        .modern-card {
            background: #ffffff;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        input:focus {
            outline: none;
            border-color: #0ea5e9;
            box-shadow: 0 0 0 4px rgba(14, 165, 233, 0.1);
        }

        .btn-login {
            background: #0f172a;
            /* Sleek dark button to match the overlay */
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .btn-login:hover {
            background: #1e293b;
            transform: translateY(-1px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.2);
        }
    </style>
</head>

<body class="ocean-bg min-h-screen flex items-center justify-center px-4">

    <div class="w-full max-w-md py-12">

        {{-- Header --}}
        <div class="text-center mb-8">
            <div class="mb-4">
                <img src="{{ asset('logo.png') }}" alt="Virac Public Market Logo"
                    class="w-20 h-20 object-contain mx-auto drop-shadow-lg">
            </div>
            <h1 class="brand-font text-white text-3xl font-bold tracking-tight">Virac Public Market</h1>
            <p class="text-sky-300/80 text-xs mt-2 font-semibold tracking-[0.2em] uppercase">Commodity Supply & Price
                Monitoring</p>
        </div>

        {{-- Card --}}
        <div class="modern-card rounded-3xl p-10">

            <div class="mb-8">
                <h2 class="text-slate-900 text-2xl font-bold">Welcome back</h2>
                <p class="text-slate-500 text-sm mt-1">Please enter your details to sign in.</p>
            </div>

            {{-- Session / General Error --}}
            @if (session('error'))
                <div
                    class="mb-6 px-4 py-3 rounded-xl bg-red-50 border border-red-100 text-red-600 text-sm flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd"
                            d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1-2 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"
                            clip-rule="evenodd" />
                    </svg>
                    {{ session('error') }}
                </div>
            @endif

            <form method="POST" action="{{ route('login.post') }}" class="space-y-6">
                @csrf

                {{-- Username --}}
                <div>
                    <label for="username" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                        Username
                    </label>
                    <input type="text" id="username" name="username" value="{{ old('username') }}"
                        autocomplete="username" autofocus placeholder="yourname123"
                        class="w-full px-4 py-3 rounded-xl bg-slate-50 border 
                               {{ $errors->has('username') ? 'border-red-300' : 'border-slate-200' }}
                               text-slate-900 placeholder-slate-400 text-sm transition-all">
                    @error('username')
                        <p class="mt-2 text-xs text-red-500 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Password --}}
                <div>
                    <label for="password" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                        Password
                    </label>
                    <input type="password" id="password" name="password" autocomplete="current-password"
                        placeholder="••••••••"
                        class="w-full px-4 py-3 rounded-xl bg-slate-50 border 
                               {{ $errors->has('password') ? 'border-red-300' : 'border-slate-200' }}
                               text-slate-900 placeholder-slate-400 text-sm transition-all">
                    @error('password')
                        <p class="mt-2 text-xs text-red-500 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Remember Me --}}
                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2 cursor-pointer group">
                        <input type="checkbox" id="remember" name="remember"
                            class="w-4 h-4 rounded border-slate-300 text-slate-900 focus:ring-slate-900 cursor-pointer">
                        <span class="text-sm text-slate-500 group-hover:text-slate-700 transition-colors">Remember
                            me</span>
                    </label>
                </div>

                {{-- Submit --}}
                <button type="submit"
                    class="btn-login w-full py-3.5 rounded-xl text-white font-bold text-sm tracking-wide shadow-lg">
                    Sign In
                </button>

            </form>
        </div>


    </div>

</body>

</html>
