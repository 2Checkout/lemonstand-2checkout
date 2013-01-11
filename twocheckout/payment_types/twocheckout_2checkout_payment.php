<?

	class Twocheckout_2Checkout_Payment extends Shop_PaymentType
	{

		public function get_info()
		{
			return array(
				'name'=>'2Checkout',
				'custom_payment_form'=>'backend_payment_form.htm',
				'description'=>'2Checkout Payment Method (Credit Card/PayPal).'
			);
		}

		public function build_config_ui($host_obj, $context = null)
		{
			$host_obj->add_field('demo_mode', 'Demo Mode')->tab('Configuration')->renderAs(frm_onoffswitcher)->comment('2Checkout Demo Mode', 'above');
			$host_obj->add_field('sid', 'Seller ID')->tab('Configuration')->renderAs(frm_text)->comment('2Checkout Account Number.', 'above')->validation()->fn('trim')->required('Please provide your 2Checkout account number.');

			$host_obj->add_field('secret_word', 'Secret Word')->tab('Configuration')->renderAs(frm_text)->comment('2Checkout Secret Word.', 'above')->validation()->fn('trim')->required('Please provide your 2Checkout Secret Word.');

			$host_obj->add_field('cancel_page', 'Cancel Page', 'left')->tab('Configuration')->renderAs(frm_dropdown)->formElementPartial(PATH_APP.'/modules/shop/controllers/partials/_page_selector.htm')->comment('Page which the customer’s browser is redirected to if payment is cancelled.', 'above')->emptyOption('<please select a page>');

			$host_obj->add_field('order_status', 'Order Status', 'right')->tab('Configuration')->renderAs(frm_dropdown)->comment('Select status to assign the order in case of successful payment.', 'above');
		}

		public function get_order_status_options($current_key_value = -1)
		{
			if ($current_key_value == -1)
				return Shop_OrderStatus::create()->order('name')->find_all()->as_array('name', 'id');

			return Shop_OrderStatus::create()->find($current_key_value)->name;
		}

		public function validate_config_on_save($host_obj)
		{
		}

		public function validate_config_on_load($host_obj)
		{
		}

		public function init_config_data($host_obj)
		{
			$host_obj->test_mode = 1;
		}

		public function get_return_page_options($current_key_value = -1)
		{
			if ($current_key_value == -1)
				return Cms_Page::create()->order('title')->find_all()->as_array('title', 'id');

			return Cms_Page::create()->find($current_key_value)->title;
		}

		public function get_form_action($host_obj)
		{
			return "https://www.2checkout.com/checkout/purchase";
		}

		public function get_hidden_fields($host_obj, $order, $backend = false)
		{
			$result = array();

			/*
			 * Billing information
			 */

			$result['email'] = $order->billing_email;
			$result['card_holder_name'] = $order->billing_first_name.' '.$order->billing_last_name;

			$result['street_address'] = $order->billing_street_addr;
			$result['city'] = $order->billing_city;
			$result['country'] = $order->billing_country->code;

			if ($order->billing_state)
				$result['state'] = $order->billing_state->code;

			$result['zip'] = $order->billing_zip;
			$result['phone'] = $order->billing_phone;
			if ($order->shipping_country) {
				$result['ship_name'] = $order->billing_first_name.' '.$order->billing_last_name;
				$result['ship_street_address'] = $order->shipping_street_addr;
				$result['ship_city'] = $order->shipping_city;
				$result['ship_country'] = $order->shipping_country->code;

				if ($order->shipping_state)
					$result['ship_state'] = $order->shipping_state->code;

				$result['ship_zip'] = $order->shipping_zip;
			}

			/*
			 * Order items
			 */

			$item_index = 1;
			foreach ($order->items as $item)
			{
				$result['li_'.$item_index.'_type'] = 'product';
				$result['li_'.$item_index.'_name'] = $item->output_product_name(true, true);
				$result['li_'.$item_index.'_price'] = round($item->unit_total_price, 2);
				$result['li_'.$item_index.'_quantity'] = $item->quantity;
				$item_index++;
			}

			/*
			 * Shipping
			 */

			$result['li_'.$item_index.'_type'] = 'shipping';
			$result['li_'.$item_index.'_name'] = 'Shipping Cost';
			$result['li_'.$item_index.'_price'] = $order->shipping_quote;

			$item_index++;
			if ($order->shipping_tax > 0)
			{
				$result['li_'.$item_index.'_type'] = 'tax';
				$result['li_'.$item_index.'_name'] = 'Shipping Tax';
				$result['li_'.$item_index.'_price'] = $order->shipping_tax;
			}

			$item_index++;
			if ($order->goods_tax > 0)
			{
				$result['li_'.$item_index.'_type'] = 'tax';
				$result['li_'.$item_index.'_name'] = 'Goods Tax';
				$result['li_'.$item_index.'_price'] = $order->goods_tax;
			}

			/*
			 * Payment setup
			 */

			if ($host_obj->demo_mode)
			{
				$result['demo'] = "Y";
			}
			else
			{
				$result['demo'] = "N";
			}

			$result['mode'] = '2CO';
			$result['cart_invoice_id'] = $order->id;
			$result['merchant_order_id'] = $order->order_hash;
			$result['sid'] = $host_obj->sid;
			$result['purchase_step'] = "payment-method";
			$result['currency_code'] = Shop_CurrencySettings::get()->code;

			$result['notify_url'] = Phpr::$request->getRootUrl().root_url('/ls_2checkout_ipn/'.$order->order_hash);

			if (!$backend)
			{
				$result['x_receipt_link_url'] = Phpr::$request->getRootUrl().root_url('/ls_2checkout_autoreturn/'.$order->order_hash);

				$cancel_page = $this->get_cancel_page($host_obj);
				if ($cancel_page)
				{
					$result['return_url'] = Phpr::$request->getRootUrl().root_url($cancel_page->url);
					if ($cancel_page->action_reference == 'shop:pay')
						$result['return_url'] .= '/'.$order->order_hash;
					elseif ($cancel_page->action_reference == 'shop:order')
						$result['return_url'] .= '/'.$order->id;
				}
			} else
			{
				$result['x_receipt_link_url'] = Phpr::$request->getRootUrl().root_url('/ls_2checkout_autoreturn/'.$order->order_hash.'/backend');
				//	$result['return'] = Phpr::$request->getRootUrl().url('shop/orders/preview/'.$order->id.'?'.uniqid());
				$result['return_url'] = Phpr::$request->getRootUrl().url('shop/orders/pay/'.$order->id.'?'.uniqid());
			}

			foreach($result as $key=>$value)
			{
				$result[$key] = str_replace("\n", ' ', $value);
			}
			return $result;
		}

		public function process_payment_form($data, $host_obj, $order, $back_end = false)
		{
			/*
			 * We do not need any code here since payments are processed on 2Checkout server.
			 */
		}

		public function register_access_points()
		{
			return array(
				'ls_2checkout_autoreturn'=>'process_2checkout_autoreturn',
				'ls_2checkout_ipn'=>'process_2checkout_ipn'
			);
		}

		protected function get_cancel_page($host_obj)
		{
			$cancel_page = $host_obj->cancel_page;
			$page_info = Cms_PageReference::get_page_info($host_obj, 'cancel_page', $host_obj->cancel_page);
			if (is_object($page_info))
				$cancel_page = $page_info->page_id;

			if (!$cancel_page)
				return null;

			return Cms_Page::create()->find($cancel_page);
		}

		public function process_2checkout_ipn($params)
		{

		}

		public function process_2checkout_autoreturn($params)
		{
			$fields = $_REQUEST;

			try
			{
				$order = null;

				$response = null;

				/*
				 * Find order and load 2Checkout settings
				 */

				$order_hash = array_key_exists(0, $params) ? $params[0] : null;
				if (!$order_hash)
					throw new Phpr_ApplicationException('Order not found');

				$order = Shop_Order::create()->find_by_order_hash($order_hash);
				if (!$order)
					throw new Phpr_ApplicationException('Order not found.');

				if (!$order->payment_method)
					throw new Phpr_ApplicationException('Payment method not found.');

				$order->payment_method->define_form_fields();
				$payment_method_obj = $order->payment_method->get_paymenttype_object();

				if (!($payment_method_obj instanceof Twocheckout_2Checkout_Payment))
					throw new Phpr_ApplicationException('Invalid payment method.');

				$is_backend = array_key_exists(1, $params) ? $params[1] == 'backend' : false;

				/*
				 * Validate returned MD5 Hash
				 */

				if (!$order->payment_processed(false))
				{
					$transaction = $fields['order_number'];
					if (!$transaction)
						throw new Phpr_ApplicationException('Invalid transaction value');

					if ($order->payment_method->demo_mode)
					{
						$order_number = 1;
					}
					else
					{
						$order_number = $fields['order_number'];
					}

					$compare_hash = strtoupper(md5($order->payment_method->secret_word . $order->payment_method->sid . $order_number . $fields['total']));
				    if ($compare_hash != $fields['key'])
				        throw new Phpr_ApplicationException('MD5 Hash Failed to Validate.');

					/*
					 * Mark order as paid
					 */

						if ($fields['cart_invoice_id'] != $order->id)
							throw new Phpr_ApplicationException('Invalid invoice number.');

						if ($fields['total'] != strval($this->get_2checkout_total($order)))
							throw new Phpr_ApplicationException('Invalid order total - order total received is '.$fields['total']);

						if ($order->set_payment_processed())
						{
							Shop_OrderStatusLog::create_record($order->payment_method->order_status, $order);
							$this->log_payment_attempt($order, 'Successful payment', 1, array(), Phpr::$request->get_fields, $response);
							$transaction_id = Phpr::$request->getField('order_number');
							if(strlen($transaction_id))
								$this->update_transaction_status($order->payment_method, $order, $transaction_id, 'Processed', 'processed');
						}
				}

				if (!$is_backend)
				{
					$return_page = $order->payment_method->receipt_page;
					if ($return_page)
						Phpr::$response->redirect(root_url($return_page->url.'/'.$order->order_hash).'?utm_nooverride=1');
					else
						throw new Phpr_ApplicationException('2Checkout Receipt page is not found.');
				}
				else
				{
					Phpr::$response->redirect(url('/shop/orders/payment_accepted/'.$order->id.'?utm_nooverride=1&nocache'.uniqid()));
				}
			}
			catch (Exception $ex)
			{
				if ($order)
					$this->log_payment_attempt($order, $ex->getMessage(), 0, array(), Phpr::$request->get_fields, $response);

				throw new Phpr_ApplicationException($ex->getMessage());
			}
		}

		public function page_deletion_check($host_obj, $page)
		{
			if ($host_obj->cancel_page == $page->id)
				throw new Phpr_ApplicationException('Page cannot be deleted because it is used in 2Checkout payment method as a cancel page.');
		}

		public function status_deletion_check($host_obj, $status)
		{
			if ($host_obj->order_status == $status->id)
				throw new Phpr_ApplicationException('Status cannot be deleted because it is used in 2Checkout payment method.');
		}

		private function get_2checkout_total($order)
		{
			$order_total = 0;
			//add up individual order items
			foreach ($order->items as $item)
			{
				$item_price = round($item->unit_total_price, 2);
				$order_total = $order_total + ($item->quantity * $item_price);
			}

			//add shipping quote + tax
			$order_total = $order_total + $order->shipping_quote;
			if ($order->shipping_tax > 0)
				$order_total = $order_total + $order->shipping_tax;

			//order items tax
			$cart_tax = round($order->goods_tax, 2);
			$order_total = $order_total + $cart_tax;

			return $order_total;
		}
	}

?>
