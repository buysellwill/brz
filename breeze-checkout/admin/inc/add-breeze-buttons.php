<?php
/**
 * Add Breeze Buttons
 *
 * @package Breeze 1-Click Checkout
 */

namespace B1CCO\Admin\Inc;

include_once __DIR__ . '/../configuration/main.php';
use B1CCO\Admin\Configuration\B1CCO_Configuration;

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

/**
 * Class B1CCO_Add_Buttons.
 */
class B1CCO_Add_Buttons
{

	/**
	 * Member Variable
	 *
	 * @var instance
	 */
	private static $instance;

	/**
	 *  Initiator
	 */
	public static function get_instance()
	{
		if (!isset(self::$instance)) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	public function __construct()
	{
		//add_action('woocommerce_after_add_to_cart_button', array($this, 'b1cco_button_on_product_page'), 0);
		add_action('woocommerce_after_cart_totals', array($this, 'b1cco_button_on_cart_page'), 0);
	//	add_action('woocommerce_review_order_before_submit', array($this, 'b1cco_button_on_cart_page'), 0);
		//add_action('woocommerce_widget_shopping_cart_after_buttons', array($this, 'b1cco_button_on_cart_drawer_page'), 0);

		// Custom button injection
		// add_action('wp-head', array($this, 'breeze_button_on_cart_drawer'), 0);
	}

	public function breeze_button_on_cart_drawer()
	{
		$nonce = wp_create_nonce('wc_store_api');
		wc_enqueue_js(
			"
			var observerMutation = false;

			function insertBreezeButton() {
				// Create a new breeze-button element
				var breezeButton = document.createElement('breeze-button');
				breezeButton.id = 'breeze-button';
				breezeButton.setAttribute('wpnonce', '" . $nonce . "');
				breezeButton.setAttribute('errortext', '');
				breezeButton.setAttribute('buynowtext', 'Buy it now');
				breezeButton.setAttribute('checkouttext', 'PROCEED TO CHECKOUT');
				breezeButton.setAttribute('buttoncolor', '#f55f1e');
				breezeButton.setAttribute('buttonradius', '0px');
				breezeButton.setAttribute('buttonhovercolor', '#e85a1c');
				breezeButton.setAttribute('showlogo', 'false');
				breezeButton.setAttribute('texthovercolor', '#ffffff');
				breezeButton.setAttribute('buttonpadding', '15px');
				breezeButton.setAttribute('fontfamily', 'Poppins');
				breezeButton.setAttribute('buttonminwidth', '0px');
				breezeButton.setAttribute('buttonfontweight', '400');
				breezeButton.setAttribute('buttonfontsize', '16px');
				breezeButton.setAttribute('letterspacing', '-0.3px');

				// Create a container for the breeze-button
				var btnContainer = document.createElement('div');
				btnContainer.id = 'breeze-button-container';
				btnContainer.style.display = 'inline-block';
				btnContainer.style.marginTop = '0px';
				btnContainer.style.width = '100%';
				btnContainer.appendChild(breezeButton);

				// Find all elements with the class 'buttons' and replace any existing breeze-button
				var elements = document.querySelectorAll('p.buttons');
				elements.forEach(function(element) {
					// Remove existing breeze-button if found
					var existingButton = element.querySelector('breeze-button');
					if (existingButton) {
						existingButton.remove();
					}
					// Append the new breeze-button
					element.appendChild(btnContainer.cloneNode(true));
				});
			}

			function observeMutation() {
				var targetElement = document.querySelector('.mcart-border');
				console.log('Target element:', targetElement);

				if (targetElement) {
					var observer = new MutationObserver(function(mutations) {
						mutations.forEach(function(mutation) {
						// Check for the presence of breeze-button
						var breezeButtonExists = targetElement.querySelector('breeze-button');
						console.log('Breeze button found:', breezeButtonExists);
						console.log(mutation.type,'type');
						console.log(mutation.addedNodes.length,'dscds');
						// Always replace the breeze-button when changes are detected
							if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
								insertBreezeButton();
							}
						});
					});

					var config = { childList: true, subtree: true }; // Observe all descendant nodes
					observer.observe(targetElement, config);
				} else {
					console.log('Target element not found');
				}
			}

			// Initialize the functions
			observeMutation();
			insertBreezeButton();
		"
		);
	}

