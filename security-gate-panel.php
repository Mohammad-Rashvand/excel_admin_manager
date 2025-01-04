<?php
/* Template Name: Security Gate Page */
require_once get_template_directory() . '/jdf.php'; // اضافه کردن کتابخانه jdf
get_header();

// دریافت لیست کاربران به جز ادمین
$users = get_users(array(
    'exclude' => array(1), // فرض می‌کنیم ID ادمین 1 است
    'role__not_in' => array('administrator'),
));

// ثبت ورود و خروج
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['employee_id']) && isset($_POST['entry_exit_type'])) {
    check_admin_referer('entry_exit_action', 'entry_exit_nonce'); // بررسی nonce برای امنیت
    $employee_id = intval($_POST['employee_id']);
    $entry_exit_type = sanitize_text_field($_POST['entry_exit_type']);
    $entry_exit_time = current_time('mysql');
    $current_date = current_time('Y-m-d');

    // بررسی وضعیت ورود و خروج کارمند برای امروز
    $log_entry = get_posts(array(
        'post_type' => 'entry_exit',
        'posts_per_page' => 1,
        'meta_query' => array(
            array(
                'key' => 'employee_id',
                'value' => $employee_id,
                'compare' => '=',
            ),
            array(
                'key' => 'entry_exit_date',
                'value' => $current_date,
                'compare' => '=',
            ),
        ),
    ));

    if (!empty($log_entry)) {
        $log_entry_id = $log_entry[0]->ID;
        $entry_exit_data = get_post_meta($log_entry_id, 'entry_exit_data', true);
        if (!is_array($entry_exit_data)) {
            $entry_exit_data = [];
        }

        $entry_exit_data[] = [
            'type' => $entry_exit_type,
            'time' => $entry_exit_time,
        ];
        update_post_meta($log_entry_id, 'entry_exit_data', $entry_exit_data);
    } else {
        $entry_exit_data = [
            [
                'type' => $entry_exit_type,
                'time' => $entry_exit_time,
            ]
        ];
        wp_insert_post(array(
            'post_title' => get_userdata($employee_id)->display_name,
            'post_content' => '',
            'post_status' => 'publish',
            'post_type' => 'entry_exit',
            'meta_input' => array(
                'employee_id' => $employee_id,
                'entry_exit_date' => $current_date,
                'entry_exit_data' => $entry_exit_data,
            ),
        ));
    }
}

// دریافت لاگ ورود و خروج
$log_entries = get_posts(array(
    'post_type' => 'entry_exit',
    'posts_per_page' => -1,
    'meta_key' => 'entry_exit_date',
    'orderby' => 'meta_value',
    'order' => 'DESC',
));
?>

