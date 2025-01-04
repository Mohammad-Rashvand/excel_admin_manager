<?php
// Panel Excel Anbar Panah || rashvand.me
// اضافه کردن کتابخانه jdf برای تاریخ شمسی
require_once get_template_directory() . '/jdf.php';

// شروع سشن با استفاده از هوک وردپرس
function start_session() {
    if (!session_id()) {
        session_start();
    }
}
add_action('init', 'start_session', 1);

// بارگذاری استایل‌ها و اسکریپت‌ها
function custom_login_scripts() {
    wp_enqueue_style('custom-login-style', get_template_directory_uri() . '/styles.css');
    wp_enqueue_script('script-js', get_template_directory_uri() . '/scripts.js', array(), '', true);
    wp_enqueue_style('bootstrap-css', 'https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css');
    wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css');
    wp_enqueue_script('bootstrap-js', 'https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js', array('jquery'), '', true);
    wp_enqueue_script('xlsx', 'https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.0/xlsx.full.min.js', array(), '', true);
    wp_enqueue_style('handsontable-css', 'https://cdn.jsdelivr.net/npm/handsontable/dist/handsontable.full.min.css');
    wp_enqueue_script('handsontable-js', 'https://cdn.jsdelivr.net/npm/handsontable/dist/handsontable.full.min.js', array(), '', true);
}
add_action('wp_enqueue_scripts', 'custom_login_scripts');

// هدایت کاربران پس از ورود
function custom_login_redirect($redirect_to, $request, $user) {
    if (isset($user->roles) && is_array($user->roles)) {
        if (in_array('administrator', $user->roles)) {
            return site_url('/admin-panel');
        } else {
            return site_url('/user-panel');
        }
    }
    return $redirect_to;
}
add_filter('login_redirect', 'custom_login_redirect', 10, 3);

// افزودن شورتکد برای فرم خروج سفارشی
function custom_logout_shortcode() {
    if (is_user_logged_in()) {
        wp_logout();
        wp_redirect(site_url('/custom-login'));
        exit;
    }
}
add_shortcode('custom_logout', 'custom_logout_shortcode');

// ذخیره فایل اکسل ویرایش‌شده
add_action('admin_post_handle_file_upload', 'handle_file_upload');
function handle_file_upload() {
    if (!is_user_logged_in() || !current_user_can('manage_options')) {
        wp_die('Permission denied');
    }

    if (isset($_FILES['excel_file']) && isset($_POST['user'])) {
        $uploadedfile = $_FILES['excel_file'];
        $upload_overrides = array('test_form' => false);

        $movefile = wp_handle_upload($uploadedfile, $upload_overrides);

        if ($movefile && !isset($movefile['error'])) {
            $file_url = $movefile['url'];
            $user_id = sanitize_text_field($_POST['user']);

            $file_id = wp_insert_post(array(
                'post_title' => basename($file_url),
                'post_content' => $file_url,
                'post_status' => 'publish',
                'post_type' => 'excel_file',
            ));

            if ($user_id !== 'all') {
                update_post_meta($file_id, 'assigned_user', $user_id);
            } else {
                update_post_meta($file_id, 'assigned_user', 'all');
            }

            wp_redirect(site_url('/admin-panel'));
            exit;
        } else {
            wp_die($movefile['error']);
        }
    }
}

add_action('admin_post_handle_file_uploadd', 'handle_file_uploadd');
function handle_file_uploadd() {
    if (!is_user_logged_in() || !current_user_can('manage_options')) {
        wp_die('Permission denied');
    }

    if (isset($_FILES['file']) && isset($_POST['file_id'])) {
        $file_id = intval($_POST['file_id']);
        $uploadedfile = $_FILES['file'];
        $upload_overrides = array('test_form' => false);

        $movefile = wp_handle_upload($uploadedfile, $upload_overrides);

        if ($movefile && !isset($movefile['error'])) {
            $file_url = $movefile['url'];
            $file_path = $movefile['file'];

            wp_update_post(array(
                'ID' => $file_id,
                'post_content' => $file_url,
            ));

            update_post_meta($file_id, '_excel_file_path', $file_path);
            wp_redirect(site_url('/admin-panel'));
            exit;
        } else {
            wp_die($movefile['error']);
        }
    }
}

