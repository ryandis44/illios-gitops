<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function illios_button( $args ) {
    $id = isset( $args['id'] ) ? esc_attr( $args['id'] ) : '';
    $text = isset( $args['text'] ) ? esc_html( $args['text'] ) : 'Button';
    $class = isset( $args['class'] ) ? esc_attr( $args['class'] ) : 'button button-secondary';
    $type = isset( $args['type'] ) ? esc_attr( $args['type'] ) : 'button';
    $disabled = isset( $args['disabled'] ) && $args['disabled'] ? 'disabled' : '';

    printf(
        '<button id="%1$s" type="%2$s" class="%3$s" %4$s>%5$s</button>',
        $id,
        $type,
        $class,
        $disabled,
        $text
    );

    if ( isset( $args['description'] ) && $args['description'] ) {
        echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
    }
}
