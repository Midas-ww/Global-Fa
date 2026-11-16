<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Global Financial Consolidation | Mekar Jaya Group</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .font-mono { font-family: 'JetBrains Mono', monospace; }
    </style>
</head>
<body class="bg-slate-900 min-h-screen flex items-center justify-center p-4 relative overflow-hidden">

    <!-- Ambient glow backgrounds -->
    <div class="absolute -top-40 -left-40 w-96 h-96 bg-blue-600/20 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute -bottom-40 -right-40 w-96 h-96 bg-indigo-600/20 rounded-full blur-3xl pointer-events-none"></div>

    <div class="w-full max-w-md relative z-10">

        <!-- LOGO & BRAND -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-gradient-to-tr from-blue-600 to-indigo-600 shadow-xl shadow-blue-500/25 mb-3 text-white">
                <i data-lucide="layers" class="w-7 h-7"></i>
            </div>
            <h1 class="text-2xl font-black text-white tracking-tight">GLOBAL-FA</h1>
            <p class="text-xs font-semibold text-blue-400 uppercase tracking-widest mt-1">Mekar Jaya Group Financial Hub</p>
            <p class="text-xs text-slate-400 mt-2">Konsolidasi Laba Rugi (P&L) & Neraca 4 Unit Bisnis</p>
        </div>

        <!-- LOGIN CARD -->
        <div class="bg-slate-800/90 backdrop-blur-xl border border-slate-700/80 rounded-3xl p-7 shadow-2xl space-y-6">

            <!-- ALERTS -->
            @if(session('error'))
            <div class="p-3.5 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-xs flex items-center gap-2">
                <i data-lucide="alert-circle" class="w-4 h-4 shrink-0"></i>
                <span>{{ session('error') }}</span>
            </div>
            @endif

            @if(session('success'))
            <div class="p-3.5 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-xs flex items-center gap-2">
                <i data-lucide="check-circle" class="w-4 h-4 shrink-0"></i>
                <span>{{ session('success') }}</span>
            </div>
            @endif

            @if($errors->any())
            <div class="p-3.5 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-xs flex items-center gap-2">
                <i data-lucide="alert-circle" class="w-4 h-4 shrink-0"></i>
                <span>{{ $errors->first() }}</span>
            </div>
            @endif

            <!-- SSO BUTTON -->
            <div>
                <a href="http://localhost:8000/port" class="w-full py-3 px-4 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white font-bold text-xs shadow-lg shadow-blue-600/30 flex items-center justify-center gap-2 transition duration-150">
                    <i data-lucide="shield-check" class="w-4 h-4"></i>
                    Masuk via Super App Portal (SSO)
                </a>
                <p class="text-center text-[11px] text-slate-400 mt-2">Masuk otomatis tanpa mengetik ulang kata sandi</p>
            </div>

            <!-- DIVIDER -->
            <div class="relative flex items-center justify-center">
                <div class="border-t border-slate-700 w-full"></div>
                <span class="bg-slate-800 px-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider absolute">atau akun lokal</span>
            </div>

            <!-- FORM LOGIN MANUAL -->
            <form action="{{ route('login.post') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1.5">Email Akun</label>
                    <div class="relative">
                        <i data-lucide="mail" class="w-4 h-4 text-slate-500 absolute left-3.5 top-3.5 pointer-events-none"></i>
                        <input type="email" name="email" value="{{ old('email', 'admin@mekarjaya.com') }}" required class="w-full bg-slate-900/80 border border-slate-700 rounded-xl pl-10 pr-4 py-2.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1.5">Kata Sandi</label>
                    <div class="relative">
                        <i data-lucide="lock" class="w-4 h-4 text-slate-500 absolute left-3.5 top-3.5 pointer-events-none"></i>
                        <input type="password" name="password" value="password" required class="w-full bg-slate-900/80 border border-slate-700 rounded-xl pl-10 pr-4 py-2.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    </div>
                </div>

                <div class="flex items-center justify-between text-xs text-slate-400">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="remember" checked class="rounded bg-slate-900 border-slate-700 text-blue-600 focus:ring-blue-500">
                        <span>Ingat saya</span>
                    </label>
                    <span class="text-[11px] text-slate-500 font-mono">admin / password</span>
                </div>

                <button type="submit" class="w-full py-3 px-4 rounded-xl bg-slate-700 hover:bg-slate-600 text-white font-bold text-xs transition duration-150 flex items-center justify-center gap-2">
                    <i data-lucide="log-in" class="w-4 h-4"></i>
                    Masuk ke Global-FA
                </button>
            </form>

        </div>

        <div class="text-center text-xs text-slate-500 mt-6">
            &copy; 2026 PT Mekar Jaya Group Holding • Sistem Terbuka Port 8003
        </div>

    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
