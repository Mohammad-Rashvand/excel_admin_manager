document.addEventListener('DOMContentLoaded', function () {
    const saveBtn = document.getElementById('saveBtn');
    const fileUrl = saveBtn?.dataset?.fileUrl;
    let workbooks = [], hotInstances = [], headersArray = [];

    // Fetch the Excel file and parse it
    if (fileUrl) {
        fetch(fileUrl)
            .then(response => response.arrayBuffer())
            .then(data => {
                const workbook = XLSX.read(new Uint8Array(data), { type: 'array' });
                workbooks = workbook.SheetNames.map(sheetName => {
                    const sheet = workbook.Sheets[sheetName];
                    const jsonData = XLSX.utils.sheet_to_json(sheet, { header: 1 });
                    headersArray.push(jsonData.shift());
                    return { name: sheetName, data: jsonData };
                });
                if (workbooks.length > 1) createSheetTabs();
                loadSheet(0);
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
        const container = document.getElementById('excelTable');
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

    // Save the edited Excel file
    saveBtn?.addEventListener('click', function () {
        document.getElementById('loading').style.display = 'block';
        const newWorkbook = XLSX.utils.book_new();
        workbooks.forEach((sheet, index) => {
            const hotInstance = hotInstances[index];
            const newData = hotInstance ? hotInstance.getData() : sheet.data;
            newData.unshift(headersArray[index]);
            XLSX.utils.book_append_sheet(newWorkbook, XLSX.utils.aoa_to_sheet(newData), sheet.name);
        });
        const blob = new Blob([XLSX.write(newWorkbook, { bookType: 'xlsx', type: 'array' })], { type: "application/octet-stream" });
        const form = new FormData();
        form.append('file', blob);
        form.append('file_id', saveBtn.dataset.fileId);
        fetch(saveBtn.dataset.uploadUrl, { method: 'POST', body: form }).then(() => location.reload());
    });

    $(document).ready(function () {
        $('#searchDate').persianDatepicker({ format: 'YYYY/MM/DD', initialValue: false });
    });

    document.getElementById('selectAll').addEventListener('change', function () {
        document.querySelectorAll('.fileCheckbox').forEach(checkbox => checkbox.checked = this.checked);
    });

    window.performSearch = function () {
        const searchName = document.getElementById('searchName').value.toLowerCase();
        const searchUser = document.getElementById('searchUser').value;
        const searchDate = document.getElementById('searchDate').value;
        document.querySelectorAll('#fileTableBody tr').forEach(row => {
            const rowData = row.getAttribute('data-search').toLowerCase();
            row.style.display = (rowData.includes(searchUser) && rowData.includes(searchName) && rowData.includes(searchDate)) ? '' : 'none';
        });
    };

    window.bulkDelete = function () {
        const fileIds = Array.from(document.querySelectorAll('.fileCheckbox:checked')).map(checkbox => checkbox.value);
        if (!fileIds.length || !confirm('آیا مطمئن هستید که می‌خواهید فایل‌های انتخاب شده را حذف کنید؟')) return;
        const form = new FormData();
        form.append('action', 'bulk_delete_files');
        form.append('file_ids', JSON.stringify(fileIds));
        fetch(saveBtn.dataset.bulkDeleteUrl, { method: 'POST', body: form }).then(() => location.reload());
    };

    // نمایش و ویرایش لاگ ورود و خروج در قالب اکسل
    if (typeof entryExitData !== 'undefined') {
        const hot = new Handsontable(document.getElementById('excelTable'), {
            data: entryExitData.data.map(entry => [entry.employee, entry.type, entry.time, entry.date]),
            rowHeaders: true,
            colHeaders: ['نام کارمند', 'نوع', 'زمان', 'تاریخ'],
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

        document.getElementById('download-excel').addEventListener('click', function () {
            const hotData = hot.getData();
            hotData.unshift(['نام کارمند', 'نوع', 'زمان', 'تاریخ']);
            const blob = new Blob([XLSX.write(XLSX.utils.book_new(), { bookType: 'xlsx', type: 'array' })], { type: "application/octet-stream" });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = 'entry_exit_log.xlsx';
            link.click();
        });

        document.getElementById('entryExitSearch').addEventListener('keyup', function () {
            const filter = this.value.toLowerCase();
            document.querySelectorAll('#entryExitTableBody tr').forEach(row => {
                row.style.display = row.getAttribute('data-search').toLowerCase().includes(filter) ? '' : 'none';
            });
        });
    }

    // ورود و خروج کاربران
    document.querySelectorAll('.duration').forEach(function (timer) {
        let duration = parseInt(timer.getAttribute('data-duration'), 10);
        setInterval(() => {
            const hours = Math.floor(duration / 3600);
            const minutes = Math.floor((duration % 3600) / 60);
            const seconds = duration % 60;
            timer.textContent = `${hours} ساعت ${minutes} دقیقه ${seconds} ثانیه`;
            if (timer.getAttribute('data-status') === 'inside') duration++;
        }, 1000);
    });

    document.querySelectorAll('.show-more, .show-less').forEach(button => {
        button.addEventListener('click', function () {
            const rows = this.parentElement.querySelectorAll('.hidden-row');
            rows.forEach(row => row.style.display = this.classList.contains('show-more') ? 'table-row' : 'none');
            this.style.display = 'none';
            this.nextElementSibling.style.display = 'block';
        });
    });

    document.getElementById('search-input').addEventListener('keyup', function () {
        const filter = this.value.toLowerCase();
        document.querySelectorAll('.employee-card').forEach(card => {
            card.style.display = card.getAttribute('data-name').toLowerCase().includes(filter) ? '' : 'none';
        });
    });

    document.getElementById('log-search-input').addEventListener('keyup', function () {
        const filter = this.value.toLowerCase();
        document.querySelectorAll('.table-report tbody tr').forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(filter) ? '' : 'none';
        });
    });

    const loginForm = document.getElementById('login-form');
    if (loginForm) {
        loginForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const form = new FormData();
            form.append('action', 'custom_login');
            form.append('username', document.getElementById('username').value);
            form.append('password', document.getElementById('password').value);
            form.append('security', customLogin.nonce);
            fetch(customLogin.ajax_url, { method: 'POST', body: form }).then(() => location.reload());
        });
    }

    const logoutButton = document.getElementById('logout-button');
    if (logoutButton) {
        logoutButton.addEventListener('click', function (e) {
            e.preventDefault();
            const form = new FormData();
            form.append('action', 'custom_logout');
            form.append('security', customLogin.nonce);
            fetch(customLogin.ajax_url, { method: 'POST', body: form }).then(() => location.reload());
        });
    }
});