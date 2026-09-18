<?php
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$isOverview = $currentPath === '/developer/' || $currentPath === '/developer/index.php';
$isAccounts = strpos($currentPath, '/developer/accounts/') === 0;
$isStores = strpos($currentPath, '/developer/stores/') === 0;
$isPackages = strpos($currentPath, '/developer/packages/') === 0;
?>
<aside id="developerSidebar" class="fixed z-50 inset-y-0 left-0 w-64 bg-white border-r border-neutral-200 pt-16 transform -translate-x-full lg:translate-x-0 transition-transform duration-200 ease-out">
    <div class="h-full flex flex-col p-3 overflow-y-auto">
        <nav class="space-y-1">
            <p class="px-3 pt-2 pb-2 text-[10px] font-semibold uppercase tracking-[0.18em] text-neutral-400">Main</p>
            <a href="/developer/" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm <?= $isOverview ? 'bg-neutral-50 font-medium text-neutral-900' : 'text-neutral-500 hover:bg-neutral-50 hover:text-neutral-900' ?>">
                <i data-lucide="layout-dashboard" class="w-4 h-4 <?= $isOverview ? 'text-blue-600' : '' ?>"></i><span>Overview</span>
            </a>

            <p class="px-3 pt-5 pb-2 text-[10px] font-semibold uppercase tracking-[0.18em] text-neutral-400">Platform</p>
            <a href="/developer/accounts/" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm <?= $isAccounts ? 'bg-neutral-50 font-medium text-neutral-900' : 'text-neutral-500 hover:bg-neutral-50 hover:text-neutral-900' ?>">
                <i data-lucide="users" class="w-4 h-4 <?= $isAccounts ? 'text-violet-600' : '' ?>"></i><span>Accounts</span>
            </a>
            <a href="/developer/stores/" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm <?= $isStores ? 'bg-neutral-50 font-medium text-neutral-900' : 'text-neutral-500 hover:bg-neutral-50 hover:text-neutral-900' ?>">
                <i data-lucide="store" class="w-4 h-4 <?= $isStores ? 'text-teal-600' : '' ?>"></i><span>Stores</span>
            </a>

            <p class="px-3 pt-5 pb-2 text-[10px] font-semibold uppercase tracking-[0.18em] text-neutral-400">Finance</p>
            <a href="/developer/packages/" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm <?= $isPackages ? 'bg-neutral-50 font-medium text-neutral-900' : 'text-neutral-500 hover:bg-neutral-50 hover:text-neutral-900' ?>">
                <i data-lucide="package" class="w-4 h-4 <?= $isPackages ? 'text-amber-600' : '' ?>"></i><span>Paket</span>
            </a>
        </nav>

        <div class="mt-auto pt-4 border-t border-neutral-100 space-y-1">
            <a href="/logout.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm text-neutral-500 hover:bg-neutral-50 hover:text-neutral-900">
                <i data-lucide="log-out" class="w-4 h-4"></i><span>Keluar</span>
            </a>
        </div>
    </div>
</aside>
<div id="developerSidebarOverlay" class="fixed inset-0 z-40 bg-black/20 hidden lg:hidden"></div>