// نمایش فایل‌های اکسل در پنل مدیریت
function list_excel_files() {
    if (!is_user_logged_in() || !current_user_can('manage_options')) {
        wp_die('Permission denied');
    }

    $args = array(
        'post_type' => 'excel_file',
        'posts_per_page' => -1,
    );

    $excel_files = new WP_Query($args);

    echo "<div class='container mt-5'>";
    echo "<h2>لیست فایل‌های اکسل</h2>";
    echo "<table class='table table-bordered table-striped'><thead><tr><th>نام فایل</th><th>کاربر</th><th>زمان ویرایش</th><th>عملیات</th></tr></thead><tbody>";

    while ($excel_files->have_posts()) {
        $excel_files->the_post();
        $file_url = get_the_content();
        $assigned_user = get_post_meta(get_the_ID(), 'assigned_user', true);
        $last_modified = get_the_modified_time('Y-m-d H:i:s');
        $last_modified_persian = jdate('Y/m/d H:i:s', strtotime($last_modified));

        $assigned_user_name = ($assigned_user === 'all') ? 'همه کاربران' : get_userdata($assigned_user)->display_name;

        echo "<tr><td>" . get_the_title() . "</td><td>" . $assigned_user_name . "</td><td>" . $last_modified_persian . "</td><td><a href='" . add_query_arg('file_id', get_the_ID(), site_url('/admin-panel')) . "' class='btn btn-primary'>مشاهده و ویرایش</a> <a href='" . esc_url($file_url) . "' class='btn btn-secondary' download>دانلود</a> <a href='" . wp_nonce_url(admin_url('admin-post.php?action=delete_file&delete_file=' . get_the_ID()), 'delete_file_' . get_the_ID()) . "' class='btn btn-danger'>حذف</a></td></tr>";
    }

    echo "</tbody></table></div>";
}
add_shortcode('list_excel_files', 'list_excel_files');

// حذف فایل اکسل
function delete_excel_file() {
    if (!is_user_logged_in() || !current_user_can('manage_options')) {
        wp_die('Permission denied');
    }

    if (isset($_GET['delete_file']) && check_admin_referer('delete_file_' . $_GET['delete_file'])) {
        $file_id = sanitize_text_field($_GET['delete_file']);
        wp_delete_post($file_id);
        wp_redirect(site_url('/admin-panel'));
        exit;
    }
}
add_action('admin_post_delete_file', 'delete_excel_file');

// ثبت نوع پست سفارشی برای فایل‌های اکسل
function register_excel_file_post_type() {
    $labels = array(
        'name' => 'فایل‌های اکسل',
        'singular_name' => 'فایل اکسل',
        'menu_name' => 'فایل‌های اکسل',
        'name_admin_bar' => 'فایل اکسل',
        'add_new' => 'افزودن جدید',
        'add_new_item' => 'افزودن فایل اکسل جدید',
        'new_item' => 'فایل اکسل جدید',
        'edit_item' => 'ویرایش فایل اکسل',
        'view_item' => 'مشاهده فایل اکسل',
        'all_items' => 'همه فایل‌های اکسل',
        'search_items' => 'جستجوی فایل‌های اکسل',
        'not_found' => 'فایلی یافت نشد.',
    );

    $args = array(
        'labels' => $labels,
        'public' => true,
        'publicly_queryable' => true,
        'show_ui' => true,
        'show_in_menu' => true,
        'query_var' => true,
        'rewrite' => array('slug' => 'excel-file'),
        'capability_type' => 'post',
        'has_archive' => true,
        'hierarchical' => false,
        'menu_position' => null,
        'supports' => array('title', 'editor', 'author'),
        'show_in_rest' => true,
    );

    register_post_type('excel_file', $args);
}
add_action('init', 'register_excel_file_post_type');

// تغییر مسیر کاربر `security_gate` به صفحه سفارشی
function redirect_security_gate_user() {
    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();
        if ($current_user->user_login === 'security_gate') {
            if (!is_page_template('security-gate-panel.php') && !is_page('security-gate-panel')) {
                wp_redirect(home_url('/security-gate-panel'));
                exit;
            }
        }
    }
}
add_action('template_redirect', 'redirect_security_gate_user');

// ثبت نوع پست سفارشی برای ورود و خروج کارکنان
if (!function_exists('register_entry_exit_post_type')) {
    function register_entry_exit_post_type() {
        $labels = array(
            'name' => 'ورود و خروج',
            'singular_name' => 'ورود و خروج',
            'menu_name' => 'ورود و خروج',
            'name_admin_bar' => 'ورود و خروج',
            'add_new' => 'افزودن جدید',
            'add_new_item' => 'افزودن ورود و خروج جدید',
            'new_item' => 'ورود و خروج جدید',
            'edit_item' => 'ویرایش ورود و خروج',
            'view_item' => 'مشاهده ورود و خروج',
            'all_items' => 'همه ورود و خروج‌ها',
            'search_items' => 'جستجوی ورود و خروج',
            'not_found' => 'ورودی و خروجی یافت نشد.',
        );

        $args = array(
            'labels' => $labels,
            'public' => false,
            'publicly_queryable' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'entry-exit'),
            'capability_type' => 'post',
            'has_archive' => false,
            'hierarchical' => false,
            'menu_position' => null,
            'supports' => array('title', 'custom-fields'),
        );

        register_post_type('entry_exit', $args);
    }
}
add_action('init', 'register_entry_exit_post_type');

