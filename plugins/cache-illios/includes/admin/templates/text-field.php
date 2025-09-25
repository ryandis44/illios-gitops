<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function illios_text_field( $args ) {
    $id = $args['id'];
    $name = $args['name'];
    $value = isset( $args['value'] ) ? esc_attr( $args['value'] ) : '';
    $description = isset( $args['description'] ) ? $args['description'] : '';
    $disabled = isset( $args['disabled'] ) && $args['disabled'] ? 'disabled' : '';
    $class = isset( $args['class'] ) ? esc_attr( $args['class'] ) : 'regular-text';

    printf(
        '<input type="text" id="%1$s" name="%2$s" value="%3$s" class="%4$s" %5$s />',
        esc_attr( $id ),
        esc_attr( $name ),
        $value,
        $class,
        $disabled
    );

    if ( $description ) {
        echo '<p class="description">' . esc_html( $description ) . '</p>';
    }
}
