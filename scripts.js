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