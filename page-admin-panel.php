<?php
/* Template Name: Admin Panel Page */
get_header();

// بررسی ورود کاربر
if (!is_user_logged_in() || !current_user_can('manage_options')) {
    wp_redirect(home_url('/custom-login'));
    exit;
}

// نمایش و ویرایش فایل اکسل
if (isset($_GET['file_id'])) {
    $file_id = intval($_GET['file_id']);
    $file_url = get_post_field('post_content', $file_id);
    ?>
    <div class="container-fluid mt-5 text-right" dir="rtl">
        <div class="header mb-4 d-flex justify-content-between align-items-center">
            <h1>پنل مدیریت</h1>
            <a href="<?php echo wp_logout_url(home_url()); ?>" class="btn btn-danger">خروج</a>
        </div>
        <div class="content">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <button id="saveBtn" class="btn btn-success" data-file-url="<?php echo esc_url($file_url); ?>" data-file-id="<?php echo esc_attr($file_id); ?>" data-upload-url="<?php echo esc_url(admin_url('admin-post.php?action=save_edited_file')); ?>">ذخیره</button>
                <a href="<?php echo esc_url(site_url('/admin-panel')); ?>" class="btn btn-secondary">بازگشت به لیست فایل‌ها</a>
            </div>
            <div id="excelTable" class="handsontable-container" style="width: 100%; height: 600px; overflow-x: auto;"></div>
            <!-- حالت لودینگ -->
            <div id="loading" class="loading" style="display: none;">
                <div class="spinner-border" role="status">
                    <span class="sr-only">Loading...</span>
                </div>
            </div>
        </div>
    </div>
    <?php
} else {
    ?>
    <div class="container-fluid mt-5 text-right" dir="rtl">
        <div class="header mb-4 d-flex justify-content-between align-items-center">
            <h1>پنل مدیریت</h1>
            <a href="<?php echo wp_logout_url(home_url()); ?>" class="btn btn-danger">خروج</a>
        </div>
        <div class="row">
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h4>آپلود فایل اکسل جدید</h4>
                    </div>
                    <div class="card-body">
                        <form action="<?php echo admin_url('admin-post.php'); ?>" method="post" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="handle_file_upload">
                            <div class="form-group">
                                <label for="excel_file">انتخاب فایل اکسل:</label>
                                <input type="file" name="excel_file" id="excel_file" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label for="user">انتخاب کاربر:</label>
                                <select name="user" id="user" class="form-control">
                                    <option value="all">همه کاربران</option>
                                    <?php
                                    $users = get_users();
                                    foreach ($users as $user) {
                                        echo '<option value="' . esc_attr($user->ID) . '">' . esc_html($user->display_name) . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary">آپلود</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h4>آمار</h4>
                    </div>
                    <div class="card-body">
                        <p>تعداد کل کارکنان: <?php echo count(get_users()); ?></p>
                        <p>تعداد کل فایل‌های اکسل: <?php echo wp_count_posts('excel_file')->publish; ?></p>
                        <p>تعداد کل ورود و خروج‌های روز جاری: 
                            <?php
                            $today = date('Y-m-d');
                            $args = array(
                                'post_type' => 'entry_exit',
                                'date_query' => array(
                                    array(
                                        'after' => $today,
                                        'before' => $today . ' 23:59:59',
                                        'inclusive' => true,
                                    ),
                                ),
                                'posts_per_page' => -1,
                            );
                            $entry_exit_posts = new WP_Query($args);
                            echo $entry_exit_posts->found_posts;
                            ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <ul class="nav nav-tabs" id="myTab" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="excel-tab" data-toggle="tab" href="#excel" role="tab" aria-controls="excel" aria-selected="true">مدیریت فایل‌های اکسل</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="entry-exit-tab" data-toggle="tab" href="#entry-exit" role="tab" aria-controls="entry-exit" aria-selected="false">مدیریت ورود و خروج</a>
            </li>
        </ul>
        <div class="tab-content" id="myTabContent">
            <div class="tab-pane fade show active" id="excel" role="tabpanel" aria-labelledby="excel-tab">
                <div class="content">
                    <h2>فایل‌های اکسل شما</h2>
                    <!-- جدول نمایش فایل‌ها -->
                    <div class="table-responsive">
                        <table class="table table-striped text-right" dir="rtl">
                            <thead>
                                <tr>
                                    <th><input type="checkbox" id="selectAll"></th>
                                    <th>نام فایل</th>
                                    <th>کاربر</th>
                                    <th>تاریخ</th>
                                    <th>ساعت</th>
                                    <th>عملیات</th>
                                </tr>
                            </thead>
                            <tbody id="fileTableBody">
                            <?php
                            $args = array(
                                'post_type' => 'excel_file',
                                'posts_per_page' => -1,
                            );

                            $excel_files = new WP_Query($args);

                            while ($excel_files->have_posts()) {
                                $excel_files->the_post();
                                $file_url = get_the_content();
                                $assigned_user = get_post_meta(get_the_ID(), 'assigned_user', true);
                                $last_modified = get_the_modified_time('Y-m-d H:i:s');
                                $last_modified_persian = jdate('Y/m/d', strtotime($last_modified));
                                $last_modified_time = jdate('H:i:s', strtotime($last_modified));

                                $assigned_user_name = ($assigned_user === 'all') ? 'همه کاربران' : get_userdata($assigned_user)->display_name;

                                echo "<tr data-search='" . esc_attr(get_the_title() . ' ' . $assigned_user_name . ' ' . $last_modified_persian . ' ' . $last_modified_time) . "'>";
                                echo "<td><input type='checkbox' class='fileCheckbox' value='" . get_the_ID() . "'></td>";
                                echo "<td>" . get_the_title() . "</td>";
                                echo "<td>" . $assigned_user_name . "</td>";
                                echo "<td>" . $last_modified_persian . "</td>";
                                echo "<td>" . $last_modified_time . "</td>";
                                echo "<td>
                                        <a href='" . add_query_arg('file_id', get_the_ID(), site_url('/admin-panel')) . "' class='btn btn-primary'>مشاهده و ویرایش</a> 
                                        <a href='" . esc_url($file_url) . "' class='btn btn-secondary' download>دانلود</a> 
                                        <a href='" . wp_nonce_url(admin_url('admin-post.php?action=delete_file&delete_file=' . get_the_ID()), 'delete_file_' . get_the_ID()) . "' class='btn btn-danger'>حذف</a>
                                      </td>";
                                echo "</tr>";
                            }

                            wp_reset_postdata();
                            ?>
                            </tbody>
                        </table>
                    </div>
                    <!-- دکمه حذف دسته‌جمعی -->
                    <div class="d-flex justify-content-end">
                        <button class="btn btn-danger" onclick="bulkDelete()">حذف دسته‌جمعی</button>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="entry-exit" role="tabpanel" aria-labelledby="entry-exit-tab">
                <div class="content">
                    <h2>مدیریت ورود و خروج</h2>
                    <button class="btn btn-success mb-3" id="download-excel">دانلود لاگ به صورت اکسل</button>
                    <input type="text" id="entryExitSearch" class="form-control mb-3" placeholder="جستجوی نام کارمند یا تاریخ">
                    <div class="table-responsive">
                        <div id="entryExitTableBody" class="handsontable-container" style="width: 100%; height: 600px; overflow-x: auto;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}

get_footer();
?>