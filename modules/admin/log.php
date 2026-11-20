<?php
/**
 * ========================================================
 * Modul Admin: Akses Log Aktivitas Sistem (tb_log_aktivitas)
 * Best practice: Query efisien dengan LIMIT & Pagination
 * ========================================================
 */
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../functions/auth.php';
require_once __DIR__ . '/../../functions/parkir.php';
require_once __DIR__ . '/../../functions/pagination.php';

require_role('admin');

$pdo = get_db_connection();

// Filter & Parameter
$search    = trim($_GET['search'] ?? '');
$user_id   = (int)($_GET['user_id'] ?? 0);
$tanggal   = trim($_GET['tanggal'] ?? '');
$page      = (int)($_GET['page'] ?? 1);
$per_page  = 15; // Limit data besar

$where_clauses = [];
$params = [];

if (!empty($search)) {
    $where_clauses[] = "l.aktivitas LIKE :search";
    $params[':search'] = "%$search%";
}

if ($user_id > 0) {
    $where_clauses[] = "l.id_user = :user_id";
    $params[':user_id'] = $user_id;
}

if (!empty($tanggal)) {
    $where_clauses[] = "DATE(l.waktu_aktivitas) = :tanggal";
    $params[':tanggal'] = $tanggal;
}

$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Hitung total data log secara efisien
$stmt_count = $pdo->prepare("SELECT COUNT(*) FROM tb_log_aktivitas l $where_sql");
$stmt_count->execute($params);
$total_records = (int)$stmt_count->fetchColumn();

$pagination = paginate($total_records, $page, $per_page);

// Query utama log dengan JOIN dan LIMIT & OFFSET
$query_log = "
    SELECT l.id_log, l.aktivitas, l.waktu_aktivitas, u.nama_lengkap, u.username, u.role
    FROM tb_log_aktivitas l
    JOIN tb_user u ON l.id_user = u.id_user
    $where_sql
    ORDER BY l.waktu_aktivitas DESC
    LIMIT :limit OFFSET :offset
";
$stmt_logs = $pdo->prepare($query_log);
foreach ($params as $k => $v) {
    $stmt_logs->bindValue($k, $v);
}
$stmt_logs->bindValue(':limit', $pagination['per_page'], PDO::PARAM_INT);
$stmt_logs->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
$stmt_logs->execute();
$logs = $stmt_logs->fetchAll();

// Ambil list user untuk dropdown filter
$users_list = $pdo->query("SELECT id_user, nama_lengkap, username, role FROM tb_user ORDER BY nama_lengkap ASC")->fetchAll();

$page_title = 'Audit Log Aktivitas';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="flex h-screen overflow-hidden bg-slate-50">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="flex-1 flex flex-col min-w-0 overflow-y-auto">
        <?php require_once __DIR__ . '/../../includes/navbar.php'; ?>

        <main class="flex-1 p-4 sm:p-6 lg:p-8">
            <div class="max-w-7xl mx-auto space-y-6">
                
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <div>
                        <h2 class="text-2xl font-bold text-slate-900 tracking-tight">Audit Log Aktivitas Sistem</h2>
                        <p class="text-xs sm:text-sm text-slate-500">Jejak rekaman otomatis setiap operasi CRUD admin dan transaksi petugas</p>
                    </div>
                    <span class="inline-flex items-center px-3 py-1 bg-slate-100 text-slate-700 text-xs font-semibold rounded-xl border border-slate-200">
                        <i class="fa-solid fa-database mr-2 text-indigo-600"></i> Total Log: <?= number_format($total_records) ?>
                    </span>
                </div>

                <!-- Filter Card -->
                <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm">
                    <form method="GET" class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                        <div>
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari aktivitas..."
                                class="w-full px-3 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        </div>
                        <div>
                            <select name="user_id" class="w-full px-3 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                                <option value="">Semua Pengguna</option>
                                <?php foreach ($users_list as $ul): ?>
                                    <option value="<?= $ul['id_user'] ?>" <?= $user_id === (int)$ul['id_user'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($ul['nama_lengkap']) ?> (<?= $ul['role'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <input type="date" name="tanggal" value="<?= htmlspecialchars($tanggal) ?>"
                                class="w-full px-3 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        </div>
                        <div class="flex gap-2">
                            <button type="submit" class="flex-1 px-4 py-2 bg-slate-800 text-white text-xs font-semibold rounded-xl hover:bg-slate-900 transition-colors">
                                <i class="fa-solid fa-filter mr-1.5"></i> Filter
                            </button>
                            <?php if (!empty($search) || $user_id > 0 || !empty($tanggal)): ?>
                                <a href="<?= BASE_URL ?>/modules/admin/log.php" class="px-3 py-2 bg-slate-100 text-slate-600 text-xs font-medium rounded-xl hover:bg-slate-200 transition-colors flex items-center justify-center">
                                    Reset
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <!-- Log Table -->
                <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="bg-slate-50 border-b border-slate-200/80 text-slate-500 uppercase font-semibold">
                                    <th class="py-3 px-4">#</th>
                                    <th class="py-3 px-4">Pengguna</th>
                                    <th class="py-3 px-4">Role</th>
                                    <th class="py-3 px-4">Aktivitas Sistem</th>
                                    <th class="py-3 px-4">Waktu Eksekusi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php if (empty($logs)): ?>
                                    <tr>
                                        <td colspan="5" class="py-8 text-center text-slate-400">
                                            Tidak ada catatan log aktivitas yang sesuai.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php 
                                    $no = $pagination['start_record'];
                                    foreach ($logs as $l): 
                                        $rClass = 'bg-slate-100 text-slate-700';
                                        if ($l['role'] === 'admin') $rClass = 'bg-purple-100 text-purple-700 border-purple-200';
                                        elseif ($l['role'] === 'petugas') $rClass = 'bg-blue-100 text-blue-700 border-blue-200';
                                        elseif ($l['role'] === 'owner') $rClass = 'bg-emerald-100 text-emerald-700 border-emerald-200';
                                    ?>
                                        <tr class="hover:bg-slate-50/80 transition-colors">
                                            <td class="py-3.5 px-4 font-medium text-slate-400"><?= $no++ ?></td>
                                            <td class="py-3.5 px-4">
                                                <div class="font-semibold text-slate-800"><?= htmlspecialchars($l['nama_lengkap']) ?></div>
                                                <div class="text-[11px] text-slate-400 font-mono-code">@<?= htmlspecialchars($l['username']) ?></div>
                                            </td>
                                            <td class="py-3.5 px-4">
                                                <span class="inline-block px-2.5 py-0.5 rounded-full border text-[10px] font-bold uppercase tracking-wider <?= $rClass ?>">
                                                    <?= htmlspecialchars($l['role']) ?>
                                                </span>
                                            </td>
                                            <td class="py-3.5 px-4">
                                                <div class="font-medium text-slate-900"><?= htmlspecialchars($l['aktivitas']) ?></div>
                                            </td>
                                            <td class="py-3.5 px-4 font-mono-code text-slate-500 whitespace-nowrap">
                                                <?= format_tanggal_indo($l['waktu_aktivitas'], true) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php 
                    $params_url = $_GET;
                    unset($params_url['page']);
                    $base_pagination_url = BASE_URL . '/modules/admin/log.php' . (!empty($params_url) ? '?' . http_build_query($params_url) : '');
                    echo render_pagination($pagination, $base_pagination_url);
                    ?>
                </div>

            </div>
        </main>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>
