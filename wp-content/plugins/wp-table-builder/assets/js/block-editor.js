(function (blocks, element, blockEditor, components, apiFetch) {
    'use strict';

    var el           = element.createElement;
    var useBlockProps = blockEditor.useBlockProps;
    var InspectorControls = blockEditor.InspectorControls;
    var PanelBody    = components.PanelBody;
    var SelectControl = components.SelectControl;
    var Spinner      = components.Spinner;
    var useState     = element.useState;
    var useEffect    = element.useEffect;

    blocks.registerBlockType('wtb/table', {
        title:       'WP Table Builder',
        icon:        'editor-table',
        category:    'common',
        description: 'Sisipkan tabel dari WP Table Builder.',
        attributes: {
            tableId: { type: 'number', default: 0 },
        },

        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;

            var state = useState({ tables: [], loading: true });
            var tables  = state[0].tables;
            var loading = state[0].loading;
            var setState = state[1];

            useEffect(function () {
                apiFetch({ path: '/wtb/v1/tables' })
                    .then(function (data) {
                        setState({ tables: data, loading: false });
                    })
                    .catch(function () {
                        setState({ tables: [], loading: false });
                    });
            }, []);

            var blockProps = useBlockProps();

            if (loading) {
                return el('div', blockProps, el(Spinner));
            }

            var options = [{ label: '— Pilih Tabel —', value: 0 }].concat(
                tables.map(function (t) {
                    return { label: t.title, value: t.id };
                })
            );

            return el(
                'div',
                blockProps,
                el(
                    InspectorControls,
                    null,
                    el(
                        PanelBody,
                        { title: 'Pilih Tabel', initialOpen: true },
                        el(SelectControl, {
                            label:    'Tabel',
                            value:    attributes.tableId,
                            options:  options,
                            onChange: function (val) {
                                setAttributes({ tableId: parseInt(val, 10) });
                            },
                        })
                    )
                ),
                attributes.tableId > 0
                    ? el('div', { className: 'wtb-block-selected' },
                          'Tabel ID #' + attributes.tableId + ' akan ditampilkan di sini.'
                      )
                    : el('div', { className: 'wtb-block-placeholder' },
                          el('span', { className: 'dashicons dashicons-editor-table' }),
                          el('p', null, 'Pilih tabel di panel kanan untuk menampilkannya.')
                      )
            );
        },

        save: function () {
            return null;
        },
    });

}(window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.apiFetch));
