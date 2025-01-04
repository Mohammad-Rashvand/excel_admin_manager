document.addEventListener('DOMContentLoaded', function () {
    const saveBtn = document.getElementById('saveBtn');
    const fileUrl = saveBtn?.dataset?.fileUrl;
    let currentSheetIndex = 0;
    let workbooks = [];
    let hotInstances = [];
    let headersArray = [];

    // Fetch the Excel file and parse it
    if (fileUrl) {
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
                if (workbooks.length > 1) createSheetTabs();
                loadSheet(currentSheetIndex);
            })
            .catch(error => console.error('Error fetching the Excel file:', error));
    }

    // Create tabs for each sheet in the workbook
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

    // Load a specific sheet into the Handsontable instance
    function loadSheet(index) {
        currentSheetIndex = index;
        const sheet = workbooks[index];
        const container = document.getElementById('excelTable');
        const headers = headersArray[index];

        if (hotInstances[currentSheetIndex]) hotInstances[currentSheetIndex].destroy();

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
                cellProperties.className = 'htRight htMiddle';
                return cellProperties;
            }
        });
    }

    // Save the edited Excel file
    saveBtn?.addEventListener('click', function () {
        document.getElementById('loading').style.display = 'block';

        const newWorkbook = XLSX.utils.book_new();

        workbooks.forEach((sheet, index) => {
            const hotInstance = hotInstances[index];
            const newData = hotInstance ? hotInstance.getData() : sheet.data;
            newData.unshift(headersArray[index]);
            const worksheet = XLSX.utils.aoa_to_sheet(newData);
            XLSX.utils.book_append_sheet(newWorkbook, worksheet, sheet.name);
        });

        const wbout = XLSX.write(newWorkbook, { bookType: 'xlsx', type: 'array' });
        const blob = new Blob([wbout], { type: "application/octet-stream" });

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = saveBtn.dataset.uploadUrl;

        const fileInput = document.createElement('input');
        fileInput.type = 'hidden';
        fileInput.name = 'file';
        fileInput.value = URL.createObjectURL(blob);
        form.appendChild(fileInput);

        const fileIdInput = document.createElement('input');
        fileIdInput.type = 'hidden';
        fileIdInput.name = 'file_id';
        fileIdInput.value = saveBtn.dataset.fileId;
        form.appendChild(fileIdInput);

        document.body.appendChild(form);
        form.submit();
    });

    $(document).ready(function () {
        $('#searchDate').persianDatepicker({
            format: 'YYYY/MM/DD',
            initialValue: false
        });
    });

    document.getElementById('selectAll').addEventListener('change', function () {
        const checkboxes = document.querySelectorAll('.fileCheckbox');
        checkboxes.forEach(checkbox => checkbox.checked = this.checked);
    });

    window.performSearch = function () {
        const searchName = document.getElementById('searchName').value.toLowerCase();
        const searchUser = document.getElementById('searchUser').value;
        const searchDate = document.getElementById('searchDate').value;

        const rows = document.querySelectorAll('#fileTableBody tr');
        rows.forEach(row => {
            const rowData = row.getAttribute('data-search').toLowerCase();
            const userMatch = searchUser === '' || rowData.includes(searchUser);
            const nameMatch = searchName === '' || rowData.includes(searchName);
            const dateMatch = searchDate === '' || rowData.includes(searchDate);

            row.style.display = (userMatch && nameMatch && dateMatch) ? '' : 'none';
        });
    };

    window.bulkDelete = function () {
        const selectedFiles = document.querySelectorAll('.fileCheckbox:checked');
        const fileIds = Array.from(selectedFiles).map(checkbox => checkbox.value);

        if (fileIds.length === 0) {
            alert('هیچ فایلی انتخاب نشده است.');
            return;
        }

        if (!confirm('آیا مطمئن هستید که می‌خواهید فایل‌های انتخاب شده را حذف کنید؟')) {
            return;
        }

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = saveBtn.dataset.bulkDeleteUrl;

        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'bulk_delete_files';
        form.appendChild(actionInput);

        const fileIdsInput = document.createElement('input');
        fileIdsInput.type = 'hidden';
        fileIdsInput.name = 'file_ids';
        fileIdsInput.value = JSON.stringify(fileIds);
        form.appendChild(fileIdsInput);

        document.body.appendChild(form);
        form.submit();
    };
});

