<?php
/* Template Name: Admin Panel Page */
get_header();

// بررسی ورود کاربر
if (!is_user_logged_in() || !current_user_can('manage_options')) {
    wp_redirect(home_url('/custom-login'));
    exit;
}

?>

<div class="container-fluid mt-5 text-right" dir="rtl">
    <div class="header mb-4 d-flex justify-content-between align-items-center">
        <h1>پنل مدیریت</h1>
        <a href="<?php echo wp_logout_url(home_url()); ?>" class="btn btn-danger">خروج</a>
    </div>
    <div class="upload-box mb-4 p-3 border rounded">
        <form action="<?php echo admin_url('admin-post.php'); ?>" method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="admin_upload_new_excel_file">
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

    <div class="content" id="fileListSection">
        <h2>فایل‌های اکسل کاربران</h2>
        <input type="text" id="searchInput" class="form-control mb-4" placeholder="جستجو...">
        <div class="row" id="fileList">
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
                $last_modified_persian = jdate('Y/m/d H:i:s', strtotime($last_modified));

                if ($assigned_user === 'all') {
                    $assigned_user_name = 'همه کاربران';
                } else {
                    $user_info = get_userdata($assigned_user);
                    $assigned_user_name = $user_info->display_name;
                }

                echo "
                <div class='col-md-4 mb-4'>
                    <div class='card'>
                        <div class='card-body'>
                            <h5 class='card-title'>$assigned_user_name</h5>
                            <table class='table table-sm'>
                                <thead>
                                    <tr>
                                        <th>فایل</th>
                                        <th>آخرین ویرایش</th>
                                        <th>عملیات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>" . get_the_title() . "</td>
                                        <td>$last_modified_persian</td>
                                        <td>
                                            <button class='btn btn-primary btn-sm view-file' data-file-url='$file_url' data-file-id='" . get_the_ID() . "'>مشاهده</button>
                                            <a href='" . $file_url . "' class='btn btn-secondary btn-sm' download>دانلود</a>
                                            <a href='" . wp_nonce_url(admin_url('admin-post.php?action=delete_file&delete_file=' . get_the_ID()), 'delete_file_' . get_the_ID()) . "' class='btn btn-danger btn-sm'>حذف</a>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>";
            }

            wp_reset_postdata();
            ?>
        </div>
    </div>

    <div class="content" id="excelEditSection" style="display: none;">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <button id="saveBtn" class="btn btn-success">ذخیره</button>
            <button id="backToListBtn" class="btn btn-secondary">بازگشت به لیست فایل‌ها</button>
        </div>
        <div id="sheetTabs" class="mb-3"></div>
        <div id="excelTable" class="handsontable-container" style="width: 100%; height: 600px; overflow-x: auto;"></div>
        <div id="loading" class="loading" style="display: none;">
            <div class="spinner-border" role="status">
                <span class="sr-only">Loading...</span>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/handsontable/dist/handsontable.full.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/handsontable/dist/handsontable.full.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script>
        document.getElementById('searchInput').addEventListener('input', function () {
            const searchValue = this.value.toLowerCase();
            document.querySelectorAll('#fileList .col-md-4').forEach(function (card) {
                const searchText = card.innerText.toLowerCase();
                if (searchText.includes(searchValue)) {
                    card.style.display = '';
                } else {
                    card.style.display = 'none';
                }
            });
        });

        document.querySelectorAll('.view-file').forEach(button => {
            button.addEventListener('click', function () {
                const fileUrl = this.getAttribute('data-file-url');
                const fileId = this.getAttribute('data-file-id');

                document.getElementById('fileListSection').style.display = 'none';
                document.getElementById('excelEditSection').style.display = 'block';
                loadExcelFile(fileUrl, fileId);
            });
        });

        document.getElementById('backToListBtn').addEventListener('click', function () {
            document.getElementById('excelEditSection').style.display = 'none';
            document.getElementById('fileListSection').style.display = 'block';
        });
        
      



        let currentSheetIndex = 0;
        let workbooks = [];
        let hotInstances = [];
        let headersArray = [];
                const fileUrl = this.getAttribute('data-file-url');
                const fileId = this.getAttribute('data-file-id');


          function loadExcelFile(fileUrl, fileId) {
            let workbooks = [], hotInstances = [], headersArray = [];
          
            fetch(fileUrl)
                .then(response => response.arrayBuffer())
                .then(data => {
                    const workbook = XLSX.read(new Uint8Array(data), { type: 'array' });
                    workbook.SheetNames.forEach((sheetName, index) => {
                        const sheet = workbook.Sheets[sheetName];
                        const jsonData = XLSX.utils.sheet_to_json(sheet, { header: 1 });
                        headersArray.push(jsonData.shift());
                        workbooks.push({ name: sheetName, data: jsonData });
                    });

                    const container = document.getElementById('excelTable');
                    if (workbooks.length > 1) createSheetTabs(workbooks);
                    loadSheet(0);

                    function createSheetTabs(workbooks) {
                        const sheetTabs = document.getElementById('sheetTabs');
                        sheetTabs.innerHTML = '';
                        workbooks.forEach((sheet, index) => {
                            const tab = document.createElement('button');
                            tab.className = 'btn btn-primary mx-1';
                            tab.innerText = sheet.name;
                            tab.addEventListener('click', () => loadSheet(index));
                            sheetTabs.appendChild(tab);
                        });
                    }

                    function loadSheet(index) {
                        if (hotInstances[index]) hotInstances[index].destroy();
                        hotInstances[index] = new Handsontable(container, {
                            data: workbooks[index].data,
                            rowHeaders: true,
                            colHeaders: headersArray[index],
                            contextMenu: true,
                            manualColumnResize: true,
                            manualRowResize: true,
                            autoColumnSize: true,
                            rowHeights: 23,
                            colWidths: 100,
                            licenseKey: 'non-commercial-and-evaluation',
                            stretchH: 'all',
                            rtl: true,
                            filters: true,
                            dropdownMenu: true,
                            columnSorting: true,
                            search: true,
                            language: 'fa-IR',
                            wordWrap: true,
                            autoWrapRow: true,
                            renderer: 'text',
                            cells: (row, col) => ({ wordWrap: true, className: 'htRight htMiddle' })
                        });
                    }
                });
        }

        document.querySelectorAll('.view-file').forEach(button => {
            button.addEventListener('click', function () {


                document.getElementById('fileListSection').style.display = 'none';
                document.getElementById('excelEditSection').style.display = 'block';
                loadExcelFile(fileUrl,fileId);
            });
        });

        document.getElementById('backToListBtn').addEventListener('click', function () {
            document.getElementById('excelEditSection').style.display = 'none';
            document.getElementById('fileListSection').style.display = 'block';
        });
        

          document.getElementById('saveBtn').addEventListener('click', function () {
            // نمایش حالت لودینگ
            document.getElementById('loading').style.display = 'block';

            const newWorkbook = XLSX.utils.book_new();

            workbooks.forEach((sheet, index) => {
                const hotInstance = hotInstances[index];
                const newData = hotInstance ? hotInstance.getData() : sheet.data;
                newData.unshift(headersArray[index]); // Add headers back to the data
                const worksheet = XLSX.utils.aoa_to_sheet(newData);
                XLSX.utils.book_append_sheet(newWorkbook, worksheet, sheet.name);
            });

            const wbout = XLSX.write(newWorkbook, { bookType: 'xlsx', type: 'array' });

            const formData = new FormData();
            formData.append('file', new Blob([wbout], { type: "application/octet-stream" }), 'edited_file.xlsx');
            formData.append('file_id', this.getAttribute('data-file-id'));
            console.log(fileId);
            fetch('<?php echo admin_url('admin-post.php?action=handle_admin_file_upload'); ?>', {
                method: 'POST',
                body: formData
            }).then(response => {
                if (response.ok) {
                    // مخفی کردن حالت لودینگ
                    document.getElementById('loading').style.display = 'none';
                    window.location.href = '<?php echo site_url('/admin-panel'); ?>';
                } else {
                    throw new Error('خطا در ذخیره‌سازی');
                }
            }).catch(error => {
                console.error('Error:', error);
                // مخفی کردن حالت لودینگ
                document.getElementById('loading').style.display = 'none';
            });
        });
 

        fetch(fileUrl)
            .then(response => response.arrayBuffer())
            .then(data => {
                const workbook = XLSX.read(new Uint8Array(data), { type: 'array' });
                workbooks = workbook.SheetNames.map(sheetName => {
                    const sheet = workbook.Sheets[sheetName];
                    const jsonData = XLSX.utils.sheet_to_json(sheet, { header: 1 });
                    const headers = jsonData.shift();
                    headersArray.push(headers);
                    return {
                        name: sheetName,
                        data: jsonData,
                        originalSheet: sheet
                    };
                });
                if (workbooks.length > 1) {
                    createSheetTabs();
                }
                loadSheet(currentSheetIndex);
            });

        function createSheetTabs() {
            const sheetTabs = document.getElementById('sheetTabs');
            workbooks.forEach((sheet, index) => {
                const tab = document.createElement('button');
                tab.className = 'btn btn-primary mx-1';
                tab.innerText = sheet.name;
                tab.addEventListener('click', () => loadSheet(index));
                sheetTabs.appendChild(tab);
            });
        }

        function loadSheet(index) {
            currentSheetIndex = index;
            const sheet = workbooks[index];
            const container = document.getElementById('excelTable');
            const headers = headersArray[index];

            if (hotInstances[currentSheetIndex]) {
                hotInstances[currentSheetIndex].destroy();
            }

            hotInstances[currentSheetIndex] = new Handsontable(container, {
                data: sheet.data,
                rowHeaders: true,
                colHeaders: headers,
                contextMenu: true,
                manualColumnResize: true,
                manualRowResize: true,
                autoColumnSize: true,
                rowHeights: 23,
                colWidths: 100,
                licenseKey: 'non-commercial-and-evaluation',
                stretchH: 'all',
                rtl: true,
                filters: true,
                dropdownMenu: true,
                columnSorting: true,
                search: true,
                language: 'fa-IR',
                wordWrap: true,
                autoWrapRow: true,
                renderer: 'text',
                cells: function (row, col) {
                    const cellProperties = {};
                    cellProperties.wordWrap = true;
                    cellProperties.className = 'htRight htMiddle'; // راست‌چین کردن متن
                    return cellProperties;
                }
            });
        }

        function toggleContextMenu() {
            hotInstances[currentSheetIndex].updateSettings({ contextMenu: !hotInstances[currentSheetIndex].getSettings().contextMenu });
        }

        function autoSizeColumns() {
            hotInstances[currentSheetIndex].updateSettings({ autoColumnSize: true });
        }

        function toggleWordWrap() {
            hotInstances[currentSheetIndex].updateSettings({ wordWrap: !hotInstances[currentSheetIndex].getSettings().wordWrap });
        }

        function addRow() {
            hotInstances[currentSheetIndex].alter('insert_row');
        }

        function addColumn() {
            hotInstances[currentSheetIndex].alter('insert_col');
        }

       
        



    </script>

<?php
get_footer();
?>

<?php
// کد PHP برای پردازش آپلود و ذخیره‌سازی فایل اکسل

// ذخیره فایل اکسل ویرایش شده
add_action('admin_post_admin_save_edited_excel_file', 'admin_save_edited_excel_file');
function admin_save_edited_excel_file() {
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

            // به‌روزرسانی پست
            wp_update_post(array(
                'ID' => $file_id,
                'post_content' => $file_url,
            ));

            // به‌روزرسانی متای پست
            update_post_meta($file_id, '_excel_file_path', $file_path);

            // بازگشت به پنل ادمین
            wp_redirect(site_url('/admin-panel'));
            exit;
        } else {
            wp_die('Upload error: ' . $movefile['error']);
        }
    } else {
        wp_die('Invalid request.');
    }
}

// آپلود فایل اکسل جدید
add_action('admin_post_admin_upload_new_excel_file', 'admin_upload_new_excel_file');
function admin_upload_new_excel_file() {
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
?>