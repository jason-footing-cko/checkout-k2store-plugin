<?php
	class model_methods_creditcardpci extends model_methods_Abstract
	{

		function _verifyForm( $submitted_values )
		{
	        $object = new JObject();
	        $object->error = false;
	        $object->message = '';
	        $user = JFactory::getUser();

	        foreach ($submitted_values as $key=>$value)
	        {
	            switch ($key)
	            {

	            	case "cardname":
	            		if (!isset($submitted_values[$key]) || !JString::strlen($submitted_values[$key]))
	            		{
	            			$object->error = true;
	            			$object->message .= "<li>".JText::_( "K2STORE_CHECKOUTAPIPAYMENT_MESSAGE_CARD_NAME_INVALID" )."</li>";
	            		}
	            	break;

	                case "cardtype":
	                    if (!isset($submitted_values[$key]) || !JString::strlen($submitted_values[$key]))
	                    {
	                        $object->error = true;
	                        $object->message .= "<li>".JText::_( "K2STORE_CHECKOUTAPIPAYMENT_MESSAGE_CARD_TYPE_INVALID" )."</li>";
	                    }
	                  break;
	                case "cardnum":
	                    if (!isset($submitted_values[$key]) || !JString::strlen($submitted_values[$key]))
	                    {
	                        $object->error = true;
	                        $object->message .= "<li>".JText::_( "K2STORE_CHECKOUTAPIPAYMENT_MESSAGE_CARD_NUMBER_INVALID" )."</li>";
	                    }
	                  break;
	                 case "month":
	                    if (!isset($submitted_values[$key]) || !JString::strlen($submitted_values[$key]))
	                    {
	                        $object->error = true;
	                        $object->message .= "<li>".JText::_( "K2STORE_CHECKOUTAPIPAYMENT_MESSAGE_CARD_EXPIRATION_DATE_INVALID" )."</li>";
	                    }
	                  break;
	                case "year":
	                    if (!isset($submitted_values[$key]) || !JString::strlen($submitted_values[$key]))
	                    {
	                        $object->error = true;
	                        $object->message .= "<li>".JText::_( "K2STORE_CHECKOUTAPIPAYMENT_MESSAGE_CARD_EXPIRATION_DATE_INVALID" )."</li>";
	                    }
	                  break;
	                case "cardcvv":
	                    if (!isset($submitted_values[$key]) || !JString::strlen($submitted_values[$key]))
	                    {
	                        $object->error = true;
	                        $object->message .= "<li>".JText::_( "K2STORE_CHECKOUTAPIPAYMENT_MESSAGE_CARD_CVV_INVALID" )."</li>";
	                    }
	                  break;
	                default:
	                  break;
	            }
	        }

	        return $object;

		}

		
	}
?>