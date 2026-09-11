<aside class="profego-sidebar" aria-label="Navegación lateral">
    <div class="border-b border-white/10 px-4 pb-6 pt-5">
        <?php require_once __DIR__ . '/../components/profego-brand.php'; ?>
    </div>

    <nav class="flex flex-1 flex-col gap-2 px-3 py-5" aria-label="Secciones de ProfeGo">
        <a class="profego-sidebar__link profego-sidebar__link--active" href="/catalog">
            <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-white/5 text-base">⌂</span>
            <span>Inicio</span>
        </a>

        <a class="profego-sidebar__link" href="/catalog">
            <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-white/5 text-base">▣</span>
            <span>Tutorías</span>
        </a>

        <a class="profego-sidebar__link" href="#ai-chat-widget">
            <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-white/5 text-base">◌</span>
            <span>Chat</span>
        </a>

        <a class="profego-sidebar__link" href="/settings">
            <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-white/5 text-base">⚙</span>
            <span>Configuración</span>
        </a>
    </nav>

    <div class="border-t border-white/10 p-4">
        <div class="rounded-2xl border border-white/10 bg-white/5 p-3">
            <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Plan actual</p>
            <p class="mt-2 text-base font-semibold text-white">ProfeGo Plus</p>
            <p class="mt-1 text-sm text-slate-300">29.000 COP / mes</p>
        </div>
    </div>
</aside>
