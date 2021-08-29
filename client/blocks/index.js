/**
 * External dependencies
 */
import { decodeEntities } from "@wordpress/html-entities";
import { __ } from "@wordpress/i18n";
import { registerPaymentMethod } from "@woocommerce/blocks-registry";

/**
 * Internal dependencies
 */
import { PAYMENT_METHOD_NAME } from "./constants";
import { getIranDargahServerData } from "./irandargah-utils";

const Content = () => {
  return decodeEntities(getIranDargahServerData()?.description || "");
};

const Label = () => {
  return (
    <img
      src={getIranDargahServerData()?.logo_url}
      alt={getIranDargahServerData()?.title}
    />
  );
};

registerPaymentMethod({
  name: PAYMENT_METHOD_NAME,
  label: <Label />,
  ariaLabel: __("IranDargah payment method", "woocommerce-gateway-irandargah"),
  canMakePayment: () => true,
  content: <Content />,
  edit: <Content />,
  supports: {
    features: getIranDargahServerData()?.supports ?? [],
  },
});
