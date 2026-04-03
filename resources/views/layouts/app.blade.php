<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
  <head>
    <meta charset="utf-8">
    <meta
      name="viewport"
      content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"
    >
    <meta name="csrf-token" content="{{ csrf_token() }}">


   
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400..700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="https://cdn.jsdelivr.net/npm/tom-select/dist/css/tom-select.default.css" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    @livewireStyles

  </head>

  <body
    class="ui-compact font-sans antialiased bg-white dark:bg-gray-900 text-gray-600 dark:text-gray-400"
    :class="{ 'sidebar-expanded': sidebarExpanded }"
    x-data="{ sidebarOpen: false, sidebarExpanded: localStorage.getItem('sidebar-expanded') == 'true' }"
    x-init="$watch('sidebarExpanded', value => localStorage.setItem('sidebar-expanded', value))"
  >
    <script>
      if (localStorage.getItem('sidebar-expanded') == 'true') {
        document.body.classList.add('sidebar-expanded');
      } else {
        document.body.classList.remove('sidebar-expanded');
      }
    </script>

    <div class="fixed inset-0 pointer-events-none z-[9999]">
      <x-toaster-hub class="pointer-events-auto top-4 right-4" />
    </div>

    <div class="flex h-[100dvh] overflow-hidden">
      <x-app.sidebar :variant="$attributes['sidebarVariant']" />

      <div class="relative flex flex-col flex-1 overflow-y-auto overflow-x-hidden @if($attributes['background']){{ $attributes['background'] }}@endif" x-ref="contentarea">
        <x-app.header :variant="$attributes['headerVariant']" />

        {{-- MAIN súper fluido y compacto --}}
        <main class="grow w-full max-w-full px-1 sm:px-2 lg:px-3">
          {{ $slot }}
        </main>
      </div>
    </div>

    @livewireScripts
    <script src="https://cdn.jsdelivr.net/npm/tom-select/dist/js/tom-select.complete.min.js"></script>
  </body>
</html>