	// create button on product page
	public function b1cco_button_on_product_page()
	{
		global $product;
		$id = $product->get_id();
		$arr = array(
			'productId' => (string) $id,
		);

		// Get button styling settings
		$b1cco_config = new B1CCO_Configuration();

		$button_settings = $b1cco_config->get_button_styling_settings();

		// Initialize default values
		$defaults = [
			'buynowtext' => __('Checkout', 'breeze-checkout'),
			'buttoncolor' => '#000000',
			'buttonhovercolor' => '#d8246c',
			'buttonborder' => '',
			'buttonhoverborder' => '',
			'buttonradius' => '3px',
			'buttonminheight' => '',
			'buttonminwidth' => '',
			'buttonfontweight' => '700',
			'buttonfontsize' => '13px',
			'buttonpadding' => '5px auto',
			'showlogo' => 'false',
			'logocolor' => 'white',
			'logohovercolor' => ''
		];

		$defaults = $this->update_button_defaults_from_settings($button_settings, $defaults, 'product');

		$nonce = wp_create_nonce('wc_store_api');
		echo '
			<div style="margin-top: 10px; display: inline-block; width: 100%;" id="breeze-button-container">
				<breeze-button
					id="breeze-button"
					wpnonce=' . esc_attr($nonce) . '
					productdata=' . esc_attr(base64_encode(wp_json_encode($arr))) . '
					errortext=""
					buynowtext="' . esc_html($defaults['buynowtext']) . '"
					checkouttext="' . esc_html($defaults['buynowtext']) . '"
					buttoncolor="' . esc_html($defaults['buttoncolor']) . '"
					buttonhovercolor="' . esc_html($defaults['buttonhovercolor']) . '"
					buttonborder="' . esc_html($defaults['buttonborder']) . '"
					buttonhoverborder="' . esc_html($defaults['buttonhoverborder']) . '"
					buttonradius="' . esc_html($defaults['buttonradius']) . '"
					buttonminheight="' . esc_html($defaults['buttonminheight']) . '"
					buttonminwidth="' . esc_html($defaults['buttonminwidth']) . '"
					buttonfontweight="' . esc_html($defaults['buttonfontweight']) . '"
					buttonfontsize="' . esc_html($defaults['buttonfontsize']) . '"
					buttonpadding="' . esc_html($defaults['buttonpadding']) . '"
					showlogo="' . esc_html($defaults['showlogo']) . '"
					logocolor="' . esc_html($defaults['logocolor']) . '"
					logohovercolor="' . esc_html($defaults['logohovercolor']) . '"
					fontfamily="' . esc_html($defaults['fontfamily']) . '">
				</breeze-button>
    	</div>
    ';
	}

	// create button on checkout page
	public function b1cco_button_on_cart_drawer_page()
	{
		$nonce = wp_create_nonce('wc_store_api');

		// Get button styling settings
		$b1cco_config = new B1CCO_Configuration();

		$button_settings = $b1cco_config->get_button_styling_settings();

		// Initialize default values
		$defaults = [
			'buynowtext' => __('Checkout', 'breeze-checkout'),
			'buttoncolor' => '#000000',
			'buttonhovercolor' => '#d8246c',
			'buttonborder' => '',
			'buttonhoverborder' => '',
			'buttonradius' => '3px',
			'buttonminheight' => '',
			'buttonminwidth' => '',
			'buttonfontweight' => '700',
			'buttonfontsize' => '13px',
			'buttonpadding' => '5px auto',
			'showlogo' => 'false',
			'logocolor' => 'white',
			'logohovercolor' => ''
		];

		$defaults = $this->update_button_defaults_from_settings($button_settings, $defaults, 'cart_drawer');

		echo '
			<div style=" width:100%;"  id="breeze-button-container">
				<breeze-button
					id="breeze-button"
					wpnonce=' . esc_attr($nonce) . '
					errortext=""
					buynowtext="' . esc_html($defaults['buynowtext']) . '"
					checkouttext="' . esc_html($defaults['buynowtext']) . '"
					buttoncolor="' . esc_html($defaults['buttoncolor']) . '"
					buttonhovercolor="' . esc_html($defaults['buttonhovercolor']) . '"
					buttonborder="' . esc_html($defaults['buttonborder']) . '"
					buttonhoverborder="' . esc_html($defaults['buttonhoverborder']) . '"
					buttonradius="' . esc_html($defaults['buttonradius']) . '"
					buttonminheight="' . esc_html($defaults['buttonminheight']) . '"
					buttonminwidth="' . esc_html($defaults['buttonminwidth']) . '"
					buttonfontweight="' . esc_html($defaults['buttonfontweight']) . '"
					buttonfontsize="' . esc_html($defaults['buttonfontsize']) . '"
					buttonpadding="' . esc_html($defaults['buttonpadding']) . '"
					showlogo="' . esc_html($defaults['showlogo']) . '"
					logocolor="' . esc_html($defaults['logocolor']) . '"
					logohovercolor="' . esc_html($defaults['logohovercolor']) . '"
					fontfamily="' . esc_html($defaults['fontfamily']) . '">
				</breeze-button>
			</div>
		';
	}

