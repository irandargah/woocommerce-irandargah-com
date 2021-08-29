/**
 * External dependencies
 */
import { getSetting } from "@woocommerce/settings";

/**
 * IranDargah data comes form the server passed on a global object.
 */
export const getIranDargahServerData = () => {
  const iranDargahServerData = getSetting("irandargah_data", null);
  if (!iranDargahServerData) {
    throw new Error("IranDargah initialization data is not available");
  }
  return iranDargahServerData;
};
