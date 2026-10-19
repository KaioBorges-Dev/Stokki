<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-950">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Stockki') }} - @yield('title', __('messages.tagline'))</title>

    {{-- Fonts --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    {{-- FontAwesome 6 Icons --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    {{-- Tailwind CSS & Alpine.js --}}
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                    },
                }
            }
        }
    </script>
</head>
<body class="h-full antialiased font-sans bg-slate-950 text-slate-100 flex flex-col md:flex-row">
    {{-- Responsive Collapsible Sidebar --}}
    @include('layouts.sidebar')

    {{-- Main Workspace Content Area (Adjusted for Desktop Collapsed 5rem/w-20 Sidebar) --}}
    <div class="flex-1 min-w-0 md:ml-20 flex flex-col min-h-screen">
        {{-- Top App Bar --}}
        <header class="h-16 px-6 border-b border-slate-800 bg-slate-900/60 backdrop-blur-md flex items-center justify-between sticky top-0 z-20">
            <div>
                <h1 class="text-lg font-semibold text-white tracking-tight">@yield('header', __('messages.nav.dashboard'))</h1>
            </div>

            <div class="flex items-center space-x-4">
                {{-- Quick Action: Scan Label --}}
                <a href="{{ route('recognition.scan') }}" class="inline-flex items-center space-x-2 px-3.5 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold shadow-sm transition-colors">
                    <i class="fa-solid fa-camera"></i>
                    <span>{{ __('messages.recognition.scan_button') }}</span>
                </a>

                {{-- Language Selector --}}
                <div class="hidden sm:flex items-center space-x-1.5 text-xs font-semibold bg-slate-800/80 px-2 py-1 rounded-lg border border-slate-700/60">
                    <a href="?lang=en" class="px-2 py-0.5 rounded {{ app()->getLocale() === 'en' ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:text-white' }}">EN</a>
                    <span class="text-slate-600">|</span>
                    <a href="?lang=pt" class="px-2 py-0.5 rounded {{ app()->getLocale() === 'pt' ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:text-white' }}">PT</a>
                </div>
            </div>
        </header>

        {{-- Main Page Body --}}
        <main class="flex-1 p-6 md:p-8 max-w-7xl w-full mx-auto">
            @yield('content')
        </main>
    </div>
</body>
</html>
