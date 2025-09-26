<?php
if (!defined('ABSPATH')) exit;

$id = $args['id'] ?? '';
$label = $args['label'] ?? '';
$description = $args['description'] ?? '';
$disabled = isset($args['disabled']) && $args['disabled'] ? 'disabled' : '';
$class = $args['class'] ?? 'button button-secondary';
?>

<button type="button" id="<?php echo esc_attr($id); ?>" class="<?php echo esc_attr($class); ?>" <?php echo $disabled; ?>>
    <?php echo esc_html($label); ?>
</button>
<?php if ($description) echo '<p class="description">' . wp_kses_post($description) . '</p>'; ?>