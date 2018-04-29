<?php
/**
 * @package itbc
 * @version 1.0
 */
/*
Plugin Name: itbc
Plugin URI: https://wordpress.org/plugins/hello-dolly/
Description: a custom plugin for sanop for sale software. develop by hooraweb.
Author: hooraweb
Version: 1.0
Author URI: http://hooraweb.com
Text Domain: Sanop
*/

function itbc_create_menu() {
	add_menu_page('tracking codes', 'شناسه ها', 'edit_posts', 'itbc', 'itbc_tracking_codes' , plugins_url('/images/icon.png', __FILE__) );
	add_submenu_page( 'itbc', 'import tracking code', 'درون ریزی شناسه‌ها', 'edit_posts', 'itbc_import', 'itbc_import_tacking_code' );
}
add_action('admin_menu', 'itbc_create_menu');

function itbc_tracking_codes() {
	global $wpdb;
	$types = $wpdb->get_results( 'SELECT DISTINCT type, type_id FROM '.$wpdb->prefix.'itbc ' );
	$page_count = 100;
	$current_page = $_GET['paged']? $_GET['paged']: 1;
	$type = $_GET['type']? $_GET['type']: 0;
	$where = 'WHERE 1=1';
	if ($_GET['search']) $where .= ' and tracking_code LIKE "%'.$_GET['search'].'%"';
	if ($_GET['type']) $where .= ' and type_id = '.$_GET['type'];
	$results = $wpdb->get_results( 'SELECT * FROM '.$wpdb->prefix.'itbc '. $where .' LIMIT '.$page_count.' OFFSET '.(($current_page-1)*$page_count), OBJECT );
	$count = $wpdb->get_var( 'SELECT COUNT(*) FROM '.$wpdb->prefix.'itbc '. $where );
	$freecount = $wpdb->get_var( 'SELECT COUNT(*) FROM '.$wpdb->prefix.'itbc '. $where . ' and order_id = 0 ' );
	$pages = ceil($count/$page_count);
	?>
	<h1>شناسه ها</h1>
	<p>
		تعداد کل شناسه ها : <?= $count ?><br/>
		تعداد شناسه های فروش رفته: <?= $count - $freecount ?><br/>
		تعداد شناسه های آزاد: <?= $freecount ?><br/>
	</p>
	<form method="get" >
		<input type="hidden" name="page" value="itbc">
		<label for="search">جستجو: </label>
		<input type="text" name="search" value="<?= $_GET['search'] ?>" >
		<label for="paged">صفحه: </label>
		<select name="paged">
			<?php for ($i=1; $i<=$pages; $i++): ?>
				<option value="<?= $i ?>" <?php if($current_page==$i) echo 'selected' ?>><?= $i ?></option>
			<?php endfor; ?>
		</select>
		<label for="type">نوع آزمون: </label>
		<select name="type">
			<option value="0">all</option>
			<?php foreach ($types as $key => $value): ?>
				<option value="<?= $value->type_id ?>" <?php if($type==$value->type_id) echo 'selected' ?>><?= $value->type ?></option>
			<?php endforeach; ?>
		</select>
		<input type="Submit" value="فیلتر" name="action" class="button button-primary button-large">
		<?php if($itbc_import_index < $excel->sheets[0]['numRows']): ?>
			<input type="Submit" value="ورود" name="action" class="button button-primary button-large">
		<?php endif; ?>
	</form>
	<br/>
	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<td></td>
				<td>id</td>
				<td>کد شناسه</td>
				<td>کد نوع</td>
				<td>نوع آزمون</td>
				<td>کد سفارش</td>
				<td>کد محصول</td>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($results as $key => $tcod):  ?>
				<tr>
					<td><?= $key+1 ?></td>
					<td><?= $tcod->id ?></td>
					<td><?= $tcod->tracking_code ?></td>
					<td><?= $tcod->type_id ?></td>
					<td><?= $tcod->type ?></td>
					<?php if($tcod->order_id): ?>
						<td><a href="/wp-admin/post.php?post=<?= $tcod->order_id ?>&action=edit"><?= $tcod->order_id ?></a></td>
						<td>
							<a href="#"><?= get_the_title( $tcod->product_id ) ?></a>
						</td>
					<?php else: ?>
						<td>-</td>
						<td>-</td>
					<?php endif; ?>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php
}

