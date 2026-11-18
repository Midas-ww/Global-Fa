<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Dashboard Overview') - Global FA Mekar Jaya Group</title>
    
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
                            green: '#10e58f',
                            yellow: '#ffd800',
                            lime: '#ebf8c8',
                            'lime-dark': '#3f6212',
                            surface: '#f8faf8',
                            border: '#edf0ee',
                        }
                    }
                }
            }
        }
    </script>
    
    <style>
        /* Standarisasi Typography: Instrument Sans persis SIR-IQOS & API-SPBU */
        body, button, input, select, textarea, table, th, td, h1, h2, h3, h4, h5, h6, p, span, a, div {
            font-family: 'Instrument Sans', ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
        }

        .font-mono, [class*="font-mono"], .tabular-nums {
            font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif !important;
            font-variant-numeric: tabular-nums;
            font-feature-settings: "tnum";
        }

        /* Skala Fluida Responsif 80% (12.8px) - Identik dengan SIR-IQOS */
        html {
            font-size: 16px;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        @media (max-width: 1599px) {
            html {
                font-size: 12.8px;
            }
        }
        @media (max-width: 1359px) {
            html {
                font-size: 12.2px;
            }
        }
        @media (max-width: 1023px) {
            html {
                font-size: 11.8px;
            }
        }
        @media (max-width: 767px) {
            html {
                font-size: 15px;
            }
        }

        /* Custom Scrollbar */
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }
        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
    </style>
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <!-- Chart.js CDN & Font Configuration -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        if (typeof Chart !== 'undefined') {
            Chart.defaults.font.family = "'Instrument Sans', ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, sans-serif";
        }
    </script>
