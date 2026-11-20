<?php
/**
 * ========================================================
 * Component: Alert & Flash Messages
 * ========================================================
 */
require_once __DIR__ . '/../functions/auth.php';

$flash = get_flash();
if ($flash):
    $type = $flash['type'];
    $bgClass = 'bg-blue-50 border-blue-200 text-blue-800';
    $icon = 'fa-circle-info text-blue-500';

    if ($type === 'success') {
        $bgClass = 'bg-emerald-50 border-emerald-200 text-emerald-800';
        $icon = 'fa-circle-check text-emerald-500';
    } elseif ($type === 'danger' || $type === 'error') {
        $bgClass = 'bg-rose-50 border-rose-200 text-rose-800';
        $icon = 'fa-circle-exclamation text-rose-500';
    } elseif ($type === 'warning') {
        $bgClass = 'bg-amber-50 border-amber-200 text-amber-800';
        $icon = 'fa-triangle-exclamation text-amber-500';
    }
?>
    <div class="mb-5 flex items-center justify-between p-4 border rounded-xl <?= $bgClass ?> transition-all shadow-sm">
        <div class="flex items-center space-x-3">
            <i class="fa-solid <?= $icon ?> text-lg"></i>
            <span class="text-sm font-medium"><?= htmlspecialchars($flash['message']) ?></span>
        </div>
        <button type="button" onclick="this.parentElement.remove()" class="text-slate-400 hover:text-slate-600 focus:outline-none">
            <i class="fa-solid fa-xmark text-sm"></i>
        </button>
    </div>
<?php endif; ?>