function itbc_import_tacking_code() {
	include 'excel_reader.php';     // include the class
	$itbc_import_index = get_option('itbc_import_index');
	$itbc_import_fail = get_option('itbc_import_fail');
	$itbc_import_success = get_option('itbc_import_success');
	$itbc_import_duplicate = get_option('itbc_import_duplicate');
	if ($_POST['action'] == 'حذف فایل') {
		unlink(plugin_dir_path( __FILE__ ). 'importdata');
		echo 'فایل با موفقیت حذف گردید.';
	}
	if ($_POST['action'] == 'ورود') {
		$excel = new PhpExcelReader;
		$excel->read(dirname(__FILE__).'/importdata');
		global $wpdb;
		$process = 0;
		$end_point = (($excel->sheets[0]['numRows']-$itbc_import_index)<1000)? $excel->sheets[0]['numRows']-$itbc_import_index : 1000;
		while ($process < $end_point) {
			$tracking_code = $excel->sheets[0]['cells'][$itbc_import_index+$process];
			$results = $wpdb->get_results( 'SELECT * FROM '.$wpdb->prefix.'itbc WHERE tracking_code = "'.$tracking_code[1].'"', OBJECT );
			if($results) {
				$itbc_import_duplicate ++;
			}else {
				$inserted = $wpdb->insert(
					'wp_itbc',
					array(
						'tracking_code' => $tracking_code[1],
						'type_id' => $tracking_code[2],
						'type' => $tracking_code[3],
					),
					array(
						'%s',
						'%d',
						'%s'
					)
				);
				if ($inserted) {
					$itbc_import_success ++;
				}else{
					$itbc_import_fail ++;
				}
			}

			$process ++;
		}
		$itbc_import_index += $process;
		update_option( 'itbc_import_index', $itbc_import_index );
		update_option( 'itbc_import_fail', $itbc_import_fail );
		update_option( 'itbc_import_success', $itbc_import_success );
		update_option( 'itbc_import_duplicate', $itbc_import_duplicate );
	}
	if ($_FILES['field1']['name']) {
		$allowed_filetypes = array('.xls', '.xlsxk'); // These will be the types of file that will pass the validation.
		$max_filesize = 55524288; // Maximum filesize in BYTES (currently 0.5MB).
		$upload_path = plugin_dir_path( __FILE__ );
		$filename = $_FILES['field1']['name']; // Get the name of the file (including file extension).
		$ext = substr($filename, strpos($filename,'.'), strlen($filename)-1); // Get the extension from the filename.
		// Check if the filetype is allowed, if not DIE and inform the user.
		if(!in_array($ext,$allowed_filetypes))
			die('The file you attempted to upload is not allowed.');
		// Now check the filesize, if it is too large then DIE and inform the user.
		if(filesize($_FILES['field1']['tmp_name']) > $max_filesize)
			die('The file you attempted to upload is too large.');
		// Check if we can upload to the specified path, if not DIE and inform the user.
		if(!is_writable($upload_path))
			die('You cannot upload to the specified directory, please CHMOD it to 777.');
		// Upload the file to your specified path.
		if(move_uploaded_file($_FILES['field1']['tmp_name'],$upload_path . 'importdata')){
			echo 'فایل شما با موفقیت بارگذاری شد.'; // It worked.
			update_option( 'itbc_import_index', 1 );
			update_option( 'itbc_import_fail', 0 );
			update_option( 'itbc_import_success', 0 );
			update_option( 'itbc_import_duplicate', 0 );
			$itbc_import_index = 1;
			$itbc_import_fail = 0;
			$itbc_import_success = 0;
			$itbc_import_duplicate = 0;
		}else{
			echo 'اختلال در بارگذاری. لطفا دوباره تلاش کنید.'; // It failed :(.
		}
	}
	if ( is_file(dirname(__FILE__).'/importdata') ):
		//http://coursesweb.net/php-mysql/read-excel-file-data-php_pc
		$excel = new PhpExcelReader;
		$excel->read(dirname(__FILE__).'/importdata');
		// print_r($excel->sheets[0]);
		?>
		<div class="wrap">
			<h1>فایل برای ورود آماده است</h1>
			<p>
				مجموع رکورد های فایل : <?= $excel->sheets[0]['numRows'] ?><br/>
				تعداد رکورد های پردازش شده: <?= $itbc_import_index-1 ?><br/>
				تعداد رکورد های وارد شده موفق: <?= $itbc_import_success ?><br/>
				تعداد رکوردهای تکراری: <?= $itbc_import_duplicate ?><br/>
				تعداد رکوردهای ناموفق: <?= $itbc_import_fail ?>
			</p>
			<form method="post" >
				<input type="Submit" value="حذف فایل" name="action" class="button button-primary button-large">
				<?php if($itbc_import_index < $excel->sheets[0]['numRows']): ?>
					<input type="Submit" value="ورود" name="action" class="button button-primary button-large">
				<?php endif; ?>
			</form>
			<br/>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<td>کد شناسه</td>
						<td>کد نوع</td>
						<td>نوع آزمون</td>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($excel->sheets[0]['cells'] as $key => $tcod):  ?>
						<tr>
							<td><?= $tcod[1] ?></td>
							<td><?= $tcod[2] ?></td>
							<td><?= $tcod[3] ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	else:
		?>
		<div class="wrap">
			<h1>فایلی برای درون ریزی موجود نیست</h1>
			<br/>
			<p>برای درون ریزی میتوانید فایل با پسوند xls بارگذاری کنید.</p>
			<br/>
			<form method="post" enctype="multipart/form-data"  >
				<input type="file" name="field1">
				<br/><br/>
				<input type="Submit" value="بارگذاری" class="button button-primary button-large">
			</form>
		</div>
		<?php
	endif;
}

