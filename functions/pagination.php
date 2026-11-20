<?php
/**
 * ========================================================
 * Modul Pembagian Halaman (Pagination Engine)
 * Membantu eksekusi query LIMIT & OFFSET yang efisien
 * ========================================================
 */

/**
 * Menghitung parameter pagination data besar
 * 
 * @param int $total_records Total data dari COUNT()
 * @param int $current_page Halaman aktif saat ini
 * @param int $per_page Jumlah baris per halaman
 * @return array Konfigurasi pagination
 */
function paginate($total_records, $current_page = 1, $per_page = 10) {
    $per_page = max(1, (int)$per_page);
    $total_records = max(0, (int)$total_records);
    $total_pages = (int)ceil($total_records / $per_page);
    if ($total_pages < 1) {
        $total_pages = 1;
    }

    $current_page = max(1, min((int)$current_page, $total_pages));
    $offset = ($current_page - 1) * $per_page;

    $start_record = ($total_records === 0) ? 0 : $offset + 1;
    $end_record   = min($offset + $per_page, $total_records);

    return [
        'total_records' => $total_records,
        'per_page'      => $per_page,
        'current_page'  => $current_page,
        'total_pages'   => $total_pages,
        'offset'        => $offset,
        'start_record'  => $start_record,
        'end_record'    => $end_record,
        'has_prev'      => ($current_page > 1),
        'has_next'      => ($current_page < $total_pages),
        'prev_page'     => max(1, $current_page - 1),
        'next_page'     => min($total_pages, $current_page + 1)
    ];
}

/**
 * Render komponen HTML pagination berbasis Tailwind CSS
 * 
 * @param array $pagination Hasil dari fungsi paginate()
 * @param string $base_url URL halaman saat ini tanpa parameter page
 * @return string Komponen HTML
 */
function render_pagination($pagination, $base_url) {
    if ($pagination['total_pages'] <= 1) {
        return '';
    }

    $glue = (strpos($base_url, '?') !== false) ? '&' : '?';

    $html = '<div class="flex flex-col sm:flex-row items-center justify-between gap-4 py-4 px-2">';
    $html .= '<div class="text-xs text-slate-500">';
    $html .= 'Menampilkan <span class="font-semibold text-slate-700">' . $pagination['start_record'] . '</span> ';
    $html .= 'sampai <span class="font-semibold text-slate-700">' . $pagination['end_record'] . '</span> ';
    $html .= 'dari total <span class="font-semibold text-slate-700">' . $pagination['total_records'] . '</span> data';
    $html .= '</div>';

    $html .= '<nav class="inline-flex -space-x-px rounded-lg shadow-sm" aria-label="Pagination">';

    // Previous Button
    if ($pagination['has_prev']) {
        $prev_url = $base_url . $glue . 'page=' . $pagination['prev_page'];
        $html .= '<a href="' . htmlspecialchars($prev_url) . '" class="inline-flex items-center px-3 py-2 text-xs font-medium text-slate-600 bg-white border border-slate-200 rounded-l-lg hover:bg-slate-50 transition-colors"><i class="fa-solid fa-chevron-left mr-1"></i> Prev</a>';
    } else {
        $html .= '<span class="inline-flex items-center px-3 py-2 text-xs font-medium text-slate-300 bg-slate-50 border border-slate-200 rounded-l-lg cursor-not-allowed"><i class="fa-solid fa-chevron-left mr-1"></i> Prev</span>';
    }

    // Nomor Halaman (Membatasi tampilan agar tidak terjadi overflow jika ratusan halaman)
    $start = max(1, $pagination['current_page'] - 2);
    $end   = min($pagination['total_pages'], $pagination['current_page'] + 2);

    if ($start > 1) {
        $html .= '<a href="' . htmlspecialchars($base_url . $glue . 'page=1') . '" class="px-3 py-2 text-xs font-medium text-slate-600 bg-white border border-slate-200 hover:bg-slate-50">1</a>';
        if ($start > 2) {
            $html .= '<span class="px-2 py-2 text-xs text-slate-400 bg-white border border-slate-200">...</span>';
        }
    }

    for ($i = $start; $i <= $end; $i++) {
        if ($i === $pagination['current_page']) {
            $html .= '<span class="px-3 py-2 text-xs font-semibold text-white bg-indigo-600 border border-indigo-600">' . $i . '</span>';
        } else {
            $page_url = $base_url . $glue . 'page=' . $i;
            $html .= '<a href="' . htmlspecialchars($page_url) . '" class="px-3 py-2 text-xs font-medium text-slate-600 bg-white border border-slate-200 hover:bg-slate-50">' . $i . '</a>';
        }
    }

    if ($end < $pagination['total_pages']) {
        if ($end < $pagination['total_pages'] - 1) {
            $html .= '<span class="px-2 py-2 text-xs text-slate-400 bg-white border border-slate-200">...</span>';
        }
        $last_url = $base_url . $glue . 'page=' . $pagination['total_pages'];
        $html .= '<a href="' . htmlspecialchars($last_url) . '" class="px-3 py-2 text-xs font-medium text-slate-600 bg-white border border-slate-200 hover:bg-slate-50">' . $pagination['total_pages'] . '</a>';
    }

    // Next Button
    if ($pagination['has_next']) {
        $next_url = $base_url . $glue . 'page=' . $pagination['next_page'];
        $html .= '<a href="' . htmlspecialchars($next_url) . '" class="inline-flex items-center px-3 py-2 text-xs font-medium text-slate-600 bg-white border border-slate-200 rounded-r-lg hover:bg-slate-50 transition-colors">Next <i class="fa-solid fa-chevron-right ml-1"></i></a>';
    } else {
        $html .= '<span class="inline-flex items-center px-3 py-2 text-xs font-medium text-slate-300 bg-slate-50 border border-slate-200 rounded-r-lg cursor-not-allowed">Next <i class="fa-solid fa-chevron-right ml-1"></i></span>';
    }

    $html .= '</nav>';
    $html .= '</div>';

    return $html;
}
