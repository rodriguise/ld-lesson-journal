<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'ldj-screen-only' ) ); ?>>
	<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</div>
