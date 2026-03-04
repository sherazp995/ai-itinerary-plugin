<?php
if (!defined('ABSPATH')) exit;
/**
 * Public share page for a generated itinerary.
 * Vars in scope: $itinerary (row), $itinerary_data (decoded JSON).
 */

$site_name = get_bloginfo('name');
$site_url = home_url('/');
$primary = get_option('aip_primary_color', '#2271b1');
$secondary = get_option('aip_secondary_color', '#135e96');
$affiliates = AIP_Travelpayouts::get_links($itinerary->destination);
$ics_url = rest_url('aip/v1/itinerary/' . $itinerary->id . '/ics?token=' . $itinerary->share_token);

$og_title = esc_attr($itinerary->title);
$og_desc = esc_attr($itinerary_data['summary'] ?? sprintf('%d-day itinerary for %s', $itinerary->days, $itinerary->destination));
?><!DOCTYPE html>
<html lang="<?php echo esc_attr(get_locale()); ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html($itinerary->title); ?> · <?php echo esc_html($site_name); ?></title>
<meta name="description" content="<?php echo $og_desc; ?>">
<meta property="og:title" content="<?php echo $og_title; ?>">
<meta property="og:description" content="<?php echo $og_desc; ?>">
<meta property="og:type" content="article">
<meta name="twitter:card" content="summary">
<link rel="canonical" href="<?php echo esc_url(home_url('/aip-itinerary/' . $itinerary->share_token . '/')); ?>">
<style>
  :root { --primary: <?php echo esc_attr($primary); ?>; --secondary: <?php echo esc_attr($secondary); ?>; }
  * { box-sizing: border-box; }
  body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; line-height: 1.55; color: #222; background: #f7f8fa; margin: 0; }
  .aip-share-wrap { max-width: 820px; margin: 0 auto; padding: 32px 20px 80px; }
  .aip-share-hero { background: linear-gradient(135deg, var(--primary), var(--secondary)); color: #fff; padding: 40px 32px; border-radius: 16px; margin-bottom: 28px; }
  .aip-share-hero h1 { margin: 0 0 8px; font-size: 28px; }
  .aip-share-hero .meta { opacity: .9; font-size: 15px; }
  .aip-share-hero .summary { margin-top: 14px; font-size: 16px; opacity: .95; }
  .aip-share-actions { display: flex; flex-wrap: wrap; gap: 10px; margin: 20px 0 28px; }
  .aip-btn { display: inline-flex; align-items: center; gap: 6px; padding: 10px 16px; border-radius: 999px; background: #fff; color: var(--primary); text-decoration: none; font-weight: 600; font-size: 14px; border: 1px solid #e4e7eb; transition: transform .1s; }
  .aip-btn:hover { transform: translateY(-1px); }
  .aip-btn--primary { background: var(--primary); color: #fff; border-color: transparent; }
  .aip-day { background: #fff; border: 1px solid #e4e7eb; border-radius: 12px; padding: 22px 24px; margin-bottom: 16px; }
  .aip-day h2 { margin: 0 0 14px; font-size: 20px; color: var(--primary); }
  .aip-activity { padding: 12px 0; border-top: 1px solid #eef0f3; }
  .aip-activity:first-child { border-top: 0; padding-top: 0; }
  .aip-activity .time { display: inline-block; background: #f1f3f5; color: #4a5158; padding: 2px 10px; border-radius: 999px; font-size: 13px; font-weight: 600; margin-right: 8px; }
  .aip-activity h3 { display: inline; margin: 0; font-size: 16px; }
  .aip-activity p { margin: 6px 0 0; color: #4a5158; font-size: 14px; }
  .aip-meta-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px; margin-top: 12px; font-size: 13px; color: #4a5158; }
  .aip-meta-grid > div { background: #f7f8fa; padding: 8px 10px; border-radius: 8px; }
  .aip-sidebar { background: #fff; border: 1px solid #e4e7eb; border-radius: 12px; padding: 22px 24px; margin-top: 20px; }
  .aip-sidebar h2 { margin: 0 0 12px; font-size: 18px; color: var(--primary); }
  .aip-sidebar ul { margin: 0; padding-left: 20px; }
  .aip-sidebar li { margin: 4px 0; }
  .aip-aff { display: flex; flex-wrap: wrap; gap: 10px; }
  .aip-aff a { padding: 10px 16px; border-radius: 10px; background: var(--primary); color: #fff; text-decoration: none; font-size: 14px; font-weight: 600; }
  .aip-footer { margin-top: 40px; padding-top: 20px; border-top: 1px solid #e4e7eb; text-align: center; color: #6b7280; font-size: 13px; }
  .aip-footer a { color: var(--primary); text-decoration: none; font-weight: 600; }
  @media (max-width: 600px) { .aip-meta-grid { grid-template-columns: 1fr; } .aip-share-hero { padding: 28px 22px; } }
</style>
</head>
<body>
<div class="aip-share-wrap">

  <div class="aip-share-hero">
    <h1><?php echo esc_html($itinerary->title); ?></h1>
    <div class="meta"><?php printf(esc_html__('%1$d days · %2$s', 'ai-itinerary'), (int) $itinerary->days, esc_html($itinerary->destination)); ?></div>
    <?php if (!empty($itinerary_data['summary'])) : ?>
      <div class="summary"><?php echo esc_html($itinerary_data['summary']); ?></div>
    <?php endif; ?>
  </div>

  <div class="aip-share-actions">
    <a class="aip-btn aip-btn--primary" href="<?php echo esc_url($ics_url); ?>">
      <?php esc_html_e('Add to calendar (.ics)', 'ai-itinerary'); ?>
    </a>
    <a class="aip-btn" href="<?php echo esc_url($site_url); ?>">
      <?php esc_html_e('Plan your own trip', 'ai-itinerary'); ?>
    </a>
  </div>

  <?php foreach (($itinerary_data['itinerary'] ?? []) as $day) : ?>
    <div class="aip-day">
      <h2><?php printf(esc_html__('Day %d', 'ai-itinerary'), (int) ($day['day'] ?? 0)); ?><?php if (!empty($day['title'])) : ?> — <?php echo esc_html($day['title']); ?><?php endif; ?></h2>

      <?php foreach (($day['activities'] ?? []) as $activity) : ?>
        <div class="aip-activity">
          <?php if (!empty($activity['time'])) : ?><span class="time"><?php echo esc_html($activity['time']); ?></span><?php endif; ?>
          <h3><?php echo esc_html($activity['title'] ?? ''); ?></h3>
          <?php if (!empty($activity['description'])) : ?>
            <p><?php echo esc_html($activity['description']); ?></p>
          <?php endif; ?>
          <?php if (!empty($activity['location']) || !empty($activity['duration']) || !empty($activity['cost_estimate'])) : ?>
            <div class="aip-meta-grid">
              <?php if (!empty($activity['location'])) : ?><div>📍 <?php echo esc_html($activity['location']); ?></div><?php endif; ?>
              <?php if (!empty($activity['duration'])) : ?><div>⏱ <?php echo esc_html($activity['duration']); ?></div><?php endif; ?>
              <?php if (!empty($activity['cost_estimate'])) : ?><div>💰 <?php echo esc_html($activity['cost_estimate']); ?></div><?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>

      <?php if (!empty($day['accommodation']['name'])) : ?>
        <div class="aip-activity">
          <h3>🏨 <?php echo esc_html($day['accommodation']['name']); ?></h3>
          <p>
            <?php if (!empty($day['accommodation']['area'])) echo esc_html($day['accommodation']['area']); ?>
            <?php if (!empty($day['accommodation']['price_range'])) echo ' · ' . esc_html($day['accommodation']['price_range']); ?>
          </p>
        </div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>

  <?php if (!empty($itinerary_data['tips'])) : ?>
    <div class="aip-sidebar">
      <h2><?php esc_html_e('Tips', 'ai-itinerary'); ?></h2>
      <ul>
        <?php foreach ($itinerary_data['tips'] as $tip) : ?>
          <li><?php echo esc_html($tip); ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <?php if (!empty($affiliates)) : ?>
    <div class="aip-sidebar">
      <h2><?php esc_html_e('Book your trip', 'ai-itinerary'); ?></h2>
      <div class="aip-aff">
        <?php foreach ($affiliates as $link) : ?>
          <a href="<?php echo esc_url($link['url']); ?>" target="_blank" rel="noopener sponsored">
            <?php echo esc_html($link['label']); ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <div class="aip-footer">
    <?php printf(
      wp_kses(__('Made with %1$s on <a href="%2$s">%3$s</a>', 'ai-itinerary'), ['a' => ['href' => []]]),
      esc_html(get_option('aip_bot_name', 'Travel Buddy')),
      esc_url($site_url),
      esc_html($site_name)
    ); ?>
  </div>

</div>
</body>
</html>
