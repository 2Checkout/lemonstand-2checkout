<?php

class Twocheckout_Module extends Core_ModuleBase {
	protected function createModuleInfo() {
		return new Core_ModuleInfo(
			"2Checkout Payment Method",
			"Add support for the 2Checkout payment method.",
			"2Checkout"
		);
	}
}

?>
