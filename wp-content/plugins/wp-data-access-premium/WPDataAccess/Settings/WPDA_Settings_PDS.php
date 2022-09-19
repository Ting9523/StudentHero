<?php

namespace WPDataAccess\Settings;

class WPDA_Settings_PDS extends WPDA_Settings {

	protected function add_content() {
		// This method is implemented in the premium version.
		if ( wpda_freemius()->can_use_premium_code__premium_only() ) {
			$pds = new \WPDataAccess\Premium\WPDAPRO_Data_Services\WPDAPRO_Data_Services();
			$pds->show();
		}
	}

}