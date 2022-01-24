<!DOCTYPE html>

<?php define('WEBPRESS_STENCIL_NAMESPACE', 'analytics-bridge'); ?>

<html dir="ltr" lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0, maximum-scale=5.0">
  <title>
    <?php echo wp_title("&middot;",true,"right"); ?>
  </title>
  <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
  <webpress-theme id="theme"></webpress-theme>

  <analytics-bridge></analytics-bridge>
  <div class='popular-posts sidebar-thing'>
	<?php	the_widget( "AnalyticBridgePopularPostWidget", array("num_posts" => 8, "title" => "Popular Posts")); ?>
	</div>

  <script>
  var element = document.getElementById('theme')
  element.global = webpress
  </script>

  <?php wp_footer(); ?>

</body>
</html>
