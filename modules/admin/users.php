<?php
/**
 * ========================================================
 * Modul Admin: Manajemen User (CRUD tb_user)
 * ========================================================
 */
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../functions/auth.php';
require_once __DIR__ . '/../../functions/log.php';
require_once __DIR__ . '/../../functions/pagination.php';

require_role('admin');

$pdo = get_db_connection();
$current_user = get_logged_in_user();

$action = $_GET['action'] ?? 'list';
$error = '';

// --------------------------------------------------------
// PROSES TAMBAH USER (CREATE)
// --------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_add_user'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Token CSRF tidak valid!');
        redirect(BASE_URL . '/modules/admin/users.php');
    }

    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    $username     = trim($_POST['username'] ?? '');
    $password     = trim($_POST['password'] ?? '');
    $role         = trim($_POST['role'] ?? 'petugas');
    $status_aktif = isset($_POST['status_aktif']) ? 1 : 0;

    if (empty($nama_lengkap) || empty($username) || empty($password)) {
        set_flash('danger', 'Nama lengkap, username, dan password wajib diisi.');
    } elseif (!in_array($role, ['admin', 'petugas', 'owner'])) {
        set_flash('danger', 'Role yang dipilih tidak valid.');
    } else {
        // Cek duplikasi username
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM tb_user WHERE username = :username");
        $stmt_check->execute([':username' => $username]);
        if ($stmt_check->fetchColumn() > 0) {
            set_flash('danger', "Username '$username' sudah terdaftar. Gunakan username lain.");
        } else {
            $hashed = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("
                INSERT INTO tb_user (nama_lengkap, username, password, role, status_aktif)
                VALUES (:nama_lengkap, :username, :password, :role, :status_aktif)
            ");
            $exec = $stmt->execute([
                ':nama_lengkap' => $nama_lengkap,
                ':username'     => $username,
                ':password'     => $hashed,
                ':role'         => $role,
                ':status_aktif' => $status_aktif
            ]);

            if ($exec) {
                catat_log($pdo, $current_user['id_user'], "Menambahkan user baru: $username ($role)");
                set_flash('success', "Pengguna '$nama_lengkap' berhasil ditambahkan.");
            } else {
                set_flash('danger', 'Gagal menambahkan pengguna.');
            }
        }
    }
    redirect(BASE_URL . '/modules/admin/users.php');
}