// نمایش لیست ورود و خروج در پنل مدیریت
function list_entry_exit() {
    if (!is_user_logged_in() || !current_user_can('manage_options')) {
        wp_die('Permission denied');
    }

    $args = array(
        'post_type' => 'entry_exit',
        'posts_per_page' => -1,
    );

    $entry_exit_posts = new WP_Query($args);

    echo "<div class='container mt-5 text-right' dir='rtl'>";
    echo "<h2>لیست ورود و خروج‌ها</h2>";
    echo "<button class='btn btn-success mb-3' id='download-excel'>دانلود لاگ به صورت اکسل</button>";
    echo "<div id='excelTable' class='handsontable-container' style='width: 100%; height: 600px; overflow-x: auto;'></div>";
    echo "</div>";

    $data = array();
    while ($entry_exit_posts->have_posts()) {
        $entry_exit_posts->the_post();
        $entry_exit_data = get_post_meta(get_the_ID(), 'entry_exit_data', true);
        $employee_name = get_the_title();

        if (is_array($entry_exit_data)) {
            foreach ($entry_exit_data as $data_entry) {
                $data[] = array(
                    'employee' => $employee_name,
                    'type' => $data_entry['type'],
                    'time' => $data_entry['time'],
                    'date' => get_post_meta(get_the_ID(), 'entry_exit_date', true)
                );
            }
        }
    }
    wp_localize_script('script-js', 'entryExitData', array('data' => $data));
}
add_shortcode('list_entry_exit', 'list_entry_exit');

// ذخیره لاگ‌های ویرایش شده
add_action('admin_post_save_updated_log', 'save_updated_log');
function save_updated_log() {
    if (!is_user_logged_in() || !current_user_can('manage_options')) {
        wp_die('Permission denied');
    }

    $updated_data = json_decode(stripslashes($_POST['updated_data']), true);

    if (is_array($updated_data)) {
        foreach ($updated_data as $entry) {
            $employee_name = $entry[0];
            $type = $entry[1];
            $time = $entry[2];
            $date = $entry[3];

            // یافتن یا ایجاد پست مربوط به کارمند
            $args = array(
                'post_type' => 'entry_exit',
                'title' => $employee_name,
                'meta_query' => array(
                    array(
                        'key' => 'entry_exit_date',
                        'value' => $date,
                        'compare' => '='
                    )
                )
            );
            $query = new WP_Query($args);

            if ($query->have_posts()) {
                $post_id = $query->posts[0]->ID;
            } else {
                $post_id = wp_insert_post(array(
                    'post_title' => $employee_name,
                    'post_type' => 'entry_exit',
                    'post_status' => 'publish'
                ));
                update_post_meta($post_id, 'entry_exit_date', $date);
            }

            // به‌روزرسانی داده‌های ورود و خروج
            $entry_exit_data = get_post_meta($post_id, 'entry_exit_data', true);
            if (!is_array($entry_exit_data)) {
                $entry_exit_data = array();
            }
            $entry_exit_data[] = array('type' => $type, 'time' => $time);
            update_post_meta($post_id, 'entry_exit_data', $entry_exit_data);
        }
    }

    wp_redirect(wp_get_referer());
    exit;
}

// دانلود لاگ ورود و خروج به صورت فایل اکسل
add_action('admin_post_download_entry_exit_log', 'download_entry_exit_log');
function download_entry_exit_log() {
    if (!is_user_logged_in() || !current_user_can('manage_options')) {
        wp_die('Permission denied');
    }

    $args = array(
        'post_type' => 'entry_exit',
        'posts_per_page' => -1,
    );

    $entry_exit_posts = new WP_Query($args);
    $data = array(
        array('نام کارمند', 'نوع', 'زمان', 'تاریخ')
    );

    while ($entry_exit_posts->have_posts()) {
        $entry_exit_posts->the_post();
        $entry_exit_data = get_post_meta(get_the_ID(), 'entry_exit_data', true);
        $employee_name = get_the_title();
        $entry_exit_date = get_post_meta(get_the_ID(), 'entry_exit_date', true);

        if (is_array($entry_exit_data)) {
            foreach ($entry_exit_data as $data_entry) {
                $data[] = array($employee_name, $data_entry['type'], $data_entry['time'], $entry_exit_date);
            }
        }
    }

    $filename = 'entry_exit_log_' . date('Y-m-d_H-i-s') . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray($data, null, 'A1');

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

// اضافه کردن متاتگ theme-color اگر وجود ندارد
function add_theme_color_if_missing() {
    global $wp_head;
    $theme_color_meta = '<meta name="theme-color" content="#125A91">';

    if (stripos($wp_head, $theme_color_meta) === false) {
        $wp_head = $theme_color_meta . "\n" . $wp_head;
    }
    echo '<meta name="theme-color" content="#125A91">';
    return $wp_head;
}
add_filter('wp_head', 'add_theme_color_if_missing', 1);

// دسترسی به کتابخانه‌های ضروری
require get_template_directory() . '/inc/custom-header.php';
require get_template_directory() . '/inc/template-tags.php';
require get_template_directory() . '/inc/extras.php';
require get_template_directory() . '/inc/admin-panel/theme-options.php';
require get_template_directory() . '/inc/jetpack.php';
require get_template_directory() . '/inc/custom-metabox.php';
require get_template_directory() . '/inc/ap-lite-woocommerce-function.php';
require get_template_directory() . '/welcome/welcome.php';

add_filter('widget_text', 'do_shortcode');