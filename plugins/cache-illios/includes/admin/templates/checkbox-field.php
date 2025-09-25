<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$field_id      = $field['id'] ?? '';
$field_name    = $field['name'] ?? '';
$field_label   = $field['label'] ?? '';
$field_desc    = $field['description'] ?? '';
$field_value   = $field['value'] ?? 0;
$field_disabled = ! empty( $field['disabled'] ) ? 'disabled' : '';
?>

<input 
    type="checkbox" 
    id="<?php echo esc_attr($field_id); ?>" 
    name="<?php echo esc_attr($field_name); ?>" 
    value="1"
    <?php checked( $field_value, 1 ); ?>
    <?php echo $field_disabled; ?>
/>
<label for="<?php echo esc_attr($field_id); ?>"><?php echo esc_html($field_label); ?></label>
<?php if ( $field_desc ) : ?>
    <p class="description"><?php echo esc_html($field_desc); ?></p>
<?php endif; ?>
