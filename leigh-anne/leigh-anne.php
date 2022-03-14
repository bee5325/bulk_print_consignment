<?php 

/*define('CAT_ID', 116);*/

/*/***/
/* **/
/* * Activation only*/
/* **/
/* **/
/*function leighanne_custom_role() {*/
/*    if ( get_option( 'leighanne_roles_version' ) < 1 ) {*/
/*        add_role( 'leighanne_shop_manager', 'Leigh Anne shop manager', get_role('shop_manager')->capabilities );*/
/*        $role = get_role( 'leighanne_shop_manager' );*/
/*        $role->add_cap( 'edit_leighanne_items' );*/
/*        $role->remove_cap( 'view_woocommerce_reports' );*/
/*        update_option( 'custom_roles_version', 1 );*/
/*    }*/
/*}*/
/*add_action( 'admin_init', 'leighanne_custom_role' );*/

/*/***/
/* **/
/* * Limit shop manager access*/
/* **/
/* **/
/*add_action( 'admin_menu', 'leighanne_remove_menu_pages', 999 );*/
/*add_action('admin_bar_menu', 'leighanne_remove_admin_bar_nodes', 999);*/

/*function leighanne_remove_menu_pages() {*/
/*    if (current_user_can( 'edit_leighanne_items' )) {*/
/*        // remove all menus*/
/*        remove_menu_page( 'jetpack' );*/
/*        remove_menu_page( 'slimview1' );*/
/*        remove_menu_page( 'feedback' );*/
/*        remove_menu_page( 'index.php' );*/
/*        remove_menu_page( 'separator1' );*/
/*        remove_menu_page( 'upload.php' );*/
/*        remove_menu_page( 'edit-comments.php' );*/
/*        remove_menu_page( 'edit.php' );*/
/*        remove_menu_page( 'edit.php?post_type=page' );*/
/*        remove_menu_page( 'edit.php?post_type=product' );*/
/*        remove_menu_page( 'separator2' );*/
/*        remove_menu_page( 'themes.php' );*/
/*        remove_menu_page( 'plugins.php' );*/
/*        remove_menu_page( 'users.php' );*/
/*        remove_menu_page( 'tools.php' );*/
/*        remove_menu_page( 'options-general.php' );*/
/*        remove_menu_page( 'separator-last' );*/
/*        remove_menu_page( 'berocket_account' );*/
/*        remove_menu_page( 'yith_plugin_panel' );*/
/*        remove_menu_page( 'wpseo_dashboard' );*/
/*        remove_menu_page( 'woocommerce-marketing' );*/
/*        remove_menu_page( 'separator-woocommerce' );*/
/*        remove_menu_page( 'woocommerce' );*/
/*        remove_menu_page( 'pixelyoursite' );*/
/*        remove_menu_page( 'woo-product-feed-pro/woocommerce-sea.php' );*/
/*        remove_menu_page( 'wpclever' );*/
/*        remove_menu_page( 'stock-manager' );*/
/*        remove_menu_page( 'limit-login-attempts' );*/
/*        remove_menu_page( 'wc-admin&path=/analytics/overview' );*/
/*        remove_menu_page( 'wp-mail-smtp' );*/

/*        // add new menu only for leigh anne*/
/*        add_menu_page('Orders', 'Orders', 'edit_leighanne_items', 'edit.php?post_type=shop_order');*/
/*        add_menu_page('Products', 'Products', 'edit_leighanne_items', 'edit.php?post_type=product');*/
/*        remove_submenu_page('edit.php?post_type=product', 'edit-tags.php?taxonomy=product_cat&amp;post_type=product');*/
/*        remove_submenu_page('edit.php?post_type=product', 'edit-tags.php?taxonomy=product_tag&amp;post_type=product');*/
/*        remove_submenu_page('edit.php?post_type=product', 'product_attributes');*/
/*        remove_submenu_page('edit.php?post_type=product', 'edit.php?post_type=wcpa_pt_forms');*/
/*    }*/
/*}*/

/*function leighanne_remove_admin_bar_nodes($wp_admin_bar) {*/
/*    if (current_user_can( 'edit_leighanne_items' )) {*/
/*        $wp_admin_bar->remove_node('wp-logo');*/
/*        $wp_admin_bar->remove_node('site-name');*/
/*        $wp_admin_bar->remove_node('woocommerce');*/

/*        $wp_admin_bar->add_menu( array(*/
/*            'id'    => 'home-page',*/
/*            'title' => 'Home',*/
/*            'href'  => 'https://loveandscone.com',*/
/*            'meta'  => array(*/
/*                'title' => __('Home'),*/            
/*            ),*/
/*        ));*/

/*        $wp_admin_bar->add_menu( array(*/
/*            'id'    => 'leighanne-orders',*/
/*            'title' => 'Orders',*/
/*            'href'  => 'https://loveandscone.com/wp-admin/edit.php?post_type=shop_order',*/
/*            'meta'  => array(*/
/*                'title' => __('Orders'),*/            
/*            ),*/
/*        ));*/

/*        $wp_admin_bar->add_menu( array(*/
/*            'id'    => 'leighanne-products',*/
/*            'title' => 'Products',*/
/*            'href'  => 'https://loveandscone.com/wp-admin/edit.php?post_type=product',*/
/*            'meta'  => array(*/
/*                'title' => __('Products'),*/            
/*            ),*/
/*        ));*/
/*    }*/
/*}*/

