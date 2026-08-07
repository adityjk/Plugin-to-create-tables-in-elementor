<?php
if ( ! defined( 'ABSPATH' ) ) exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;

class WTB_Elementor_Widget extends Widget_Base {

    public function get_name(): string      { return 'wtb_table'; }
    public function get_title(): string     { return __( 'WP Table Builder', 'wp-table-builder' ); }
    public function get_icon(): string      { return 'eicon-table'; }
    public function get_categories(): array { return [ 'general' ]; }

    protected function register_controls() {

        // ==================================================================
        // --- Section: Sumber Data & Pilih Tabel ---
        $this->start_controls_section( 'content_section', [
            'label' => __( 'Sumber Data & Pilih Tabel', 'wp-table-builder' ),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ] );

        $this->add_control( 'source_type', [
            'label'   => __( 'Sumber Data Tabel', 'wp-table-builder' ),
            'type'    => Controls_Manager::SELECT,
            'options' => [
                'static'  => __( 'Tabel Statis (Table Builder)', 'wp-table-builder' ),
                'dynamic' => __( 'Dynamic Posts & Taxonomy Query', 'wp-table-builder' ),
            ],
            'default' => 'static',
        ] );

        $tables = get_posts( [
            'post_type'      => 'wtb_table',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ] );

        $options = [ '0' => __( '— Pilih Tabel —', 'wp-table-builder' ) ];
        foreach ( $tables as $table ) {
            $options[ $table->ID ] = $table->post_title;
        }

        $this->add_control( 'table_id', [
            'label'     => __( 'Pilih Tabel Statis', 'wp-table-builder' ),
            'type'      => Controls_Manager::SELECT,
            'options'   => $options,
            'default'   => '0',
            'condition' => [ 'source_type' => 'static' ],
        ] );

        // --- Dynamic Post & Taxonomy Query Controls ---
        $this->add_control( 'heading_dynamic_query', [
            'label'     => __( 'Pengaturan Dynamic Query', 'wp-table-builder' ),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
            'condition' => [ 'source_type' => 'dynamic' ],
        ] );

        $post_type_options = [];
        $public_pts = get_post_types( [ 'public' => true ], 'objects' );
        foreach ( $public_pts as $pt_slug => $pt_obj ) {
            if ( $pt_slug !== 'wtb_table' && $pt_slug !== 'attachment' ) {
                $post_type_options[ $pt_slug ] = $pt_obj->labels->name . ' (' . $pt_slug . ')';
            }
        }

        $this->add_control( 'post_type', [
            'label'     => __( 'Pilih Post Type', 'wp-table-builder' ),
            'type'      => Controls_Manager::SELECT,
            'options'   => $post_type_options,
            'default'   => 'post',
            'condition' => [ 'source_type' => 'dynamic' ],
        ] );

        $tax_options = [];
        $public_taxes = get_taxonomies( [ 'public' => true ], 'objects' );
        foreach ( $public_taxes as $tax_slug => $tax_obj ) {
            $tax_options[ $tax_slug ] = $tax_obj->labels->name . ' (' . $tax_slug . ')';
        }

        $this->add_control( 'taxonomy', [
            'label'     => __( 'Pilih Taxonomy', 'wp-table-builder' ),
            'type'      => Controls_Manager::SELECT,
            'options'   => $tax_options,
            'default'   => 'category',
            'condition' => [ 'source_type' => 'dynamic' ],
        ] );

        $this->add_control( 'tax_terms', [
            'label'       => __( 'Slug / ID Term Kategori', 'wp-table-builder' ),
            'type'        => Controls_Manager::TEXT,
            'placeholder' => __( 'misal: berita, gadget (pisahkan koma)', 'wp-table-builder' ),
            'description' => __( 'Kosongkan jika ingin menampilkan semua kategori.', 'wp-table-builder' ),
            'condition'   => [ 'source_type' => 'dynamic' ],
        ] );

        $this->add_control( 'tax_operator', [
            'label'     => __( 'Taxonomy Operator', 'wp-table-builder' ),
            'type'      => Controls_Manager::SELECT,
            'options'   => [
                'IN'     => __( 'Termasuk (IN)', 'wp-table-builder' ),
                'NOT IN' => __( 'Kecualikan (NOT IN)', 'wp-table-builder' ),
                'AND'    => __( 'Harus Memenuhi Semua (AND)', 'wp-table-builder' ),
            ],
            'default'   => 'IN',
            'condition' => [ 'source_type' => 'dynamic' ],
        ] );

        $this->add_control( 'posts_per_page', [
            'label'     => __( 'Jumlah Data (Limit)', 'wp-table-builder' ),
            'type'      => Controls_Manager::NUMBER,
            'default'   => 10,
            'min'       => -1,
            'max'       => 100,
            'condition' => [ 'source_type' => 'dynamic' ],
        ] );

        $this->add_control( 'orderby', [
            'label'     => __( 'Urutkan Berdasarkan (Order By)', 'wp-table-builder' ),
            'type'      => Controls_Manager::SELECT,
            'options'   => [
                'date'       => __( 'Tanggal (Date)', 'wp-table-builder' ),
                'title'      => __( 'Judul (Title)', 'wp-table-builder' ),
                'ID'         => __( 'Post ID', 'wp-table-builder' ),
                'modified'   => __( 'Tanggal Edit (Modified)', 'wp-table-builder' ),
                'rand'       => __( 'Acak (Random)', 'wp-table-builder' ),
                'menu_order' => __( 'Urutan Menu (Menu Order)', 'wp-table-builder' ),
            ],
            'default'   => 'date',
            'condition' => [ 'source_type' => 'dynamic' ],
        ] );

        $this->add_control( 'order', [
            'label'     => __( 'Arah Urutan (Order)', 'wp-table-builder' ),
            'type'      => Controls_Manager::SELECT,
            'options'   => [
                'DESC' => __( 'Menurun (DESC)', 'wp-table-builder' ),
                'ASC'  => __( 'Menaik (ASC)', 'wp-table-builder' ),
            ],
            'default'   => 'DESC',
            'condition' => [ 'source_type' => 'dynamic' ],
        ] );

        // --- Heading: Kolom Yang Ditampilkan ---
        $this->add_control( 'heading_dynamic_cols', [
            'label'     => __( 'Kolom Yang Ditampilkan', 'wp-table-builder' ),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
            'condition' => [ 'source_type' => 'dynamic' ],
        ] );

        $this->add_control( 'show_img', [
            'label'        => __( 'Tampilkan Gambar', 'wp-table-builder' ),
            'type'         => Controls_Manager::SWITCHER,
            'default'      => 'yes',
            'return_value' => 'yes',
            'condition'    => [ 'source_type' => 'dynamic' ],
        ] );

        $this->add_control( 'show_title', [
            'label'        => __( 'Tampilkan Judul', 'wp-table-builder' ),
            'type'         => Controls_Manager::SWITCHER,
            'default'      => 'yes',
            'return_value' => 'yes',
            'condition'    => [ 'source_type' => 'dynamic' ],
        ] );

        $this->add_control( 'show_cats', [
            'label'        => __( 'Tampilkan Kategori/Badge', 'wp-table-builder' ),
            'type'         => Controls_Manager::SWITCHER,
            'default'      => 'yes',
            'return_value' => 'yes',
            'condition'    => [ 'source_type' => 'dynamic' ],
        ] );

        $this->add_control( 'show_excerpt', [
            'label'        => __( 'Tampilkan Ringkasan', 'wp-table-builder' ),
            'type'         => Controls_Manager::SWITCHER,
            'default'      => 'yes',
            'return_value' => 'yes',
            'condition'    => [ 'source_type' => 'dynamic' ],
        ] );

        $this->add_control( 'show_date', [
            'label'        => __( 'Tampilkan Tanggal', 'wp-table-builder' ),
            'type'         => Controls_Manager::SWITCHER,
            'default'      => 'yes',
            'return_value' => 'yes',
            'condition'    => [ 'source_type' => 'dynamic' ],
        ] );

        $this->add_control( 'show_author', [
            'label'        => __( 'Tampilkan Penulis', 'wp-table-builder' ),
            'type'         => Controls_Manager::SWITCHER,
            'default'      => 'no',
            'return_value' => 'yes',
            'condition'    => [ 'source_type' => 'dynamic' ],
        ] );

        $this->add_control( 'show_btn', [
            'label'        => __( 'Tampilkan Tombol Detail', 'wp-table-builder' ),
            'type'         => Controls_Manager::SWITCHER,
            'default'      => 'yes',
            'return_value' => 'yes',
            'condition'    => [ 'source_type' => 'dynamic' ],
        ] );

        $this->add_control( 'btn_text', [
            'label'     => __( 'Teks Tombol Detail', 'wp-table-builder' ),
            'type'      => Controls_Manager::TEXT,
            'default'   => __( 'Detail', 'wp-table-builder' ),
            'condition' => [
                'source_type' => 'dynamic',
                'show_btn'    => 'yes',
            ],
        ] );

        $this->end_controls_section();

        // --- Section: Frontend Taxonomy Filter Bar ---
        $this->start_controls_section( 'tax_filter_section', [
            'label' => __( 'Baris Filter Kategori (Taxonomy Filter Bar)', 'wp-table-builder' ),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ] );

        $this->add_control( 'show_tax_filter_bar', [
            'label'        => __( 'Tampilkan Baris Filter Kategori di Atas Tabel', 'wp-table-builder' ),
            'type'         => Controls_Manager::SWITCHER,
            'label_on'     => __( 'Ya', 'wp-table-builder' ),
            'label_off'    => __( 'Tidak', 'wp-table-builder' ),
            'return_value' => 'yes',
            'default'      => 'no',
        ] );

        $this->add_control( 'tax_filter_label_all', [
            'label'     => __( 'Label Tombol Semua (All)', 'wp-table-builder' ),
            'type'      => Controls_Manager::TEXT,
            'default'   => __( 'Semua', 'wp-table-builder' ),
            'condition' => [ 'show_tax_filter_bar' => 'yes' ],
        ] );

        $this->end_controls_section();

        // --- Section: Kontrol & Navigasi Tabel ---
        $this->start_controls_section( 'controls_section', [
            'label' => __( 'Element & Kontrol Tabel', 'wp-table-builder' ),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ] );

        $this->add_control( 'show_search', [
            'label'        => __( 'Tampilkan Search Box (Cari)', 'wp-table-builder' ),
            'type'         => Controls_Manager::SWITCHER,
            'label_on'     => __( 'Ya', 'wp-table-builder' ),
            'label_off'    => __( 'Tidak', 'wp-table-builder' ),
            'return_value' => 'yes',
            'default'      => 'yes',
            'selectors'    => [
                '{{WRAPPER}} .dataTables_wrapper .dataTables_filter' => 'display: block !important;',
            ],
        ] );

        $this->add_control( 'hide_search_css', [
            'type'      => Controls_Manager::HIDDEN,
            'default'   => 'hide',
            'condition' => [ 'show_search!' => 'yes' ],
            'selectors' => [
                '{{WRAPPER}} .dataTables_wrapper .dataTables_filter' => 'display: none !important;',
            ],
        ] );

        $this->add_control( 'show_length', [
            'label'        => __( 'Tampilkan Jumlah Baris (Page Size)', 'wp-table-builder' ),
            'type'         => Controls_Manager::SWITCHER,
            'label_on'     => __( 'Ya', 'wp-table-builder' ),
            'label_off'    => __( 'Tidak', 'wp-table-builder' ),
            'return_value' => 'yes',
            'default'      => 'yes',
        ] );

        $this->add_control( 'hide_length_css', [
            'type'      => Controls_Manager::HIDDEN,
            'default'   => 'hide',
            'condition' => [ 'show_length!' => 'yes' ],
            'selectors' => [
                '{{WRAPPER}} .dataTables_wrapper .dataTables_length' => 'display: none !important;',
            ],
        ] );

        $this->add_control( 'show_info', [
            'label'        => __( 'Tampilkan Info Status Data', 'wp-table-builder' ),
            'type'         => Controls_Manager::SWITCHER,
            'label_on'     => __( 'Ya', 'wp-table-builder' ),
            'label_off'    => __( 'Tidak', 'wp-table-builder' ),
            'return_value' => 'yes',
            'default'      => 'yes',
        ] );

        $this->add_control( 'hide_info_css', [
            'type'      => Controls_Manager::HIDDEN,
            'default'   => 'hide',
            'condition' => [ 'show_info!' => 'yes' ],
            'selectors' => [
                '{{WRAPPER}} .dataTables_wrapper .dataTables_info' => 'display: none !important;',
            ],
        ] );

        $this->add_control( 'show_pagination', [
            'label'        => __( 'Tampilkan Pagination', 'wp-table-builder' ),
            'type'         => Controls_Manager::SWITCHER,
            'label_on'     => __( 'Ya', 'wp-table-builder' ),
            'label_off'    => __( 'Tidak', 'wp-table-builder' ),
            'return_value' => 'yes',
            'default'      => 'yes',
        ] );

        $this->add_control( 'hide_pagination_css', [
            'type'      => Controls_Manager::HIDDEN,
            'default'   => 'hide',
            'condition' => [ 'show_pagination!' => 'yes' ],
            'selectors' => [
                '{{WRAPPER}} .dataTables_wrapper .dataTables_paginate' => 'display: none !important;',
            ],
        ] );

        $this->add_control( 'show_prev_next', [
            'label'        => __( 'Tampilkan Tombol Sebelum / Sesudah', 'wp-table-builder' ),
            'type'         => Controls_Manager::SWITCHER,
            'label_on'     => __( 'Ya', 'wp-table-builder' ),
            'label_off'    => __( 'Tidak', 'wp-table-builder' ),
            'return_value' => 'yes',
            'default'      => 'yes',
            'condition'    => [ 'show_pagination' => 'yes' ],
        ] );

        $this->add_control( 'hide_prev_next_css', [
            'type'      => Controls_Manager::HIDDEN,
            'default'   => 'hide',
            'condition' => [
                'show_pagination'  => 'yes',
                'show_prev_next!' => 'yes',
            ],
            'selectors' => [
                '{{WRAPPER}} .dataTables_wrapper .dataTables_paginate .previous, {{WRAPPER}} .dataTables_wrapper .dataTables_paginate .next' => 'display: none !important;',
            ],
        ] );

        $this->add_control( 'show_page_numbers', [
            'label'        => __( 'Tampilkan Nomor Halaman', 'wp-table-builder' ),
            'type'         => Controls_Manager::SWITCHER,
            'label_on'     => __( 'Ya', 'wp-table-builder' ),
            'label_off'    => __( 'Tidak', 'wp-table-builder' ),
            'return_value' => 'yes',
            'default'      => 'yes',
            'condition'    => [ 'show_pagination' => 'yes' ],
        ] );

        $this->add_control( 'hide_page_numbers_css', [
            'type'      => Controls_Manager::HIDDEN,
            'default'   => 'hide',
            'condition' => [
                'show_pagination'    => 'yes',
                'show_page_numbers!' => 'yes',
            ],
            'selectors' => [
                '{{WRAPPER}} .dataTables_wrapper .dataTables_paginate span' => 'display: none !important;',
            ],
        ] );

        $this->add_control( 'pagination_type', [
            'label'     => __( 'Tipe Pagination', 'wp-table-builder' ),
            'type'      => Controls_Manager::SELECT,
            'options'   => [
                'numbers' => __( 'Nomor Halaman (Numbers)', 'wp-table-builder' ),
                'dots'    => __( 'Indicator Dots', 'wp-table-builder' ),
            ],
            'default'   => 'numbers',
            'condition' => [ 'show_pagination' => 'yes' ],
        ] );

        $this->end_controls_section();

        // ==================================================================
        // TAB STYLE
        // ==================================================================

        // --- Section 1: Dimensi & Layout ---
        $this->start_controls_section( 'section_style_layout', [
            'label' => __( 'Dimensi & Layout', 'wp-table-builder' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ] );

        $this->add_responsive_control( 'override_width', [
            'label'      => __( 'Width (Lebar)', 'wp-table-builder' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => [ 'px', '%', 'vw', 'em', 'rem' ],
            'range'      => [
                'px' => [ 'min' => 50, 'max' => 1600 ],
                '%'  => [ 'min' => 10, 'max' => 100 ],
            ],
            'selectors' => [
                '{{WRAPPER}} .wtb-table-wrap' => 'width: {{SIZE}}{{UNIT}} !important;',
            ],
        ] );

        $this->add_responsive_control( 'override_max_width', [
            'label'      => __( 'Max Width', 'wp-table-builder' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => [ 'px', '%', 'vw', 'em', 'rem' ],
            'range'      => [
                'px' => [ 'min' => 50, 'max' => 2000 ],
                '%'  => [ 'min' => 10, 'max' => 100 ],
            ],
            'selectors' => [
                '{{WRAPPER}} .wtb-table-wrap' => 'max-width: {{SIZE}}{{UNIT}} !important;',
            ],
        ] );

        $this->add_responsive_control( 'override_height', [
            'label'      => __( 'Height (Tinggi)', 'wp-table-builder' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => [ 'px', 'vh', 'em', 'rem' ],
            'range'      => [
                'px' => [ 'min' => 50, 'max' => 1200 ],
            ],
            'selectors' => [
                '{{WRAPPER}} .wtb-table-wrap' => 'height: {{SIZE}}{{UNIT}} !important;',
            ],
        ] );

        $this->add_responsive_control( 'override_max_height', [
            'label'      => __( 'Max Height', 'wp-table-builder' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => [ 'px', 'vh', 'em', 'rem' ],
            'range'      => [
                'px' => [ 'min' => 50, 'max' => 1200 ],
            ],
            'selectors' => [
                '{{WRAPPER}} .wtb-table-wrap' => 'max-height: {{SIZE}}{{UNIT}} !important; overflow-y: auto !important;',
            ],
        ] );

        $this->add_responsive_control( 'override_alignment', [
            'label'   => __( 'Alignment Teks Tabel', 'wp-table-builder' ),
            'type'    => Controls_Manager::CHOOSE,
            'options' => [
                'left' => [
                    'title' => __( 'Left', 'wp-table-builder' ),
                    'icon'  => 'eicon-text-align-left',
                ],
                'center' => [
                    'title' => __( 'Center', 'wp-table-builder' ),
                    'icon'  => 'eicon-text-align-center',
                ],
                'right' => [
                    'title' => __( 'Right', 'wp-table-builder' ),
                    'icon'  => 'eicon-text-align-right',
                ],
                'justify' => [
                    'title' => __( 'Justify', 'wp-table-builder' ),
                    'icon'  => 'eicon-text-align-justify',
                ],
            ],
            'selectors' => [
                '{{WRAPPER}} .wtb-table th, {{WRAPPER}} .wtb-table td' => 'text-align: {{VALUE}} !important;',
            ],
        ] );

        $this->add_responsive_control( 'table_position', [
            'label'       => __( 'Posisi Tabel (Wrapper)', 'wp-table-builder' ),
            'type'        => Controls_Manager::CHOOSE,
            'description' => __( 'Mengatur posisi bingkai tabel di halaman (Kiri / Tengah / Kanan). Berfungsi jika Lebar (Width) tabel kurang dari 100%.', 'wp-table-builder' ),
            'options'     => [
                'left' => [
                    'title' => __( 'Left', 'wp-table-builder' ),
                    'icon'  => 'eicon-h-align-left',
                ],
                'center' => [
                    'title' => __( 'Center', 'wp-table-builder' ),
                    'icon'  => 'eicon-h-align-center',
                ],
                'right' => [
                    'title' => __( 'Right', 'wp-table-builder' ),
                    'icon'  => 'eicon-h-align-right',
                ],
            ],
            'selectors_dictionary' => [
                'left'   => 'margin-left: 0 !important; margin-right: auto !important;',
                'center' => 'margin-left: auto !important; margin-right: auto !important;',
                'right'  => 'margin-left: auto !important; margin-right: 0 !important;',
            ],
            'selectors' => [
                '{{WRAPPER}} .wtb-table-wrap' => '{{VALUE}}',
            ],
        ] );

        $this->end_controls_section();

        // --- Section 2: Border & Shadow ---
        $this->start_controls_section( 'section_style_border', [
            'label' => __( 'Border & Shadow', 'wp-table-builder' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ] );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name'     => 'override_border',
                'label'    => __( 'Border Tabel', 'wp-table-builder' ),
                'selector' => '{{WRAPPER}} .wtb-table, {{WRAPPER}} .wtb-table th, {{WRAPPER}} .wtb-table td',
            ]
        );

        $this->add_responsive_control( 'override_border_radius', [
            'label'      => __( 'Border Radius', 'wp-table-builder' ),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => [ 'px', '%', 'em', 'rem' ],
            'selectors'  => [
                '{{WRAPPER}} .wtb-table-wrap' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important; overflow: hidden !important;',
                '{{WRAPPER}} .wtb-table-scroll' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important; overflow: hidden !important;',
                '{{WRAPPER}} .wtb-table' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important; overflow: hidden !important;',
                '{{WRAPPER}} .wtb-table thead tr:first-child th:first-child' => 'border-top-left-radius: {{TOP}}{{UNIT}} !important;',
                '{{WRAPPER}} .wtb-table thead tr:first-child th:last-child'  => 'border-top-right-radius: {{RIGHT}}{{UNIT}} !important;',
                '{{WRAPPER}} .wtb-table tbody tr:last-child td:first-child'  => 'border-bottom-left-radius: {{LEFT}}{{UNIT}} !important;',
                '{{WRAPPER}} .wtb-table tbody tr:last-child td:last-child'   => 'border-bottom-right-radius: {{BOTTOM}}{{UNIT}} !important;',
            ],
        ] );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name'     => 'override_box_shadow',
                'label'    => __( 'Box Shadow', 'wp-table-builder' ),
                'selector' => '{{WRAPPER}} .wtb-table',
            ]
        );

        $this->end_controls_section();

        // --- Section 3: Warna Header & Baris ---
        $this->start_controls_section( 'section_style_colors', [
            'label' => __( 'Warna Header & Baris', 'wp-table-builder' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ] );

        $this->add_control( 'override_header_bg', [
            'label'     => __( 'Warna Background Header', 'wp-table-builder' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#2271b1',
            'selectors' => [
                '{{WRAPPER}} .wtb-table thead tr' => 'background: {{VALUE}} !important;',
            ],
        ] );

        $this->add_control( 'override_header_text', [
            'label'     => __( 'Warna Teks Header', 'wp-table-builder' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#ffffff',
            'selectors' => [
                '{{WRAPPER}} .wtb-table thead tr th' => 'color: {{VALUE}} !important;',
            ],
        ] );

        $this->add_control( 'override_row_stripe', [
            'label'        => __( 'Baris Selang-seling (Stripe)', 'wp-table-builder' ),
            'type'         => Controls_Manager::SWITCHER,
            'label_on'     => __( 'Ya', 'wp-table-builder' ),
            'label_off'    => __( 'Tidak', 'wp-table-builder' ),
            'return_value' => 'yes',
            'default'      => 'yes',
        ] );

        $this->add_control( 'override_stripe_color', [
            'label'     => __( 'Warna Baris Stripe', 'wp-table-builder' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#f5f5f5',
            'condition' => [ 'override_row_stripe' => 'yes' ],
            'selectors' => [
                '{{WRAPPER}} .wtb-table tbody tr:nth-child(even)' => 'background: {{VALUE}} !important;',
            ],
        ] );

        $this->add_control( 'override_hover_color', [
            'label'     => __( 'Warna Baris Hover', 'wp-table-builder' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '',
            'selectors' => [
                '{{WRAPPER}} .wtb-table tbody tr:hover td' => 'background: {{VALUE}} !important;',
            ],
        ] );

        $this->end_controls_section();


        // --- Section 4: Tipografi ---
        $this->start_controls_section( 'section_style_typography', [
            'label' => __( 'Tipografi', 'wp-table-builder' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ] );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'override_typography',
                'label'    => __( 'Tipografi Tabel (Umum)', 'wp-table-builder' ),
                'selector' => '{{WRAPPER}} .wtb-table-wrap, {{WRAPPER}} .wtb-table, {{WRAPPER}} .wtb-table th, {{WRAPPER}} .wtb-table td, {{WRAPPER}} .dataTables_wrapper',
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'override_header_typography',
                'label'    => __( 'Tipografi Header', 'wp-table-builder' ),
                'selector' => '{{WRAPPER}} .wtb-table thead tr th',
            ]
        );

        $this->end_controls_section();

        // --- Section 5: Navigasi & Pagination Style ---
        $this->start_controls_section( 'section_style_pagination', [
            'label' => __( 'Navigasi & Pagination Style', 'wp-table-builder' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ] );

        // --- Heading: Tombol Prev & Next ---
        $this->add_control( 'heading_prev_next', [
            'label'     => __( 'Tombol Sebelum & Sesudah (Prev / Next)', 'wp-table-builder' ),
            'type'      => Controls_Manager::HEADING,
        ] );

        $this->add_control( 'prev_text', [
            'label'   => __( 'Teks Tombol Sebelum (Prev)', 'wp-table-builder' ),
            'type'    => Controls_Manager::TEXT,
            'default' => __( 'Sebelumnya', 'wp-table-builder' ),
        ] );

        $this->add_control( 'prev_icon', [
            'label'       => __( 'Icon Tombol Sebelum', 'wp-table-builder' ),
            'type'        => Controls_Manager::ICONS,
            'label_block' => true,
        ] );

        $this->add_control( 'next_text', [
            'label'   => __( 'Teks Tombol Sesudah (Next)', 'wp-table-builder' ),
            'type'    => Controls_Manager::TEXT,
            'default' => __( 'Selanjutnya', 'wp-table-builder' ),
        ] );

        $this->add_control( 'next_icon', [
            'label'       => __( 'Icon Tombol Sesudah', 'wp-table-builder' ),
            'type'        => Controls_Manager::ICONS,
            'label_block' => true,
        ] );

        $this->add_responsive_control( 'prev_next_icon_size', [
            'label'      => __( 'Ukuran Icon', 'wp-table-builder' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => [ 'px', 'em' ],
            'range'      => [
                'px' => [ 'min' => 8, 'max' => 30 ],
            ],
            'selectors'  => [
                '{{WRAPPER}} .dataTables_wrapper .dataTables_paginate .previous i, {{WRAPPER}} .dataTables_wrapper .dataTables_paginate .next i, {{WRAPPER}} .dataTables_wrapper .dataTables_paginate .previous svg, {{WRAPPER}} .dataTables_wrapper .dataTables_paginate .next svg' => 'font-size: {{SIZE}}{{UNIT}} !important; width: {{SIZE}}{{UNIT}} !important; height: {{SIZE}}{{UNIT}} !important;',
            ],
        ] );

        $this->add_responsive_control( 'prev_next_border_radius', [
            'label'      => __( 'Border Radius Tombol', 'wp-table-builder' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => [ 'px', '%' ],
            'range'      => [
                'px' => [ 'min' => 0, 'max' => 50 ],
            ],
            'selectors'  => [
                '{{WRAPPER}} .dataTables_wrapper .dataTables_paginate .previous, {{WRAPPER}} .dataTables_wrapper .dataTables_paginate .next' => 'border-radius: {{SIZE}}{{UNIT}} !important;',
            ],
        ] );

        $this->start_controls_tabs( 'tabs_prev_next_style' );

        $this->start_controls_tab(
            'tab_prev_next_normal',
            [
                'label' => __( 'Normal', 'wp-table-builder' ),
            ]
        );

        $this->add_control( 'prev_next_color', [
            'label'     => __( 'Warna Teks & Icon', 'wp-table-builder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .dataTables_wrapper .dataTables_paginate .previous, {{WRAPPER}} .dataTables_wrapper .dataTables_paginate .next' => 'color: {{VALUE}} !important;',
            ],
        ] );

        $this->add_control( 'prev_next_bg', [
            'label'     => __( 'Warna Background', 'wp-table-builder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .dataTables_wrapper .dataTables_paginate .previous, {{WRAPPER}} .dataTables_wrapper .dataTables_paginate .next' => 'background: {{VALUE}} !important;',
            ],
        ] );

        $this->end_controls_tab();

        $this->start_controls_tab(
            'tab_prev_next_hover',
            [
                'label' => __( 'Hover', 'wp-table-builder' ),
            ]
        );

        $this->add_control( 'prev_next_hover_color', [
            'label'     => __( 'Warna Teks & Icon', 'wp-table-builder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .dataTables_wrapper .dataTables_paginate .previous:hover, {{WRAPPER}} .dataTables_wrapper .dataTables_paginate .next:hover' => 'color: {{VALUE}} !important;',
            ],
        ] );

        $this->add_control( 'prev_next_hover_bg', [
            'label'     => __( 'Warna Background', 'wp-table-builder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .dataTables_wrapper .dataTables_paginate .previous:hover, {{WRAPPER}} .dataTables_wrapper .dataTables_paginate .next:hover' => 'background: {{VALUE}} !important;',
            ],
        ] );

        $this->end_controls_tab();

        $this->end_controls_tabs();

        // --- Heading: Styling Indicator Dots ---
        $this->add_control( 'heading_dots', [
            'label'     => __( 'Styling Indicator Dots', 'wp-table-builder' ),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
            'condition' => [ 'pagination_type' => 'dots' ],
        ] );

        $this->add_responsive_control( 'dots_size', [
            'label'      => __( 'Ukuran Dots', 'wp-table-builder' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => [ 'px' ],
            'range'      => [
                'px' => [ 'min' => 4, 'max' => 30 ],
            ],
            'condition'  => [ 'pagination_type' => 'dots' ],
            'selectors'  => [
                '{{WRAPPER}} .wtb-table-wrap.wtb-dots-mode .dataTables_wrapper .dataTables_paginate span .paginate_button' => 'width: {{SIZE}}px !important; height: {{SIZE}}px !important; min-width: {{SIZE}}px !important;',
            ],
        ] );

        $this->add_responsive_control( 'dots_gap', [
            'label'      => __( 'Spasi Antar Dots', 'wp-table-builder' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => [ 'px' ],
            'range'      => [
                'px' => [ 'min' => 1, 'max' => 25 ],
            ],
            'condition'  => [ 'pagination_type' => 'dots' ],
            'selectors'  => [
                '{{WRAPPER}} .wtb-table-wrap.wtb-dots-mode .dataTables_wrapper .dataTables_paginate span .paginate_button' => 'margin: 0 {{SIZE}}px !important;',
            ],
        ] );

        $this->start_controls_tabs( 'tabs_dots_style', [
            'condition' => [ 'pagination_type' => 'dots' ],
        ] );

        $this->start_controls_tab(
            'tab_dots_normal',
            [
                'label' => __( 'Inaktif', 'wp-table-builder' ),
            ]
        );

        $this->add_control( 'dots_color', [
            'label'     => __( 'Warna Dots', 'wp-table-builder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .wtb-table-wrap.wtb-dots-mode .dataTables_wrapper .dataTables_paginate span .paginate_button' => 'background: {{VALUE}} !important;',
            ],
        ] );

        $this->end_controls_tab();

        $this->start_controls_tab(
            'tab_dots_active',
            [
                'label' => __( 'Aktif', 'wp-table-builder' ),
            ]
        );

        $this->add_control( 'dots_active_color', [
            'label'     => __( 'Warna Dots', 'wp-table-builder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .wtb-table-wrap.wtb-dots-mode .dataTables_wrapper .dataTables_paginate span .paginate_button.current' => 'background: {{VALUE}} !important;',
            ],
        ] );

        $this->end_controls_tab();

        $this->end_controls_tabs();

        // --- Heading: Styling Nomor Halaman (Numbers) ---
        $this->add_control( 'heading_page_numbers', [
            'label'     => __( 'Styling Nomor Halaman (Page Numbers)', 'wp-table-builder' ),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
            'condition' => [ 'pagination_type' => 'numbers' ],
        ] );

        $this->add_responsive_control( 'page_num_border_radius', [
            'label'      => __( 'Border Radius Nomor Halaman', 'wp-table-builder' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => [ 'px', '%' ],
            'range'      => [
                'px' => [ 'min' => 0, 'max' => 50 ],
            ],
            'condition'  => [ 'pagination_type' => 'numbers' ],
            'selectors'  => [
                '{{WRAPPER}} .dataTables_wrapper .dataTables_paginate span .paginate_button' => 'border-radius: {{SIZE}}{{UNIT}} !important;',
            ],
        ] );

        $this->start_controls_tabs( 'tabs_page_num_style', [
            'condition' => [ 'pagination_type' => 'numbers' ],
        ] );

        $this->start_controls_tab(
            'tab_page_num_normal',
            [
                'label' => __( 'Normal', 'wp-table-builder' ),
            ]
        );

        $this->add_control( 'page_num_color', [
            'label'     => __( 'Warna Teks', 'wp-table-builder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .dataTables_wrapper .dataTables_paginate span .paginate_button' => 'color: {{VALUE}} !important;',
            ],
        ] );

        $this->add_control( 'page_num_bg', [
            'label'     => __( 'Warna Background', 'wp-table-builder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .dataTables_wrapper .dataTables_paginate span .paginate_button' => 'background: {{VALUE}} !important;',
            ],
        ] );

        $this->end_controls_tab();

        $this->start_controls_tab(
            'tab_page_num_active',
            [
                'label' => __( 'Aktif', 'wp-table-builder' ),
            ]
        );

        $this->add_control( 'page_num_active_color', [
            'label'     => __( 'Warna Teks', 'wp-table-builder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .dataTables_wrapper .dataTables_paginate span .paginate_button.current' => 'color: {{VALUE}} !important;',
            ],
        ] );

        $this->add_control( 'page_num_active_bg', [
            'label'     => __( 'Warna Background', 'wp-table-builder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .dataTables_wrapper .dataTables_paginate span .paginate_button.current' => 'background: {{VALUE}} !important; border-color: {{VALUE}} !important;',
            ],
        ] );

        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();

        $prev_icon_html = '';
        if ( ! empty( $settings['prev_icon']['value'] ) ) {
            ob_start();
            \Elementor\Icons_Manager::render_icon( $settings['prev_icon'], [ 'aria-hidden' => 'true' ] );
            $prev_icon_html = ob_get_clean();
        }

        $next_icon_html = '';
        if ( ! empty( $settings['next_icon']['value'] ) ) {
            ob_start();
            \Elementor\Icons_Manager::render_icon( $settings['next_icon'], [ 'aria-hidden' => 'true' ] );
            $next_icon_html = ob_get_clean();
        }

        $override_settings = [
            // Navigation / pagination
            'prev_text'       => $settings['prev_text']        ?? 'Sebelumnya',
            'next_text'       => $settings['next_text']        ?? 'Selanjutnya',
            'prev_icon_html'  => $prev_icon_html,
            'next_icon_html'  => $next_icon_html,
            'pagination_type' => $settings['pagination_type'] ?? 'numbers',

            // Colors — use Elementor values as the definitive source
            'header_bg'        => $settings['override_header_bg']    ?? '#2271b1',
            'header_text'      => $settings['override_header_text']   ?? '#ffffff',
            'row_stripe'       => ( ( $settings['override_row_stripe'] ?? 'yes' ) === 'yes' ),
            'row_stripe_color' => $settings['override_stripe_color'] ?? '#f5f5f5',
        ];

        $source_type = $settings['source_type'] ?? 'static';
        if ( $source_type === 'dynamic' ) {
            $query_args = [
                'post_type'      => $settings['post_type']      ?? 'post',
                'taxonomy'       => $settings['taxonomy']       ?? 'category',
                'terms'          => $settings['tax_terms']      ?? '',
                'operator'       => $settings['tax_operator']   ?? 'IN',
                'posts_per_page' => $settings['posts_per_page'] ?? 10,
                'orderby'        => $settings['orderby']        ?? 'date',
                'order'          => $settings['order']          ?? 'DESC',
            ];

            $display_options = [
                'show_img'            => ( ( $settings['show_img']     ?? 'yes' ) === 'yes' ),
                'show_title'          => ( ( $settings['show_title']   ?? 'yes' ) === 'yes' ),
                'show_cats'           => ( ( $settings['show_cats']    ?? 'yes' ) === 'yes' ),
                'show_excerpt'        => ( ( $settings['show_excerpt'] ?? 'yes' ) === 'yes' ),
                'show_date'           => ( ( $settings['show_date']    ?? 'yes' ) === 'yes' ),
                'show_author'         => ( ( $settings['show_author']  ?? 'no'  ) === 'yes' ),
                'show_btn'            => ( ( $settings['show_btn']     ?? 'yes' ) === 'yes' ),
                'btn_text'            => $settings['btn_text']         ?? 'Detail',
                'show_tax_filter_bar' => ( ( $settings['show_tax_filter_bar'] ?? 'no' ) === 'yes' ),
                'tax_filter_label_all'=> $settings['tax_filter_label_all'] ?? 'Semua',
            ];

            echo WTB_Render::render_dynamic_table( $query_args, $display_options, $override_settings );
            return;
        }

        $table_id = absint( $settings['table_id'] ?? 0 );

        if ( ! $table_id ) {
            echo '<div class="wtb-elementor-placeholder">';
            echo '<span class="eicon-table" aria-hidden="true"></span>';
            echo '<p>' . esc_html__( 'Pilih tabel di panel Elementor (tab Content).', 'wp-table-builder' ) . '</p>';
            echo '</div>';
            return;
        }

        echo WTB_Render::render_table( $table_id, $override_settings );
    }
}
