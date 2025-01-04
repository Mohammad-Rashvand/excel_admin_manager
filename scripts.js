document.addEventListener('DOMContentLoaded', () => {
    const saveBtn = document.getElementById('saveBtn');
    const fileUrl = saveBtn ? saveBtn.dataset.fileUrl : null;
    let workbooks = [], hotInstances = [], headersArray = [];

    // ثبت زبان فارسی در Handsontable
    Handsontable.languages.registerLanguageDictionary({
        languageCode: 'fa-IR',
        languageDirection: 'rtl',
        translations: {
            'Cancel': 'لغو',
            'Remove row': 'حذف ردیف',
            'Insert row above': 'درج ردیف بالا',
            'Insert row below': 'درج ردیف پایین',
            'Undo': 'واگرد',
            'Redo': 'بازگرد',
            'Read-only': 'فقط خواندنی',
            'Alignment': 'تراز',
            'Insert column left': 'درج ستون چپ',
            'Insert column right': 'درج ستون راست',
            'Clear column': 'پاک کردن ستون',
            'Sort ascending': 'مرتب‌سازی صعودی',
            'Sort descending': 'مرتب‌سازی نزولی'
            // سایر ترجمه‌ها
        }
    });

    const fetchExcelFile = async (url) => {
        try {
            const response = await fetch(url);
            const data = await response.arrayBuffer();
            const workbook = XLSX.read(new Uint8Array(data), { type: 'array' });
            workbooks = workbook.SheetNames.map(sheetName => {
                const sheet = workbook.Sheets[sheetName];
                const jsonData = XLSX.utils.sheet_to_json(sheet, { header: 1 });
                headersArray.push(jsonData.shift());
                return { name: sheetName, data: jsonData };
            });
            if (workbooks.length > 1) createSheetTabs();
            loadSheet(0);
        } catch (error) {
            console.error('Error fetching the Excel file:', error);
        }
    };

    const createSheetTabs = () => {
        const sheetTabs = document.getElementById('sheetTabs');
        if (!sheetTabs) return;
        sheetTabs.innerHTML = ''; // Clear any existing tabs
        workbooks.forEach((sheet, index) => {
            const tab = document.createElement('button');
            tab.className = 'btn btn-primary mx-1';
            tab.innerText = sheet.name;
            tab.addEventListener('click', () => loadSheet(index));
            sheetTabs.appendChild(tab);
        });
    };

    const loadSheet = (index) => {
        const container = document.getElementById('excelTable');
        if (!container) return;
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
    };

    const saveEditedFile = async () => {
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
        try {
            const response = await fetch(saveBtn.dataset.uploadUrl, { method: 'POST', body: form });
            if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
            location.reload();
        } catch (error) {
            console.error('Error saving the edited file:', error);
            document.getElementById('loading').style.display = 'none';
        }
    };

    if (fileUrl) fetchExcelFile(fileUrl);
    if (saveBtn) saveBtn.addEventListener('click', saveEditedFile);

    // اطمینان از اینکه پلاگین persianDatepicker بارگذاری شده است
    if (typeof jQuery.fn.persianDatepicker !== 'undefined') {
        jQuery('#searchDate').persianDatepicker({
            format: 'YYYY/MM/DD',
            initialValue: false
        });
    } else {
        console.error('persianDatepicker is not loaded');
    }

    const selectAllCheckbox = document.getElementById('selectAll');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function () {
            document.querySelectorAll('.fileCheckbox').forEach(checkbox => checkbox.checked = this.checked);
        });
    }

    window.performSearch = () => {
        const searchName = document.getElementById('searchName').value.toLowerCase();
        const searchUser = document.getElementById('searchUser').value;
        const searchDate = document.getElementById('searchDate').value;
        document.querySelectorAll('#fileTableBody tr').forEach(row => {
            const rowData = row.getAttribute('data-search').toLowerCase();
            row.style.display = (rowData.includes(searchUser) && rowData.includes(searchName) && rowData.includes(searchDate)) ? '' : 'none';
        });
    };

    window.bulkDelete = async () => {
        const fileIds = Array.from(document.querySelectorAll('.fileCheckbox:checked')).map(checkbox => checkbox.value);
        if (!fileIds.length || !confirm('آیا مطمئن هستید که می‌خواهید فایل‌های انتخاب شده را حذف کنید؟')) return;
        const form = new FormData();
        form.append('action', 'bulk_delete_files');
        form.append('file_ids', JSON.stringify(fileIds));
        try {
            const response = await fetch(saveBtn.dataset.bulkDeleteUrl, { method: 'POST', body: form });
            if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
            location.reload();
        } catch (error) {
            console.error('Error deleting files:', error);
        }
    };

    document.querySelectorAll('.duration').forEach(timer => {
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

    const searchInput = document.getElementById('search-input');
    if (searchInput) {
        searchInput.addEventListener('keyup', function () {
            const filter = this.value.toLowerCase();
            document.querySelectorAll('.employee-card').forEach(card => {
                card.style.display = card.getAttribute('data-name').toLowerCase().includes(filter) ? '' : 'none';
            });
        });
    }

    const logSearchInput = document.getElementById('log-search-input');
    if (logSearchInput) {
        logSearchInput.addEventListener('keyup', function () {
            const filter = this.value.toLowerCase();
            document.querySelectorAll('.table-report tbody tr').forEach(row => {
                row.style.display = row.textContent.toLowerCase().includes(filter) ? '' : 'none';
            });
        });
    }

    const loginForm = document.getElementById('login-form');
    if (loginForm) {
        loginForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            const form = new FormData();
            form.append('action', 'custom_login');
            form.append('username', document.getElementById('username').value);
            form.append('password', document.getElementById('password').value);
            form.append('security', customLogin.nonce);
            await fetch(customLogin.ajax_url, { method: 'POST', body: form });
            location.reload();
        });
    }

    const logoutButton = document.getElementById('logout-button');
    if (logoutButton) {
        logoutButton.addEventListener('click', async function (e) {
            e.preventDefault();
            const form = new FormData();
            form.append('action', 'custom_logout');
            form.append('security', customLogin.nonce);
            await fetch(customLogin.ajax_url, { method: 'POST', body: form });
            location.reload();
        });
    }
});