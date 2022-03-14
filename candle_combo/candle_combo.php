<?php

define("COMBO_LABEL", "Combo type");
define("NO_COMBO_VALUE", "Necklace / bracelet alone");
define("PRODUCTS_WITH_ADDONS",
  array( 21410, 21409, 21407, 21406, 21405, 21404, 21402, 21401 )
);
define("PRICE_ADJUST", 10);
define("ADDON_ITEMS",
  array(
    array(
      "name" => "With green candle",
      "product_id" => 21413
    ),
    array(
      "name" => "With red candle",
      "product_id" => 21415
    )
  )
);

add_action('wp_enqueue_scripts', 'addon_items_scripts');
function addon_items_scripts() {
  wp_enqueue_style( 'addon-items-style', plugins_url('addon_items.css', __FILE__), array(), "v0.01" );

  global $post;
  $form = addon_get_form($post->ID);
  if (is_null($form)) {
    return;
  }

  wp_register_script( "addon-items-js", plugins_url('addon_items.js', __FILE__), array('jquery'), "v0.02", true );
  wp_localize_script( 'addon-items-js', 'addon', array( 'formName' => $form->name ) );
  wp_enqueue_script( 'addon-items-js' );
}

add_filter('woocommerce_add_cart_item_data', 'addon_items_remove_no_addon', 20, 3);
function addon_items_remove_no_addon($cart_item_data, $product_id, $variation_id) {
  if (!isset($cart_item_data[WCPA_CART_ITEM_KEY])) {
    return $cart_item_data;
  }

  foreach ($cart_item_data['wcpa_data'] as $k => $data) {
    if ($data['label'] !== COMBO_LABEL) {
      continue;
    }

    if (strtolower($data['value']) === strtolower(NO_COMBO_VALUE)) {
      unset($cart_item_data['wcpa_data'][$k]);
    }
  }

  return $cart_item_data;
}

add_action( 'woocommerce_add_to_cart', 'addon_items_add_to_cart', 10, 6 );
function addon_items_add_to_cart( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
  if (!isset($cart_item_data['wcpa_data'])) {
    return;
  }

  foreach ($cart_item_data['wcpa_data'] as $data) {
    if ($data['label'] !== COMBO_LABEL) {
      continue;
    }

    foreach (ADDON_ITEMS as $addon) {
      if ($data['value'] !== $addon['name']) {
        continue;
      }

      if (false !== WC()->cart->add_to_cart($addon["product_id"], $quantity, 0, array(), array( 'addon_parent' => $cart_item_key ))) {
        $product = wc_get_product($addon['product_id']);
        wc_add_notice($product->get_name() . " has been added to your cart.");
      } else {
        WC()->cart->remove_cart_item($cart_item_key);
        wc_clear_notices();
        throw new Exception("Unable to add the items as " . $addon["name"] . " is out of stock.");
      }
    }
  }
}

add_filter( 'woocommerce_get_price_html', 'addon_items_price_variants', 20, 2);
function addon_items_price_variants($price, $product) {
  $addon_form = addon_get_form($product->get_id());

  if (is_null($addon_form)) {
    return $price;
  }

  $regular_price = $product->get_regular_price();

  foreach (ADDON_ITEMS as $addon) {
    $addon_product = wc_get_product($addon['product_id']);
    $addon_slug = strtolower(str_replace(" ", "-", $addon['name']));
    $addon_prices[$addon_slug] = array(
      "regular"    => $regular_price + $addon_product->get_regular_price(),
      "discounted" => $regular_price + $addon_product->get_regular_price() - PRICE_ADJUST
    );
  }

  ob_start();
  ?>
  <span class="woocommerce-Price-amount amount no-addon">
    <bdi><span class="woocommerce-Price-currencySymbol">
      RM</span><?php echo $regular_price; ?>
    </bdi>
  </span> 

  <?php foreach ($addon_prices as $slug => $p) { ?>
    <del aria-hidden="true" class="hidden <?php echo $slug; ?>">
      <span class="woocommerce-Price-amount amount">
        <bdi><span class="woocommerce-Price-currencySymbol">
          RM</span><?php echo $p['regular']; ?>
        </bdi>
      </span>
    </del>
    <ins class="hidden <?php echo $slug; ?>">
      <span class="woocommerce-Price-amount amount">
        <bdi><span class="woocommerce-Price-currencySymbol">
          RM</span><?php echo $p['discounted']; ?>
        </bdi>
      </span>
    </ins>
  <?php } ?>

  <?php return ob_get_clean();
}

add_action( 'woocommerce_remove_cart_item', 'addon_item_remove_from_cart', 10, 2 );
function addon_item_remove_from_cart( $cart_item_key, $cart ) {
  $cart_item = $cart->get_cart_contents()[$cart_item_key];

  if (isset($cart_item['addon_parent'])) {
    $parent = $cart_item['addon_parent'];
    $parent_content = $cart->get_cart_contents()[$parent];

    if (!empty($parent_content)) {
      $parent_id = $parent_content['product_id'];
      $parent_quantity = $parent_content['quantity'];
      $parent_var_id = $parent_content['variation_id'];
      $parent_var = $parent_content['variation'];

      // remove addon info from parent form
      $parent_form = $parent_content['wcpa_data'];
      foreach ($parent_form as $k => $form) {
        if ($form['label'] === COMBO_LABEL) {
          unset($parent_form[$k]);
        }
      }
      $new_item_data = count($parent_form) === 0 ? [] : [ 'wcpa_data' => $parent_form ];

      $cart->remove_cart_item($parent);
      remove_filter('woocommerce_add_cart_item_data', array(WCPAFront(), 'add_cart_item_data'), 10);
      $cart->add_to_cart($parent_id, $parent_quantity, $parent_var_id, $parent_var, $new_item_data);
    }
  }
}

function addon_get_form($product_id) {
  $form = new WCPA_Form();
  $form_ids = $form->get_form_ids($product_id);

  if (empty($form_ids)) {
    return null;
  }

  foreach ($form_ids as $id) {
    $json_string = get_post_meta($id, WCPA_FORM_META_KEY, true);
    $json_encoded = json_decode($json_string);
    if ($json_encoded[0]->label === COMBO_LABEL) {
      return $json_encoded[0];
    }
  }

  return null;
}
