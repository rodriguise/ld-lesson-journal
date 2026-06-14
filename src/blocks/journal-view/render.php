<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$course_id    = absint( $attributes['courseId'] ?? 0 );
$lesson_id    = absint( $attributes['lessonId'] ?? 0 );
$show_title   = ! empty( $attributes['showTitle'] ) ? '1' : '0';
$show_student = ! empty( $attributes['showStudent'] ) ? '1' : '0';
$show_print   = isset( $attributes['showPrint'] ) ? ( $attributes['showPrint'] ? '1' : '0' ) : '1';
$heading      = $attributes['heading'] ?? '';

$shortcode = sprintf(
	'[ldj_journal course_id="%d" lesson_id="%d" show_title="%s" show_student="%s" show_print="%s" heading="%s"]',
	$course_id,
	$lesson_id,
	$show_title,
	$show_student,
	$show_print,
	esc_attr( $heading )
);

echo do_shortcode( $shortcode );
