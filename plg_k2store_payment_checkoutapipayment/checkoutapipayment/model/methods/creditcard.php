<?php
	class model_methods_creditcard extends model_methods_Abstract
	{


		function _verifyForm( $submitted_values )
		{
	        $object = new JObject();
	        $object->error = false;
	        $object->message = '';

	        return $object;

	    }

		
	}
?>