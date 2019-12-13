<?php
/*
 * Plugin Name: Custom table Plugin
 * Text Domain: customtableplugin
 * Plugin URI: https://www.wisetr.com
 * Author: Deepak Kumar
 * Author URI: https://www.wisetr.com
 * Description: Deepak kumar progress second assignment for fill checkout page billing and shipping address by google api
 * Version: 1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}
include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

if ( is_admin() ) {
	new custom_wp_list_table();
}


final class custom_wp_list_table {

	public function __construct() {
		if ( ! is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
			add_action( 'admin_init', array( $this, 'woocommerce_active' ) );

			return;
		}
		add_action( 'admin_menu', array( $this, 'add_menu_custom_table_page' ) );

	}

	public function woocommerce_active() {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error">This plugin requires WooCommerce plugin in order to run. Kindly install it.</div>';
		} );
	}

	public function add_menu_custom_table_page() {
		add_menu_page( 'Custom List Table', 'Custom List Table', 'manage_options', 'custom-list-table', array( $this, 'create_table' ) );
	}


	public function create_table() {
		$listTable = new custom_list_table();
		$listTable->prepare_items();
		?>
		<div class="wrap">
			<div id="icon-users" class="icon32"></div>
			<h2>Product Table Page</h2>
			<?php $listTable->views(); ?>
			<form method="post">
				<?php
				$listTable->search_box( 'Search', 'search' );
				$listTable->prepare_items();
				$listTable->display();
				?>
			</form>
		</div>
		<?php
	}
}

/**
 * Create a new table class that will extend the WP_List_Table
 */

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class custom_list_table extends WP_List_Table {

	public $per_page = 10;

	public function prepare_items() {
		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();
		$data     = $this->table_data();
		usort( $data, array( &$this, 'sort_data' ) );
		$currentPage = $this->get_pagenum();
		$totalItems  = count( $data );
		$this->set_pagination_args( array(
			'total_items' => $totalItems,
			'per_page'    => $this->per_page
		) );
		$data                  = array_slice( $data, ( ( $currentPage - 1 ) * $this->per_page ), $this->per_page );
		$this->_column_headers = array( $columns, $hidden, $sortable );
		$this->items           = $data;
	}

	protected function get_views() {
		$views = array();
		$current = ( !empty($_REQUEST['stock']) ? $_REQUEST['stock'] : 'all');

		//All link
		$class = ($current == 'all' ? ' class="current"' :'');
		$all_url = remove_query_arg('stock');
		$views['all'] = "<a href='".$all_url."' ".$class." >All</a>";

		//in stock product show
		$in_url = add_query_arg('stock','instock');
		$class = ($current == 'recovered' ? ' class="current"' :'');
		$views['recovered'] = "<a href='".$in_url."' ".$class." >In Stock</a>";

		//out stock product show
		$out_url = add_query_arg('stock','outofstock');
		$class = ($current == 'abandon' ? ' class="current"' :'');
		$views['abandon'] = "<a href='".$out_url."' ".$class." >Out Stock</a>";

		return $views;
	}


	public function no_items() {
		esc_html_e( 'No project found.', 'customtableplugin' );
	}

	/**
	 * @param object $item
	 *
	 * @return string|void
	 */
	function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="product_id[]" value="%s" />', $item['id'] );
	}

	function column_stock( $item ) {
		return $stock = ( $item['stock'] == 'outofstock' ? 'Out Of Stock' : 'Stock' );
	}
	/**
	 * @return array
	 */
	public function get_columns() {
		$columns = array(
			'cb'         => '<input type="checkbox" />',
			'title'      => __( 'Title', 'customtableplugin' ),
			'sku'        => __( 'SKU', 'customtableplugin' ),
			'stock'      => __( 'Stock', 'customtableplugin' ),
			'categories' => __( 'Categories', 'customtableplugin' )
		);

		return $columns;
	}

	/**
	 * @return array
	 */
	public function get_sortable_columns() {
		$sortable_columns = array(
			'title' => array( 'title', false ),
			'sku'   => array( 'sku', false )
		);

		return $sortable_columns;
	}

	/**
	 * @return array
	 */

	private function table_data() {
		$data = array();
		$args = array(
			'limit'   => - 1,
			'orderby' => 'id',
			'order'   => 'ASC',
			'return'  => 'ids',
		);

		if ( isset( $_POST['cat_sel'] ) && $_POST['cat_sel'] != '' ) {
			$args['tax_query'] = array(
				'relation' => 'AND',
				array(
					'taxonomy' => 'product_cat',
					'field'    => 'term_id',
					'terms'    => $_POST['cat_sel'],
					'operator' => 'IN',
				),
			);
		}

		if ( isset($_GET['stock']) && $_GET['stock'] != '' ) {
			$campare = ($_GET['stock'] == 'outofstock' ? 'IN' :'NOT IN');
			$args['tax_query'] = array(
				'relation' => 'AND',
				array(
					'taxonomy' => 'product_visibility',
					'field'    => 'term_id',
					'terms'    => 'outofstock',
					'operator'  => $campare,
				),
			);
		}

		$query = new WC_Product_Query( $args );

		$ids = $query->get_products();
		foreach ( $ids as $id ) {
			$product       = new WC_Product( $id );
			$product_title = '<a href="' . get_edit_post_link( $id, 'display' ) . '">' . $product->get_title() . '</a>';

			if ( isset( $_POST['s'] ) && $_POST['s'] != '' ) {

				$title  = strtolower( $product->get_title() );
				$sku    = strtolower( $product->get_sku() );
				$search = strtolower( $_POST['s'] );

				if ( strpos( $title, $search ) !== false || strpos( $sku, $search ) !== false ) {
					$data[] = array(
						'id'         => $id,
						'title'      => $product_title,
						'sku'        => $product->get_sku(),
						'stock'      => $product->get_stock_status(),
						'categories' => wc_get_product_category_list( $id )
					);
				}
			} else {
				$data[] = array(
					'id'         => $id,
					'title'      => $product_title,
					'sku'        => $product->get_sku(),
					'stock'      => $product->get_stock_status(),
					'categories' => wc_get_product_category_list( $id )
				);
			}
		}

		return $data;
	}

	/**
	 * @param object $item
	 * @param string $column_name
	 *
	 * @return string|true|void
	 */
	public function column_default( $item, $column_name ) {

		switch ( $column_name ) {
			case 'id':
			case 'title':
			case 'sku':
			case 'stock':
			case 'categories':
				return $item[ $column_name ];
			default:
				return print_r( $item, true );
		}
	}

	/**
	 * @param $a
	 * @param $b
	 *
	 * @return int|lt
	 */
	private function sort_data( $a, $b ) {
		$orderby = 'title';
		$order   = 'asc';
		if ( ! empty( $_GET['orderby'] ) ) {
			$orderby = $_GET['orderby'];
		}
		if ( ! empty( $_GET['order'] ) ) {
			$order = $_GET['order'];
		}
		$result = strcmp( $a[ $orderby ], $b[ $orderby ] );
		if ( $order === 'asc' ) {
			return $result;
		}

		return - $result;
	}

	public function search_box( $text, $input_id ) {
		?>
		<p class="search-box">
			<label class="screen-reader-text" for="<?php echo $input_id ?>"><?php echo $text; ?>:</label>
			<input type="search" id="<?php echo $input_id ?>" name="s" value="<?php _admin_search_query(); ?>"/>
			<?php submit_button( $text, 'button', false, false, array( 'id' => 'search-submit' ) ); ?>
		</p>
		<?php
	}

	public function extra_tablenav( $which ) {
		if ( $which == "top" ) :
			$cat_tax_terms = get_terms( 'product_cat', array( 'hide_empty' => false ) );
			$custom_opt    = true;
			echo '<div class="alignleft actions customfiltercat">';
			echo '<select name="cat_sel" id="cat-sel" class="postform">';
			foreach ( $cat_tax_terms as $tax_term ) {
				$selected = ! empty( $_POST['cat_sel'] ) && $tax_term->term_id == $_POST['cat_sel'] ? ' selected="selected" ' : '';
				if ( $custom_opt == true ) {
					echo '<option value="" selected="selected">Select a category</option>';
				}
				if ( $tax_term->name != 'Uncategorized' ) {
					echo '<option value="' . $tax_term->term_id . '" ' . $selected . '>' . $tax_term->name . '</option>';
				}
				$custom_opt = false;
			}
			echo '</select>';
			submit_button( __( 'Filter', 'customtableplugin' ), 'action', 'custom_filter', false );
			echo '</div>';
		endif;
	}
}

?>