@props(['target' => null])

{{--
    Selector visual de emojis para insertar en un textarea.
    Uso: <x-emoji-picker target="id-del-textarea" />
    El target es el ID del <textarea> donde queremos insertar.
--}}

<div x-data="{
        abierto: false,
        categoria: 'estados',
        categorias: {
            estados:    { label: 'Estados',    icon: 'fa-circle-check', emojis: ['✅','❌','⚠️','🟢','🔴','🟡','🟠','🟣','✨','🎉','🎊','💯','🔥','⭐','🌟','💫','✔️','✖️','🆕','🆗','🆙','♻️'] },
            pedido:     { label: 'Pedido',     icon: 'fa-receipt',      emojis: ['🧾','📋','📦','🛒','🛍️','💰','💵','💸','💳','🏷️','🎁','📝','📌','📍','📞','☎️','📱','📲','💬','✉️','📧','🔔'] },
            entrega:    { label: 'Entrega',    icon: 'fa-truck-fast',   emojis: ['🛵','🏍️','🚲','🚴','🚚','🚛','🚐','🚗','🚙','🏃','💨','📍','🗺️','🧭','🏁','🚦','🚧','⏱️','⏰','🕐','🕒','🕔'] },
            comida:     { label: 'Comida',     icon: 'fa-utensils',     emojis: ['🍳','🍔','🌭','🍕','🌮','🌯','🥙','🥪','🥗','🍜','🍝','🍣','🍱','🍛','🍙','🍚','🍘','🍢','🍤','🍖','🍗','🥩','🍞','🥐','🥯','🥖','🥨','🧀','🥓','🥚','🍦','🍰','🎂','🍪','🥤','☕','🍷'] },
            personas:   { label: 'Personas',   icon: 'fa-user',         emojis: ['👤','👥','👨‍🍳','🧑‍🍳','👨‍💼','👩‍💼','💁','🙋','🤵','💅','💁‍♂️','🙋‍♀️','👋','🤝','🙌','👍','👏','🤞','💪','🙏','👌','✋','✌️','🫶'] },
            simbolos:   { label: 'Símbolos',   icon: 'fa-shapes',       emojis: ['❤️','💚','💙','💛','🧡','💜','🖤','🤍','💔','❣️','💕','💖','💝','💗','💞','💟','♥️','💎','🔐','🔓','🔑','🗝️','🛡️','🔒','📊','📈','📉','✨','💫','⚡'] },
        },
        insertar(emoji) {
            const target = document.getElementById('{{ $target }}');
            if (!target) return;
            const start = target.selectionStart ?? target.value.length;
            const end   = target.selectionEnd ?? target.value.length;
            const before = target.value.substring(0, start);
            const after  = target.value.substring(end);
            target.value = before + emoji + after;

            // Disparar evento input para que Livewire wire:model se entere
            target.dispatchEvent(new Event('input', { bubbles: true }));
            target.dispatchEvent(new Event('change', { bubbles: true }));

            // Mover cursor después del emoji insertado
            target.focus();
            const pos = start + emoji.length;
            target.setSelectionRange(pos, pos);
        }
     }"
     class="relative mb-2">

    {{-- Botón principal --}}
    <button type="button" @click="abierto = !abierto"
            class="inline-flex items-center gap-1.5 rounded-lg bg-white border border-slate-200 hover:border-amber-300 hover:bg-amber-50 px-2.5 py-1.5 text-[11px] font-bold text-slate-700 transition shadow-sm">
        <i class="fa-regular fa-face-smile text-amber-500"></i>
        Insertar emoji
        <i class="fa-solid fa-chevron-down text-[8px] text-slate-400"></i>
    </button>

    {{-- Picker desplegable --}}
    <div x-show="abierto" x-cloak
         @click.outside="abierto = false"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 -translate-y-1"
         x-transition:enter-end="opacity-100 translate-y-0"
         class="absolute z-30 mt-1 w-80 rounded-xl bg-white border border-slate-200 shadow-2xl overflow-hidden">

        {{-- Tabs de categorías --}}
        <div class="flex border-b border-slate-100 bg-slate-50">
            <template x-for="(cat, key) in categorias" :key="key">
                <button type="button" @click="categoria = key"
                        :class="categoria === key ? 'bg-white text-amber-600 border-b-2 border-amber-500' : 'text-slate-500 hover:text-slate-700'"
                        class="flex-1 py-2 text-[10px] font-bold uppercase tracking-wider transition flex items-center justify-center gap-1"
                        :title="cat.label">
                    <i class="fa-solid" :class="cat.icon"></i>
                </button>
            </template>
        </div>

        {{-- Grid de emojis --}}
        <div class="p-2 max-h-56 overflow-y-auto">
            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1.5 px-1"
                 x-text="categorias[categoria].label"></div>
            <div class="grid grid-cols-8 gap-1">
                <template x-for="emoji in categorias[categoria].emojis" :key="emoji">
                    <button type="button"
                            @click="insertar(emoji)"
                            class="text-xl rounded-lg hover:bg-amber-50 p-1.5 transition cursor-pointer"
                            :title="emoji"
                            x-text="emoji"></button>
                </template>
            </div>
        </div>

        {{-- Footer --}}
        <div class="border-t border-slate-100 px-3 py-2 bg-slate-50 flex items-center justify-between text-[10px] text-slate-500">
            <span><i class="fa-solid fa-circle-info"></i> Click para insertar en el cursor</span>
            <button type="button" @click="abierto = false" class="font-bold text-slate-700 hover:text-slate-900">Cerrar</button>
        </div>
    </div>
</div>
