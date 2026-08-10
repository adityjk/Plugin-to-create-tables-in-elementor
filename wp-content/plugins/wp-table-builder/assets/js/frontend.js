(function ($) {
    'use strict';

    /**
     * Build icon HTML from a data-* attribute value.
     * Supports font icon class strings ("fas fa-arrow-left")
     * and image/SVG URLs.
     */
    function buildIconHtml(iconVal) {
        if ( ! iconVal ) return '';
        if ( /\.(svg|png|jpg|jpeg|gif|webp)(\?.*)?$/i.test(iconVal) ) {
            return '<img src="' + iconVal + '" alt="" style="width:1em;height:1em;vertical-align:middle;" aria-hidden="true">';
        }
        return '<i class="' + iconVal + '" aria-hidden="true"></i>';
    }

    /**
     * Initialize (or re-initialize) DataTables on a single .wtb-table element.
     */
    function initTable($table) {
        if ( ! $table.length ) return;

        var tableId      = $table.data('table-id');
        var config       = window['WTB_Table_' + tableId] || {};
        var serverSide   = $table.data('server-side')   === 1 || $table.data('server-side')   === '1';
        var enableSort   = $table.data('enable-sort')   !== 0  && $table.data('enable-sort')   !== '0';
        var enableSearch = $table.data('enable-search') !== 0  && $table.data('enable-search') !== '0';

        var prevText = $table.attr('data-prev-text') !== undefined ? String($table.attr('data-prev-text')) : 'Sebelumnya';
        var nextText = $table.attr('data-next-text') !== undefined ? String($table.attr('data-next-text')) : 'Selanjutnya';

        var prevIconHtml = $table.attr('data-prev-icon-html') ? String($table.attr('data-prev-icon-html')) : buildIconHtml($table.data('prev-icon'));
        var nextIconHtml = $table.attr('data-next-icon-html') ? String($table.attr('data-next-icon-html')) : buildIconHtml($table.data('next-icon'));

        var prevLabel = prevIconHtml ? (prevText ? prevIconHtml + ' ' + prevText : prevIconHtml) : prevText;
        var nextLabel = nextIconHtml ? (nextText ? nextText + ' ' + nextIconHtml : nextIconHtml) : nextText;

        var pagType = $table.data('pagination-type') || 'numbers';
        var $wrap   = $table.closest('.wtb-table-wrap');

        $wrap.toggleClass('wtb-dots-mode', pagType === 'dots');

        // Destroy existing instance before re-init (needed in Elementor editor)
        if ( $.fn.DataTable && $.fn.DataTable.isDataTable($table) ) {
            $table.DataTable().destroy();
        }

        var dtOptions = {
            ordering:   enableSort,
            searching:  enableSearch,
            pagingType: 'simple_numbers',
            responsive: $table.data('responsive') === 'collapse',
            language: {
                search:     'Cari:',
                lengthMenu: 'Tampilkan _MENU_ baris',
                info:       'Menampilkan _START_\u2013_END_ dari _TOTAL_ data',
                paginate: {
                    first:    'Pertama',
                    last:     'Terakhir',
                    next:     nextLabel,
                    previous: prevLabel,
                },
                emptyTable:  'Tidak ada data.',
                zeroRecords: 'Data tidak ditemukan.',
            },
        };

        // In the Elementor editor: use pageLength=1 so pagination renders with
        // ACTIVE (not disabled) prev/next buttons — makes styling much easier.
        var isEditorMode = window.elementorFrontend &&
                           typeof window.elementorFrontend.isEditMode === 'function' &&
                           window.elementorFrontend.isEditMode();

        dtOptions.pageLength = isEditorMode ? 1 : 10;


        if ( serverSide && config.restUrl ) {
            dtOptions.processing = true;
            dtOptions.serverSide = true;
            dtOptions.ajax = {
                url:  config.restUrl + '/tables/' + tableId + '/data',
                type: 'GET',
                headers: { 'X-WP-Nonce': config.nonce || '' },
                data: function (d) {
                    var req = {
                        draw:   d.draw,
                        start:  d.start,
                        length: d.length,
                        search: d.search,
                    };
                    if (d.columns) {
                        req.columns = d.columns.map(function(col) {
                            return { search: col.search };
                        });
                    }
                    return req;
                },
            };
            if ( config.columns ) {
                dtOptions.columns = config.columns.map(function (col) {
                    return { data: String(col.id), defaultContent: '\u2014' };
                });
            }
        }

        var dt = $table.DataTable(dtOptions);

        // Bind Advanced Column Filters inside <th>
        $table.find('thead .wtb-header-filter').each(function () {
            var $input      = $(this);
            var $th         = $input.closest('th');
            var filterColId = String( $input.data('filter-col-id') || $th.data('col-id') || '' );
            var colIndex    = $th.index();

            if ( filterColId && config.columns ) {
                for ( var i = 0; i < config.columns.length; i++ ) {
                    if ( String(config.columns[i].id) === filterColId ) {
                        colIndex = i;
                        break;
                    }
                }
            }

            $input.off('click mousedown').on('click mousedown', function (e) {
                e.stopPropagation();
            });

            if ( $input.is('select') ) {
                $input.off('change').on('change', function (e) {
                    e.stopPropagation();
                    var val = $.fn.dataTable.util.escapeRegex($(this).val());
                    if ( colIndex !== -1 ) {
                        var regex = val ? '(^|,\\s*)' + val + '(\\s*,|$)' : '';
                        dt.column(colIndex).search(regex, true, false).draw();
                    } else {
                        dt.search(val).draw();
                    }
                });
            } else {
                var searchTimeout;
                $input.off('input').on('input', function(e) {
                    e.stopPropagation();
                    var val = $(this).val();
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(function() {
                        if ( colIndex !== -1 ) {
                            dt.column(colIndex).search(val).draw();
                        }
                    }, 400); // 400ms debounce
                });
            }
        });
    }

    /**
     * Initialize all .wtb-table elements inside a given $scope container.
     */
    function initScope($scope) {
        $scope.find('.wtb-table').each(function () {
            initTable( $(this) );
        });
    }

    // ── Standard page load ──────────────────────────────────────────────────
    $(document).ready(function () {
        initScope( $(document) );
    });

    // ── Elementor editor preview init ────────────────────────────────────────
    // Register the widget-ready hook so DataTables initializes every time the
    // WTB widget is rendered (or re-rendered) inside the Elementor preview iframe.
    var elementorInitTimers = {};

    function registerElementorHook() {
        if ( ! window.elementorFrontend ) return;

        window.elementorFrontend.hooks.addAction(
            'frontend/element_ready/wtb_table.default',
            function ($scope) {
                var scopeId = $scope.data('id') || 'default';
                if ( elementorInitTimers[scopeId] ) {
                    clearTimeout(elementorInitTimers[scopeId]);
                }

                var $table = $scope.find('.wtb-table');
                if ( $table.length && $.fn.DataTable && $.fn.DataTable.isDataTable($table) ) {
                    try {
                        $table.DataTable().columns.adjust();
                    } catch (e) {}
                }

                elementorInitTimers[scopeId] = setTimeout(function () {
                    initScope($scope);
                    delete elementorInitTimers[scopeId];
                }, 250);
            }
        );
    }

    if ( window.elementorFrontend && window.elementorFrontend.isInit ) {
        registerElementorHook();
    } else {
        $(window).on('elementor/frontend/init', registerElementorHook);
    }

    // ── File Preview Modal ───────────────────────────────────────────────────
    function openFilePreviewModal(fileUrl, fileName) {
        if ( ! fileUrl ) return;

        var urlClean  = String(fileUrl).trim();
        var nameClean = fileName ? String(fileName) : (urlClean.split('/').pop() || 'File Preview');
        var ext       = urlClean.split('.').pop().split(/\#|\?/)[0].toLowerCase();

        var $overlay = $('<div class="wtb-file-modal-overlay"></div>');
        var $dialog  = $('<div class="wtb-file-modal-dialog" role="dialog" aria-modal="true"></div>');

        var $header = $(
            '<div class="wtb-file-modal-header">' +
            '  <h3 class="wtb-file-modal-title" title="' + escapeHtml(nameClean) + '">' + escapeHtml(nameClean) + '</h3>' +
            '  <button type="button" class="wtb-file-modal-close" aria-label="Tutup modal">\u00d7</button>' +
            '</div>'
        );

        var $body = $('<div class="wtb-file-modal-body"></div>');
        var $footer = $(
            '<div class="wtb-file-modal-footer">' +
            '  <a href="' + urlClean + '" target="_blank" download class="wtb-modal-btn wtb-modal-btn-download">' +
            '    <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>' +
            '    <span>Download File</span>' +
            '  </a>' +
            '  <button type="button" class="wtb-modal-btn wtb-modal-btn-close">Tutup</button>' +
            '</div>'
        );

        if ( ext === 'pdf' ) {
            $body.html('<iframe src="' + urlClean + '#toolbar=1" width="100%" height="500px" style="border:none;display:block;" title="' + escapeHtml(nameClean) + '"></iframe>');
        } else if ( ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp'].indexOf(ext) !== -1 ) {
            $body.html('<div class="wtb-preview-img-wrap"><img src="' + urlClean + '" alt="' + escapeHtml(nameClean) + '" class="wtb-preview-img"></div>');
        } else if ( ['mp3', 'wav', 'ogg', 'm4a'].indexOf(ext) !== -1 ) {
            $body.html('<div class="wtb-preview-media-wrap"><audio controls autoplay style="width:100%;max-width:500px;"><source src="' + urlClean + '"></audio></div>');
        } else if ( ['mp4', 'webm', 'ogv', 'mov'].indexOf(ext) !== -1 ) {
            $body.html('<div class="wtb-preview-media-wrap"><video controls autoplay style="max-width:100%;max-height:65vh;display:block;margin:0 auto;border-radius:8px;"><source src="' + urlClean + '"></video></div>');
        } else {
            $body.html(
                '<div class="wtb-preview-fallback-wrap">' +
                '  <svg viewBox="0 0 24 24" width="48" height="48" stroke="#4f46e5" stroke-width="1.5" fill="none" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom:12px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>' +
                '  <h4 style="margin:0 0 8px;font-size:1.1em;color:#1e293b;">' + escapeHtml(nameClean) + '</h4>' +
                '  <p style="color:#64748b;font-size:0.9em;margin:0;">Pratinjau langsung tidak tersedia untuk format file ini (.' + escapeHtml(ext) + '). Silakan unduh file untuk melihat isinya.</p>' +
                '</div>'
            );
        }

        $dialog.append($header).append($body).append($footer);
        $overlay.append($dialog);
        $('body').append($overlay);

        setTimeout(function() {
            $overlay.addClass('wtb-modal-active');
        }, 10);

        function closeModal() {
            $overlay.removeClass('wtb-modal-active');
            setTimeout(function() {
                $overlay.remove();
            }, 250);
            $(document).off('keydown.wtbmodal');
        }

        $overlay.on('click', function (e) {
            if ( $(e.target).hasClass('wtb-file-modal-overlay') || $(e.target).hasClass('wtb-file-modal-close') || $(e.target).hasClass('wtb-modal-btn-close') ) {
                closeModal();
            }
        });

        $(document).on('keydown.wtbmodal', function (e) {
            if ( e.key === 'Escape' || e.keyCode === 27 ) {
                closeModal();
            }
        });
    }

    function escapeHtml(text) {
        return String(text)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    $(document).on('click', '.wtb-cell-file[data-preview="1"], .wtb-btn-file-preview', function (e) {
        e.preventDefault();
        var $el      = $(this);
        var fileUrl  = $el.data('file-url')  || $el.attr('href');
        var fileName = $el.data('file-name') || $el.find('span').text() || $el.attr('title');
        openFilePreviewModal(fileUrl, fileName);
    });

    // ── Form Submission Handler ──────────────────────────────────────────────
    $(document).on('submit', '.wtb-user-submit-form', function (e) {
        e.preventDefault();

        var $form   = $(this);
        var $btn    = $form.find('.wtb-form-btn-submit');
        var $msgBox = $form.find('.wtb-form-response-msg');
        var tableId = $form.data('table-id');
        var restUrl = $form.data('rest-url');
        var config  = window['WTB_Table_' + tableId] || {};

        $btn.prop('disabled', true).addClass('wtb-btn-loading');
        $msgBox.hide().removeClass('wtb-msg-success wtb-msg-error');

        var formData = {};
        $form.find('[name^="cells_data["]').each(function () {
            var name  = $(this).attr('name');
            var match = name.match(/cells_data\[(\d+)\]/);
            if ( match && match[1] ) {
                formData[match[1]] = $(this).val();
            }
        });

        $.ajax({
            url:         restUrl,
            type:        'POST',
            contentType: 'application/json',
            headers:     { 'X-WP-Nonce': config.nonce || '' },
            data:        JSON.stringify({ cells_data: formData }),
            success: function (res) {
                $btn.prop('disabled', false).removeClass('wtb-btn-loading');
                if ( res && res.success ) {
                    $msgBox.addClass('wtb-msg-success')
                        .html('<svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px;vertical-align:middle;"><polyline points="20 6 9 17 4 12"></polyline></svg>' + escapeHtml(res.message))
                        .fadeIn();
                    $form[0].reset();

                    var $table = $('#wtb-table-' + tableId);
                    if ( $table.length && $.fn.DataTable && $.fn.DataTable.isDataTable($table) ) {
                        var dt = $table.DataTable();
                        if ( $table.data('server-side') === 1 || $table.data('server-side') === '1' ) {
                            dt.ajax.reload(null, false);
                        } else if ( res.status === 'published' ) {
                            setTimeout(function () { window.location.reload(); }, 1200);
                        }
                    }
                } else {
                    $msgBox.addClass('wtb-msg-error')
                        .text((res && res.message) ? res.message : 'Gagal mengirim data.')
                        .fadeIn();
                }
            },
            error: function (xhr) {
                $btn.prop('disabled', false).removeClass('wtb-btn-loading');
                var errText = 'Terjadi kesalahan saat mengirim data.';
                if ( xhr.responseJSON && xhr.responseJSON.message ) {
                    errText = xhr.responseJSON.message;
                }
                $msgBox.addClass('wtb-msg-error').text(errText).fadeIn();
            }
        });
    });

}(jQuery));

