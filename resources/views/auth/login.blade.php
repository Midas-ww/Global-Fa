<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Global Financial Consolidation | Mekar Jaya Group</title>
    
    <!-- Favicon & Icons persis SIR-IQOS & SPBU -->
    <link rel="icon" type="image/png" href="/assets/images/logo.png">
    <link rel="apple-touch-icon" href="/assets/images/logo.png">

    <!-- Fonts: Instrument Sans (Identik 100% dengan SIR-IQOS & API-SPBU) -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:ital,wght@0,400..700;1,400..700&display=swap" rel="stylesheet">
    
    @fonts

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Instrument Sans"', 'ui-sans-serif', 'system-ui', 'sans-serif', '"Apple Color Emoji"', '"Segoe UI Emoji"', '"Segoe UI Symbol"', '"Noto Color Emoji"'],
                        mono: ['"Instrument Sans"', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            green: '#059669',
                            yellow: '#ffd800',
                            lime: '#ecfdf5',
                            'lime-dark': '#065f46',
                            surface: '#f8faf8',
                            border: '#edf0ee',
                        }
                    }
                }
            }
        }
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <style>
        /* Standarisasi Typography: Instrument Sans persis SIR-IQOS & API-SPBU */
        body, button, input, select, textarea, h1, h2, h3, p, span, a, label {
            font-family: 'Instrument Sans', ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
        }

        html {
            font-size: 16px;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        @media (max-width: 1599px) {
            html { font-size: 13.5px; }
        }
    </style>
</head>
<body class="bg-[#f8faf8] min-h-screen flex items-center justify-center p-4 relative font-sans text-slate-800">

    <div class="w-full max-w-md relative z-10">

        <!-- LOGO & BRAND -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center p-3 rounded-2xl bg-white shadow-xs mb-3 border border-[#edf0ee]">
                <img src="/assets/images/logo-full.png" alt="Mekar Jaya Group" class="h-11 sm:h-12 w-auto object-contain max-w-[220px]" />
            </div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">GLOBAL-FA</h1>
            <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mt-1">Mekar Jaya Group Financial Hub</p>
            <div class="flex items-center justify-center gap-2 mt-2 text-[10px] font-mono font-bold">
                <span class="px-2 py-0.5 rounded bg-rose-50 text-rose-700 border border-rose-200">SPBU</span>
                <span class="text-slate-300">•</span>
                <span class="px-2 py-0.5 rounded bg-teal-50 text-teal-800 border border-teal-200">RETAIL</span>
                <span class="text-slate-300">•</span>
                <span class="px-2 py-0.5 rounded bg-sky-50 text-sky-700 border border-sky-200">FMCG</span>
                <span class="text-slate-300">•</span>
                <span class="px-2 py-0.5 rounded bg-amber-50 text-amber-700 border border-amber-200">XTRANS</span>
            </div>
        </div>

        <!-- LOGIN CARD (SOLID FLAT - NO GRADIENT) -->
        <div class="bg-white border border-[#edf0ee] rounded-3xl p-7 shadow-xs space-y-6">

            <!-- ALERTS -->
            @if(session('error'))
            <div class="p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs flex items-center gap-2">
                <i data-lucide="alert-circle" class="w-4 h-4 shrink-0 text-rose-600"></i>
                <span>{{ session('error') }}</span>
            </div>
            @endif

            @if(session('success'))
            <div class="p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs flex items-center gap-2">
                <i data-lucide="check-circle" class="w-4 h-4 shrink-0 text-emerald-600"></i>
                <span>{{ session('success') }}</span>
            </div>
            @endif

            @if($errors->any())
            <div class="p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs flex items-center gap-2">
                <i data-lucide="alert-circle" class="w-4 h-4 shrink-0 text-rose-600"></i>
                <span>{{ $errors->first() }}</span>
            </div>
            @endif

            <!-- SSO BUTTON (SOLID FLAT - NO GRADIENT) -->
            <div>
                <a href="{{ rtrim(config('services.mkj_superapp.url', env('SUPERAPP_URL', 'http://localhost:8000')), '/') }}/login" class="w-full py-3 px-4 rounded-xl bg-[#ecfdf5] hover:bg-[#d1fae5] text-[#065f46] font-bold text-xs border border-[#a7f3d0] flex items-center justify-center gap-2 transition duration-150 cursor-pointer shadow-2xs">
                    <svg class="w-4 h-4 text-[#065f46]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83z" />
                        <path d="m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65" />
                        <path d="m22 12.65-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65" />
                    </svg>
                    Masuk via Super App Portal (SSO)
                </a>
                <p class="text-center text-[11px] text-slate-400 mt-2">Masuk otomatis melalui Portal Mekar Jaya Group</p>
            </div>

            <!-- DIVIDER -->
            <div class="relative flex items-center justify-center">
                <div class="border-t border-[#edf0ee] w-full"></div>
                <span class="bg-white px-3 text-[11px] font-bold text-slate-400 uppercase tracking-wider absolute">atau akun lokal</span>
            </div>

            <!-- FORM LOGIN MANUAL -->
            <form action="{{ route('login.post') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Email Akun</label>
                    <div class="relative">
                        <i data-lucide="mail" class="w-4 h-4 text-slate-400 absolute left-3.5 top-3.5 pointer-events-none"></i>
                        <input type="email" name="email" value="{{ old('email', 'admin@mekarjaya.com') }}" required class="w-full bg-[#f8faf8] border border-[#edf0ee] rounded-xl pl-10 pr-4 py-2.5 text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-[#059669] focus:ring-1 focus:ring-[#059669]">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Kata Sandi</label>
                    <div class="relative">
                        <i data-lucide="lock" class="w-4 h-4 text-slate-400 absolute left-3.5 top-3.5 pointer-events-none"></i>
                        <input type="password" name="password" value="password" required class="w-full bg-[#f8faf8] border border-[#edf0ee] rounded-xl pl-10 pr-4 py-2.5 text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-[#059669] focus:ring-1 focus:ring-[#059669]">
                    </div>
                </div>

                <div class="flex items-center justify-between text-xs text-slate-500">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="remember" checked class="rounded border-slate-300 text-slate-900 focus:ring-[#059669]">
                        <span>Ingat saya</span>
                    </label>
                    <span class="text-[11px] text-slate-400 font-mono">admin / password</span>
                </div>

                <button type="submit" class="w-full py-3 px-4 rounded-xl bg-white hover:bg-slate-50 text-slate-800 border border-slate-300 font-bold text-xs transition duration-150 flex items-center justify-center gap-2 shadow-xs cursor-pointer">
                    <i data-lucide="log-in" class="w-4 h-4 text-emerald-600"></i>
                    Masuk ke Global-FA
                </button>
            </form>

        </div>

        <div class="text-center text-xs text-slate-400 mt-6">
            &copy; 2026 PT Mekar Jaya Group Holding
        </div>

    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
