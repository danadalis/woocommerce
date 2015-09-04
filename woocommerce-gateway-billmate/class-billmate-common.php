<?php
/**
 * Created by PhpStorm.
 * User: jesper
 * Date: 15-03-02
 * Time: 16:29
 */

class BillmateCommon {

	private $options;

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
		add_action( 'admin_init', array( $this, 'page_init' ) );
		add_action('wp_ajax_verify_credentials', array($this,'verify_credentials'));
        add_action('wp_ajax_nopriv_getaddress',array($this,'getaddress'));
        add_action('wp_ajax_getaddress',array($this,'getaddress'));
        add_action('woocommerce_checkout_before_customer_details',array($this,'get_address_fields'));
		add_filter('woocommerce_payment_successful_result',array($this,'clear_pno'));


	}

	public function clear_pno($result,$order_id = null)
	{
		if(isset($_SESSION['billmate_pno']))
			unset($_SESSION['billmate_pno']);
		return $result;

	}
    public function get_address_fields()
    {
        if(get_option('billmate_common_getaddress') == 'active'){
            ?>
            <p class="form-row">
                <label for="pno"><?php echo __('Social Security Number / Corporate Registration Number','billmate'); ?></label>
                <input type="text" name="pno" label="12345678-1235" class="form-row-wide input-text" style="width: 60%;" value="<?php echo isset($_SESSION['billmate_pno']) ? $_SESSION['billmate_pno'] : ''; ?>"/>
                <button id="getaddress"><?php echo __('Get Address','billmate'); ?></button>
            </p>
            <div id="getaddresserr"></div>
            <div class="clear"></div>
            <script type="text/javascript">
                var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
				var nopno = '<?php echo __('You have to type in Social Security number/Corporate number','billmate'); ?>';
            </script>

            <?php
        }
    }

    public function getaddress()
    {
		if(!defined('BILLMATE_CLIENT')) define('BILLMATE_CLIENT','WooCommerce:2.0');
		if(!defined('BILLMATE_SERVER')) define('BILLMATE_SERVER','2.1.7');
        $billmate = new BillMate(get_option('billmate_common_eid'),get_option('billmate_common_secret'),true,false,false);
		$_SESSION['billmate_pno'] = $_POST['pno'];
        $addr = $billmate->getAddress(array('pno' => $_POST['pno']));
        if(isset($addr['code'])) {
            $response['success'] = false;
            $response['message'] = utf8_encode($addr['message']);
        } else {
            $data = array();
            foreach($addr as $key => $value){
                $data[$key] = mb_convert_encoding($value,'UTF-8','auto');
            }
            $response['success'] = true;
            $response['data'] = $data;
        }

        die(json_encode($response));
    }

	public function page_init() {
		register_setting(
			'billmate_common', // Option group
			'billmate_common_eid', // Option name
			array( $this, 'sanitize' ) // Sanitize
		);
		register_setting(
			'billmate_common', // Option group
			'billmate_common_secret', // Option name
			array( $this, 'sanitize' ) // Sanitize
		);
        register_setting(
            'billmate_common',
            'billmate_common_getaddress',
            array($this,'sanitize')
        );
		add_settings_section(
			'setting_credentials', // ID
			__('Common Billmate Settings','billmate'), // Title
			array( $this, 'print_section_info' ), // Callback
			'billmate-settings' // Page
		);

		add_settings_field(
			'billmate_common_eid', // ID
			__('Billmate ID','billmate'), // Title
			array( $this, 'eid_callback' ), // Callback
			'billmate-settings', // Page
			'setting_credentials' // Section
		);

		add_settings_field(
			'billmate_common_secret',
			__('Secret','billmate'),
			array( $this, 'secret_callback' ),
			'billmate-settings',
			'setting_credentials'
		);
        add_settings_field(
            'billmate_common_getaddress',
            __('Get Address','billmate'),
            array($this,'getaddress_callback'),
            'billmate-settings',
            'setting_credentials'
        );
	}

	public function add_plugin_page() {
		add_options_page(
			'Billmate Common',
			__('Billmate Settings','billmate'),
			'manage_options',
			'billmate-settings',
			array( $this, 'create_admin_page' )
		);
	}

	public function eid_callback(){
		$value = get_option('billmate_common_eid','');
		echo '<input type="text" id="billmate_common_eid" name="billmate_common_eid" value="'.$value.'" />';
	}

	public function secret_callback(){
		$value = get_option('billmate_common_secret','');
		echo '<input type="text" id="billmate_common_secret" name="billmate_common_secret" value="'.$value.'" />';
	}

    public function getaddress_callback()
    {
        $value = get_option('billmate_common_getaddress','');
        $inactive = ($value == 'inactive') ? 'selected="selected"' : '';
        $active = ($value == 'active') ? 'selected="selected"' : '';
        echo '<select name="billmate_common_getaddress" id="billmate_common_getaddress">';
        echo '<option value="inactive"'.$inactive.'>'.__('Inactive','billmate').'</option>';
        echo '<option value="active"'.$active.'>'.__('Active','billmate').'</option>';
        echo '</select>';
    }

	public function print_section_info()
	{
		echo __('Here is the common settings for the Billmate Payment module','billmate');
	}

	public function sanitize($input){
		return $input;
	}
	public function create_admin_page()
	{
		// Set class property
		$this->options = get_option( 'billmate_common_settings' );
		?>
		<div class="wrap">
			<?php screen_icon(); ?>
			<h2><?php echo __('Billmate Settings','billmate'); ?></h2>
			<form method="post" action="options.php">
				<?php
				// This prints out all hidden setting fields
				settings_fields( 'billmate_common' );
				do_settings_sections( 'billmate-settings' );
				submit_button();
				?>
			</form>
		</div>
		<script type="text/javascript">
			jQuery(document).ready(function($){
				$('form').on('submit',function(e){
				var credentialStatus = false;

					$.ajax({
						url: ajaxurl,
						type: 'POST',
						async: false,
						data: {
							action:'verify_credentials',
							billmate_id: $('#billmate_common_eid').val(),
							billmate_secret: $('#billmate_common_secret').val()
						},
						success: function(response){
							var result = JSON.parse(response);

							if(result.success){
								$(this).parent('form').submit();
								credentialStatus = true;
							} else {
								alert("<?php echo __('Please, check your credentials','billmate')?>");
								credentialStatus = false;


							}
						}
					});
					if(!credentialStatus){
						e.preventDefault();
						e.stopPropagation();
						e.returnValue = false;
						return false;
					}

				})
			})

		</script>
	<?php
	}

	public function verify_credentials()
	{
		require_once 'library/Billmate.php';
		$billmate = new BillMate($_POST['billmate_id'],$_POST['billmate_secret'],true, false,false);
		$values['PaymentData'] = array(
			'currency' => 'SEK',
			'language' => 'sv',
			'country' => 'se'
		);
		$result = $billmate->getPaymentplans($values);
		$response = array();
		if(isset($result['code']) && ($result['code'] == 9013 || $result['code'] == 9010 || $result['code'] == 9012)){
			$response['success'] = false;
		}
		else{
			$response['success'] = true;
		}
		echo json_encode($response);
		wp_die();
	}
}