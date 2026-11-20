<?php
/**
 * ========================================================
 * Component: Footer & Closing Tags
 * ========================================================
 */
?>
        <!-- Footer Info -->
        <footer class="no-print mt-auto py-4 px-6 border-t border-slate-200 bg-white text-center sm:text-left text-xs text-slate-500 flex flex-col sm:flex-row justify-between items-center gap-2">
            <div>
                &copy; <?= date('Y') ?> <strong class="text-slate-700"><?= APP_NAME ?></strong>. Perusahaan Software Developer Enterprise.
            </div>
            <div class="flex items-center space-x-4">
                <span>Versi <?= APP_VERSION ?></span>
                <span class="text-slate-300">|</span>
                <span class="text-emerald-600 font-medium"><i class="fa-solid fa-shield-halved mr-1"></i> Sistem Aman & Terenkripsi</span>
            </div>
        </footer>
    </div>
</div>

<!-- Core Javascript -->
<script src="<?= BASE_URL ?>/assets/js/script.js"></script>
</body>
</html>