</head>
<body class="bg-[#f8faf8] text-slate-800 antialiased font-sans flex h-screen overflow-hidden">

    <!-- MOBILE OVERLAY BACKDROP -->
    <div id="sidebarBackdrop" onclick="toggleSidebar()" class="fixed inset-0 bg-slate-900/40 z-40 hidden lg:hidden transition-opacity"></div>

    <!-- SIDEBAR (Flat Clean Modern Style ala SPBU & IQOS - NO GRADIENT) -->
    <aside id="mainSidebar" class="w-64 bg-white text-slate-700 flex flex-col justify-between border-r border-[#edf0ee] shrink-0 z-50 transition-all duration-300 ease-in-out fixed lg:static inset-y-0 left-0 -translate-x-full lg:translate-x-0 shadow-xs">
        <div class="flex flex-col flex-1 overflow-hidden">
            <!-- Sidebar Header / Logo -->
            <div class="h-16 flex items-center justify-center border-b border-[#edf0ee] shrink-0 transition-all px-3 bg-white">
                <a href="{{ route('finance.dashboard') }}" class="flex items-center justify-center w-full overflow-hidden">
                    <img src="/assets/images/mekarjaya-group-logo.png" alt="Mekar Jaya Group" class="h-10 w-auto max-w-[150px] object-contain shrink-0 sidebar-logo-full" />
                    <div class="w-9 h-9 rounded-xl bg-white border border-slate-200 text-slate-800 font-black flex items-center justify-center text-xs shrink-0 hidden sidebar-logo-compact shadow-2xs" title="Mekar Jaya Group">
                        MJ
                    </div>
                </a>
            </div>

            <!-- Sidebar Search Input (Hidden when collapsed) -->
            <div class="p-3 border-b border-[#edf0ee] shrink-0 sidebar-search-box">
                <div class="relative flex items-center">
                    <svg class="w-4 h-4 absolute left-3 text-slate-400 pointer-events-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8" />
                        <path d="m21 21-4.3-4.3" />
                    </svg>
                    <input type="text" id="sidebarSearch" placeholder="Cari menu Global FA..."
                        class="w-full pl-9 pr-8 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#10e58f] focus:bg-white transition-all shadow-2xs"
                        oninput="filterSidebar(this.value)" />
                    <button id="searchClearBtn" onclick="clearSearch()" type="button"
                        class="absolute right-2.5 p-0.5 text-slate-400 hover:text-slate-600 rounded-full hover:bg-slate-200/60 transition-colors cursor-pointer hidden"
                        title="Hapus pencarian">
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Collapsed Search Button (Muncul saat Sidebar Ditutup ala SPBU & IQOS) -->
            <div class="p-2 border-b border-[#edf0ee] flex justify-center shrink-0 hidden sidebar-collapsed-search">
                <button type="button" onclick="expandSidebar(); document.getElementById('sidebarSearch').focus();"
                    class="w-10 h-10 rounded-xl flex items-center justify-center text-slate-500 hover:text-slate-900 hover:bg-slate-100 transition-all cursor-pointer relative"
                    title="Cari Menu (Ctrl+K)">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8" />
                        <path d="m21 21-4.3-4.3" />
                    </svg>
                </button>
            </div>

            <!-- Sidebar Navigation Links (Scrollable Navigation Groups) -->
            <div class="flex-1 overflow-y-auto px-2 py-3 space-y-6 no-scrollbar">
                
                <!-- Category 1: IKHTISAR & LAPORAN -->
                <div class="sidebar-group">
                    <div class="px-3 mb-2 text-[10px] font-bold uppercase tracking-wider text-slate-400 sidebar-label">IKHTISAR & LAPORAN</div>
                    <nav class="space-y-1.5">
                        
                        <!-- Overview Link (Persis Dashboard Icon ala SPBU & IQOS) -->
                        <a href="{{ route('finance.dashboard') }}"
                           title="Overview"
                           class="sidebar-item flex items-center text-xs transition-all duration-300 ease-in-out group relative cursor-pointer rounded-tl-[1.2rem] rounded-br-[1.2rem] rounded-tr-none rounded-bl-none hover:rounded-tr-[1.2rem] hover:rounded-bl-[1.2rem] hover:rounded-tl-none hover:rounded-br-none {{ request()->routeIs('finance.dashboard') ? 'bg-[#ebf8c8] text-[#3f6212] font-bold shadow-2xs' : 'text-slate-600 hover:bg-[#ebf8c8]/60 hover:text-[#3f6212] font-semibold' }} w-full px-3.5 py-2.5 justify-start gap-3">
                            <svg class="w-5 h-5 shrink-0 {{ request()->routeIs('finance.dashboard') ? 'text-[#3f6212]' : 'text-slate-500 group-hover:text-[#3f6212]' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect width="7" height="9" x="3" y="3" rx="1" />
                                <rect width="7" height="5" x="14" y="3" rx="1" />
                                <rect width="7" height="9" x="14" y="12" rx="1" />
                                <rect width="7" height="5" x="3" y="16" rx="1" />
                            </svg>
                            <span class="truncate flex-1 sidebar-label">Overview</span>
                        </a>

                        <!-- Laba Rugi (P&L) Link (Persis PNL Chart Icon ala SPBU & IQOS) -->
                        <a href="{{ route('finance.pnl') }}"
                           title="Laba Rugi (P&L)"
                           class="sidebar-item flex items-center text-xs transition-all duration-300 ease-in-out group relative cursor-pointer rounded-tl-[1.2rem] rounded-br-[1.2rem] rounded-tr-none rounded-bl-none hover:rounded-tr-[1.2rem] hover:rounded-bl-[1.2rem] hover:rounded-tl-none hover:rounded-br-none {{ request()->routeIs('finance.pnl') ? 'bg-[#ebf8c8] text-[#3f6212] font-bold shadow-2xs' : 'text-slate-600 hover:bg-[#ebf8c8]/60 hover:text-[#3f6212] font-semibold' }} w-full px-3.5 py-2.5 justify-start gap-3">
                            <svg class="w-5 h-5 shrink-0 {{ request()->routeIs('finance.pnl') ? 'text-[#3f6212]' : 'text-slate-500 group-hover:text-[#3f6212]' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 17l6-6 4 3 8-9" />
                                <circle cx="3" cy="17" r="1" />
                                <circle cx="9" cy="11" r="1" />
                                <circle cx="13" cy="14" r="1" />
                                <circle cx="21" cy="5" r="1" />
                            </svg>
                            <span class="truncate flex-1 sidebar-label">Laba Rugi (P&L)</span>
                        </a>

                        <!-- Neraca Keuangan (Balance Sheet) Link (Persis Balance Scale ala SPBU & IQOS) -->
                        <a href="{{ route('finance.balance-sheet') }}"
                           title="Neraca Keuangan"
                           class="sidebar-item flex items-center text-xs transition-all duration-300 ease-in-out group relative cursor-pointer rounded-tl-[1.2rem] rounded-br-[1.2rem] rounded-tr-none rounded-bl-none hover:rounded-tr-[1.2rem] hover:rounded-bl-[1.2rem] hover:rounded-tl-none hover:rounded-br-none {{ request()->routeIs('finance.balance-sheet') ? 'bg-[#ebf8c8] text-[#3f6212] font-bold shadow-2xs' : 'text-slate-600 hover:bg-[#ebf8c8]/60 hover:text-[#3f6212] font-semibold' }} w-full px-3.5 py-2.5 justify-start gap-3">
                            <svg class="w-5 h-5 shrink-0 {{ request()->routeIs('finance.balance-sheet') ? 'text-[#3f6212]' : 'text-slate-500 group-hover:text-[#3f6212]' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 3v18" />
                                <path d="M3 7h18" />
                                <path d="M3 7l-2 5h7L6 7" />
                                <path d="M18 7l-2 5h7l-2-5" />
                                <path d="M8 21h8" />
                            </svg>
                            <span class="truncate flex-1 sidebar-label">Neraca Keuangan</span>
                        </a>
                    </nav>
                </div>

                <!-- Category 2: OPERASIONAL & SYNC -->
                <div class="sidebar-group">
                    <div class="px-3 mb-2 text-[10px] font-bold uppercase tracking-wider text-slate-400 sidebar-label">OPERASIONAL & SYNC</div>
                    <nav class="space-y-1.5">
                        <!-- Data Ingestion Hub Link -->
                        <a href="{{ route('finance.sync') }}"
                           title="Data Ingestion Hub"
                           class="sidebar-item flex items-center text-xs transition-all duration-300 ease-in-out group relative cursor-pointer rounded-tl-[1.2rem] rounded-br-[1.2rem] rounded-tr-none rounded-bl-none hover:rounded-tr-[1.2rem] hover:rounded-bl-[1.2rem] hover:rounded-tl-none hover:rounded-br-none {{ request()->routeIs('finance.sync') ? 'bg-[#ebf8c8] text-[#3f6212] font-bold shadow-2xs' : 'text-slate-600 hover:bg-[#ebf8c8]/60 hover:text-[#3f6212] font-semibold' }} w-full px-3.5 py-2.5 justify-start gap-3">
                            <svg class="w-5 h-5 shrink-0 {{ request()->routeIs('finance.sync') ? 'text-[#3f6212]' : 'text-slate-500 group-hover:text-[#3f6212]' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8" />
                                <path d="M21 3v5h-5" />
                                <path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16" />
                                <path d="M8 16H3v5" />
                            </svg>
                            <span class="truncate flex-1 sidebar-label">Data Ingestion Hub</span>
                        </a>

                        <!-- Super App Portal Link -->
                        <a href="http://localhost:8000/port" target="_blank"
                           title="Super App Portal"
                           class="sidebar-item flex items-center text-xs transition-all duration-300 ease-in-out group relative cursor-pointer rounded-tl-[1.2rem] rounded-br-[1.2rem] rounded-tr-none rounded-bl-none hover:rounded-tr-[1.2rem] hover:rounded-bl-[1.2rem] hover:rounded-tl-none hover:rounded-br-none text-slate-600 hover:bg-[#ebf8c8]/60 hover:text-[#3f6212] font-semibold w-full px-3.5 py-2.5 justify-start gap-3">
                            <svg class="w-5 h-5 shrink-0 text-slate-500 group-hover:text-[#3f6212]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6" />
                                <polyline points="15 3 21 3 21 9" />
                                <line x1="21" y1="3" x2="14" y2="10" />
                            </svg>
                            <span class="truncate flex-1 sidebar-label">Super App Portal</span>
                        </a>
                    </nav>
                </div>

                <!-- Category: PENGATURAN & MASTER -->
                <div class="sidebar-group">
                    <div class="px-3 mb-2 text-[10px] font-bold uppercase tracking-wider text-slate-400 sidebar-label">PENGATURAN & MASTER</div>
                    <nav class="space-y-1.5">
                        <a href="{{ route('finance.master.units') }}"
                           title="Master Unit Bisnis"
                           class="sidebar-item flex items-center text-xs transition-all duration-300 ease-in-out group relative cursor-pointer rounded-tl-[1.2rem] rounded-br-[1.2rem] rounded-tr-none rounded-bl-none hover:rounded-tr-[1.2rem] hover:rounded-bl-[1.2rem] hover:rounded-tl-none hover:rounded-br-none {{ request()->routeIs('finance.master.*') ? 'bg-[#ebf8c8] text-[#3f6212] font-bold shadow-2xs' : 'text-slate-600 hover:bg-[#ebf8c8]/60 hover:text-[#3f6212] font-semibold' }} w-full px-3.5 py-2.5 justify-start gap-3">
                            <svg class="w-5 h-5 shrink-0 {{ request()->routeIs('finance.master.*') ? 'text-[#3f6212]' : 'text-slate-500 group-hover:text-[#3f6212]' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect width="16" height="20" x="4" y="2" rx="2" />
                                <path d="M9 22v-4h6v4" />
                                <path d="M8 6h.01" />
                                <path d="M16 6h.01" />
                                <path d="M12 6h.01" />
                                <path d="M12 10h.01" />
                                <path d="M12 14h.01" />
                                <path d="M16 10h.01" />
                                <path d="M16 14h.01" />
                                <path d="M8 10h.01" />
                                <path d="M8 14h.01" />
                            </svg>
                            <span class="truncate flex-1 sidebar-label">Master Unit Bisnis</span>
                        </a>
                    </nav>
                </div>

                <!-- Category 3: KEPATUHAN & STANDAR -->
                <div class="sidebar-group">
                    <div class="px-3 mb-2 text-[10px] font-bold uppercase tracking-wider text-slate-400 sidebar-label">KEPATUHAN & RISIKO</div>
                    <nav class="space-y-1.5">
                        <div class="sidebar-item flex items-center text-xs font-semibold text-slate-400 cursor-default rounded-tl-[1.2rem] rounded-br-[1.2rem] rounded-tr-none rounded-bl-none w-full px-3.5 py-2.5 justify-start gap-3" title="Accounting Standards">
                            <svg class="w-5 h-5 shrink-0 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z" />
                                <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z" />
                            </svg>
                            <span class="truncate flex-1 sidebar-label">Accounting Standards</span>
                        </div>
                    </nav>
                </div>

                <!-- Empty state saat menu tidak ditemukan (Persis SPBU & IQOS) -->
                <div id="sidebarEmptyState" class="py-8 px-3 text-center hidden">
                    <div class="w-10 h-10 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-2.5">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                    </div>
                    <p class="text-xs font-bold text-slate-700">Menu tidak ditemukan</p>
                    <p class="text-[11px] text-slate-400 mt-0.5">Coba kata kunci yang lain</p>
                    <button onclick="clearSearch()" type="button" class="mt-3 px-3 py-1 bg-slate-100 hover:bg-slate-200 text-slate-600 text-[11px] font-semibold rounded-lg transition-colors cursor-pointer">
                        Reset
                    </button>
                </div>

            </div>
        </div>

        <!-- Sidebar Footer / User Profile & Logout (Persis SPBU & IQOS) -->
        <div class="p-3 border-t border-[#edf0ee] shrink-0 bg-slate-50/50 flex items-center transition-all mt-auto justify-between sidebar-footer">
            <div class="flex items-center gap-2.5 overflow-hidden">
                <div class="w-8 h-8 rounded-full bg-[#10e58f] text-slate-900 font-bold text-xs flex items-center justify-center shrink-0 shadow-xs" title="{{ auth()->user()->name ?? 'Administrator' }}">
                    {{ substr(auth()->user()->name ?? 'A', 0, 1) }}
                </div>
                <div class="flex flex-col truncate min-w-0 sidebar-label">
                    <span class="text-xs font-bold text-slate-900 truncate" title="{{ auth()->user()->name ?? 'Administrator' }}">{{ auth()->user()->name ?? 'Administrator' }}</span>
                    <span class="text-[10px] text-slate-500 font-medium truncate" title="{{ auth()->user()->email ?? 'Holding Executive' }}">{{ auth()->user()->email ?? 'Holding Executive' }}</span>
                </div>
            </div>

            <form action="{{ route('logout') }}" method="POST" class="sidebar-label">
                @csrf
                <button type="submit" class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors cursor-pointer shrink-0" title="Keluar">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                        <polyline points="16 17 21 12 16 7" />
                        <line x1="21" y1="12" x2="9" y2="12" />
                    </svg>
                </button>
            </form>
        </div>
    </aside>

    <!-- MAIN CONTENT AREA -->
    <div class="flex-1 flex flex-col overflow-hidden bg-[#f8faf8]">
        
        <!-- TOPBAR (Clean Solid White ala SPBU & IQOS - NO GRADIENT) -->
        <header class="h-16 bg-white border-b border-[#edf0ee] px-4 sm:px-6 flex items-center justify-between sticky top-0 z-30 shrink-0 shadow-xs">
            <!-- Left Section: Sidebar Toggle & Breadcrumb (Persis SPBU & IQOS) -->
            <div class="flex items-center gap-3 sm:gap-4 min-w-0">
                <!-- Toggle Mobile (Mobile Only ala SPBU & IQOS) -->
                <button
                    type="button"
                    onclick="toggleSidebar()"
                    class="p-2 -ml-2 text-slate-500 hover:text-slate-800 hover:bg-slate-100 rounded-lg lg:hidden cursor-pointer transition-colors"
                    aria-label="Buka Menu"
                    title="Buka Menu"
                >
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="3" y1="12" x2="21" y2="12"></line>
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <line x1="3" y1="18" x2="21" y2="18"></line>
                    </svg>
                </button>

                <!-- Toggle Desktop Collapse (Desktop Only ala SPBU & IQOS) -->
                <button
                    type="button"
                    onclick="toggleSidebar()"
                    class="hidden lg:flex p-1.5 text-slate-500 hover:text-slate-800 hover:bg-slate-100 rounded-lg cursor-pointer transition-colors"
                    title="Toggle Sidebar (Ctrl+B)"
                >
                    <!-- Signature panel-left sidebar toggle icon persis SPBU & IQOS -->
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect width="18" height="18" x="3" y="3" rx="2" />
                        <path d="M9 3v18" />
                    </svg>
                </button>

                <!-- Breadcrumb Navigation ala SPBU & IQOS -->
                <nav class="flex items-center gap-1 sm:gap-1.5 text-xs text-slate-500 font-medium whitespace-nowrap min-w-0">
                    <span class="hidden sm:inline">Mekarjaya Group</span>
                    <span class="inline sm:hidden font-semibold">MJG</span>
                    <span>/</span>
                    <span class="text-slate-900 font-bold shrink-0 text-[#10e58f]">
                        Global FA
                    </span>
                    <span>/</span>
                    <span class="text-slate-900 font-semibold shrink-0">@yield('title', 'Dashboard Overview')</span>
                </nav>
            </div>

            <!-- Right Controls: Status & Period Selector & User Avatar -->
            <div class="flex items-center gap-2 sm:gap-3">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-[#ebf8c8] text-[#3f6212] border border-[#d9f99d]">
                    <span class="w-1.5 h-1.5 rounded-full bg-[#10e58f]"></span>
                    <span class="hidden sm:inline">Live Data</span>
                </span>

                @if(isset($availablePeriods) && count($availablePeriods) > 0)
                <form action="{{ url()->current() }}" method="GET" class="flex items-center gap-2">
                    @if(request('business_unit'))
                        <input type="hidden" name="business_unit" value="{{ request('business_unit') }}">
                    @endif
                    @if(request('entity'))
                        <input type="hidden" name="entity" value="{{ request('entity') }}">
                    @endif
                    <div class="flex items-center gap-1.5 px-2.5 sm:px-3 py-1.5 bg-white border border-[#edf0ee] rounded-xl transition-all">
                        <i data-lucide="calendar" class="w-3.5 h-3.5 text-slate-400 shrink-0"></i>
                        <span class="text-xs font-semibold text-slate-500 hidden md:inline">Period:</span>
                        <select name="period" id="period" onchange="this.form.submit()" class="text-xs font-bold font-mono bg-transparent text-slate-800 focus:outline-none cursor-pointer">
                            @foreach($availablePeriods as $p)
                                <option value="{{ $p }}" {{ (isset($selectedPeriod) && $selectedPeriod === $p) ? 'selected' : '' }}>
                                    {{ \Carbon\Carbon::parse($p)->translatedFormat('F Y') }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </form>
                @endif

                <!-- Circular profile badge -->
                <div class="w-9 h-9 rounded-full bg-white border border-slate-200 text-slate-800 flex items-center justify-center font-bold text-xs shrink-0 cursor-pointer shadow-xs" title="{{ auth()->user()->name ?? 'Administrator' }}">
                    {{ substr(auth()->user()->name ?? 'A', 0, 1) }}
                </div>
            </div>
        </header>

        <!-- FLASH NOTIFICATIONS -->
        <div class="px-4 sm:px-8 pt-4">
            @if(session('success'))
            <div class="p-3 mb-3 text-xs text-emerald-900 rounded-xl bg-[#ecfdf5] border border-emerald-200 flex items-center gap-2.5">
                <i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-600 shrink-0"></i>
                <div class="font-medium">{{ session('success') }}</div>
            </div>
            @endif

            @if(session('error'))
            <div class="p-3 mb-3 text-xs text-rose-900 rounded-xl bg-rose-50 border border-rose-200 flex items-center gap-2.5">
                <i data-lucide="alert-circle" class="w-4 h-4 text-rose-600 shrink-0"></i>
                <div class="font-medium">{{ session('error') }}</div>
            </div>
            @endif
        </div>

        <!-- VIEW BODY -->
        <main class="flex-1 overflow-y-auto px-4 sm:px-8 py-5">
            @yield('content')
        </main>
    </div>

    <!-- Sidebar & Filter Scripts (Identik SPBU & IQOS) -->
    <script>
        lucide.createIcons();

        function filterSidebar(query) {
            const items = document.querySelectorAll('.sidebar-item');
            const clearBtn = document.getElementById('searchClearBtn');
            const emptyState = document.getElementById('sidebarEmptyState');
            const q = query.toLowerCase().trim();
            
            if (clearBtn) {
                if (q.length > 0) clearBtn.classList.remove('hidden');
                else clearBtn.classList.add('hidden');
            }
            
            let visibleCount = 0;
            items.forEach(el => {
                const text = el.innerText.toLowerCase();
                const matches = text.includes(q);
                el.style.display = matches ? 'flex' : 'none';
                if (matches) visibleCount++;
            });

            // Sembunyikan judul kategori jika tidak ada menu yang cocok
            document.querySelectorAll('.sidebar-group').forEach(grp => {
                const groupItems = grp.querySelectorAll('.sidebar-item');
                const hasVisible = Array.from(groupItems).some(i => i.style.display !== 'none');
                grp.style.display = hasVisible ? 'block' : 'none';
            });

            if (emptyState) {
                if (visibleCount === 0 && q.length > 0) {
                    emptyState.classList.remove('hidden');
                } else {
                    emptyState.classList.add('hidden');
                }
            }
        }

        function clearSearch() {
            const input = document.getElementById('sidebarSearch');
            if (input) {
                input.value = '';
                filterSidebar('');
                input.focus();
            }
        }

        // Sidebar Toggle Functions
        function toggleSidebar() {
            const isMobile = window.innerWidth < 1024;
            const sidebar = document.getElementById('mainSidebar');
            const backdrop = document.getElementById('sidebarBackdrop');
            
            if (isMobile) {
                const isClosed = sidebar.classList.contains('-translate-x-full');
                if (isClosed) {
                    sidebar.classList.remove('-translate-x-full');
                    backdrop.classList.remove('hidden');
                } else {
                    closeMobileSidebar();
                }
            } else {
                const isCollapsed = sidebar.classList.contains('sidebar-collapsed');
                if (isCollapsed) {
                    expandSidebar();
                } else {
                    collapseSidebar();
                }
            }
        }

        function closeMobileSidebar() {
            const sidebar = document.getElementById('mainSidebar');
            const backdrop = document.getElementById('sidebarBackdrop');
            if (sidebar) sidebar.classList.add('-translate-x-full');
            if (backdrop) backdrop.classList.add('hidden');
        }

        function collapseSidebar() {
            const sidebar = document.getElementById('mainSidebar');
            if (!sidebar) return;
            sidebar.classList.remove('w-64');
            sidebar.classList.add('w-16', 'sidebar-collapsed');
            
            document.querySelectorAll('.sidebar-label').forEach(el => el.classList.add('hidden'));
            document.querySelectorAll('.sidebar-logo-full').forEach(el => el.classList.add('hidden'));
            document.querySelectorAll('.sidebar-logo-compact').forEach(el => el.classList.remove('hidden'));
            document.querySelectorAll('.sidebar-search-box').forEach(el => el.classList.add('hidden'));
            document.querySelectorAll('.sidebar-collapsed-search').forEach(el => el.classList.remove('hidden'));
            document.querySelectorAll('.sidebar-item').forEach(el => {
                el.classList.remove('w-full', 'px-3.5', 'justify-start', 'gap-3');
                el.classList.add('w-10', 'h-10', 'mx-auto', 'justify-center', 'p-0');
            });
            const footer = document.querySelector('.sidebar-footer');
            if (footer) {
                footer.classList.remove('justify-between');
                footer.classList.add('justify-center');
            }
            
            localStorage.setItem('sidebar_collapsed', 'true');
            lucide.createIcons();
        }

        function expandSidebar() {
            const sidebar = document.getElementById('mainSidebar');
            if (!sidebar) return;
            sidebar.classList.remove('w-16', 'sidebar-collapsed');
            sidebar.classList.add('w-64');
            
            document.querySelectorAll('.sidebar-label').forEach(el => el.classList.remove('hidden'));
            document.querySelectorAll('.sidebar-logo-full').forEach(el => el.classList.remove('hidden'));
            document.querySelectorAll('.sidebar-logo-compact').forEach(el => el.classList.add('hidden'));
            document.querySelectorAll('.sidebar-search-box').forEach(el => el.classList.remove('hidden'));
            document.querySelectorAll('.sidebar-collapsed-search').forEach(el => el.classList.add('hidden'));
            document.querySelectorAll('.sidebar-item').forEach(el => {
                el.classList.remove('w-10', 'h-10', 'mx-auto', 'justify-center', 'p-0');
                el.classList.add('w-full', 'px-3.5', 'justify-start', 'gap-3');
            });
            const footer = document.querySelector('.sidebar-footer');
            if (footer) {
                footer.classList.remove('justify-center');
                footer.classList.add('justify-between');
            }
            
            localStorage.setItem('sidebar_collapsed', 'false');
            lucide.createIcons();
        }

        // Restore saved preference on page load (desktop only)
        document.addEventListener('DOMContentLoaded', () => {
            if (window.innerWidth >= 1024 && localStorage.getItem('sidebar_collapsed') === 'true') {
                collapseSidebar();
            }

            // Close mobile sidebar on item click
            document.querySelectorAll('.sidebar-item').forEach(el => {
                el.addEventListener('click', () => {
                    if (window.innerWidth < 1024) closeMobileSidebar();
                });
            });

            // Keyboard shortcut Ctrl+B / Cmd+B to toggle sidebar & Ctrl+K to search
            window.addEventListener('keydown', (e) => {
                if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'b') {
                    e.preventDefault();
                    toggleSidebar();
                }
                if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                    e.preventDefault();
                    if (document.getElementById('mainSidebar').classList.contains('sidebar-collapsed')) {
                        expandSidebar();
                    }
                    const searchInput = document.getElementById('sidebarSearch');
                    if (searchInput) searchInput.focus();
                }
            });
        });
    </script>
    @yield('scripts')
</body>
</html>
