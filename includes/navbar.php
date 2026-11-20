<?php
/**
 * ========================================================
 * Component: Navbar Header
 * ========================================================
 */
require_once __DIR__ . '/../functions/auth.php';
$current_user = get_logged_in_user();
?>
<header class="no-print bg-white border-b border-slate-200 sticky top-0 z-30">
    <div class="px-4 sm:px-6 lg:px-8 py-3 flex items-center justify-between">
        <div class="flex items-center space-x-3">
            <button id="mobile-menu-btn" type="button" class="lg:hidden p-2 text-slate-500 hover:text-slate-700 hover:bg-slate-100 rounded-lg">
                <i class="fa-solid fa-bars text-lg"></i>
            </button>
            <div class="flex items-center space-x-2">
                <div class="w-8 h-8 rounded-lg bg-indigo-600 flex items-center justify-center text-white font-bold shadow-md shadow-indigo-200">
                    <i class="fa-solid fa-square-parking"></i>
                </div>
                <div>
                    <h1 class="text-base font-bold text-slate-900 leading-tight"><?= APP_NAME ?></h1>
                    <p class="text-xs text-slate-500 hidden sm:block"><?= APP_SUBTITLE ?></p>
                </div>
            </div>
        </div>

        <div class="flex items-center space-x-4">
            <!-- Jam Digital Real-time -->
            <div class="hidden md:flex items-center text-xs font-mono-code font-medium text-slate-600 bg-slate-100 px-3 py-1.5 rounded-lg border border-slate-200">
                <i class="fa-regular fa-clock text-indigo-600 mr-2"></i>
                <span id="live-clock"><?= date('H:i:s') ?> WIB</span>
            </div>

            <!-- Profile Info & Role Badge -->
            <div class="flex items-center space-x-3 pl-2 border-l border-slate-200">
                <div class="text-right hidden sm:block">
                    <div class="text-sm font-semibold text-slate-800"><?= htmlspecialchars($current_user['nama_lengkap'] ?? 'User') ?></div>
                    <div class="flex items-center justify-end space-x-1">
                        <?php 
                            $role = $current_user['role'] ?? 'guest';
                            $badgeColor = 'bg-slate-100 text-slate-700';
                            if ($role === 'admin') $badgeColor = 'bg-purple-100 text-purple-700 border-purple-200';
                            elseif ($role === 'petugas') $badgeColor = 'bg-blue-100 text-blue-700 border-blue-200';
                            elseif ($role === 'owner') $badgeColor = 'bg-emerald-100 text-emerald-700 border-emerald-200';
                        ?>
                        <span class="inline-block text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full border <?= $badgeColor ?>">
                            <?= htmlspecialchars($role) ?>
                        </span>
                    </div>
                </div>

                <div class="w-9 h-9 rounded-full bg-slate-200 border border-slate-300 flex items-center justify-center text-slate-700 font-bold uppercase">
                    <?= substr($current_user['nama_lengkap'] ?? 'U', 0, 1) ?>
                </div>

                <a href="<?= BASE_URL ?>/modules/auth/logout.php" title="Keluar dari Aplikasi" class="p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors">
                    <i class="fa-solid fa-arrow-right-from-bracket text-base"></i>
                </a>
            </div>
        </div>
    </div>
</header>
