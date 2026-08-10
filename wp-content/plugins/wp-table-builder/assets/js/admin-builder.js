(function () {
    'use strict';

    var config   = window.WTB_Admin_Config || {};
    var REST_URL = config.restUrl  || '';
    var NONCE    = config.nonce    || '';
    var TABLE_ID = config.tableId  || 0;
    var STRINGS  = config.strings  || {};

    var state = {
        columns:          [],
        rows:             [],
        settings:         {},
        nextTempKeyIndex: 0,
    };

    function generateTempKey() {
        return 'tempkey_' + (state.nextTempKeyIndex++) + '_' + Date.now();
    }

    function getCellKey(column) {
        return column.id > 0 ? String(column.id) : column.temp_key;
    }

    function getRowKey(row) {
        return row.id > 0 ? String(row.id) : (row.temp_key || '');
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (!TABLE_ID) return;
        loadTableData();
        attachGlobalEventListeners();
        setupColorInputSyncing();
    });

    function loadTableData() {
        showLoader();

        fetch(REST_URL + '/tables/' + TABLE_ID, {
            headers: { 'X-WP-Nonce': NONCE },
        })
        .then(function (response) {
            if (!response.ok) throw new Error('HTTP ' + response.status);
            return response.json();
        })
        .then(function (data) {
            state.columns  = data.columns  || [];
            state.rows     = data.rows     || [];
            state.settings = data.settings || {};

            state.columns.forEach(function (col) {
                col.id = parseInt(col.id, 10);
                if (!col.temp_key) col.temp_key = '';
            });

            state.rows.forEach(function (row) {
                row.id = parseInt(row.id, 10);
                if (!row.temp_key) row.temp_key = '';
            });

            renderEditorGrid();
            populateSettingsPanel();
            hideLoader();
            showEditorMain();
        })
        .catch(function (err) {
            hideLoader();
            showNotice('error', 'Gagal memuat data tabel: ' + err.message);
        });
    }

    function renderEditorGrid() {
        renderColumnHeaders();
        renderRows();
    }

    function renderColumnHeaders() {
        var headerRow = document.getElementById('wtb-column-headers');
        if (!headerRow) return;

        headerRow.innerHTML = '';

        var actionTh = document.createElement('th');
        actionTh.className = 'wtb-action-col';
        headerRow.appendChild(actionTh);

        state.columns.forEach(function (col) {
            var th = document.createElement('th');
            th.innerHTML = buildColumnHeaderHtml(col);
            headerRow.appendChild(th);
        });

        var addTh = document.createElement('th');
        addTh.className = 'wtb-th-add-col';
        addTh.innerHTML = '<button type="button" class="wtb-btn-add-col">+ Kolom</button>';
        headerRow.appendChild(addTh);
    }

    function buildColumnHeaderHtml(col) {
        var col_key = escapeHtml(getCellKey(col));
        var label   = escapeHtml(col.label || '');

        var html = '<div class="wtb-col-header">' +
               '  <div class="wtb-col-header__top">' +
               '    <input type="text" class="wtb-col-label" ' +
               '           data-col-id="' + col_key + '" ' +
               '           value="' + label + '" ' +
               '           placeholder="Nama Kolom">' +
               '    <button type="button" class="wtb-btn-delete-col" ' +
               '            data-col-id="' + col_key + '" ' +
               '            title="Hapus kolom ini">\u00d7</button>' +
               '  </div>' +
               '  <select class="wtb-col-type" data-col-id="' + col_key + '" title="Tipe Data">' +
               buildDataTypeOptions(col.data_type) +
               '  </select>';

        if (state.settings.data_source === 'wp_posts') {
            html += '<div style="margin-top:8px; border-top:1px solid #e2e8f0; padding-top:8px;">' +
                    '<label style="display:block; font-size:0.8em; margin-bottom:4px; color:#475569;">Map to Post Field:</label>' +
                    '<select class="wtb-col-post-field" data-col-id="' + col_key + '" style="width:100%; font-size:0.9em; padding:2px;">' +
                    buildPostFieldOptions(col.post_field) +
                    '</select></div>';
            
            if (col.post_field === 'thumbnail') {
                html += '<div style="margin-top:4px;">' +
                        '<label style="display:block; font-size:0.8em; margin-bottom:4px; color:#475569;">Image Size:</label>' +
                        '<select class="wtb-col-image-size" data-col-id="' + col_key + '" style="width:100%; font-size:0.9em; padding:2px;">' +
                        buildImageSizeOptions(col.image_size) +
                        '</select></div>';
                        
                if (col.image_size === 'custom') {
                    var w = col.image_custom_w || 100;
                    var h = col.image_custom_h || 100;
                    html += '<div style="margin-top:4px; display:flex; gap:4px;">' +
                            '<input type="number" class="wtb-col-img-w" data-col-id="' + col_key + '" value="' + w + '" placeholder="W" style="width:50%; font-size:0.9em; padding:2px;">' +
                            '<input type="number" class="wtb-col-img-h" data-col-id="' + col_key + '" value="' + h + '" placeholder="H" style="width:50%; font-size:0.9em; padding:2px;">' +
                            '</div>';
                }
            }
        }

        html += '</div>';
        return html;
    }

    function buildPostFieldOptions(selectedField) {
        var fields = [
            { value: '',         label: '-- Pilih --' },
            { value: 'title',    label: 'Post Title' },
            { value: 'content',  label: 'Post Content' },
            { value: 'excerpt',  label: 'Post Excerpt' },
            { value: 'date',     label: 'Post Date' },
            { value: 'author',   label: 'Author Name' },
            { value: 'category', label: 'Categories' },
            { value: 'tag',      label: 'Tags' },
            { value: 'thumbnail',label: 'Featured Image' }
        ];
        return fields.map(function(f) {
            var selected = f.value === (selectedField || '') ? ' selected' : '';
            return '<option value="' + f.value + '"' + selected + '>' + f.label + '</option>';
        }).join('');
    }

    function buildImageSizeOptions(selectedSize) {
        var sizes = [
            { value: 'thumbnail', label: 'Thumbnail' },
            { value: 'medium',    label: 'Medium' },
            { value: 'large',     label: 'Large' },
            { value: 'full',      label: 'Full Size' },
            { value: 'custom',    label: 'Custom Size' }
        ];
        return sizes.map(function(s) {
            var selected = s.value === (selectedSize || 'thumbnail') ? ' selected' : '';
            return '<option value="' + s.value + '"' + selected + '>' + s.label + '</option>';
        }).join('');
    }

    function buildDataTypeOptions(selectedType) {
        var types = [
            { value: 'text',     label: 'Teks Biasa'       },
            { value: 'number',   label: 'Angka'            },
            { value: 'date',     label: 'Tanggal'          },
            { value: 'richtext', label: 'Rich Text / HTML' },
            { value: 'link',     label: 'Link / URL'       },
            { value: 'button',   label: 'Tombol'           },
            { value: 'image',    label: 'Gambar'           },
            { value: 'file',     label: 'File Upload / Lampiran' },
            { value: 'badge',    label: 'Badge / Label'    },
            { value: 'rating',   label: 'Rating Bintang'   },
        ];

        return types.map(function (type) {
            var selected = type.value === selectedType ? ' selected' : '';
            return '<option value="' + type.value + '"' + selected + '>' + type.label + '</option>';
        }).join('');
    }

    function renderRows() {
        var tbody = document.getElementById('wtb-rows-body');
        if (!tbody) return;

        tbody.innerHTML = '';

        if (state.rows.length === 0) {
            var emptyRow = document.createElement('tr');
            var colSpan  = state.columns.length + 2;
            emptyRow.innerHTML = '<td colspan="' + colSpan + '" class="wtb-empty-rows-notice">' +
                                 'Belum ada baris data. Klik "\u002b Tambah Baris Baru" untuk mulai mengisikan data.' +
                                 '</td>';
            tbody.appendChild(emptyRow);
            return;
        }

        state.rows.forEach(function (row) {
            tbody.appendChild(buildRowElement(row));
        });
    }

    function buildRowElement(row) {
        var tr     = document.createElement('tr');
        var rowKey = getRowKey(row);
        tr.dataset.rowId = rowKey;

        var actionTd = document.createElement('td');
        actionTd.className = 'wtb-action-col';
        actionTd.innerHTML = '<button type="button" class="wtb-btn-delete-row" ' +
                             '        data-row-id="' + escapeHtml(rowKey) + '" ' +
                             '        title="Hapus baris ini">\u2715</button>';
        tr.appendChild(actionTd);

        state.columns.forEach(function (col) {
            var td       = document.createElement('td');
            var cell_key = getCellKey(col);
            var value    = (row.cells_data && row.cells_data[cell_key]) ? row.cells_data[cell_key] : '';

            td.innerHTML = buildCellInput(col, rowKey, cell_key, value);
            tr.appendChild(td);
        });

        tr.appendChild(document.createElement('td'));

        return tr;
    }

    function buildCellInput(col, rowKey, cell_key, value) {
        var rowIdEsc = escapeHtml(String(rowKey));
        var keyEsc   = escapeHtml(cell_key);
        var valueEsc = escapeHtml(value);
        var type     = col.data_type || 'text';
        var typeEsc  = escapeHtml(type);

        var base = 'class="wtb-cell-input" ' +
                   'data-row-id="' + rowIdEsc + '" ' +
                   'data-col-key="' + keyEsc + '" ' +
                   'data-col-type="' + typeEsc + '"';

        switch (type) {
            case 'number':
                return '<input type="number" ' + base +
                       ' value="' + valueEsc + '" placeholder="0" step="any">';

            case 'date':
                return '<input type="date" ' + base +
                       ' value="' + valueEsc + '">';

            case 'link':
                return '<input type="url" ' + base +
                       ' value="' + valueEsc + '" placeholder="https://">';

            case 'image':
                return '<div class="wtb-file-cell-wrap">' +
                       '  <input type="url" ' + base +
                       '         value="' + valueEsc + '" placeholder="https:// (URL gambar)">' +
                       '  <button type="button" class="wtb-btn-upload-file" data-media-type="image" title="Upload / Pilih Gambar">' +
                       '    🖼️ Upload' +
                       '  </button>' +
                       '</div>';

            case 'file':
                return '<div class="wtb-file-cell-wrap">' +
                       '  <input type="url" ' + base +
                       '         value="' + valueEsc + '" placeholder="URL File / Lampiran...">' +
                       '  <button type="button" class="wtb-btn-upload-file" title="Upload / Pilih File">' +
                       '    📁 Upload' +
                       '  </button>' +
                       '</div>';

            case 'rating':
                return '<input type="number" ' + base +
                       ' value="' + valueEsc + '" min="1" max="5" placeholder="1\u20135" step="1">';

            case 'badge':
                return '<input type="text" ' + base +
                       ' value="' + valueEsc + '" placeholder="Label" maxlength="60">';

            case 'button':
                return '<input type="text" ' + base +
                       ' value="' + valueEsc + '" placeholder="Teks tombol" maxlength="120">';

            case 'richtext':
                return '<textarea ' + base + ' rows="2" ' +
                       'placeholder="HTML atau teks kaya...">' + valueEsc + '</textarea>';

            case 'text':
            default:
                return '<textarea ' + base + ' rows="1" ' +
                       'placeholder="\u2014">' + valueEsc + '</textarea>';
        }
    }

    function populateSettingsPanel() {
        var settings = state.settings;

        var fieldMap = [
            { settingKey: 'header_bg',             elementId: 'wtb_header_bg' },
            { settingKey: 'header_text',            elementId: 'wtb_header_text' },
            { settingKey: 'row_stripe_color',       elementId: 'wtb_row_stripe_color' },
            { settingKey: 'border_color',           elementId: 'wtb_border_color' },
            { settingKey: 'border_width',           elementId: 'wtb_border_width' },
            { settingKey: 'border_radius',          elementId: 'wtb_border_radius' },
            { settingKey: 'box_shadow',             elementId: 'wtb_box_shadow' },
            { settingKey: 'width',                  elementId: 'wtb_width' },
            { settingKey: 'max_width',              elementId: 'wtb_max_width' },
            { settingKey: 'height',                 elementId: 'wtb_height' },
            { settingKey: 'max_height',             elementId: 'wtb_max_height' },
            { settingKey: 'alignment',              elementId: 'wtb_alignment' },
            { settingKey: 'cell_padding',           elementId: 'wtb_cell_padding' },
            { settingKey: 'enable_search',          elementId: 'wtb_enable_search' },
            { settingKey: 'enable_sort',            elementId: 'wtb_enable_sort' },
            { settingKey: 'row_stripe',             elementId: 'wtb_row_stripe' },
            { settingKey: 'responsive_mode',        elementId: 'wtb_responsive_mode' },
            { settingKey: 'server_side_threshold',  elementId: 'wtb_server_side_threshold' },
            { settingKey: 'show_file_preview',      elementId: 'wtb_show_file_preview' },
            { settingKey: 'data_source',            elementId: 'wtb_data_source' },
            { settingKey: 'post_type',              elementId: 'wtb_post_type' },
            { settingKey: 'posts_limit',            elementId: 'wtb_posts_limit' },
        ];

        fieldMap.forEach(function (field) {
            var el = document.getElementById(field.elementId);
            if (!el) return;

            var value = settings[field.settingKey];
            if (value === undefined || value === null) return;

            if (el.type === 'checkbox') {
                el.checked = Boolean(value);
            } else {
                el.value = value;
            }
        });

        document.querySelectorAll('.wtb-color-text').forEach(function (textInput) {
            var linkedId = textInput.dataset.linkedColor;
            var colorEl  = document.getElementById(linkedId);
            if (colorEl) textInput.value = colorEl.value.toUpperCase();
        });

        toggleDataSourceUI();
    }

    function toggleDataSourceUI() {
        var ds = state.settings.data_source || 'manual';
        var wpSettings = document.querySelector('.wtb-wp-posts-setting');
        var editorMain = document.getElementById('wtb-editor-table-wrapper');
        var addRowBtn  = document.querySelector('.wtb-add-row-area');
        
        if (wpSettings) {
            wpSettings.style.display = (ds === 'wp_posts') ? 'block' : 'none';
        }
        
        if (editorMain && addRowBtn) {
            if (ds === 'wp_posts') {
                document.getElementById('wtb-rows-body').style.display = 'none';
                addRowBtn.style.display = 'none';
                var msg = document.getElementById('wtb-dynamic-mode-msg');
                if (!msg) {
                    msg = document.createElement('div');
                    msg.id = 'wtb-dynamic-mode-msg';
                    msg.innerHTML = '<div class="notice notice-info inline"><p><strong>Mode WordPress Posts Aktif:</strong> Baris data akan diisi secara otomatis dari postingan. Atur pemetaan (mapping) kolom di pengaturan setiap kolom header di atas.</p></div>';
                    editorMain.appendChild(msg);
                }
                msg.style.display = 'block';
            } else {
                document.getElementById('wtb-rows-body').style.display = '';
                addRowBtn.style.display = 'block';
                var msg = document.getElementById('wtb-dynamic-mode-msg');
                if (msg) msg.style.display = 'none';
            }
        }
        
        // Re-render headers to show/hide dynamic field mappings
        renderColumnHeaders();
    }

    function readSettingsFromForm() {
        document.querySelectorAll('#wtb-settings-form [data-setting-key]').forEach(function (el) {
            var key = el.dataset.settingKey;
            if (!key) return;

            if (el.type === 'checkbox') {
                state.settings[key] = el.checked;
            } else if (el.type === 'number') {
                state.settings[key] = parseInt(el.value, 10) || 0;
            } else {
                state.settings[key] = el.value;
            }
        });
    }

    function attachGlobalEventListeners() {
        var editorTable = document.getElementById('wtb-editor-table');
        if (editorTable) {
            editorTable.addEventListener('click', handleEditorClick);

            editorTable.addEventListener('change', function (e) {
                if (e.target.classList.contains('wtb-col-type') || 
                    e.target.classList.contains('wtb-col-post-field') || 
                    e.target.classList.contains('wtb-col-image-size') || 
                    e.target.classList.contains('wtb-col-img-w') || 
                    e.target.classList.contains('wtb-col-img-h')) {
                    
                    syncAllEditsFromDom();

                    var colKey = e.target.dataset.colId;
                    var col = state.columns.find(function (c) {
                        return getCellKey(c) === colKey;
                    });
                    
                    if (col) {
                        if (e.target.classList.contains('wtb-col-type')) col.data_type = e.target.value;
                        if (e.target.classList.contains('wtb-col-post-field')) col.post_field = e.target.value;
                        if (e.target.classList.contains('wtb-col-image-size')) col.image_size = e.target.value;
                        if (e.target.classList.contains('wtb-col-img-w')) col.image_custom_w = parseInt(e.target.value, 10) || 100;
                        if (e.target.classList.contains('wtb-col-img-h')) col.image_custom_h = parseInt(e.target.value, 10) || 100;
                    }

                    renderColumnHeaders();
                    renderRows();
                }
            });
        }

        var addRowBtn = document.getElementById('wtb-btn-add-row');
        if (addRowBtn) {
            addRowBtn.addEventListener('click', addNewRow);
        }

        var saveBtn = document.getElementById('wtb-btn-save');
        if (saveBtn) {
            saveBtn.addEventListener('click', saveTable);
        }

        document.addEventListener('click', function (e) {
            var targetBtn = e.target.closest('.wtb-btn-copy-shortcode');
            if (targetBtn) {
                copyToClipboard(targetBtn.dataset.shortcode || '', targetBtn);
            }
        });

        var dataSourceSelect = document.getElementById('wtb_data_source');
        if (dataSourceSelect) {
            dataSourceSelect.addEventListener('change', function(e) {
                state.settings.data_source = e.target.value;
                toggleDataSourceUI();
            });
        }
    }

    function handleEditorClick(event) {
        var target = event.target;

        var uploadBtn = target.closest('.wtb-btn-upload-file');
        if (uploadBtn) {
            event.preventDefault();
            openMediaUploaderForFileCell(uploadBtn);
            return;
        }

        if (target.classList.contains('wtb-btn-delete-col')) {
            deleteColumn(target.dataset.colId);
        }

        if (target.classList.contains('wtb-btn-delete-row')) {
            if (window.confirm(STRINGS.confirm_delete || 'Hapus baris ini?')) {
                deleteRow(target.dataset.rowId);
            }
        }

        if (target.classList.contains('wtb-btn-add-col')) {
            addNewColumn();
        }
    }

    function openMediaUploaderForFileCell(buttonEl) {
        if (typeof wp === 'undefined' || !wp.media) {
            alert('WordPress Media Library tidak tersedia.');
            return;
        }

        var wrap = buttonEl.closest('.wtb-file-cell-wrap');
        if (!wrap) return;
        var inputEl = wrap.querySelector('.wtb-cell-input');

        var isImageOnly = buttonEl.dataset.mediaType === 'image';

        var frame = wp.media({
            title: isImageOnly ? 'Pilih / Upload Gambar' : 'Pilih / Upload File',
            button: { text: 'Gunakan File Ini' },
            library: isImageOnly ? { type: 'image' } : {},
            multiple: false
        });

        frame.on('select', function () {
            var attachment = frame.state().get('selection').first().toJSON();
            if (attachment && attachment.url && inputEl) {
                inputEl.value = attachment.url;
                inputEl.dispatchEvent(new Event('input', { bubbles: true }));
                inputEl.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });

        frame.open();
    }

    function setupColorInputSyncing() {
        document.querySelectorAll('input[type="color"]').forEach(function (colorInput) {
            colorInput.addEventListener('input', function () {
                var linkedTextId = colorInput.id + '_text';
                var textInput = document.getElementById(linkedTextId);
                if (textInput) textInput.value = colorInput.value.toUpperCase();
            });
        });

        document.querySelectorAll('.wtb-color-text').forEach(function (textInput) {
            textInput.addEventListener('input', function () {
                var linkedId = textInput.dataset.linkedColor;
                var colorInput = document.getElementById(linkedId);
                if (colorInput && /^#[0-9A-Fa-f]{6}$/.test(textInput.value)) {
                    colorInput.value = textInput.value;
                }
            });
        });
    }

    function addNewColumn() {
        syncAllEditsFromDom();

        var newCol = {
            id:         0,
            temp_key:   generateTempKey(),
            label:      STRINGS.new_col_label || 'Kolom Baru',
            data_type:  'text',
            sort_order: state.columns.length,
        };

        state.columns.push(newCol);
        renderEditorGrid();

        var headers = document.querySelectorAll('#wtb-column-headers th .wtb-col-label');
        var lastColLabel = headers[headers.length - 1] || null;
        if (lastColLabel) {
            lastColLabel.focus();
            lastColLabel.select();
        }
    }

    function deleteColumn(colId) {
        colId = String(colId);
        syncAllEditsFromDom();

        state.columns = state.columns.filter(function (col) {
            return getCellKey(col) !== colId;
        });

        state.rows.forEach(function (row) {
            if (row.cells_data) {
                delete row.cells_data[colId];
            }
        });

        renderEditorGrid();
    }

    function addNewRow() {
        syncAllEditsFromDom();

        var newRow = {
            id:         0,
            temp_key:   generateTempKey(),
            sort_order: state.rows.length,
            cells_data: {},
        };

        state.rows.push(newRow);
        renderRows();

        var lastRow = document.querySelector('#wtb-rows-body tr:last-child .wtb-cell-input');
        if (lastRow) {
            lastRow.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            lastRow.focus();
        }
    }

    function deleteRow(rowKey) {
        rowKey = String(rowKey);
        syncAllEditsFromDom();

        state.rows = state.rows.filter(function (row) {
            return getRowKey(row) !== rowKey;
        });
        renderRows();
    }

    function syncAllEditsFromDom() {
        var headerInputs = document.querySelectorAll('.wtb-col-label');
        headerInputs.forEach(function (input) {
            var colKey = input.dataset.colId;
            var col = state.columns.find(function (c) {
                return getCellKey(c) === colKey;
            });
            if (col) {
                col.label = input.value;
                var wrap = input.closest('.wtb-col-header');
                if (wrap) {
                    var typeEl = wrap.querySelector('.wtb-col-type');
                    if (typeEl) col.data_type = typeEl.value;

                    var pf = wrap.querySelector('.wtb-col-post-field');
                    if (pf) col.post_field = pf.value;
                    
                    var isz = wrap.querySelector('.wtb-col-image-size');
                    if (isz) col.image_size = isz.value;
                    
                    var cw = wrap.querySelector('.wtb-col-img-w');
                    if (cw) col.image_custom_w = parseInt(cw.value, 10) || 100;
                    
                    var ch = wrap.querySelector('.wtb-col-img-h');
                    if (ch) col.image_custom_h = parseInt(ch.value, 10) || 100;
                }
            }
        });

        document.querySelectorAll('.wtb-cell-input').forEach(function (el) {
            var rowKey = el.dataset.rowId;
            var colKey = el.dataset.colKey;
            var value  = el.value;

            var row = state.rows.find(function (r) { return getRowKey(r) === String(rowKey); });
            if (row) {
                if (!row.cells_data) row.cells_data = {};
                row.cells_data[colKey] = value;
            }
        });

        readSettingsFromForm();
    }

    function saveTable() {
        syncAllEditsFromDom();

        var titleInput = document.getElementById('wtb-input-title');
        var title      = titleInput ? titleInput.value.trim() : '';

        if (!title) {
            showNotice('error', 'Nama tabel tidak boleh kosong.');
            titleInput && titleInput.focus();
            return;
        }

        setSavingState(true);

        var payload = {
            title:    title,
            settings: state.settings,
            columns:  state.columns.map(function (col, index) {
                return {
                    id:         col.id > 0 ? col.id : 0,
                    temp_key:   col.temp_key || '',
                    label:      col.label    || '',
                    data_type:  col.data_type || 'text',
                    post_field: col.post_field || '',
                    image_size: col.image_size || 'thumbnail',
                    image_custom_w: col.image_custom_w || 100,
                    image_custom_h: col.image_custom_h || 100,
                    sort_order: index,
                };
            }),
            rows: state.rows.map(function (row, index) {
                return {
                    id:         row.id > 0 ? row.id : 0,
                    temp_key:   row.temp_key || '',
                    sort_order: index,
                    cells_data: row.cells_data || {},
                };
            }),
        };

        fetch(REST_URL + '/tables/' + TABLE_ID + '/save', {
            method:  'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce':   NONCE,
            },
            body: JSON.stringify(payload),
        })
        .then(function (response) {
            if (!response.ok) {
                return response.json().then(function (err) {
                    throw new Error(err.message || 'HTTP ' + response.status);
                });
            }
            return response.json();
        })
        .then(function (data) {
            if (data.success) {
                showNotice('success', STRINGS.save_success || 'Tabel berhasil disimpan!');
                loadTableData();
            } else {
                throw new Error(STRINGS.save_error || 'Gagal menyimpan tabel.');
            }
        })
        .catch(function (err) {
            showNotice('error', (STRINGS.save_error || 'Gagal menyimpan:') + ' ' + err.message);
        })
        .finally(function () {
            setSavingState(false);
        });
    }

    function copyToClipboard(text, buttonEl) {
        if (!navigator.clipboard) return;

        navigator.clipboard.writeText(text).then(function () {
            var original = buttonEl.innerHTML;
            buttonEl.innerHTML = '<span class="dashicons dashicons-yes" style="font-size:14px; width:14px; height:14px; vertical-align:middle;"></span> Disalin!';
            buttonEl.style.color = '#10b981';
            setTimeout(function () {
                buttonEl.innerHTML = original;
                buttonEl.style.color = '';
            }, 2000);
        });
    }

    function showLoader() {
        var loader = document.getElementById('wtb-editor-loader');
        if (loader) loader.style.display = 'flex';
    }

    function hideLoader() {
        var loader = document.getElementById('wtb-editor-loader');
        if (loader) loader.style.display = 'none';
    }

    function showEditorMain() {
        var main = document.getElementById('wtb-editor-main');
        if (main) main.style.display = 'grid';
    }

    function setSavingState(isSaving) {
        var btn = document.getElementById('wtb-btn-save');
        if (!btn) return;

        btn.disabled = isSaving;
        btn.innerHTML = isSaving
            ? '<span class="spinner is-active" style="float:none; margin:0 4px 0 0;"></span> ' + (STRINGS.saving || 'Menyimpan...')
            : '<span class="dashicons dashicons-saved" style="font-size:16px; width:16px; height:16px;" aria-hidden="true"></span> ' + (STRINGS.save_btn_label || 'Simpan Tabel');
    }

    function showNotice(type, message) {
        var noticeEl = document.getElementById('wtb-save-notice');
        if (!noticeEl) return;

        noticeEl.className = 'notice notice-' + type + ' wtb-save-notice is-dismissible';
        noticeEl.innerHTML = '<p>' + escapeHtml(message) + '</p>';
        noticeEl.style.display = 'block';

        if (type === 'success') {
            setTimeout(function () {
                noticeEl.style.display = 'none';
            }, 4000);
        }
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(String(text)));
        return div.innerHTML;
    }

})();