/* add_action( 'pre_get_posts', 'get_only_leighanne_orders' ); */
/* function get_only_leighanne_orders( $query ) { */

/*     global $pagenow; */
/*     if (current_user_can( 'edit_leighanne_items' ) */
/*         && $query->is_admin */
/*         && $pagenow == 'edit.php' */
/*         && $_GET['post_type'] == 'shop_order' ) { */

/*         $meta_key_query = array( */
/*             array( */
/*                 'key'     => '_leighanne_order', */
/*                 'value'   => 1, */
/*                 'compare' => '=' */
/*             ) */
/*         ); */

/*         $query->set( 'meta_query', $meta_key_query ); */
/*     } */
/* } */

/*/* add_action( 'pre_get_posts', 'get_only_leighanne_products' ); */
/*/* function get_only_leighanne_products( $query ) { */
/*/*     global $pagenow; */
/*/*     if (current_user_can( 'edit_leighanne_items' ) */
/*/*         && $query->is_admin */
/*/*         && $pagenow == 'edit.php' */
/*/*         && $_GET['post_type'] == 'product' ) { */

/*/*         $taxonomy_query = array( */
/*/*             array( */
/*/*                 'taxonomy' => 'product_cat', */
/*/*                 'field'    => 'term_id', */
/*/*                 'terms'    => CAT_ID */
/*/*             ) */
/*/*         ); */

/*/*         $query->set( 'tax_query', $taxonomy_query ); */
/*/*     } */
/*/* } */

/*/* add_action( 'admin_footer', 'leigh_anne_hide_status_counts' ); */
/*/* function leigh_anne_hide_status_counts() { */
/*/*     if (current_user_can( 'edit_leighanne_items' )) { */
/*/*         echo "<style>"; */
/*/*         echo "ul.subsubsub .count { display: none }"; */
/*/*         echo ".page-title-action, .tablenav.top .actions:not(.bulkactions) { display: none }"; */
/*/*         echo "</style>"; */
/*/*     } */
/*/* } */

/*/***/
/* **/
/* * Cart operations*/
/* **/
/* **/
/*/* add_action( 'woocommerce_after_checkout_validation', 'allow_only_west_msia', 10, 2 ); */
/*/* add_action( 'woocommerce_add_to_cart', 'separate_le_las_orders', 10, 6 ); */
/*/* add_action( 'woocommerce_checkout_update_order_meta', 'mark_order_as_leighanne' ); */
/*/* add_action( 'woocommerce_process_shop_order_meta', 'mark_order_as_leighanne' ); */

/*/* function allow_only_west_msia($data, $errors) { */
/*/*     if ($data['shipping_country'] !== "MY" */
/*/*         || $data['shipping_state'] === 'SWK' */
/*/*         || $data['shipping_state'] === 'SBH') { */
/*/*         foreach (WC()->cart->get_cart_contents() as $item) { */
/*/*             if (is_leigh_anne_product($item['data'])) { */
/*/*                 $errors->add( 'shipping', "Sorry but shipping of Leigh Anne products are only supported for West Malaysia."  ); */
/*/*                 break; */
/*/*             } */
/*/*         } */
/*/*     } */
/*/* } */

/*/* function separate_le_las_orders($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) { */
/*/*     $product = wc_get_product($product_id); */

/*/*     // if L&S products already in cart, and try to add LA product */
/*/*     if (is_leigh_anne_product($product)) { */
/*/*         foreach (WC()->cart->get_cart_contents() as $item) { */
/*/*             if (!is_leigh_anne_product($item['data'])) { */
/*/*                 throw new Exception("Sorry but Leigh Anne products cannot be mixed with Love and Scone products. Please place a separate orders"); */
/*/*             } */
/*/*         } */
/*/*     } */

/*/*     // if LA products already in cart, and try to add L&S product */
/*/*     elseif (!is_leigh_anne_product($product)) { */
/*/*         foreach (WC()->cart->get_cart_contents() as $item) { */
/*/*             if (is_leigh_anne_product($item['data'])) { */
/*/*                 throw new Exception("Sorry but Leigh Anne products cannot be mixed with Love and Scone products. Please place a separate orders"); */
/*/*             } */
/*/*         } */
/*/*     } */
/*/* } */

/*/* function mark_order_as_leighanne($order_id) { */
/*/*     $order = wc_get_order($order_id); */
/*/*     foreach ($order->get_items() as $item) { */
/*/*         $product = wc_get_product($item->get_product_id()); */
/*/*         if (is_leigh_anne_product($product)) { */
/*/*             update_post_meta($order_id, '_leighanne_order', 1); */
/*/*             break; */
/*/*         } */
/*/*     } */
/*/* } */

/*/***/
/* **/
/* * Helpers*/
/* **/
/* **/
/*function is_leigh_anne_product($product) {*/
/*    $categories = $product->get_category_ids();*/
/*    return (in_array(CAT_ID, $categories));*/
/*}*/

/*function logging($message) {*/
/*    $filename = plugin_dir_path( __FILE__ ) . "/events.log";*/
/*    error_log(wp_date("ymdHis") . " - " . $message . "\n", 3, $filename );*/
/*}*/

/*?>*/
