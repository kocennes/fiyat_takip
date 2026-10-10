interface Props {
  visible: boolean;
  onClose: () => void;
  onPaymentComplete: () => void;
}

/**
 * Plan / e-signature add-on purchase modal of the hosted service.
 * Plan upgrades and purchases are not offered in this installation,
 * so the modal never renders.
 */
export function UpgradeModal(_props: Props) {
  return null;
}
