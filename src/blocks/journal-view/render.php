<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$course_id    = absint( $attributes['courseId'] ?? 0 );
$lesson_id    = absint( $attributes['lessonId'] ?? 0 );
$show_title   = ! empty( $attributes['showTitle'] ) ? '1' : '0';
$show_student = ! empty( $attributes['showStudent'] ) ? '1' : '0';
$show_print   = isset( $attributes['showPrint'] ) ? ( $attributes['showPrint'] ? '1' : '0' ) : '1';
$show_save    = isset( $attributes['showSave'] ) ? ( $attributes['showSave'] ? '1' : '0' ) : '1';
$show_refresh = isset( $attributes['showRefresh'] ) ? ( $attributes['showRefresh'] ? '1' : '0' ) : '1';
$heading       = $attributes['heading'] ?? '';
$instructions  = $attributes['instructions'] ?? '';
$show_content  = isset( $attributes['showContent'] ) ? ( $attributes['showContent'] ? '1' : '0' ) : '1';
$button_style  = ( $attributes['buttonStyle'] ?? 'icons' ) === 'text' ? 'text' : 'icons';
$show_filter   = isset( $attributes['showFilter'] ) ? ( $attributes['showFilter'] ? '1' : '0' ) : '0';

$shortcode = sprintf(
	'[ldj_journal course_id="%d" lesson_id="%d" show_title="%s" show_student="%s" show_print="%s" show_save="%s" show_refresh="%s" heading="%s" instructions="%s" show_content="%s" button_style="%s" show_filter="%s"]',
	$course_id,
	$lesson_id,
	$show_title,
	$show_student,
	$show_print,
	$show_save,
	$show_refresh,
	esc_attr( $heading ),
	esc_attr( $instructions ),
	$show_content,
	$button_style,
	$show_filter
);

echo do_shortcode( $shortcode );