<div class="container-fluid mt-5 text-right" dir="rtl">
    <div class="header mb-4 d-flex justify-content-between align-items-center">
        <h1>پنل ثبت ورود و خروج</h1>
        <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="btn btn-danger">خروج</a>
    </div>

    <ul class="nav nav-tabs" id="myTab" role="tablist">
        <li class="nav-item" role="presentation">
            <a class="nav-link active" id="employees-tab" data-toggle="tab" href="#employees" role="tab" aria-controls="employees" aria-selected="true">کارمندان</a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link" id="log-tab" data-toggle="tab" href="#log" role="tab" aria-controls="log" aria-selected="false">تاریخچه و لاگ</a>
        </li>
    </ul>

    <div class="tab-content" id="myTabContent">
        <div class="tab-pane fade show active" id="employees" role="tabpanel" aria-labelledby="employees-tab">
            <div class="search-box">
                <input type="text" id="search-input" class="form-control" placeholder="جستجوی نام یا فامیلی کارمند">
            </div>
            <div class="employee-grid">
                <div class="row" id="employee-list">
                    <?php foreach ($users as $user) : ?>
                        <?php
                        $employee_name = esc_html($user->display_name);
                        $employee_department = esc_html(get_user_meta($user->ID, 'department', true));

                        // بررسی وضعیت ورود و خروج کارمند
                        $current_status = 'خارج از شرکت';
                        $button_text = 'ورود';
                        $button_class = 'btn-success';
                        $status_class = 'status-outside';
                        $duration_class = 'total-duration-outside';

                        $log_entry = get_posts(array(
                            'post_type' => 'entry_exit',
                            'posts_per_page' => 1,
                            'meta_query' => array(
                                array(
                                    'key' => 'employee_id',
                                    'value' => $user->ID,
                                    'compare' => '=',
                                ),
                                array(
                                    'key' => 'entry_exit_date',
                                    'value' => current_time('Y-m-d'),
                                    'compare' => '=',
                                ),
                            ),
                        ));

                        $entry_exit_data = [];
                        $total_duration = new DateTime('@0'); // شروع از زمان 0
                        if (!empty($log_entry)) {
                            $entry_exit_data = get_post_meta($log_entry[0]->ID, 'entry_exit_data', true);
                            if (is_array($entry_exit_data)) {
                                $last_entry = end($entry_exit_data);
                                if ($last_entry['type'] === 'ورود') {
                                    $current_status = 'داخل شرکت';
                                    $button_text = 'خروج';
                                    $button_class = 'btn-danger';
                                    $status_class = 'status-inside';
                                    $duration_class = 'total-duration-inside';
                                }

                                // محاسبه مجموع مدت زمان حضور
                                $entry_time = null;
                                foreach ($entry_exit_data as $data) {
                                    if ($data['type'] === 'ورود') {
                                        $entry_time = new DateTime($data['time']);
                                    } elseif ($entry_time && $data['type'] === 'خروج') {
                                        $exit_time = new DateTime($data['time']);
                                        $interval = $entry_time->diff($exit_time);
                                        $total_duration->add($interval);
                                        $entry_time = null;
                                    }
                                }
                            }
                        }

                        $total_duration_str = $total_duration->format('G ساعت i دقیقه s ثانیه');
                        ?>
                        <div class="col-md-4 mb-4 employee-card" data-name="<?php echo esc_attr($employee_name); ?>">
                            <div class="card">
                                <div class="card-header">
                                    <img src="<?php echo esc_url(get_avatar_url($user->ID, ['size' => '100'])); ?>" alt="User Image" style="width: 100px; height: 100px;">
                                    <div class="employee-info">
                                        <h5><?php echo $employee_name; ?></h5>
                                        <div class="status <?php echo esc_attr($status_class); ?>"><?php echo esc_html($current_status); ?></div>
                                    </div>
                                    <form method="post" action="">
                                        <?php wp_nonce_field('entry_exit_action', 'entry_exit_nonce'); ?>
                                        <input type="hidden" name="employee_id" value="<?php echo esc_attr($user->ID); ?>">
                                        <input type="hidden" name="entry_exit_type" value="<?php echo esc_attr($button_text); ?>">
                                        <button type="submit" class="btn <?php echo esc_attr($button_class); ?>"><?php echo esc_html($button_text); ?></button>
                                    </form>
                                </div>
                                <div class="card-body">
                                    <div class="total-duration <?php echo esc_attr($duration_class); ?>">
                                        مجموع حضور: <span class="duration" data-duration="<?php echo esc_attr($total_duration->getTimestamp()); ?>" data-status="<?php echo esc_attr($current_status === 'داخل شرکت' ? 'inside' : 'outside'); ?>"></span>
                                    </div>
                                    <?php if (!empty($entry_exit_data)) : ?>
                                        <table class="table table-sm mt-3">
                                            <thead>
                                                <tr>
                                                    <th>نوع</th>
                                                    <th>ساعت</th>
                                                    <th>تاریخ</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($entry_exit_data as $index => $data) : ?>
                                                    <tr class="<?php echo esc_attr($data['type'] === 'ورود' ? 'entry' : 'exit'); ?> <?php echo $index >= 4 ? 'hidden-row' : ''; ?>">
                                                        <td><?php echo esc_html($data['type']); ?></td>
                                                        <td><?php echo esc_html(date('H:i:s', strtotime($data['time']))); ?></td>
                                                        <td><?php echo esc_html(jdate('Y/m/d', strtotime($data['time']))); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                        <?php if (count($entry_exit_data) > 4) : ?>
                                            <div class="show-more">نمایش بیشتر</div>
                                            <div class="show-less" style="display: none;">نمایش کمتر</div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="tab-pane fade" id="log" role="tabpanel" aria-labelledby="log-tab">
            <h2 class="mt-5">گزارش ورود و خروج امروز</h2>
            <div class="search-box">
                <input type="text" id="log-search-input" class="form-control" placeholder="جستجوی نام کارمند یا تاریخ">
            </div>
            <table class="table table-bordered table-striped text-right table-report" dir="rtl">
                <thead>
                    <tr>
                        <th>نام کارمند</th>
                        <th>ورود اول</th>
                        <th>خروج اول</th>
                        <th>ورود دوم</th>
                        <th>خروج دوم</th>
                        <th>تاریخ</th>
                        <th>مدت زمان حضور</th>
                    </tr>
                </thead>
                <tbody id="log-table-body">
                    <?php foreach ($log_entries as $entry) : ?>
                        <?php
                        $entry_exit_data = get_post_meta($entry->ID, 'entry_exit_data', true);
                        if (!is_array($entry_exit_data)) {
                            $entry_exit_data = [];
                        }
                        $employee_id = get_post_meta($entry->ID, 'employee_id', true);
                        $employee_name = esc_html(get_userdata($employee_id)->display_name);
                        $entry_exit_date = get_post_meta($entry->ID, 'entry_exit_date', true);

                        $entry1 = '';
                        $exit1 = '';
                        $entry2 = '';
                        $exit2 = '';
                        $total_duration = new DateTime('@0'); // شروع از زمان 0

                        foreach ($entry_exit_data as $data) {
                            if ($data['type'] === 'ورود') {
                                if (empty($entry1)) {
                                    $entry1 = date('H:i:s', strtotime($data['time']));
                                } elseif (empty($entry2)) {
                                    $entry2 = date('H:i:s', strtotime($data['time']));
                                }
                            } else {
                                if (empty($exit1)) {
                                    $exit1 = date('H:i:s', strtotime($data['time']));
                                    if ($entry1) {
                                        $entry_time = new DateTime($entry1);
                                        $exit_time = new DateTime($data['time']);
                                        $interval = $entry_time->diff($exit_time);
                                        $total_duration->add($interval);
                                    }
                                } elseif (empty($exit2)) {
                                    $exit2 = date('H:i:s', strtotime($data['time']));
                                    if ($entry2) {
                                        $entry_time = new DateTime($entry2);
                                        $exit_time = new DateTime($data['time']);
                                        $interval = $entry_time->diff($exit_time);
                                        $total_duration->add($interval);
                                    }
                                }
                            }
                        }
                        $total_duration_str = $total_duration->format('G ساعت i دقیقه s ثانیه');
                        ?>
                        <tr>
                            <td><?php echo esc_html($employee_name); ?></td>
                            <td><?php echo esc_html($entry1); ?></td>
                            <td><?php echo esc_html($exit1); ?></td>
                            <td><?php echo esc_html($entry2); ?></td>
                            <td><?php echo esc_html($exit2); ?></td>
                            <td><?php echo esc_html(jdate('Y/m/d', strtotime($entry_exit_date))); ?></td>
                            <td><?php echo esc_html($total_duration_str); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="show-more" id="show-more-log">نمایش بیشتر</div>
            <div class="show-less" id="show-less-log" style="display: none;">نمایش کمتر</div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var timers = document.querySelectorAll('.duration');
    timers.forEach(function (timer) {
        var duration = parseInt(timer.getAttribute('data-duration'), 10);
        var status = timer.getAttribute('data-status');

        function updateTimer() {
            var hours = Math.floor(duration / 3600);
            var minutes = Math.floor((duration % 3600) / 60);
            var seconds = duration % 60;

            timer.textContent = hours + " ساعت " + minutes + " دقیقه " + seconds + " ثانیه";

            if (status === 'inside') {
                duration++;
            }
        }

        updateTimer();
        setInterval(updateTimer, 1000);
    });

    var showMoreButtons = document.querySelectorAll('.show-more');
    var showLessButtons = document.querySelectorAll('.show-less');
    showMoreButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            var rows = this.previousElementSibling.querySelectorAll('.hidden-row');
            rows.forEach(function (row) {
                row.style.display = 'table-row';
            });
            this.style.display = 'none';
            this.nextElementSibling.style.display = 'block';
        });
    });

    showLessButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            var rows = this.previousElementSibling.previousElementSibling.querySelectorAll('.hidden-row');
            rows.forEach(function (row) {
                row.style.display = 'none';
            });
            this.style.display = 'none';
            this.previousElementSibling.style.display = 'block';
        });
    });

    var searchInput = document.getElementById('search-input');
    searchInput.addEventListener('keyup', function () {
        var filter = searchInput.value.toLowerCase();
        var cards = document.querySelectorAll('.employee-card');
        cards.forEach(function (card) {
            var name = card.getAttribute('data-name').toLowerCase();
            if (name.includes(filter)) {
                card.style.display = '';
            } else {
                card.style.display = 'none';
            }
        });
    });

    // جستجوی پیشرفته در تب لاگ
    var logSearchInput = document.getElementById('log-search-input');
    logSearchInput.addEventListener('keyup', function () {
        var filter = logSearchInput.value.toLowerCase();
        var rows = document.querySelectorAll('#log-table-body tr');
        rows.forEach(function (row) {
            var name = row.cells[0].textContent.toLowerCase();
            var date = row.cells[5].textContent.toLowerCase();
            if (name.includes(filter) || date.includes(filter)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });

    // نمایش بیشتر و کمتر در تب لاگ
    var showMoreLogButton = document.getElementById('show-more-log');
    var showLessLogButton = document.getElementById('show-less-log');
    var logTableBody = document.getElementById('log-table-body');
    var logRows = logTableBody.querySelectorAll('tr');
    var visibleRows = 5; // تعداد ردیف‌هایی که به صورت پیش‌فرض نمایش داده می‌شوند

    function updateLogTableVisibility() {
        logRows.forEach(function (row, index) {
            if (index < visibleRows) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });

        if (visibleRows >= logRows.length) {
            showMoreLogButton.style.display = 'none';
            showLessLogButton.style.display = 'none';
        } else if (visibleRows > 5) {
            showMoreLogButton.style.display = 'none';
            showLessLogButton.style.display = 'block';
        } else {
            showMoreLogButton.style.display = 'block';
            showLessLogButton.style.display = 'none';
        }
    }

    showMoreLogButton.addEventListener('click', function () {
        visibleRows += 5;
        updateLogTableVisibility();
    });

    showLessLogButton.addEventListener('click', function () {
        visibleRows = 5;
        updateLogTableVisibility();
    });

    updateLogTableVisibility();
});
</script>

<?php
get_footer();
?>