register_activation_hook( __FILE__, 'itbc_create_db' );
function itbc_create_db() {
	global $wpdb;
	$charset_collate = $wpdb->get_charset_collate();
	$table_name = $wpdb->prefix . 'itbc';
	$sql = "CREATE TABLE $table_name (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		tracking_code varchar(12) DEFAULT '' NOT NULL,
		type_id smallint(5) NOT NULL,
		type varchar(20) DEFAULT '' NOT NULL,
		order_id  bigint(20),
		product_id  bigint(20),
		PRIMARY KEY  (id)
	) $charset_collate;";
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );
}

// function wdm_my_custom_notes_on_single_order_page($order){
// 	$category_array=array();
// 	foreach( $order->get_items() as $item_id => $item ) {
// 		$product_id=$item['product_id'];
// 		$product_cats = wp_get_post_terms( $product_id, 'product_cat' );
// 		foreach( $product_cats as $key => $value ){
// 			if(!in_array($value->name,$category_array)){
// 				array_push($category_array,$value->name);
// 			}
// 		}
// 	}
// 	$note = '<b>Categories of products in this Order : </b>'.implode(' , ',$category_array);
// echo $note;
// $order->add_order_note( $note );
// }
// add_action( 'woocommerce_order_details_after_order_table', 'wdm_my_custom_notes_on_single_order_page',10,1 );


// function zzzz($item_data, $cart_item){
// 	echo 'xxxxxxxxxxxxx';
// 	die();
// }
// add_action('woocommerce_get_item_data', 'zzzz');


function itbc_show_tc($html, $item, $args){
	global $wpdb;
	$order_id = explode('/', $_SERVER['REQUEST_URI']);
	$order_id = $order_id[3];
	$item_data = $item->get_data();
	$product_id = $item_data['product_id'];
	$results = $wpdb->get_results( 'SELECT * FROM '.$wpdb->prefix.'itbc WHERE order_id = '.$order_id.' and product_id = '. $product_id, OBJECT );
	$display = ( count($results) > 1 )? "<div>شناسه ها: </div>" : "<div>شناسه: </div>";
	foreach ($results as $key => $result){
		$display .= "<div>$result->tracking_code</div>";
	}
// 	echo "<div>xxx";
// 	print_r( $results );
// 	echo "</div>";
	
	return $display;
}
add_filter( 'woocommerce_display_item_meta', 'itbc_show_tc',10,3 );

// function foobar_func( $atts ){
	// print_r($atts);
	// return "foo and bar";
// }
// add_shortcode( 'itbc', 'foobar_func' );

function itbc_order_completed( $order_id ) {
	global $wpdb;
	$order = new WC_Order( $order_id );
	$items = $order->get_items();
	$products = array();
	foreach ($items as $key => $item) {
		$item_data = $items[$key]->get_data();
		$product = wc_get_product( $item_data['product_id'] );
		$quantity = $item_data['quantity'];
		$test_tracking_codes = woocommerce_get_product_terms($product->id, 'pa_test_tracking_code');
		if($test_tracking_codes){
			$results = $wpdb->get_results( 'SELECT * FROM '.$wpdb->prefix.'itbc WHERE order_id = '.$order_id.' and product_id = '. $product->id, OBJECT );
			$quantity -= count($results);
			$wpdb->query( 
				$wpdb->prepare(
					"update wp_itbc set order_id = %d, product_id = %d WHERE order_id IS NULL AND product_id IS NULL LIMIT %d",
					$order_id,
					$product->id,
					$quantity
				)
			);
		}
	}
}
add_action( 'woocommerce_payment_complete', 'itbc_order_completed', 10, 1 );
// add_action( 'woocommerce_order_status_completed', 'itbc_order_completed', 10, 1 );
?>
