<header class="fixed top-0 left-0 right-0 z-50 border-b border-[#d88e4a] bg-[#d68643] shadow-md">
    <div class="flex h-20 items-center justify-between px-6 lg:px-10">

        {{-- IZQUIERDA --}}
        <div class="flex items-center gap-4">

            {{-- BOTÓN --}}
            <button
                class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/10 text-white shadow-lg hover:bg-white/20"
            >
                <i class="fa-solid fa-bars text-xl"></i>
            </button>

            {{-- LOGO + TEXTO --}}
            <div class="flex items-center gap-3">
                <img
                    src="{{ asset('images/logo.png') }}"
                    alt="Logo"
                    class="h-10 w-10 rounded-xl object-contain"
                    onerror="this.style.display='none'"
                >

                <h1 class="text-2xl font-extrabold tracking-tight text-white drop-shadow-md md:text-3xl">
                    Alimentos la Hacienda
                </h1>
            </div>
        </div>


    </div>
</header>