<?php
/* Template Name: User Panel Page */
get_header();

if (!is_user_logged_in() || !current_user_can('read')) {
    wp_redirect(home_url('/custom-login'));
    exit;
}

// نمایش فایل اکسل و فرم ویرایش
if (isset($_GET['file_id'])) {
    $file_id = intval($_GET['file_id']);
    $file_url = get_post_field('post_content', $file_id);
    ?>
    <div class="container-fluid mt-5 text-right" dir="rtl">
        <div class="header mb-4 d-flex justify-content-between align-items-center">
            <h1>پنل کاربر</h1>
            <a href="<?php echo wp_logout_url(home_url()); ?>" class="btn btn-danger">خروج</a>
        </div>
        <div class="content">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <button id="saveBtn" class="btn btn-success">ذخیره</button>
                <a href="<?php echo site_url('/user-panel'); ?>" class="btn btn-secondary">بازگشت به لیست فایل‌ها</a>
            </div>
            <div id="sheetTabs" class="mb-3"></div>
            <div id="toolbar" class="mb-3">
                <!-- امکانات مربوط به فایل اکسل -->
                <button class="btn btn-primary" onclick="toggleContextMenu()">فعال کردن منوی راست کلیک</button>
                <button class="btn btn-primary" onclick="autoSizeColumns()">تنظیم عرض ستون‌ها</button>
                <button class="btn btn-primary" onclick="toggleWordWrap()">فعال کردن Wrap Text</button>
                <button class="btn btn-primary" onclick="addRow()">اضافه کردن ردیف</button>
                <button class="btn btn-primary" onclick="addColumn()">اضافه کردن ستون</button>
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

    <!-- Include Bootstrap CSS for responsive design -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Include Handsontable CSS and JS for advanced table features -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/handsontable/dist/handsontable.full.min.css">
    <script src="https://cdn.jsdelivr.net/npm/handsontable/dist/handsontable.full.min.js"></script>
    <!-- Include SheetJS for Excel file processing -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.16.9/xlsx.full.min.js"></script>

    <style>
        /* سبک دهی به حالت لودینگ */
        .loading {
            position: fixed;
            z-index: 999;
            height: 2em;
            width: 2em;
            overflow: show;
            margin: auto;
            top: 0;
            left: 0;
            bottom: 0;
            right: 0;
        }
        .loading:before {
            content: '';
            display: block;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(255, 255, 255, 0.5);
        }
        /* سبک دهی مدرن به دکمه‌ها */
        .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
        }
        .btn-primary:hover {
            background-color: #0056b3;
            border-color: #004085;
        }
        .btn-danger {
            background-color: #dc3545;
            border-color: #dc3545;
        }
        .btn-danger:hover {
            background-color: #c82333;
            border-color: #bd2130;
        }
        .btn-success {
            background-color: #28a745;
            border-color: #28a745;
        }
        .btn-success:hover {
            background-color: #218838;
            border-color: #1e7e34;
        }
    </style>

    <script>
        const fileUrl = "<?php echo $file_url; ?>";
        let currentSheetIndex = 0;
        let workbooks = [];
        let hotInstances = [];
        let headersArray = [];

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
            formData.append('file_id', <?php echo $file_id; ?>);

            fetch('<?php echo admin_url('admin-post.php?action=handle_file_uploadd'); ?>', {
                method: 'POST',
                body: formData
            }).then(response => {
                if (response.ok) {
                    // مخفی کردن حالت لودینگ
                    document.getElementById('loading').style.display = 'none';
                    window.location.href = '<?php echo site_url('/user-panel'); ?>';
                } else {
                    throw new Error('خطا در ذخیره‌سازی');
                }
            }).catch(error => {
                console.error('Error:', error);
                // مخفی کردن حالت لودینگ
                document.getElementById('loading').style.display = 'none';
            });
        });
    </script>

    <?php
} else {
    ?>
    <div class="container-fluid mt-5 text-right" dir="rtl">
        <div class="header mb-4 d-flex justify-content-between align-items-center">
            <h1>پنل کاربر</h1>
            <a href="<?php echo wp_logout_url(home_url()); ?>" class="btn btn-danger">خروج</a>
        </div>
        <div class="content">
            <h2>فایل‌های شما</h2>
            
            <!-- Table for displaying files -->
            <div class="table-responsive">
                <table class="table table-striped text-right" dir="rtl">
                    <thead>
                        <tr>
                            <th>نام فایل</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $current_user_id = get_current_user_id();
                    $args = array(
                        'post_type' => 'excel_file',
                        'posts_per_page' => -1,
                        'meta_query' => array(
                            'relation' => 'OR',
                            array(
                                'key' => 'assigned_user',
                                'value' => $current_user_id,
                                'compare' => '=',
                            ),
                            array(
                                'key' => 'assigned_user',
                                'value' => 'all',
                                'compare' => '=',
                            ),
                        ),
                    );

                    $excel_files = new WP_Query($args);

                    while ($excel_files->have_posts()) {
                        $excel_files->the_post();
                        $file_url = get_the_content();
                        echo "<tr><td>" . get_the_title() . "</td><td><a href='" . add_query_arg('file_id', get_the_ID(), site_url('/user-panel')) . "' class='btn btn-primary'>ویرایش</a></td></tr>";
                    }

                    wp_reset_postdata();
                    ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php
}

get_footer();
?>