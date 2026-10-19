{{-- 
  Stockki Web Administration - Collapsible Sidebar Navigation
  Features:
  - Desktop: Collapsed to icons-only by default (w-20), smoothly expands to w-64 on hover.
  - Mobile: Off-canvas drawer with smooth slide-over animation and backdrop overlay.
  - FontAwesome 6 icons with curated color indicators.
  - Fully localized with Laravel translation helpers (__()).
--}}

<div x-data="{ mobileOpen: false }" class="relative">
    {{-- Mobile Top Bar with Hamburger Trigger --}}
    <header class="md:hidden flex items-center justify-between px-4 py-3 bg-white/80 dark:bg-slate-900/80 border-b border-slate-200 dark:border-slate-800 text-slate-800 dark:text-slate-100 sticky top-0 z-30">
        <div class="flex items-center space-x-3">
            <button 
                type="button"
                @click="mobileOpen = true"
                class="p-2 rounded-lg text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors"
                aria-label="Open navigation menu"
            >
                <i class="fa-solid fa-bars text-xl"></i>
            </button>
            <div class="flex items-center space-x-2">
                <i class="fa-solid fa-cubes-stacked text-indigo-600 text-lg"></i>
                <span class="font-bold tracking-tight text-slate-900 dark:text-white">{{ __('messages.app_name') }}</span>
            </div>
        </div>

        {{-- Mobile Language Selector --}}
        <div class="flex items-center space-x-2 text-xs font-semibold">
            <a href="?lang=en" class="px-2 py-1 rounded {{ app()->getLocale() === 'en' ? 'bg-indigo-600 text-white' : 'text-slate-500 dark:text-slate-400' }}">EN</a>
            <span class="text-slate-400">|</span>
            <a href="?lang=pt" class="px-2 py-1 rounded {{ app()->getLocale() === 'pt' ? 'bg-indigo-600 text-white' : 'text-slate-500 dark:text-slate-400' }}">PT</a>
        </div>
    </header>

    {{-- Mobile Backdrop Overlay --}}
    <div 
        x-show="mobileOpen" 
        x-cloak
        @click="mobileOpen = false"
        class="fixed inset-0 z-40 bg-slate-950/70 backdrop-blur-sm md:hidden"
    ></div>

    {{-- Main Sidebar (Desktop Hover Expandable + Mobile Drawer) --}}
    <aside 
        :class="mobileOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0'"
        class="group/sidebar fixed inset-y-0 left-0 z-50 flex flex-col justify-between bg-white/90 dark:bg-slate-900/90 backdrop-blur-2xl border-r border-slate-200/90 dark:border-slate-800/90 text-slate-600 dark:text-slate-300 transition-all duration-300 ease-in-out w-72 md:w-20 md:hover:w-64 select-none shadow-xl"
    >
        {{-- Brand / Header --}}
        <div>
            <div class="flex items-center justify-between h-16 px-4 border-b border-slate-200/80 dark:border-slate-800/80">
                <a href="{{ route('dashboard') }}" class="flex items-center space-x-3.5 overflow-hidden">
                    <div class="w-10 h-10 flex-shrink-0 flex items-center justify-center rounded-xl bg-gradient-to-tr from-indigo-600 to-violet-500 text-white shadow-md shadow-indigo-500/20">
                        <i class="fa-solid fa-cubes-stacked text-lg"></i>
                    </div>
                    <div class="flex flex-col whitespace-nowrap opacity-100 md:opacity-0 md:group-hover/sidebar:opacity-100 transition-opacity duration-200">
                        <span class="font-bold text-base tracking-wide text-slate-900 dark:text-white leading-tight">{{ __('messages.app_name') }}</span>
                        <span class="text-[10px] font-semibold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">{{ __('messages.tagline') }}</span>
                    </div>
                </a>

                {{-- Mobile Close Button --}}
                <button 
                    type="button" 
                    @click="mobileOpen = false" 
                    class="md:hidden p-1.5 rounded-lg text-slate-400 hover:text-slate-800 dark:hover:text-white"
                    aria-label="Close navigation"
                >
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            {{-- Navigation Items --}}
            <nav class="mt-4 px-2 space-y-1.5" aria-label="Sidebar Navigation">
                {{-- Dashboard --}}
                <a 
                    href="{{ route('dashboard') }}" 
                    class="flex items-center h-11 px-3 rounded-xl text-xs font-semibold transition-colors group {{ request()->routeIs('dashboard') ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 border-l-2 border-indigo-600' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100/70 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-white' }}"
                >
                    <div class="w-8 flex-shrink-0 flex items-center justify-center">
                        <i class="fa-solid fa-chart-pie text-base transition-transform group-hover:scale-110"></i>
                    </div>
                    <span class="ml-3 truncate whitespace-nowrap opacity-100 md:opacity-0 md:group-hover/sidebar:opacity-100 transition-opacity duration-200">
                        {{ __('messages.nav.dashboard') }}
                    </span>
                </a>

                {{-- Inventory --}}
                <a 
                    href="{{ route('inventory.index') }}" 
                    class="flex items-center h-11 px-3 rounded-xl text-xs font-semibold transition-colors group {{ request()->routeIs('inventory.*') && !request()->routeIs('inventory.expiry-alerts') ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 border-l-2 border-indigo-600' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100/70 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-white' }}"
                >
                    <div class="w-8 flex-shrink-0 flex items-center justify-center">
                        <i class="fa-solid fa-boxes-stacked text-base transition-transform group-hover:scale-110"></i>
                    </div>
                    <span class="ml-3 truncate whitespace-nowrap opacity-100 md:opacity-0 md:group-hover/sidebar:opacity-100 transition-opacity duration-200">
                        {{ __('messages.nav.inventory') }}
                    </span>
                </a>

                {{-- Expiry Alerts --}}
                <a 
                    href="{{ route('inventory.expiry-alerts') }}" 
                    class="flex items-center h-11 px-3 rounded-xl text-xs font-semibold transition-colors group relative {{ request()->routeIs('inventory.expiry-alerts') ? 'bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 border-l-2 border-amber-500' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100/70 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-white' }}"
                >
                    <div class="w-8 flex-shrink-0 flex items-center justify-center relative">
                        <i class="fa-solid fa-triangle-exclamation text-base text-amber-500 transition-transform group-hover:scale-110"></i>
                    </div>
                    <span class="ml-3 truncate whitespace-nowrap opacity-100 md:opacity-0 md:group-hover/sidebar:opacity-100 transition-opacity duration-200 flex-1">
                        {{ __('messages.nav.expiry_alerts') }}
                    </span>
                </a>

                {{-- Optical Recognition (Camera Icon - FontAwesome Free) --}}
                <a 
                    href="{{ route('recognition.scan') }}" 
                    class="flex items-center h-11 px-3 rounded-xl text-xs font-semibold transition-colors group {{ request()->routeIs('recognition.*') ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 border-l-2 border-indigo-600' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100/70 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-white' }}"
                >
                    <div class="w-8 flex-shrink-0 flex items-center justify-center">
                        <i class="fa-solid fa-camera text-base transition-transform group-hover:scale-110"></i>
                    </div>
                    <span class="ml-3 truncate whitespace-nowrap opacity-100 md:opacity-0 md:group-hover/sidebar:opacity-100 transition-opacity duration-200">
                        {{ __('messages.nav.recognition') }}
                    </span>
                </a>

                {{-- Users & Permissions --}}
                <a 
                    href="{{ route('users.index') }}" 
                    class="flex items-center h-11 px-3 rounded-xl text-xs font-semibold transition-colors group {{ request()->routeIs('users.*') ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 border-l-2 border-indigo-600' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100/70 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-white' }}"
                >
                    <div class="w-8 flex-shrink-0 flex items-center justify-center">
                        <i class="fa-solid fa-users-gear text-base transition-transform group-hover:scale-110"></i>
                    </div>
                    <span class="ml-3 truncate whitespace-nowrap opacity-100 md:opacity-0 md:group-hover/sidebar:opacity-100 transition-opacity duration-200">
                        {{ __('messages.nav.users') }}
                    </span>
                </a>
            </nav>
        </div>

        {{-- Footer Section: User Profile & Unclipped Avatar --}}
        <div class="px-3 py-4 border-t border-slate-200/80 dark:border-slate-800/80 overflow-visible">
            <div class="flex items-center md:justify-center md:group-hover/sidebar:justify-between w-full overflow-visible">
                <div class="flex items-center space-x-3 overflow-visible">
                    <div class="w-10 h-10 min-w-[2.5rem] min-h-[2.5rem] flex-shrink-0 rounded-full bg-gradient-to-tr from-indigo-500 to-violet-600 text-white flex items-center justify-center font-bold text-xs shadow-sm ring-2 ring-white dark:ring-slate-800">
                        {{ substr(auth()->user()->name ?? 'U', 0, 1) }}
                    </div>
                    <div class="hidden md:group-hover/sidebar:flex flex-col truncate transition-opacity duration-200">
                        <span class="text-xs font-bold text-slate-900 dark:text-white truncate">{{ auth()->user()->name ?? 'Workspace User' }}</span>
                        <span class="text-[10px] text-slate-500 truncate">{{ auth()->user()->email ?? 'user@tenant.local' }}</span>
                    </div>
                </div>

                <form method="POST" action="{{ route('logout') }}" class="hidden md:group-hover/sidebar:flex">
                    @csrf
                    <button 
                        type="submit" 
                        class="p-2 text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 rounded-lg transition-colors"
                        title="{{ __('messages.nav.logout') }}"
                        aria-label="{{ __('messages.nav.logout') }}"
                    >
                        <i class="fa-solid fa-arrow-right-from-bracket"></i>
                    </button>
                </form>
            </div>
        </div>
    </aside>
</div>
