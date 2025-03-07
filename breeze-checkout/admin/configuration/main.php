<?php
/**
 * Add Breeze Checkout Settings Tab with Custom Inputs and Toggles
 *
 * @package Breeze 1-Click Checkout
 */

namespace B1CCO\Admin\Configuration;

if (!defined("ABSPATH")) {
  exit(); // Exit if accessed directly.
}

/**
 * Class B1CCO_Configuration.
 */
class B1CCO_Configuration
{
  private static $instance;

  public static function get_instance()
  {
    if (!isset(self::$instance)) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  public function __construct()
  {
    add_filter('woocommerce_settings_tabs_array', array($this, 'add_settings_tab'), 50);
    add_action('woocommerce_settings_tabs_breeze_checkout', array($this, 'settings_tab'));
    add_action('woocommerce_update_options_breeze_checkout', array($this, 'update_settings'));
    add_action('woocommerce_admin_field_signkey_with_regenerate', array($this, 'custom_woocommerce_admin_field_signkey_with_regenerate'), 10, 1);
    add_action('wp_ajax_regenerate_signkey', array($this, 'handle_regenerate_signkey'));
  }

  public function add_settings_tab($settings_tabs)
  {
    $settings_tabs['breeze_checkout'] = __('Breeze Checkout', 'breeze-checkout');
    return $settings_tabs;
  }

  public function settings_tab()
  {
    // Get the current section from the URL, default to 'settings'
    $current_section = isset($_GET['section']) ? sanitize_text_field($_GET['section']) : 'settings';

    // Display the sections as list items with corresponding URLs
    echo '<ul class="subsubsub">';
    echo '<li><a href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=breeze_checkout&section=settings')) . '" class="' . esc_attr($current_section === 'settings' ? 'current' : '') . '">' . esc_html__('Settings', 'breeze-checkout') . '</a> | </li>';
    echo '<li><a href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=breeze_checkout&section=webhook')) . '" class="' . esc_attr($current_section === 'webhook' ? 'current' : '') . '">' . esc_html__('Webhooks', 'breeze-checkout') . '</a> | </li>';
    echo '<li><a href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=breeze_checkout&section=button')) . '" class="' . esc_attr($current_section === 'button' ? 'current' : '') . '">' . esc_html__('Button Styling', 'breeze-checkout') . '</a></li>';
    echo '</ul>';

    echo '<br class="clear">'; // Adds a clear div after the list for proper layout.

    // Show settings based on the current section
    switch ($current_section) {
      case 'webhook':
        $this->render_webhook_settings();
        break;
      case 'button':
        $this->render_button_styling_settings();
        break;
      case 'settings':
      default:
        $this->render_general_settings();
        break;
    }
  }

  public function update_settings()
  {
    $current_section = isset($_GET['section']) ? sanitize_text_field($_GET['section']) : 'settings';
    switch ($current_section) {
      case 'webhook':
        woocommerce_update_options($this->get_webhook_settings());
        break;
      case 'button':
        woocommerce_update_options($this->get_button_styling_settings());
        break;
      case 'settings':
      default:
        woocommerce_update_options($this->get_general_settings());
        break;
    }
  }

  public function handle_regenerate_signkey()
  {
    // Verify nonce for security
    check_ajax_referer('regenerate_signkey_nonce', 'security');

    // Generate a new signkey
    $new_signkey = bin2hex(random_bytes(32));

    // Send the new signkey back to the client
    wp_send_json_success(['new_signkey' => $new_signkey]);
  }


  public function render_general_settings()
  {
    woocommerce_admin_fields($this->get_general_settings());
    ?>
    <script>
      jQuery(document).ready(function ($) {
        // Show/Hide Measurement ID based on Google Tracker toggle
        $('#breeze_enable_google_tracker').change(function () {
          if ($(this).is(':checked')) {
            $('#breeze_google_measurement_id').closest('tr').show();
          } else {
            $('#breeze_google_measurement_id').closest('tr').hide();
          }
        }).change(); // Trigger change on page load to set initial visibility
      });
    </script>
    <?php
  }

  public function render_webhook_settings()
  {
    woocommerce_admin_fields($this->get_webhook_settings());
    ?>
    <script>
      jQuery(document).ready(function ($) {
        // Show/Hide refund api url based on refund toggle
        $('#breeze_enable_refund').change(function () {
          if ($(this).is(':checked')) {
            $('#breeze_refund_shop_id').closest('tr').show();
            $('#breeze_refund_api_signkey').closest('tr').show();
          } else {
            $('#breeze_refund_shop_id').closest('tr').hide();
            $('#breeze_refund_api_signkey').closest('tr').hide();
          }
        }).change(); // Trigger change on page load to set initial visibility

        // Handle signkey copy
        $('#breeze_refund_signkey_copy').click(function (e) {
          e.preventDefault();
          var signkeyInput = $('#breeze_refund_api_signkey');
          var signkeyValue = signkeyInput.val();

          // Validate if the signkey exists
          if (!signkeyValue) {
            alert('No signkey available to copy.');
            return;
          }

          // Create a temporary textarea to copy from
          var tempTextArea = $('<textarea>');
          $('body').append(tempTextArea);
          tempTextArea.val(signkeyValue).select();

          try {
            // Copy to clipboard
            document.execCommand('copy');
            // Provide visual feedback
            $(this).text('Copied!').addClass('button-primary');
            setTimeout(() => {
              $('#breeze_refund_signkey_copy').text('Copy').removeClass('button-primary');
            }, 2000);
          } catch (err) {
            alert('Failed to copy the signkey. Please try again.');
          }

          // Remove temporary textarea
          tempTextArea.remove();
        });

        // Handle signkey regeneration
        $('#breeze_refund_signkey_regenerate').click(function (e) {
          e.preventDefault();

          // Provide feedback while processing
          $(this).text('Regenerating...').prop('disabled', true);

          $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
              action: 'regenerate_signkey',
              security: '<?php echo esc_js(wp_create_nonce("regenerate_signkey_nonce")); ?>',
            },
            success: function (response) {
              if (response.success) {
                var newSignkey = response.data.new_signkey;

                // Validate the new signkey
                if (!newSignkey) {
                  alert('Failed to regenerate a valid signkey.');
                  return;
                }

                $('#breeze_refund_api_signkey').val(newSignkey);
                // Trigger change event to activate save button
                $('#breeze_refund_api_signkey').trigger('change');
                alert('Signkey regenerated successfully!');
              } else {
                alert(response.data && response.data.error ? response.data.error : 'Failed to regenerate the signkey.');
              }
            },
            error: function () {
              alert('An error occurred while regenerating the signkey. Please try again.');
            },
            complete: function () {
              // Reset the regenerate button state
              $('#breeze_refund_signkey_regenerate').text('Regenerate').prop('disabled', false);
            },
          });
        });
      });
    </script>
    <?php
  }

  public function render_button_styling_settings()
  {
    woocommerce_admin_fields($this->get_button_styling_settings());
    ?>
    <script type="text/javascript">
      jQuery(document).ready(function ($) {
        $('table.form-table tbody').first().addClass('drop_down_btn');
        $('table.form-table tbody').eq(1).addClass('product_pg_btn breeze_btn');
        $('table.form-table tbody').eq(2).addClass('cart_drawer_pg_btn breeze_btn');
        $('table.form-table tbody').eq(3).addClass('cart_pg_btn breeze_btn');

        // $('h2').first().addClass('product_h2');

        // Function to toggle sections based on dropdown selection
        function toggleSections() {
          var selectedPage = $('#breeze_page_select').val();
          $('.breeze_btn ').hide(); // Hide all sections

          if (selectedPage === 'product_page') {
            $('.product_pg_btn').show(); // Show Product Page settings
          } else if (selectedPage === 'cart_drawer_page') {
            $('.cart_drawer_pg_btn').show(); // Show Cart Drawer Page settings
          } else if (selectedPage === 'cart_page') {
            $('.cart_pg_btn').show(); // Show Cart Page settings
          }
        }

        // Initialize sections on page load
        toggleSections();

        // Trigger section visibility change on dropdown change
        $('#breeze_page_select').on('change', function () {
          toggleSections();
        });
      });
    </script>
    <?php
  }

  public function custom_woocommerce_admin_field_signkey_with_regenerate($value)
  {
    // Get the current value or use the default.
    $option_value = get_option($value['id'], isset($value['default']) ? $value['default'] : '');
    ?>
    <tr valign="top">
      <th scope="row" class="titledesc">
        <label for="<?php echo esc_attr($value['id']); ?>">
          <?php echo esc_html($value['title']); ?>
        </label>
      </th>
      <td class="forminp forminp-<?php echo esc_attr($value['type']); ?>">
        <div style="display: flex; align-items: center;">
          <input type="password" name="<?php echo esc_attr($value['id']); ?>" id="<?php echo esc_attr($value['id']); ?>"
            value="<?php echo esc_attr($option_value); ?>" class="input-text regular-input breeze-signkey-input"
            style="margin-right: 10px;" data-original-value="<?php echo esc_attr($option_value); ?>" readonly />
          <button id="breeze_refund_signkey_copy" class="button" type="button" style="margin-right: 10px;">
            <?php esc_html_e('Copy', 'breeze-checkout'); ?>
          </button>
          <button id="breeze_refund_signkey_regenerate" class="button" type="button">
            <?php esc_html_e('Regenerate', 'breeze-checkout'); ?>
          </button>
        </div>
        <?php if (!empty($value['desc'])): ?>
          <p class="description"><?php echo esc_html($value['desc']); ?></p>
        <?php endif; ?>
      </td>
    </tr>
    <?php
  }

  public function get_general_settings()
  {
    return [
      [
        'title' => __('Breeze Checkout General Settings', 'breeze-checkout'),
        'type' => 'title',
        'id' => 'breeze_checkout_general_title',
      ],

      [
        'title' => __('Ghost Mode', 'breeze-checkout'),
        'desc' => __('Enable or disable Ghost Mode.', 'breeze-checkout'),
        'id' => 'breeze_enable_ghost_mode',
        'type' => 'checkbox',
        'default' => 'yes',
      ],
      [
        'title' => __('Version', 'breeze-checkout'),
        'desc' => __('Enter the file version for the Breeze plugin.', 'breeze-checkout'),
        'id' => 'breeze_file_version',
        'type' => 'text',
        'default' => '0.1',
        'css' => 'min-width:100px;',
      ],

      [
        'title' => __('Google Analytics', 'breeze-checkout'),
        'desc' => __('Enable or disable Google Analytics.', 'breeze-checkout'),
        'id' => 'breeze_enable_google_tracker',
        'type' => 'checkbox',
        'default' => 'no',
      ],
      [
        'title' => __('Google Measurement Id', 'breeze-checkout'),
        'desc' => __('Enter the Google Measurement Id for tracking.', 'breeze-checkout'),
        'id' => 'breeze_google_measurement_id',
        'type' => 'text',
        'default' => 'G-',
        'css' => 'min-width:300px;',
      ],
      [
        'title' => __('Facebook Analytics', 'breeze-checkout'),
        'desc' => __('Enable or disable Facebook Analytics.', 'breeze-checkout'),
        'id' => 'breeze_enable_fb_tracker',
        'type' => 'checkbox',
        'default' => 'no',
      ],
      [
        'title' => __('Merchant Id', 'breeze-checkout'),
        'desc' => __('Enter your Merchant Id.', 'breeze-checkout'),
        'id' => 'breeze_merchant_id',
        'type' => 'text',
        'default' => '',
        'css' => 'min-width:300px;',
      ],

      [
        'type' => 'sectionend',
        'id' => 'breeze_checkout_settings_end',
      ],
    ];
  }

  public function get_webhook_settings()
  {
    return [
      [
        'title' => __('Webhook Settings', 'breeze-checkout'),
        'type' => 'title',
        'id' => 'breeze_refund_settings_title',
      ],
      [
        'title' => __('Enable Refund Webhook', 'breeze-checkout'),
        'desc' => __('Enable or disable refund webhook.', 'breeze-checkout'),
        'id' => 'breeze_enable_refund',
        'type' => 'checkbox',
        'default' => 'no',
      ],
      [
        'title' => __('Shop Id', 'breeze-checkout'),
        'desc' => __('Enter the Shop Id for refunds.', 'breeze-checkout'),
        'id' => 'breeze_refund_shop_id',
        'type' => 'text',
        'default' => 'CHANGE_SHOP_ID',
        'css' => 'min-width:100px;',
      ],
      [
        'title' => __('Refund Webhook Signkey', 'breeze-checkout'),
        'desc' => __('Copy the webhook signkey for refunds.', 'breeze-checkout'),
        'id' => 'breeze_refund_api_signkey',
        'default' => '48304eb47085c8996815aacaf1862f4352d3788f507dbd4d4dd51bf86ab686e33c14b1020b85027321443e7e80cbd251',
        'type' => 'signkey_with_regenerate',
      ],
      [
        'type' => 'sectionend',
        'id' => 'breeze_refund_settings_end',
      ],
    ];
  }

  public function get_button_styling_settings()
  {
    return [
      [
        'title' => __('Button Styling Settings', 'breeze-checkout'),
        'type' => 'title',
        'id' => 'breeze_button_styling_title',
      ],

      [
        'title' => __('Select Page', 'breeze-checkout'),
        'desc' => __('Choose which page you want to customize the button for.', 'breeze-checkout'),
        'id' => 'breeze_page_select',
        'type' => 'select',
        'options' => [
          'product_page' => __('Product Page', 'breeze-checkout'),
          'cart_drawer_page' => __('Cart Drawer Page', 'breeze-checkout'),
          'cart_page' => __('Cart Page', 'breeze-checkout'),
        ],
        'class' => 'dropdown_tr',
        'default' => 'product_page',
      ],

      // Product Page Styling
      [
        'title' => __('', 'breeze-checkout'),
        'type' => 'title',
        'class' => 'breeze_product_page_section breeze_page_section ',
        'id' => 'breeze_product_button_styling',
      ],
      [
        'title' => __('Buy Now Text', 'breeze-checkout'),
        'id' => 'breeze_product_button_buynow_text',
        'type' => 'text',
        'default' => __('Buy Now', 'breeze-checkout'),
        'class' => 'breeze_product_page_section breeze_page_section ',
      ],
      [
        'title' => __('Font Family', 'breeze-checkout'),
        'id' => 'breeze_product_button_font_family',
        'type' => 'text',
        'default' => 'Arial, sans-serif',
        'class' => 'breeze_product_page_section breeze_page_section ',
      ],
      [
        'title' => __('Button Font Size', 'breeze-checkout'),
        'id' => 'breeze_product_button_font_size',
        'type' => 'text',
        'css' => 'min-width:50px;',
        'default' => '16',
        'class' => 'breeze_product_page_section breeze_page_section ',
      ],
      [
        'title' => __('Button Font Weight', 'breeze-checkout'),
        'id' => 'breeze_product_button_font_weight',
        'type' => 'text',
        'default' => 'bold',
        'class' => 'breeze_product_page_section breeze_page_section ',
      ],
      [
        'title' => __('Button Radius', 'breeze-checkout'),
        'id' => 'breeze_product_button_radius',
        'type' => 'text',
        'css' => 'min-width:50px;',
        'default' => '4',
        'class' => 'breeze_product_page_section breeze_page_section ',
      ],
      [
        'title' => __('Button Color', 'breeze-checkout'),
        'id' => 'breeze_product_button_color',
        'type' => 'color',
        'default' => '#000000',
        'class' => 'breeze_product_page_section breeze_page_section ',
      ],
      [
        'title' => __('Button Hover Color', 'breeze-checkout'),
        'id' => 'breeze_product_button_hover_color',
        'type' => 'color',
        'default' => '#ffffff',
        'class' => 'breeze_product_page_section breeze_page_section ',
      ],
      [
        'title' => __('Button Border', 'breeze-checkout'),
        'id' => 'breeze_product_button_border',
        'type' => 'text',
        'desc' => __('Example: 1px solid #000000', 'breeze-checkout'),
        'default' => '1px solid #000000',
        'class' => 'breeze_product_page_section breeze_page_section ',
      ],
      [
        'title' => __('Button Hover Border', 'breeze-checkout'),
        'id' => 'breeze_product_button_hover_border',
        'type' => 'text',
        'desc' => __('Example: 1px solid #ffffff', 'breeze-checkout'),
        'default' => '1px solid #ffffff',
        'class' => 'breeze_product_page_section breeze_page_section ',
      ],

      [
        'type' => 'sectionend',
        'id' => 'breeze_product_page_section_end',
      ],

      // Cart Drawer Page Styling
      [
        'title' => __('', 'breeze-checkout'),
        'type' => 'title',
        'class' => 'breeze_cart_drawer_page_section breeze_page_section ',
        'id' => 'breeze_cart_drawer_button_styling',
      ],
      [
        'title' => __('Checkout Text', 'breeze-checkout'),
        'id' => 'breeze_cart_drawer_button_buynow_text',
        'type' => 'text',
        'default' => __('Checkout', 'breeze-checkout'),
        'class' => 'breeze_cart_drawer_page_section breeze_page_section ',
      ],
      [
        'title' => __('Font Family', 'breeze-checkout'),
        'id' => 'breeze_cart_drawer_button_font_family',
        'type' => 'text',
        'default' => 'Arial, sans-serif',
        'class' => 'breeze_cart_drawer_page_section breeze_page_section ',
      ],
      [
        'title' => __('Button Font Size', 'breeze-checkout'),
        'id' => 'breeze_cart_drawer_button_font_size',
        'type' => 'text',
        'css' => 'min-width:50px;',
        'default' => '16',
        'class' => 'breeze_cart_drawer_page_section breeze_page_section ',
      ],
      [
        'title' => __('Button Font Weight', 'breeze-checkout'),
        'id' => 'breeze_cart_drawer_button_font_weight',
        'type' => 'text',
        'default' => 'bold',
        'class' => 'breeze_cart_drawer_page_section breeze_page_section ',
      ],
      [
        'title' => __('Button Radius', 'breeze-checkout'),
        'id' => 'breeze_cart_drawer_button_radius',
        'type' => 'text',
        'css' => 'min-width:50px;',
        'default' => '4',
        'class' => 'breeze_cart_drawer_page_section breeze_page_section ',
      ],
      [
        'title' => __('Button Color', 'breeze-checkout'),
        'id' => 'breeze_cart_drawer_button_color',
        'type' => 'color',
        'default' => '#000000',
        'class' => 'breeze_cart_drawer_page_section breeze_page_section ',
      ],
      [
        'title' => __('Button Hover Color', 'breeze-checkout'),
        'id' => 'breeze_cart_drawer_button_hover_color',
        'type' => 'color',
        'default' => '#ffffff',
        'class' => 'breeze_cart_drawer_page_section breeze_page_section ',
      ],
      [
        'title' => __('Button Border', 'breeze-checkout'),
        'id' => 'breeze_cart_drawer_button_border',
        'type' => 'text',
        'desc' => __('Example: 1px solid #000000', 'breeze-checkout'),
        'default' => '1px solid #000000',
        'class' => 'breeze_cart_drawer_page_section breeze_page_section ',
      ],
      [
        'title' => __('Button Hover Border', 'breeze-checkout'),
        'id' => 'breeze_cart_drawer_button_hover_border',
        'type' => 'text',
        'desc' => __('Example: 1px solid #ffffff', 'breeze-checkout'),
        'default' => '1px solid #ffffff',
        'class' => 'breeze_cart_drawer_page_section breeze_page_section ',
      ],

      // Add remaining cart drawer page fields here
      [
        'type' => 'sectionend',
        'id' => 'breeze_cart_drawer_page_section_end',
      ],

      // Cart Page Styling
      [
        'title' => __('', 'breeze-checkout'),
        'type' => 'title',
        'class' => 'breeze_cart_page_section breeze_page_section ',
        'id' => 'breeze_cart_button_styling',
      ],
      [
        'title' => __('Checkout Text', 'breeze-checkout'),
        'id' => 'breeze_cart_button_buynow_text',
        'type' => 'text',
        'default' => __('Proceed to Checkout', 'breeze-checkout'),
        'class' => 'breeze_cart_page_section breeze_page_section ',
      ],
      [
        'title' => __('Font Family', 'breeze-checkout'),
        'id' => 'breeze_cart_button_font_family',
        'type' => 'text',
        'default' => 'Arial, sans-serif',
        'class' => 'breeze_cart_page_section breeze_page_section ',
      ],
      [
        'title' => __('Button Font Size', 'breeze-checkout'),
        'id' => 'breeze_cart_button_font_size',
        'type' => 'text',
        'css' => 'min-width:50px;',
        'default' => '16',
        'class' => 'breeze_cart_page_section breeze_page_section ',
      ],
      [
        'title' => __('Button Font Weight', 'breeze-checkout'),
        'id' => 'breeze_cart_button_font_weight',
        'type' => 'text',
        'default' => 'bold',
        'class' => 'breeze_cart_page_section breeze_page_section ',
      ],
      [
        'title' => __('Button Radius', 'breeze-checkout'),
        'id' => 'breeze_cart_button_radius',
        'type' => 'text',
        'css' => 'min-width:50px;',
        'default' => '4',
        'class' => 'breeze_cart_page_section breeze_page_section ',
      ],
      [
        'title' => __('Button Color', 'breeze-checkout'),
        'id' => 'breeze_cart_button_color',
        'type' => 'color',
        'default' => '#000000',
        'class' => 'breeze_cart_page_section breeze_page_section ',
      ],
      [
        'title' => __('Button Hover Color', 'breeze-checkout'),
        'id' => 'breeze_cart_button_hover_color',
        'type' => 'color',
        'default' => '#ffffff',
        'class' => 'breeze_cart_page_section breeze_page_section ',
      ],
      [
        'title' => __('Button Border', 'breeze-checkout'),
        'id' => 'breeze_cart_button_border',
        'type' => 'text',
        'desc' => __('Example: 1px solid #000000', 'breeze-checkout'),
        'default' => '1px solid #000000',
        'class' => 'breeze_cart_page_section breeze_page_section ',
      ],
      [
        'title' => __('Button Hover Border', 'breeze-checkout'),
        'id' => 'breeze_cart_button_hover_border',
        'type' => 'text',
        'desc' => __('Example: 1px solid #ffffff', 'breeze-checkout'),
        'default' => '1px solid #ffffff',
        'class' => 'breeze_cart_page_section breeze_page_section ',
      ],

      [
        'type' => 'sectionend',
        'id' => 'breeze_cart_page_section_end',
      ],
    ];
  }
}

B1CCO_Configuration::get_instance();