// --------------------------------------------------------
// PROSES EDIT USER (UPDATE)
// --------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_edit_user'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Token CSRF tidak valid!');
        redirect(BASE_URL . '/modules/admin/users.php');
    }

    $id_user      = (int)($_POST['id_user'] ?? 0);
    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    $username     = trim($_POST['username'] ?? '');
    $password     = trim($_POST['password'] ?? '');
    $role         = trim($_POST['role'] ?? 'petugas');
    $status_aktif = isset($_POST['status_aktif']) ? 1 : 0;

    // Cek duplikasi username untuk pengguna lain
    $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM tb_user WHERE username = :username AND id_user != :id_user");
    $stmt_check->execute([':username' => $username, ':id_user' => $id_user]);
    if ($stmt_check->fetchColumn() > 0) {
        set_flash('danger', "Username '$username' sudah digunakan oleh pengguna lain.");
    } else {
        if (!empty($password)) {
            // Update password baru jika diisi
            $hashed = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("
                UPDATE tb_user 
                SET nama_lengkap = :nama_lengkap, username = :username, password = :password, role = :role, status_aktif = :status_aktif
                WHERE id_user = :id_user
            ");
            $params = [
                ':nama_lengkap' => $nama_lengkap,
                ':username'     => $username,
                ':password'     => $hashed,
                ':role'         => $role,
                ':status_aktif' => $status_aktif,
                ':id_user'      => $id_user
            ];
        } else {
            // Biarkan password lama
            $stmt = $pdo->prepare("
                UPDATE tb_user 
                SET nama_lengkap = :nama_lengkap, username = :username, role = :role, status_aktif = :status_aktif
                WHERE id_user = :id_user
            ");
            $params = [
                ':nama_lengkap' => $nama_lengkap,
                ':username'     => $username,
                ':role'         => $role,
                ':status_aktif' => $status_aktif,
                ':id_user'      => $id_user
            ];
        }

        if ($stmt->execute($params)) {
            catat_log($pdo, $current_user['id_user'], "Mengubah data user: $username (ID: $id_user)");
            set_flash('success', "Data pengguna '$nama_lengkap' berhasil diperbarui.");
        } else {
            set_flash('danger', 'Gagal memperbarui data pengguna.');
        }
    }
    redirect(BASE_URL . '/modules/admin/users.php');
}

// --------------------------------------------------------
// PROSES HAPUS USER (DELETE)
// --------------------------------------------------------
if ($action === 'delete') {
    $id_del = (int)($_GET['id'] ?? 0);
    if ($id_del === $current_user['id_user']) {
        set_flash('danger', 'Anda tidak dapat menghapus akun Anda sendiri yang sedang aktif.');
    } else {
        // Ambil data user untuk pencatatan log
        $stmt_find = $pdo->prepare("SELECT username FROM tb_user WHERE id_user = :id_user");
        $stmt_find->execute([':id_user' => $id_del]);
        $del_user = $stmt_find->fetch();

        if ($del_user) {
            try {
                $stmt = $pdo->prepare("DELETE FROM tb_user WHERE id_user = :id_user");
                $stmt->execute([':id_user' => $id_del]);
                catat_log($pdo, $current_user['id_user'], "Menghapus user: " . $del_user['username'] . " (ID: $id_del)");
                set_flash('success', "Pengguna '{$del_user['username']}' berhasil dihapus.");
            } catch (PDOException $e) {
                // Terjadi constraint foreign key jika user sudah memiliki transaksi/kendaraan
                set_flash('warning', "Pengguna tidak dapat dihapus karena memiliki relasi data riwayat transaksi/kendaraan. Nonaktifkan status akun sebagai gantinya.");
            }
        }
    }
    redirect(BASE_URL . '/modules/admin/users.php');
}

// --------------------------------------------------------
// READ & FILTER LIST DENGAN PAGINATION & LIMIT
// --------------------------------------------------------
$search = trim($_GET['search'] ?? '');
$role_filter = trim($_GET['role'] ?? '');
$page = (int)($_GET['page'] ?? 1);
$per_page = 8;

$where_clauses = [];
$params = [];

if (!empty($search)) {
    $where_clauses[] = "(nama_lengkap LIKE :search OR username LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($role_filter) && in_array($role_filter, ['admin', 'petugas', 'owner'])) {
    $where_clauses[] = "role = :role";
    $params[':role'] = $role_filter;
}

$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Hitung total record untuk pagination
$stmt_count = $pdo->prepare("SELECT COUNT(*) FROM tb_user $where_sql");
$stmt_count->execute($params);
$total_records = (int)$stmt_count->fetchColumn();

$pagination = paginate($total_records, $page, $per_page);

// Query utama dengan LIMIT & OFFSET
$query_users = "
    SELECT id_user, nama_lengkap, username, role, status_aktif
    FROM tb_user 
    $where_sql
    ORDER BY id_user DESC
    LIMIT :limit OFFSET :offset
";
$stmt_users = $pdo->prepare($query_users);
foreach ($params as $k => $v) {
    $stmt_users->bindValue($k, $v);
}
$stmt_users->bindValue(':limit', $pagination['per_page'], PDO::PARAM_INT);
$stmt_users->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
$stmt_users->execute();
$users = $stmt_users->fetchAll();

$page_title = 'Manajemen Pengguna';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="flex h-screen overflow-hidden bg-slate-50">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="flex-1 flex flex-col min-w-0 overflow-y-auto">
        <?php require_once __DIR__ . '/../../includes/navbar.php'; ?>

        <main class="flex-1 p-4 sm:p-6 lg:p-8">
            <div class="max-w-7xl mx-auto space-y-6">
                
                <!-- Page Header -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <div>
                        <h2 class="text-2xl font-bold text-slate-900 tracking-tight">Manajemen Data Pengguna</h2>
                        <p class="text-xs sm:text-sm text-slate-500">Kelola akun, role hak akses, dan status operasional sistem</p>
                    </div>
                    <button type="button" onclick="openModal('modalAddUser')" class="inline-flex items-center px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-lg shadow-indigo-600/20 transition-all">
                        <i class="fa-solid fa-user-plus mr-2"></i> Tambah Pengguna Baru
                    </button>
                </div>

                <?php require_once __DIR__ . '/../../includes/alerts.php'; ?>

                <!-- Filters & Search Bar -->
                <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm flex flex-col sm:flex-row gap-4 items-center justify-between">
                    <form method="GET" class="flex flex-1 flex-col sm:flex-row gap-3 w-full">
                        <div class="relative flex-1">
                            <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-3 text-slate-400 text-xs"></i>
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari nama lengkap atau username..."
                                class="w-full pl-9 pr-4 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <select name="role" class="text-xs bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="">Semua Role</option>
                            <option value="admin" <?= $role_filter === 'admin' ? 'selected' : '' ?>>Admin</option>
                            <option value="petugas" <?= $role_filter === 'petugas' ? 'selected' : '' ?>>Petugas</option>
                            <option value="owner" <?= $role_filter === 'owner' ? 'selected' : '' ?>>Owner</option>
                        </select>
                        <button type="submit" class="px-4 py-2 bg-slate-800 text-white text-xs font-semibold rounded-xl hover:bg-slate-900 transition-colors">
                            Filter
                        </button>
                        <?php if (!empty($search) || !empty($role_filter)): ?>
                            <a href="<?= BASE_URL ?>/modules/admin/users.php" class="px-3 py-2 bg-slate-100 text-slate-600 text-xs font-medium rounded-xl hover:bg-slate-200 transition-colors flex items-center justify-center">
                                Reset
                            </a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Users Table -->
                <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="bg-slate-50 border-b border-slate-200/80 text-slate-500 uppercase font-semibold">
                                    <th class="py-3 px-4">#</th>
                                    <th class="py-3 px-4">Nama Lengkap</th>
                                    <th class="py-3 px-4">Username</th>
                                    <th class="py-3 px-4">Hak Akses (Role)</th>
                                    <th class="py-3 px-4">Status Akun</th>
                                    <th class="py-3 px-4 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php if (empty($users)): ?>
                                    <tr>
                                        <td colspan="6" class="py-8 text-center text-slate-400">
                                            Tidak ditemukan data pengguna yang cocok.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php 
                                    $no = $pagination['start_record'];
                                    foreach ($users as $u): 
                                    ?>
                                        <tr class="hover:bg-slate-50/80 transition-colors">
                                            <td class="py-3.5 px-4 font-medium text-slate-400"><?= $no++ ?></td>
                                            <td class="py-3.5 px-4">
                                                <div class="font-semibold text-slate-800"><?= htmlspecialchars($u['nama_lengkap']) ?></div>
                                            </td>
                                            <td class="py-3.5 px-4 font-mono-code text-slate-600">@<?= htmlspecialchars($u['username']) ?></td>
                                            <td class="py-3.5 px-4">
                                                <?php 
                                                    $rClass = 'bg-slate-100 text-slate-700';
                                                    if ($u['role'] === 'admin') $rClass = 'bg-purple-100 text-purple-700 border-purple-200';
                                                    elseif ($u['role'] === 'petugas') $rClass = 'bg-blue-100 text-blue-700 border-blue-200';
                                                    elseif ($u['role'] === 'owner') $rClass = 'bg-emerald-100 text-emerald-700 border-emerald-200';
                                                ?>
                                                <span class="inline-block px-2.5 py-0.5 rounded-full border text-[11px] font-bold uppercase tracking-wider <?= $rClass ?>">
                                                    <?= htmlspecialchars($u['role']) ?>
                                                </span>
                                            </td>
                                            <td class="py-3.5 px-4">
                                                <?php if ((int)$u['status_aktif'] === 1): ?>
                                                    <span class="inline-flex items-center text-emerald-600 font-semibold text-[11px]">
                                                        <i class="fa-solid fa-circle-check mr-1.5"></i> Aktif
                                                    </span>
                                                <?php else: ?>
                                                    <span class="inline-flex items-center text-rose-500 font-semibold text-[11px]">
                                                        <i class="fa-solid fa-circle-xmark mr-1.5"></i> Nonaktif
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="py-3.5 px-4 text-center">
                                                <div class="inline-flex items-center space-x-2">
                                                    <!-- Edit Button -->
                                                    <button type="button" 
                                                        onclick='editUser(<?= json_encode($u) ?>)'
                                                        class="p-1.5 text-slate-500 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors" title="Edit Pengguna">
                                                        <i class="fa-solid fa-pen-to-square text-sm"></i>
                                                    </button>

                                                    <!-- Delete Button -->
                                                    <?php if ($u['id_user'] != $current_user['id_user']): ?>
                                                        <button type="button" 
                                                            onclick="confirmDelete('<?= BASE_URL ?>/modules/admin/users.php?action=delete&id=<?= $u['id_user'] ?>', 'Hapus pengguna @<?= htmlspecialchars($u['username']) ?>?')"
                                                            class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors" title="Hapus Pengguna">
                                                            <i class="fa-solid fa-trash-can text-sm"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="p-1.5 text-slate-300 cursor-not-allowed" title="Akun Anda Sendiri">
                                                            <i class="fa-solid fa-trash-can text-sm"></i>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
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
                    $base_pagination_url = BASE_URL . '/modules/admin/users.php' . (!empty($params_url) ? '?' . http_build_query($params_url) : '');
                    echo render_pagination($pagination, $base_pagination_url);
                    ?>
                </div>

            </div>
        </main>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<!-- ========================================================
     MODAL TAMBAH USER
     ======================================================== -->
<div id="modalAddUser" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-md w-full overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50">
            <h3 class="text-sm font-bold text-slate-900 flex items-center">
                <i class="fa-solid fa-user-plus text-indigo-600 mr-2"></i> Tambah Pengguna Baru
            </h3>
            <button type="button" onclick="closeModal('modalAddUser')" class="text-slate-400 hover:text-slate-600">
                <i class="fa-solid fa-xmark text-base"></i>
            </button>
        </div>

        <form action="" method="POST" class="p-6 space-y-4">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="btn_add_user" value="1">

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Lengkap</label>
                <input type="text" name="nama_lengkap" required placeholder="Contoh: Budi Santoso"
                    class="w-full px-3 py-2 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Username</label>
                <input type="text" name="username" required placeholder="Contoh: budi_petugas"
                    class="w-full px-3 py-2 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Password</label>
                <input type="password" name="password" required placeholder="Minimal 6 karakter"
                    class="w-full px-3 py-2 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Role Akses</label>
                    <select name="role" required class="w-full px-3 py-2 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        <option value="petugas">Petugas</option>
                        <option value="admin">Admin</option>
                        <option value="owner">Owner</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Status Akun</label>
                    <div class="pt-2">
                        <label class="inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="status_aktif" value="1" checked class="sr-only peer">
                            <div class="w-9 h-5 bg-slate-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-emerald-500"></div>
                            <span class="ml-2 text-xs font-medium text-slate-700">Aktif</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="pt-4 border-t border-slate-100 flex items-center justify-end space-x-2">
                <button type="button" onclick="closeModal('modalAddUser')" class="px-4 py-2 text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-xl transition-colors">
                    Batal
                </button>
                <button type="submit" class="px-4 py-2 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-md shadow-indigo-600/20 transition-all">
                    Simpan Pengguna
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================
     MODAL EDIT USER
     ======================================================== -->
<div id="modalEditUser" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-md w-full overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50">
            <h3 class="text-sm font-bold text-slate-900 flex items-center">
                <i class="fa-solid fa-pen-to-square text-indigo-600 mr-2"></i> Edit Data Pengguna
            </h3>
            <button type="button" onclick="closeModal('modalEditUser')" class="text-slate-400 hover:text-slate-600">
                <i class="fa-solid fa-xmark text-base"></i>
            </button>
        </div>

        <form action="" method="POST" class="p-6 space-y-4">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="btn_edit_user" value="1">
            <input type="hidden" id="edit_id_user" name="id_user" value="">

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Lengkap</label>
                <input type="text" id="edit_nama_lengkap" name="nama_lengkap" required
                    class="w-full px-3 py-2 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Username</label>
                <input type="text" id="edit_username" name="username" required
                    class="w-full px-3 py-2 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">
                    Password <span class="font-normal text-slate-400">(Kosongkan jika tidak diubah)</span>
                </label>
                <input type="password" name="password" placeholder="Biarkan kosong jika tetap"
                    class="w-full px-3 py-2 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Role Akses</label>
                    <select id="edit_role" name="role" required class="w-full px-3 py-2 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        <option value="petugas">Petugas</option>
                        <option value="admin">Admin</option>
                        <option value="owner">Owner</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Status Akun</label>
                    <div class="pt-2">
                        <label class="inline-flex items-center cursor-pointer">
                            <input type="checkbox" id="edit_status_aktif" name="status_aktif" value="1" class="sr-only peer">
                            <div class="w-9 h-5 bg-slate-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-emerald-500"></div>
                            <span class="ml-2 text-xs font-medium text-slate-700">Aktif</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="pt-4 border-t border-slate-100 flex items-center justify-end space-x-2">
                <button type="button" onclick="closeModal('modalEditUser')" class="px-4 py-2 text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-xl transition-colors">
                    Batal
                </button>
                <button type="submit" class="px-4 py-2 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-md shadow-indigo-600/20 transition-all">
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function editUser(user) {
    document.getElementById('edit_id_user').value = user.id_user;
    document.getElementById('edit_nama_lengkap').value = user.nama_lengkap;
    document.getElementById('edit_username').value = user.username;
    document.getElementById('edit_role').value = user.role;
    document.getElementById('edit_status_aktif').checked = (parseInt(user.status_aktif) === 1);
    openModal('modalEditUser');
}
</script>
