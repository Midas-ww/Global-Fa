<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Dashboard') - Global FA Mekar Jaya Group</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a',
                        }
                    }
                }
            }
        }
    </script>
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-slate-50 text-slate-800 antialiased font-sans flex h-screen overflow-hidden">

    <!-- SIDEBAR -->
    <aside class="w-64 bg-slate-900 text-slate-300 flex flex-col justify-between border-r border-slate-800 shrink-0">
        <div>
            <!-- Brand Header -->
            <div class="h-16 flex items-center px-6 gap-3 border-b border-slate-800 bg-slate-950">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-blue-600 to-indigo-500 flex items-center justify-center text-white shadow-lg shadow-blue-500/30">
                    <i data-lucide="layers" class="w-5 h-5"></i>
                </div>
                <div>
                    <h1 class="font-bold text-white text-sm tracking-wide leading-tight">GLOBAL FA</h1>
                    <p class="text-[10px] text-slate-400 font-medium">Mekar Jaya Holding</p>
                </div>
            </div>

            <!-- Navigation Links -->
            <nav class="p-4 space-y-1.5 text-sm font-medium">
                <div class="px-3 py-2 text-[10px] uppercase font-bold tracking-wider text-slate-400">Konsolidasi Holding</div>
                
                <a href="{{ route('finance.dashboard') }}" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all {{ request()->routeIs('finance.dashboard') ? 'bg-blue-600 text-white shadow-md shadow-blue-600/30 font-semibold' : 'hover:bg-slate-800/80 text-slate-300 hover:text-white' }}">
                    <i data-lucide="layout-dashboard" class="w-4 h-4"></i>
                    <span>Executive Dashboard</span>
                </a>

                <a href="{{ route('finance.pnl') }}" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all {{ request()->routeIs('finance.pnl') ? 'bg-blue-600 text-white shadow-md shadow-blue-600/30 font-semibold' : 'hover:bg-slate-800/80 text-slate-300 hover:text-white' }}">
                    <i data-lucide="trending-up" class="w-4 h-4"></i>
                    <span>P&L Global (Laba Rugi)</span>
                </a>

                <a href="{{ route('finance.balance-sheet') }}" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all {{ request()->routeIs('finance.balance-sheet') ? 'bg-blue-600 text-white shadow-md shadow-blue-600/30 font-semibold' : 'hover:bg-slate-800/80 text-slate-300 hover:text-white' }}">
                    <i data-lucide="scale" class="w-4 h-4"></i>
                    <span>Neraca Konsolidasi</span>
                </a>

                <div class="pt-4 px-3 py-2 text-[10px] uppercase font-bold tracking-wider text-slate-400">Manajemen Data</div>

                <a href="{{ route('finance.sync') }}" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all {{ request()->routeIs('finance.sync') ? 'bg-blue-600 text-white shadow-md shadow-blue-600/30 font-semibold' : 'hover:bg-slate-800/80 text-slate-300 hover:text-white' }}">
                    <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                    <span>Sinkronisasi & Import</span>
                </a>
            </nav>
        </div>

        <!-- Footer / Return to Portal -->
        <div class="p-4 border-t border-slate-800 space-y-3">
            <a href="http://localhost:8000/port" target="_blank" class="w-full flex items-center justify-center gap-2 px-3 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-xs font-semibold text-slate-200 transition-all border border-slate-700">
                <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
                <span>Kembali ke Portal MJG</span>
            </a>

            <div class="flex items-center justify-between px-2 pt-1 text-xs">
                <div class="flex items-center gap-2 truncate">
                    <div class="w-7 h-7 rounded-full bg-blue-600/30 text-blue-400 flex items-center justify-center font-bold text-xs">
                        {{ substr(auth()->user()->name ?? 'U', 0, 1) }}
                    </div>
                    <span class="truncate font-medium text-slate-300 text-xs">{{ auth()->user()->name ?? 'Administrator' }}</span>
                </div>
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" title="Keluar" class="text-slate-400 hover:text-red-400 p-1 rounded transition-colors">
                        <i data-lucide="log-out" class="w-4 h-4"></i>
                    </button>
                </form>
            </div>
        </div>
    </aside>

    <!-- MAIN CONTENT AREA -->
    <div class="flex-1 flex flex-col overflow-hidden">
        
        <!-- TOPBAR -->
        <header class="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-8 shrink-0">
            <div class="flex items-center gap-4">
                <h2 class="text-lg font-bold text-slate-900">@yield('page_title', 'Dashboard Konsolidasi')</h2>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200/60">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    Live Data Mode
                </span>
            </div>

            <!-- Global Period Selector -->
            @if(isset($availablePeriods) && count($availablePeriods) > 0)
            <form action="{{ url()->current() }}" method="GET" class="flex items-center gap-2">
                @if(request('business_unit'))
                    <input type="hidden" name="business_unit" value="{{ request('business_unit') }}">
                @endif
                @if(request('entity'))
                    <input type="hidden" name="entity" value="{{ request('entity') }}">
                @endif
                <label for="period" class="text-xs font-semibold text-slate-500 flex items-center gap-1">
                    <i data-lucide="calendar" class="w-3.5 h-3.5"></i>
                    Periode:
                </label>
                <select name="period" id="period" onchange="this.form.submit()" class="text-xs font-bold bg-slate-50 border border-slate-300 rounded-lg px-3 py-1.5 text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    @foreach($availablePeriods as $p)
                        <option value="{{ $p }}" {{ (isset($selectedPeriod) && $selectedPeriod === $p) ? 'selected' : '' }}>
                            {{ \Carbon\Carbon::parse($p)->translatedFormat('F Y') }}
                        </option>
                    @endforeach
                </select>
            </form>
            @endif
        </header>

        <!-- FLASH NOTIFICATIONS -->
        <div class="px-8 pt-4">
            @if(session('success'))
            <div class="p-4 mb-3 text-sm text-emerald-800 rounded-xl bg-emerald-50 border border-emerald-200 flex items-center gap-3 shadow-sm">
                <i data-lucide="check-circle-2" class="w-5 h-5 text-emerald-600 shrink-0"></i>
                <div class="font-medium">{{ session('success') }}</div>
            </div>
            @endif

            @if(session('error'))
            <div class="p-4 mb-3 text-sm text-rose-800 rounded-xl bg-rose-50 border border-rose-200 flex items-center gap-3 shadow-sm">
                <i data-lucide="alert-circle" class="w-5 h-5 text-rose-600 shrink-0"></i>
                <div class="font-medium">{{ session('error') }}</div>
            </div>
            @endif
        </div>

        <!-- VIEW BODY -->
        <main class="flex-1 overflow-y-auto px-8 py-4">
            @yield('content')
        </main>
    </div>

    <!-- Init Lucide Icons -->
    <script>
        lucide.createIcons();
    </script>
    @yield('scripts')
</body>
</html>
