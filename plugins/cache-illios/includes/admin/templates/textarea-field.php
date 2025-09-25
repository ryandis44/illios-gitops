<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function illios_textarea_field( $args ) {
    $id = $args['id'];
    $name = $args['name'];
    $value = isset( $args['value'] ) ? esc_textarea( $args['value'] ) : '';
    $rows = isset( $args['rows'] ) ? intval( $args['rows'] ) : 5;
    $description = isset( $args['description'] ) ? $args['description'] : '';
    $disabled = isset( $args['disabled'] ) && $args['disabled'] ? 'disabled' : '';
    $class = isset( $args['class'] ) ? esc_attr( $args['class'] ) : 'large-text';

    printf(
        '<textarea id="%1$s" name="%2$s" rows="%3$d" class="%4$s" %5$s>%6$s</textarea>',
        esc_attr( $id ),
        esc_attr( $name ),
        $rows,
        $class,
        $disabled,
        $value
    );

    if ( $description ) {
        echo '<p class="description">' . esc_html( $description ) . '</p>';
    }
}
