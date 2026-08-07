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
                    return {
                        draw:   d.draw,
                        start:  d.start,
                        length: d.length,
                        search: d.search.value,
                    };
                },
            };
            if ( config.columns ) {
                dtOptions.columns = config.columns.map(function (col) {
                    return { data: String(col.id), defaultContent: '\u2014' };
                });
            }
        }

        $table.DataTable(dtOptions);
    }

    /**
     * Initialize all .wtb-table elements inside a given $scope container.
     */
    function initScope($scope) {
        $scope.find('.wtb-table').each(function () {
            initTable( $(this) );
        });
    }

    // ── Taxonomy Filter Bar Click Handler ──────────────────────────────────
    $(document).on('click', '.wtb-tax-btn', function (e) {
        e.preventDefault();
        var $btn     = $(this);
        var $bar     = $btn.closest('.wtb-tax-filter-bar');
        var targetId = $bar.data('table-id');
        var term     = String($btn.data('term') || 'all');

        $bar.find('.wtb-tax-btn').removeClass('active');
        $btn.addClass('active');

        var $table = targetId ? $('#' + targetId) : $bar.parent().find('.wtb-table');
        if ( ! $table.length ) return;

        if ( $.fn.DataTable && $.fn.DataTable.isDataTable($table) ) {
            var dt = $table.DataTable();
            if ( term === 'all' ) {
                dt.draw();
            } else {
                $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
                    if ( settings.nTable.id !== $table.attr('id') ) {
                        return true;
                    }
                    var $row = $(dt.row(dataIndex).node());
                    var terms = ($row.attr('data-tax-terms') || '').split(' ');
                    return terms.indexOf(term) !== -1;
                });
                dt.draw();
                $.fn.dataTable.ext.search.pop();
            }
        } else {
            $table.find('tbody tr').each(function () {
                var $tr = $(this);
                var terms = ($tr.attr('data-tax-terms') || '').split(' ');
                if ( term === 'all' || terms.indexOf(term) !== -1 ) {
                    $tr.show();
                } else {
                    $tr.hide();
                }
            });
        }
    });

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
        // elementorFrontend already bootstrapped — register immediately
        registerElementorHook();
    } else {
        // Wait for elementorFrontend to bootstrap
        $(window).on('elementor/frontend/init', registerElementorHook);
    }

}(jQuery));

