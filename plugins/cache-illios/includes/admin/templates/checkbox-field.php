<?php
if (!defined('ABSPATH')) exit;

$id = $args['id'] ?? '';
$name = $args['name'] ?? '';
$value = isset($args['value']) && $args['value'] ? 'checked' : '';
$label = $args['label'] ?? '';
$description = $args['description'] ?? '';
$disabled = isset($args['disabled']) && $args['disabled'] ? 'disabled' : '';
$class = $args['class'] ?? '';
$label_style = $disabled ? 'style="color: #999; cursor: not-allowed;"' : '';
?>

<input type="checkbox" id="<?php echo esc_attr($id); ?>" 
       name="<?php echo esc_attr($name); ?>" 
       value="1" 
       <?php echo $value; ?> 
       <?php echo $disabled; ?> 
       <?php echo $class; ?>
/>
<label for="<?php echo esc_attr($id); ?>" <?php echo $label_style; ?>><?php echo esc_html($label); ?></label>
<?php if ($description) echo wp_kses_post($description); ?>