	// create button on cart page
	public function b1cco_button_on_cart_page()
	{
		$nonce = wp_create_nonce('wc_store_api');

		// Get button styling settings
		$b1cco_config = new B1CCO_Configuration();

		$button_settings = $b1cco_config->get_button_styling_settings();

		// Initialize default values
		$defaults = [
			'buynowtext' => __('Checkout', 'breeze-checkout'),
			'buttoncolor' => '#000000',
			'buttonhovercolor' => '#d8246c',
			'buttonborder' => '1px solid #D26E4B',
			'buttonhoverborder' => '1px solid #D26E4B',
			'buttonradius' => '3px',
			'buttonminheight' => '',
			'buttonminwidth' => '',
			'buttonfontweight' => '700',
			'buttonfontsize' => '18px',
			'buttonpadding' => '5px auto',
			'showlogo' => 'false',
			'logocolor' => 'white',
			'logohovercolor' => ''
		];

		$defaults = $this->update_button_defaults_from_settings($button_settings, $defaults, 'cart');

		echo '
			<div style="margin-top: 0px; display: inline-block; width: 100%; margin-bottom: 10px;" id="breeze-button-container" class="desktop_button">
				<breeze-button
					id="breeze-button"
					wpnonce=' . esc_attr($nonce) . '
					errortext=""
					buynowtext="' . esc_html($defaults['buynowtext']) . '"
					checkouttext="' . esc_html($defaults['buynowtext']) . '"
					buttoncolor="' . esc_html($defaults['buttoncolor']) . '"
					buttonhovercolor="' . esc_html($defaults['buttonhovercolor']) . '"
					buttonborder="1px solid #D26E4B"
					buttonhoverborder="1px solid #D26E4B"
					showlogo="true"
					buttonicon="https://sdk.breeze.in/gallery/icons/upi-icons.svg"
					buttonradius="' . esc_html($defaults['buttonradius']) . '"
					buttonminheight="' . esc_html($defaults['buttonminheight']) . '"
					buttonminwidth="' . esc_html($defaults['buttonminwidth']) . '"
					buttonfontweight="' . esc_html($defaults['buttonfontweight']) . '"
					buttonfontsize="16px"
					letterspacing="0.03em"
					buttonpadding="' . esc_html($defaults['buttonpadding']) . '"
					showlogo="' . esc_html($defaults['showlogo']) . '"
					logocolor="' . esc_html($defaults['logocolor']) . '"
					logohovercolor="' . esc_html($defaults['logohovercolor']) . '"
					fontfamily="' . esc_html($defaults['fontfamily']) . '">
				</breeze-button>
			</div>
		';
	}

	public function update_button_defaults_from_settings($button_settings, $defaults, $button_type)
	{
		// Log settings for debugging
		// error_log('Settings: ' . wp_json_encode($button_settings));

		// Map properties to dynamic option keys
		$property_mappings = [
			'buynowtext' => 'breeze_' . $button_type . '_button_buynow_text',
			'fontfamily' => 'breeze_' . $button_type . '_button_font_family',
			'buttonfontsize' => 'breeze_' . $button_type . '_button_font_size',
			'buttonfontweight' => 'breeze_' . $button_type . '_button_font_weight',
			'buttonradius' => 'breeze_' . $button_type . '_button_radius',
			'buttoncolor' => 'breeze_' . $button_type . '_button_color',
			'buttonhovercolor' => 'breeze_' . $button_type . '_button_hover_color',
			'buttonborder' => 'breeze_' . $button_type . '_button_border',
			'buttonhoverborder' => 'breeze_' . $button_type . '_button_hover_border',
		];

		// Loop through each setting and update defaults if applicable
		foreach ($button_settings as $setting) {
			if (isset($setting['id'])) {
				// Use a fallback value if 'default' key is missing
				$setting_value = get_option($setting['id'], isset($setting['default']) ? $setting['default'] : '');
				foreach ($property_mappings as $default_key => $expected_setting_id) {
					if ($setting['id'] === $expected_setting_id) {
						$defaults[$default_key] = esc_attr($setting_value);
						break;
					}
				}
			}
		}

		return $defaults;
	}
}

/**
 *  Prepare if class 'B1CCO_Add_Buttons' exist.
 *  Kicking this off by calling 'get_instance()' method
 */
B1CCO_Add_Buttons::get_instance();
