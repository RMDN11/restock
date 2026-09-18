<?php
$pageTitle = $pageTitle ?? 'Developer Console';
$authName = $authName ?? 'Developer';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#ffffff">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> · RESTOCK Developer</title>
    <link rel="icon" type="image/png" href="/assets/images/logo.png">
    <link rel="stylesheet" href="/assets/css/app.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-neutral-50 text-neutral-900">
<header class="fixed inset-x-0 top-0 z-50 h-16 bg-white/95 backdrop-blur border-b border-neutral-200">
    <div class="h-full flex items-center justify-between px-4 lg:px-6">
        <div class="flex items-center gap-3 min-w-0">
            <button type="button" id="developerMenuToggle" class="lg:hidden w-10 h-10 rounded-xl hover:bg-neutral-100 flex items-center justify-center" aria-label="Buka menu" aria-controls="developerSidebar" aria-expanded="false">
                <i data-lucide="menu" class="w-5 h-5"></i>
            </button>
            <a href="/developer/" class="flex items-center gap-3 min-w-0">
                <img src="/assets/images/logo.png" alt="RESTOCK" class="w-8 h-8 object-contain">
                <div class="hidden sm:block leading-tight">
                    <div class="font-semibold tracking-tight">RESTOCK</div>
                    <div class="text-[10px] uppercase tracking-[0.18em] text-neutral-400">Developer</div>
                </div>
            </a>
        </div>
        <div class="flex items-center gap-2">
            <a href="/" class="hidden sm:inline-flex h-10 items-center gap-2 rounded-xl border border-neutral-200 bg-white px-3 text-sm font-medium text-neutral-600 hover:bg-neutral-50" title="Buka aplikasi">
                <i data-lucide="external-link" class="w-4 h-4"></i>Aplikasi
            </a>
            <button type="button" onclick="window.location.reload()" class="w-10 h-10 rounded-xl hover:bg-neutral-100 flex items-center justify-center" title="Muat ulang" aria-label="Muat ulang">
                <i data-lucide="refresh-cw" class="w-4 h-4 text-neutral-500"></i>
            </button>
            <div class="hidden sm:flex items-center gap-2 px-3 py-2 rounded-xl bg-neutral-50 border border-neutral-100">
                <span class="w-7 h-7 rounded-lg bg-neutral-900 text-white flex items-center justify-center text-xs font-semibold"><?= htmlspecialchars(strtoupper(substr($authName, 0, 1)), ENT_QUOTES, 'UTF-8') ?></span>
                <div class="leading-tight">
                    <div class="text-sm font-medium"><?= htmlspecialchars($authName, ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="text-[10px] uppercase tracking-wider text-neutral-400">Developer</div>
                </div>
            </div>
            <a href="/logout.php" class="w-10 h-10 rounded-xl hover:bg-neutral-100 flex items-center justify-center" title="Keluar" aria-label="Keluar">
                <i data-lucide="log-out" class="w-5 h-5 text-neutral-500"></i>
            </a>
        </div>
    </div>
</header>
