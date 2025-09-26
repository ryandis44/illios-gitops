<?php
if (!defined('ABSPATH')) exit;

$id = $args['id'] ?? '';
$name = $args['name'] ?? '';
$value = $args['value'] ?? '';
$type = $args['type'] ?? 'text';
$description = $args['description'] ?? '';
$disabled = isset($args['disabled']) && $args['disabled'] ? 'disabled' : '';
$class = $args['class'] ?? 'class="regular-text"';
$min = isset($args['min']) ? 'min="' . esc_attr($args['min']) . '"' : '';
$max = isset($args['max']) ? 'max="' . esc_attr($args['max']) . '"' : '';
?>

<input type="<?php echo esc_attr($type); ?>" 
       id="<?php echo esc_attr($id); ?>" 
       name="<?php echo esc_attr($name); ?>" 
       value="<?php echo esc_attr($value); ?>" 
       <?php echo $class; ?>
       <?php echo $disabled; ?>
       <?php echo $min; ?>
       <?php echo $max; ?>
/>
<?php if ($description) echo '<p class="description">' . wp_kses_post($description) . '</p>'; ?>