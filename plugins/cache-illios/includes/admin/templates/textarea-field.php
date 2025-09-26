<?php
if (!defined('ABSPATH')) exit;

$id = $args['id'] ?? '';
$name = $args['name'] ?? '';
$value = $args['value'] ?? '';
$description = $args['description'] ?? '';
$disabled = isset($args['disabled']) && $args['disabled'] ? 'disabled' : '';
$class = $args['class'] ?? 'class="large-text"';
$rows = $args['rows'] ?? 5;
?>

<textarea id="<?php echo esc_attr($id); ?>" 
          name="<?php echo esc_attr($name); ?>" 
          rows="<?php echo esc_attr($rows); ?>"
          <?php echo $class; ?>
          <?php echo $disabled; ?>><?php echo esc_textarea($value); ?></textarea>
<?php if ($description) echo '<p class="description">' . wp_kses_post($description) . '</p>'; ?>