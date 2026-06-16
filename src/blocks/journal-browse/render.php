<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$show_course_filter = isset( $attributes['showCourseFilter'] ) ? ( $attributes['showCourseFilter'] ? '1' : '0' ) : '1';
$show_lesson_filter = isset( $attributes['showLessonFilter'] ) ? ( $attributes['showLessonFilter'] ? '1' : '0' ) : '1';
$show_print         = isset( $attributes['showPrint'] ) ? ( $attributes['showPrint'] ? '1' : '0' ) : '1';
$show_save          = isset( $attributes['showSave'] ) ? ( $attributes['showSave'] ? '1' : '0' ) : '1';
$button_style       = ( $attributes['buttonStyle'] ?? 'icons' ) === 'text' ? 'text' : 'icons';

$shortcode = sprintf(
	'[ldj_journal_browse show_course_filter="%s" show_lesson_filter="%s" show_print="%s" show_save="%s" button_style="%s"]',
	$show_course_filter,
	$show_lesson_filter,
	$show_print,
	$show_save,
	$button_style
);

echo do_shortcode( $shortcode );
