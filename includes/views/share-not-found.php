<?php if (!defined('ABSPATH')) exit; ?><!DOCTYPE html>
<html lang="<?php echo esc_attr(get_locale()); ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php esc_html_e('Itinerary not found', 'ai-itinerary'); ?></title>
<style>
  body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f7f8fa; color: #222; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
  .box { background: #fff; padding: 40px 32px; border-radius: 12px; max-width: 460px; text-align: center; border: 1px solid #e4e7eb; }
  h1 { margin-top: 0; color: #dc2626; }
  a { color: <?php echo esc_attr(get_option('aip_primary_color', '#2271b1')); ?>; font-weight: 600; text-decoration: none; }
</style>
</head>
<body>
<div class="box">
  <h1><?php esc_html_e('404 — Itinerary not found', 'ai-itinerary'); ?></h1>
  <p><?php esc_html_e('This shared itinerary link is invalid or has been revoked.', 'ai-itinerary'); ?></p>
  <p><a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('← Back to site', 'ai-itinerary'); ?></a></p>
</div>
</body>
</html>
