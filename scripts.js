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
        }
    });

    const fetchExcelFile = (url) => {
        fetch(url)
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
            .catch(error => {
                console.error('Error fetching the Excel file:', error);
            });
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



    if (fileUrl) fetchExcelFile(fileUrl);


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

    window.bulkDelete = () => {
        const fileIds = Array.from(document.querySelectorAll('.fileCheckbox:checked')).map(checkbox => checkbox.value);
        if (!fileIds.length || !confirm('آیا مطمئن هستید که می‌خواهید فایل‌های انتخاب شده را حذف کنید؟')) return;
        const form = new FormData();
        form.append('action', 'bulk_delete_files');
        form.append('file_ids', JSON.stringify(fileIds));

        fetch(saveBtn.dataset.bulkDeleteUrl, {
            method: 'POST',
            body: form
        })
            .then(response => {
                if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
                location.reload();
            })
            .catch(error => {
                console.error('Error deleting files:', error);
            });
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
        loginForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const form = new FormData(loginForm);
            form.append('action', 'custom_login');
            form.append('security', customLogin.nonce);
            fetch(customLogin.ajax_url, {
                method: 'POST',
                body: form
            })
                .then(() => {
                    location.reload();
                })
                .catch(error => {
                    console.error('Error during login:', error);
                });
        });
    }

    const logoutButton = document.getElementById('logout-button');
    if (logoutButton) {
        logoutButton.addEventListener('click', function (e) {
            e.preventDefault();
            const form = new FormData();
            form.append('action', 'custom_logout');
            form.append('security', customLogin.nonce);
            fetch(customLogin.ajax_url, {
                method: 'POST',
                body: form
            })
                .then(() => {
                    location.reload();
                })
                .catch(error => {
                    console.error('Error during logout:', error);
                });
        });
    }
});