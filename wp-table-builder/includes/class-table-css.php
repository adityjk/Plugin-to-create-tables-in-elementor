<?php
/**
 * Per-table inline CSS built from the settings blob.
 *
 * Deliberately separate from WTB_Table_Renderer: this class has zero
 * markup logic, it only translates sanitized settings into a scoped
 * <style> block. Structural CSS that every table shares lives in
 * assets/css/frontend.css instead.
 *
 * @package WTB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WTB_Table_Css {

	/**
	 * Style block for one table, or empty string when no visual
	 * setting is configured (callers skip printing the tag entirely).
	 */
	public static function render( $table_id, $settings ) {
		$scope  = '.wtb-table-wrap[data-table-id="'
			. absint( $table_id ) . '"] .wtb-table';
		$colors = $settings['colors'];
		$layout = $settings['layout'];

		$css = self::header_rules( $scope, $colors )
			. self::cell_rules( $scope, $colors, $layout )
			. self::stripe_rules( $scope, $colors );

		if ( '' === $css ) {
			return '';
		}

		return '<style id="wtb-table-css-' . absint( $table_id ) . '">'
			. $css . '</style>';
	}

	/**
	 * Color and padding values are interpolated without escaping
	 * functions because their format is already guaranteed by
	 * WTB_Sanitizer (hex pattern, absint); anything else was dropped
	 * before storage.
	 */
	private static function header_rules( $scope, $colors ) {
		if ( ! $colors['header_background'] && ! $colors['header_text'] ) {
			return '';
		}

		$css = $scope . ' th {';
		if ( $colors['header_background'] ) {
			$css .= 'background:' . $colors['header_background'] . ';';
		}
		if ( $colors['header_text'] ) {
			$css .= 'color:' . $colors['header_text'] . ';';
		}
		return $css . '}';
	}

	private static function cell_rules( $scope, $colors, $layout ) {
		$has_padding = $layout['cell_padding'] > 0;
		$has_border  = $layout['border_width'] > 0 && $colors['border'];

		if ( ! $has_padding && ! $has_border && ! $colors['body_text'] ) {
			return '';
		}

		$css = $scope . ' th,' . $scope . ' td {';
		if ( $has_padding ) {
			$css .= 'padding:' . $layout['cell_padding'] . 'px;';
		}
		if ( $colors['body_text'] ) {
			$css .= 'color:' . $colors['body_text'] . ';';
		}
		if ( $has_border ) {
			$css .= 'border:' . $layout['border_width'] . 'px solid '
				. $colors['border'] . ';';
		}
		return $css . '}';
	}

	private static function stripe_rules( $scope, $colors ) {
		if ( ! $colors['row_even'] && ! $colors['row_odd'] ) {
			return '';
		}

		$css = '';
		foreach ( [ 'odd', 'even' ] as $band ) {
			$key = 'row_' . $band;
			if ( $colors[ $key ] ) {
				$css .= $scope . ' tbody tr:nth-child(' . $band . ') td'
					. '{background:' . $colors[ $key ] . ';}';
			}
		}
		return $css;
	}
}