document.addEventListener('DOMContentLoaded', function () {
    // نمایش و ویرایش لاگ ورود و خروج در قالب اکسل
    if (typeof entryExitData !== 'undefined') {
        const container = document.getElementById('excelTable');
        const headers = ['نام کارمند', 'نوع', 'زمان', 'تاریخ'];
        const data = entryExitData.data.map(entry => [entry.employee, entry.type, entry.time, entry.date]);

        const hot = new Handsontable(container, {
            data: data,
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
                cellProperties.className = 'htRight htMiddle';
                return cellProperties;
            }
        });

        // دکمه ذخیره برای بروز رسانی لاگ‌ها
        const saveBtn = document.createElement('button');
        saveBtn.className = 'btn btn-primary mt-3';
        saveBtn.innerText = 'ذخیره';
        saveBtn.addEventListener('click', function () {
            const updatedData = hot.getData();
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = window.location.href;

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'save_updated_log';
            form.appendChild(actionInput);

            const dataInput = document.createElement('input');
            dataInput.type = 'hidden';
            dataInput.name = 'updated_data';
            dataInput.value = JSON.stringify(updatedData);
            form.appendChild(dataInput);

            document.body.appendChild(form);
            form.submit();
        });
        container.parentElement.appendChild(saveBtn);
    }

    // جستجوی لاگ ورود و خروج
    document.getElementById('entryExitSearch').addEventListener('keyup', function () {
        const filter = this.value.toLowerCase();
        const rows = document.querySelectorAll('#entryExitTableBody tr');
        rows.forEach(function (row) {
            const rowText = row.getAttribute('data-search').toLowerCase();
            row.style.display = rowText.includes(filter) ? '' : 'none';
        });
    });

    // دانلود لاگ ورود و خروج به صورت فایل اکسل
    document.getElementById('download-excel').addEventListener('click', function () {
        const hotData = hot.getData();
        hotData.unshift(headers);  // اضافه کردن هدرها به داده‌ها
        const worksheet = XLSX.utils.aoa_to_sheet(hotData);
        const newWorkbook = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(newWorkbook, worksheet, 'EntryExitLog');
        const wbout = XLSX.write(newWorkbook, { bookType: 'xlsx', type: 'array' });
        const blob = new Blob([wbout], { type: "application/octet-stream" });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'entry_exit_log.xlsx';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    });

    // بخش ورود و خروج کارکنان
    document.querySelectorAll('.duration').forEach(function (timer) {
        let duration = parseInt(timer.getAttribute('data-duration'), 10);
        const status = timer.getAttribute('data-status');

        function updateTimer() {
            const hours = Math.floor(duration / 3600);
            const minutes = Math.floor((duration % 3600) / 60);
            const seconds = duration % 60;
            timer.textContent = `${hours} ساعت ${minutes} دقیقه ${seconds} ثانیه`;

            if (status === 'inside') duration++;
        }

        updateTimer();
        setInterval(updateTimer, 1000);
    });

    document.querySelectorAll('.show-more').forEach(button => {
        button.addEventListener('click', function () {
            const rows = this.previousElementSibling.querySelectorAll('.hidden-row');
            rows.forEach(row => row.style.display = 'table-row');
            this.style.display = 'none';
            this.nextElementSibling.style.display = 'block';
        });
    });

    document.querySelectorAll('.show-less').forEach(button => {
        button.addEventListener('click', function () {
            const rows = this.previousElementSibling.previousElementSibling.querySelectorAll('.hidden-row');
            rows.forEach(row => row.style.display = 'none');
            this.style.display = 'none';
            this.previousElementSibling.style.display = 'block';
        });
    });

    const searchInput = document.getElementById('search-input');
    searchInput.addEventListener('keyup', function () {
        const filter = searchInput.value.toLowerCase();
        document.querySelectorAll('.employee-card').forEach(card => {
            const name = card.getAttribute('data-name').toLowerCase();
            card.style.display = name.includes(filter) ? '' : 'none';
        });
    });

    const logSearchInput = document.getElementById('log-search-input');
    logSearchInput.addEventListener('keyup', function () {
        const filter = logSearchInput.value.toLowerCase();
        document.querySelectorAll('.table-report tbody tr').forEach(row => {
            const rowText = row.textContent.toLowerCase();
            row.style.display = rowText.includes(filter) ? '' : 'none';
        });
    });
});

document.addEventListener('DOMContentLoaded', function () {
    const loginForm = document.getElementById('login-form');
    if (loginForm) {
        loginForm.addEventListener('submit', function (e) {
            e.preventDefault();

            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = customLogin.ajax_url;

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'custom_login';
            form.appendChild(actionInput);

            const usernameInput = document.createElement('input');
            usernameInput.type = 'hidden';
            usernameInput.name = 'username';
            usernameInput.value = username;
            form.appendChild(usernameInput);

            const passwordInput = document.createElement('input');
            passwordInput.type = 'hidden';
            passwordInput.name = 'password';
            passwordInput.value = password;
            form.appendChild(passwordInput);

            const securityInput = document.createElement('input');
            securityInput.type = 'hidden';
            securityInput.name = 'security';
            securityInput.value = customLogin.nonce;
            form.appendChild(securityInput);

            document.body.appendChild(form);
            form.submit();
        });
    }

    const logoutButton = document.getElementById('logout-button');
    if (logoutButton) {
        logoutButton.addEventListener('click', function (e) {
            e.preventDefault();

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = customLogin.ajax_url;

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'custom_logout';
            form.appendChild(actionInput);

            const securityInput = document.createElement('input');
            securityInput.type = 'hidden';
            securityInput.name = 'security';
            securityInput.value = customLogin.nonce;
            form.appendChild(securityInput);

            document.body.appendChild(form);
            form.submit();
        });
    }
});